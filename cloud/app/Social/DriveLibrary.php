<?php

declare(strict_types=1);

namespace App\Social;

use App\Models\PlatformSetting;
use App\Models\SocialConnection;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Folderul de Google Drive al firmei: de acolo agentul ia fotografiile și clipurile video. Platforma citește
 * folderul printr-un cont de serviciu Google (doar citire); firma partajează folderul cu adresa acelui cont,
 * deci folderul rămâne privat. Cheia contului de serviciu e salvată criptat și nu se afișează.
 */
final class DriveLibrary
{
    public const KEY = 'drive_service_account';

    private const API = 'https://www.googleapis.com/drive/v3/files';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const IMAGES = ['image/jpeg', 'image/png', 'image/webp'];

    private const VIDEOS = ['video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/webm' => 'webm'];

    public function __construct(private readonly MediaLibrary $library) {}

    public static function configured(): bool
    {
        return self::account() !== null;
    }

    /** Adresa contului de serviciu, cu care firmele își partajează folderul. */
    public static function email(): string
    {
        return (string) (self::account()['client_email'] ?? '');
    }

    /** @throws DriveError */
    public static function saveKey(string $json): void
    {
        $data = json_decode($json, true);
        if (! is_array($data) || ($data['type'] ?? '') !== 'service_account' || ! filter_var($data['client_email'] ?? '', FILTER_VALIDATE_EMAIL)
            || ! is_string($data['private_key'] ?? null) || @openssl_pkey_get_private($data['private_key']) === false) {
            throw new DriveError('Fișierul nu este o cheie JSON de cont de serviciu Google.');
        }
        PlatformSetting::put(self::KEY, Crypt::encryptString((string) json_encode(['client_email' => $data['client_email'], 'private_key' => $data['private_key']])));
        Cache::forget('drive.token');
    }

    public static function clearKey(): void
    {
        PlatformSetting::query()->whereKey(self::KEY)->delete();
        Cache::forget('drive.token');
    }

    /** ID-ul folderului dintr-un link de Google Drive (sau ID-ul direct). */
    public static function folderId(string $link): ?string
    {
        $link = trim($link);
        if (preg_match('#^https://drive\.google\.com/(?:drive/(?:u/\d+/)?folders/|open\?id=)([A-Za-z0-9_-]{10,80})#', $link, $m)) {
            return $m[1];
        }

        return preg_match('/^[A-Za-z0-9_-]{10,80}$/', $link) ? $link : null;
    }

    /**
     * Aduce fișierele noi din folder (și din subfolderele lui directe) în materialele firmei.
     *
     * @return array{images: int, videos: int, skipped: int}
     *
     * @throws DriveError
     */
    public function sync(SocialConnection $connection): array
    {
        $result = ['images' => 0, 'videos' => 0, 'skipped' => 0];
        try {
            $token = $this->token();
            $root = (string) ($connection->config['folder_id'] ?? '');
            $files = $this->children($token, $root);
            foreach (array_filter($files, fn (array $f) => ($f['mimeType'] ?? '') === 'application/vnd.google-apps.folder') as $folder) {
                foreach ($this->children($token, (string) $folder['id']) as $file) {
                    $files[] = ['folder' => (string) ($folder['name'] ?? '')] + $file;
                }
            }
            foreach ($files as $file) {
                $mime = (string) ($file['mimeType'] ?? '');
                $id = (string) ($file['id'] ?? '');
                if ($mime === 'application/vnd.google-apps.folder' || ! preg_match('/^[A-Za-z0-9_-]{10,80}$/', $id)) {
                    continue;
                }
                $title = trim(($file['folder'] ?? '') !== '' ? $file['folder'].': '.pathinfo((string) $file['name'], PATHINFO_FILENAME) : pathinfo((string) ($file['name'] ?? ''), PATHINFO_FILENAME));
                $size = (int) ($file['size'] ?? 0);
                if ($this->library->known('drive:'.$id)) {
                    continue;
                }
                $kept = match (true) {
                    $this->library->full() => null,
                    in_array($mime, self::IMAGES, true) && $size <= MediaLibrary::MAX_BYTES => $this->image($token, $id, $title),
                    isset(self::VIDEOS[$mime]) && $size > 0 && $size <= MediaLibrary::MAX_VIDEO_BYTES && $this->library->videoRoom() => $this->video($token, $id, $title, self::VIDEOS[$mime]),
                    default => null,
                };
                $result[$kept === null ? 'skipped' : ($kept ? 'videos' : 'images')]++;
            }
        } catch (DriveError $e) {
            $connection->forceFill(['status' => 'error', 'last_error' => Str::limit($e->getMessage(), 480)])->save();

            throw $e;
        }
        $connection->forceFill(['status' => 'ok', 'last_error' => null, 'synced_at' => now()])->save();

        return $result;
    }

