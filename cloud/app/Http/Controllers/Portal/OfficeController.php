<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Office\Office;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Biroul firmei: agenții platformei, fiecare cu ce răspunde, ce face acum și ce așteaptă de la om. */
final class OfficeController extends PortalController
{
    public function __invoke(Request $request, Office $office): View
    {
        // echipa VITIM vede starea brută; clientul vede ce ține de VITIM ca „în grija echipei”
        $desks = $office->desks(! $request->user()->isPlatformStaff());

        return view('portal.office', ['organization' => $this->organization(), 'desks' => $desks, 'summary' => Office::summary($desks)]);
    }
}
