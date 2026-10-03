<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AnnouncementDelivery;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Paginile publice ale emailurilor de noutăți VITIM: pixelul de deschidere și dezabonarea (link semnat). */
final class UpdatesController extends Controller
{
    private const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function open(string $code): Response
    {
        // cod de platformă: livrarea se află doar din codul aleator de 24 de caractere
        AnnouncementDelivery::withoutTenancy()->where('code', $code)->whereNull('opened_at')->update(['opened_at' => now()]);

        return response(base64_decode(self::GIF), 200, ['Content-Type' => 'image/gif', 'Cache-Control' => 'no-store, max-age=0']);
    }

    public function show(Request $request, User $user): View
    {
        abort_unless($request->hasValidSignature(), 403);

        return view('updates-unsubscribe', ['done' => $user->product_updates === false, 'url' => $request->fullUrl()]);
    }

    public function unsubscribe(Request $request, AuditLogger $audit, User $user): View
    {
        abort_unless($request->hasValidSignature(), 403);
        if ($user->product_updates !== false) {
            $user->forceFill(['product_updates' => false])->save();
            $audit->record('user.product_updates_off', $user, [], 'system');
        }

        return view('updates-unsubscribe', ['done' => true, 'url' => null]);
    }
}
