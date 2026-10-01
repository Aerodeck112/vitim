<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformRole;
use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\Updater;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/**
 * Sistem: versiunea instalată și actualizarea din arhivă .zip. Doar super admin, cu parola reconfirmată
 * (actualizarea schimbă codul platformei pentru toți clienții).
 */
final class SystemController extends Controller
{
    public function show(Request $request, Updater $updater): View
    {
        $this->authorizeSuperAdmin($request);
        File::ensureDirectoryExists(Updater::inbox());

        return view('admin.system', [
            'version' => $updater->currentVersion(),
            'deployed' => trim((string) @file_get_contents(storage_path('app/deployed_version'))),
            'php' => PHP_VERSION,
            'uploadLimit' => min($this->bytes((string) ini_get('upload_max_filesize')), $this->bytes((string) ini_get('post_max_size'))),
            'pending' => collect(File::files(Updater::inbox()))->filter(fn ($f) => strtolower($f->getExtension()) === 'zip')
                ->map(fn ($f) => ['name' => $f->getFilename(), 'size' => $f->getSize(), 'time' => $f->getMTime()])->values(),
        ]);
    }

    public function upload(Request $request, Updater $updater, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        $request->validate(['archive' => ['required', 'file', 'max:262144'], 'password' => ['required', 'string']]);
        $this->confirmPassword($request);
        if (strtolower($request->file('archive')->getClientOriginalExtension()) !== 'zip') {
            throw ValidationException::withMessages(['archive' => 'Alege arhiva .zip a versiunii.']);
        }

        return $this->apply($updater, $audit, (string) $request->file('archive')->getRealPath(), $request->file('archive')->getClientOriginalName());
    }

    /** Arhivă urcată prin File Manager în storage/app/updates (pentru limite mici de upload). */
    public function applyPending(Request $request, Updater $updater, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        $data = $request->validate(['name' => ['required', 'string', 'regex:/^[A-Za-z0-9._-]+\.zip$/'], 'password' => ['required', 'string']]);
        $this->confirmPassword($request);
        $path = Updater::inbox().'/'.$data['name'];
        if (! is_file($path)) {
            throw ValidationException::withMessages(['name' => 'Arhiva nu mai există.']);
        }
        $response = $this->apply($updater, $audit, $path, $data['name']);
        if (session('ok')) {
            @unlink($path);
        }

        return $response;
    }

    private function apply(Updater $updater, AuditLogger $audit, string $path, string $name): RedirectResponse
    {
        $from = $updater->currentVersion();
        try {
            $result = $updater->apply($path);
        } catch (RuntimeException $e) {
            $audit->record('system.update_failed', null, ['archive' => mb_substr($name, 0, 120), 'error' => mb_substr($e->getMessage(), 0, 250)]);

            return redirect()->route('admin.system')->with('error', $e->getMessage());
        }
        $audit->record('system.updated', null, ['from' => $from, 'to' => $result['version'], 'files' => $result['files']]);

        return redirect()->route('admin.system')->with('ok', "Actualizat de la {$from} la {$result['version']} ({$result['files']} fișiere). Backup al bazei de date făcut înainte.");
    }

    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->platform_role === PlatformRole::SuperAdmin, 403);
    }

    private function confirmPassword(Request $request): void
    {
        if (! Hash::check((string) $request->input('password'), (string) $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Parola nu e corectă.']);
        }
    }

    private function bytes(string $value): int
    {
        $number = (int) $value;

        return match (strtolower(substr(trim($value), -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
