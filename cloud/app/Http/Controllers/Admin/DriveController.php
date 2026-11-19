<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformRole;
use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Social\DriveError;
use App\Social\DriveLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Cheia contului de serviciu Google al platformei, pentru citirea folderelor de Drive (doar super admin, cu parola contului). */
final class DriveController extends Controller
{
    public function save(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->confirm($request);
        $request->validate(['drive_key' => ['required', 'file', 'max:20']], ['drive_key.required' => 'Alege fișierul JSON al contului de serviciu.']);
        try {
            DriveLibrary::saveKey((string) file_get_contents($request->file('drive_key')->getRealPath()));
        } catch (DriveError $e) {
            throw ValidationException::withMessages(['drive_key' => $e->getMessage()]);
        }
        $audit->record('platform.drive_key_saved', null, ['email' => DriveLibrary::email()]);

        return redirect()->route('admin.ai')->with('status', 'Google Drive este configurat. Firmele își partajează folderul cu '.DriveLibrary::email().'.');
    }

    public function clear(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->confirm($request);
        DriveLibrary::clearKey();
        $audit->record('platform.drive_key_removed');

        return redirect()->route('admin.ai')->with('status', 'Cheia de Google Drive a fost ștearsă.');
    }

    private function confirm(Request $request): void
    {
        abort_unless($request->user()->platform_role === PlatformRole::SuperAdmin, 403);
        if (! Hash::check((string) $request->input('password'), (string) $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Parola nu este corectă.']);
        }
    }
}
