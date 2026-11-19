<?php

declare(strict_types=1);

namespace App\Social;

use App\Models\PlatformSetting;
use App\Models\SocialPost;
use App\Services\UsageMeter;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Agentul audio: transformă textul vocii unei postări în înregistrare, în limba română, printr-un serviciu de
 * sinteză vocală (ElevenLabs, model multilingv). Cheia serviciului e a platformei: salvată criptat din panoul
 * super admin, niciodată afișată. Fiecare firmă are un plafon lunar de caractere.
 */
final class VoiceStudio
{
    public const KEY = 'voice_api_key';

    public const MONTHLY_CHARS = 40_000;

    public const MAX_TEXT = 1200;

    private const API = 'https://api.elevenlabs.io/v1';

    private const MODEL = 'eleven_multilingual_v2';

    public function __construct(private readonly TenantContext $context, private readonly UsageMeter $usage) {}

    public static function configured(): bool
    {
        return self::key() !== '';
    }

    public static function saveKey(string $key, string $voice): void
    {
        PlatformSetting::put(self::KEY, Crypt::encryptString(trim($key)));
        PlatformSetting::put('voice_default', trim($voice));
        Cache::forget('voice.list');
    }

    public static function clearKey(): void
    {
        PlatformSetting::query()->whereKey(self::KEY)->delete();
        Cache::forget('voice.list');
    }

    public static function defaultVoice(): string
    {
        return (string) PlatformSetting::get('voice_default', '');
    }

    /** Vocile disponibile în contul serviciului: id => nume. Lista se ține o oră. */
    public static function voices(): array
    {
        if (! self::configured()) {
            return [];
        }

        return Cache::remember('voice.list', 3600, function (): array {
            try {
                $response = Http::timeout(15)->withHeaders(['xi-api-key' => self::key()])->acceptJson()->get(self::API.'/voices');
            } catch (ConnectionException) {
                return [];
            }
            $voices = [];
            foreach ((array) $response->json('voices', []) as $voice) {
                if (is_array($voice) && preg_match('/^[A-Za-z0-9]{10,40}$/', (string) ($voice['voice_id'] ?? ''))) {
                    $voices[(string) $voice['voice_id']] = Str::limit((string) ($voice['name'] ?? 'Voce'), 60);
                }
            }

            return $voices;
        });
    }

    public function remaining(): int
    {
        $cap = $this->context->organization()->subscription?->limit('voice_chars_per_month') ?? self::MONTHLY_CHARS;

        return max(0, $cap - $this->usage->thisMonth('voice_chars'));
    }

    /**
     * Generează vocea postării și o păstrează în platformă (înlocuiește înregistrarea anterioară).
     *
     * @throws VoiceError
     */
    public function speak(SocialPost $post, string $voice): void
    {
        $text = trim((string) $post->voiceover);
        if ($text === '') {
            throw new VoiceError('Scrie întâi textul vocii și salvează postarea.');
        }
        if (! self::configured()) {
            throw new VoiceError('Serviciul de voce nu este configurat încă pentru platformă.');
        }
        if (! preg_match('/^[A-Za-z0-9]{10,40}$/', $voice)) {
            throw new VoiceError('Alege o voce din setările agentului de social media.');
        }
        $length = mb_strlen($text);
        if ($length > self::MAX_TEXT) {
            throw new VoiceError('Textul vocii este prea lung (cel mult '.self::MAX_TEXT.' de caractere).');
        }
        if ($length > $this->remaining()) {
            throw new VoiceError('Plafonul lunar de voce al firmei a fost atins.');
        }
        try {
            $response = Http::timeout(60)->connectTimeout(8)->withHeaders(['xi-api-key' => self::key(), 'Accept' => 'audio/mpeg'])
                ->post(self::API.'/text-to-speech/'.$voice.'?output_format=mp3_44100_128', [
                    'text' => $text, 'model_id' => self::MODEL, 'language_code' => 'ro',
                    'voice_settings' => ['stability' => 0.5, 'similarity_boost' => 0.75],
                ]);
        } catch (ConnectionException) {
            throw new VoiceError('Serviciul de voce nu a răspuns. Încearcă din nou peste câteva minute.');
        }
        $bytes = $response->body();
        if (! $response->successful() || strlen($bytes) < 1000) {
            throw new VoiceError('Serviciul de voce a refuzat cererea: '.Str::limit((string) ($response->json('detail.message') ?? 'HTTP '.$response->status()), 200));
        }
        $this->usage->increment('voice_chars', $length);
        if ($post->voice_path) {
            Storage::disk('local')->delete($post->voice_path);
        }
        $path = 'social/'.$this->context->id().'/voce-'.$post->id.'-'.Str::lower(Str::random(8)).'.mp3';
        Storage::disk('local')->put($path, $bytes);
        $post->forceFill(['voice_path' => $path])->save();
    }

    public function forget(SocialPost $post): void
    {
        if ($post->voice_path) {
            Storage::disk('local')->delete($post->voice_path);
            $post->forceFill(['voice_path' => null])->save();
        }
    }

    private static function key(): string
    {
        $stored = PlatformSetting::get(self::KEY);
        if (! is_string($stored) || $stored === '') {
            return '';
        }
        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            return ''; // APP_KEY schimbat: cheia trebuie salvată din nou
        }
    }
}
