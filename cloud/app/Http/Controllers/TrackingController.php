<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CampaignRecipient;
use App\Services\Tracking;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Pixelul de deschidere și redirectul semnat al click-urilor din emailuri. */
final class TrackingController extends Controller
{
    private const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function __construct(private readonly TenantContext $context, private readonly Tracking $tracking) {}

    public function open(string $code): Response
    {
        $recipient = $this->recipient($code);
        if ($recipient) {
            $this->context->runAs($recipient->organization, fn () => $this->tracking->opened($recipient));
        }

        return response(base64_decode(self::GIF), 200, ['Content-Type' => 'image/gif', 'Cache-Control' => 'no-store, max-age=0']);
    }

    public function click(Request $request, string $code): RedirectResponse|Response
    {
        $url = (string) $request->query('u', '');
        if (! str_starts_with($url, 'https://') || ! Tracking::valid($code, $url, (string) $request->query('s', ''))) {
            return response('Link invalid.', 404);
        }
        $recipient = $this->recipient($code);
        if ($recipient) {
            $this->context->runAs($recipient->organization, fn () => $this->tracking->clicked($recipient, $url));
        }

        return redirect()->away($url);
    }

    private function recipient(string $code): ?CampaignRecipient
    {
        // cod de platformă: destinatarul se află din codul aleator unic, firma din destinatar
        return preg_match('/^[A-Za-z0-9]{10}$/', $code) ? CampaignRecipient::withoutTenancy()->with('organization')->where('unsubscribe_code', $code)->first() : null;
    }
}
