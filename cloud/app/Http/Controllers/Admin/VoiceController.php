<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformRole;
use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Social\VoiceStudio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Cheia serviciului de voce al platformei (doar super admin, cu parola contului; cheia nu se afișează înapoi). */
final class VoiceController extends Controller
{
    public function save(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->confirm($request);
        $data = $request->validate(['voice_key' => ['required', 'string', 'min:20', 'max:300', 'regex:/^[A-Za-z0-9_\-]+$/'], 'voice_default' => ['required', 'regex:/^[A-Za-z0-9]{10,40}$/']],
            ['voice_default.regex' => 'ID-ul vocii este un șir de litere și cifre din contul serviciului de voce.']);
        VoiceStudio::saveKey($data['voice_key'], $data['voice_default']);
        $audit->record('platform.voice_key_saved', null, ['last4' => substr($data['voice_key'], -4)]);
        $count = count(VoiceStudio::voices());

        return redirect()->route('admin.ai')->with('status', $count > 0 ? "Cheia de voce a fost salvată: {$count} voci disponibile." : 'Cheia de voce a fost salvată, dar serviciul nu a întors nicio voce. Verifică cheia.');
    }

    public function clear(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->confirm($request);
        VoiceStudio::clearKey();
        $audit->record('platform.voice_key_removed');

        return redirect()->route('admin.ai')->with('status', 'Cheia de voce a fost ștearsă.');
    }

    private function confirm(Request $request): void
    {
        abort_unless($request->user()->platform_role === PlatformRole::SuperAdmin, 403);
        if (! Hash::check((string) $request->input('password'), (string) $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Parola nu este corectă.']);
        }
    }
}
