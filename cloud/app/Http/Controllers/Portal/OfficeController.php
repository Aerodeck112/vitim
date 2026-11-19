<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Office\Office;
use Illuminate\View\View;

/** Biroul firmei: agenții platformei, fiecare cu ce răspunde, ce face acum și ce așteaptă de la om. */
final class OfficeController extends PortalController
{
    public function __invoke(Office $office): View
    {
        $desks = $office->desks();

        return view('portal.office', ['organization' => $this->organization(), 'desks' => $desks, 'summary' => Office::summary($desks)]);
    }
}