    /** false = fotografie păstrată, null = sărită */
    private function image(string $token, string $id, string $title): ?bool
    {
        $response = Http::timeout(30)->withToken($token)->get(self::API.'/'.$id, ['alt' => 'media']);

        return $response->successful() && $this->library->keep('drive', 'drive:'.$id, $response->body(), $title) ? false : null;
    }

    /** true = clip păstrat, null = sărit */
    private function video(string $token, string $id, string $title, string $extension): ?bool
    {
        $tmp = tempnam(sys_get_temp_dir(), 'drv');
        try {
            $response = Http::timeout(180)->withToken($token)->sink($tmp)->get(self::API.'/'.$id, ['alt' => 'media']);

            return $response->successful() && $this->library->keepVideo('drive:'.$id, $tmp, $title, $extension) ? true : null;
        } catch (ConnectionException) {
            return null;
        } finally {
            @unlink($tmp);
        }
    }

    /** @return list<array<string, mixed>> */
    private function children(string $token, string $folder): array
    {
        if (! preg_match('/^[A-Za-z0-9_-]{10,80}$/', $folder)) {
            throw new DriveError('Linkul folderului nu este valid.');
        }
        try {
            $response = Http::timeout(20)->withToken($token)->acceptJson()->get(self::API, [
                'q' => "'{$folder}' in parents and trashed = false", 'fields' => 'files(id,name,mimeType,size)', 'pageSize' => 200,
                'orderBy' => 'modifiedTime desc', 'supportsAllDrives' => 'true', 'includeItemsFromAllDrives' => 'true',
            ]);
        } catch (ConnectionException) {
            throw new DriveError('Google Drive nu a răspuns. Încearcă din nou peste câteva minute.');
        }
        if ($response->status() === 404) {
            throw new DriveError('Folderul nu a fost găsit. Verifică linkul și dacă folderul este partajat cu '.self::email().'.');
        }
        if (! $response->successful()) {
            throw new DriveError('Google Drive a refuzat cererea: '.Str::limit((string) ($response->json('error.message') ?? 'HTTP '.$response->status()), 250));
        }

        return array_values(array_filter((array) $response->json('files', []), 'is_array'));
    }

    /** Jeton de acces doar pentru citire, obținut cu cheia contului de serviciu (valabil o oră; îl ținem 50 de minute). */
    private function token(): string
    {
        $account = self::account();
        if ($account === null) {
            throw new DriveError('Google Drive nu este configurat încă pentru platformă.');
        }

        return Cache::remember('drive.token', 3000, function () use ($account): string {
            $encode = fn (string $data) => rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
            $now = time();
            $unsigned = $encode((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$encode((string) json_encode([
                'iss' => $account['client_email'], 'scope' => 'https://www.googleapis.com/auth/drive.readonly', 'aud' => self::TOKEN_URL, 'iat' => $now, 'exp' => $now + 3600]));
            if (! openssl_sign($unsigned, $signature, $account['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new DriveError('Cheia contului de serviciu nu poate semna cererea. Salveaz-o din nou.');
            }
            try {
                $response = Http::timeout(20)->asForm()->post(self::TOKEN_URL, ['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $unsigned.'.'.$encode($signature)]);
            } catch (ConnectionException) {
                throw new DriveError('Google nu a răspuns. Încearcă din nou peste câteva minute.');
            }
            $token = (string) $response->json('access_token', '');
            if (! $response->successful() || $token === '') {
                throw new DriveError('Google a refuzat cheia contului de serviciu: '.Str::limit((string) ($response->json('error_description') ?? 'HTTP '.$response->status()), 200));
            }

            return $token;
        });
    }

    /** @return array{client_email: string, private_key: string}|null */
    private static function account(): ?array
    {
        $stored = PlatformSetting::get(self::KEY);
        if (! is_string($stored) || $stored === '') {
            return null;
        }
        try {
            $data = json_decode(Crypt::decryptString($stored), true);
        } catch (DecryptException) {
            return null; // APP_KEY schimbat: cheia trebuie salvată din nou
        }

        return is_array($data) && isset($data['client_email'], $data['private_key']) ? $data : null;
    }
}
