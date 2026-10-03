<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Models\Announcement;
use App\Models\AnnouncementDelivery;
use App\Services\Announcements;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** „Noutăți VITIM” în panoul firmei: emailurile de noutăți primite de firmă, ca să le găsească oricând. */
final class NewsController extends PortalController
{
    public function index(): View
    {
        $ids = AnnouncementDelivery::query()->distinct()->pluck('announcement_id');

        $items = Announcement::query()->whereIn('id', $ids)->where('status', 'sent')->latest('sent_at')->paginate(20);
        $company = $this->organization()->company_name ?: $this->organization()->name;

        return view('portal.news.index', [
            'organization' => $this->organization(), 'items' => $items,
            'intros' => $items->mapWithKeys(fn (Announcement $a) => [$a->id => str_replace('{{firma}}', $company, (string) $a->intro)]),
        ]);
    }

    public function show(Request $request, Announcements $service, int $announcement): View
    {
        // doar noutățile trimise acestei firme (altfel 404)
        abort_unless(AnnouncementDelivery::query()->where('announcement_id', $announcement)->exists(), 404);
        $a = Announcement::query()->whereKey($announcement)->where('status', 'sent')->firstOrFail();

        return view('portal.news.show', ['organization' => $this->organization(), 'a' => $a,
            'html' => $service->render($a, $this->organization(), $request->user())['html']]);
    }
}
