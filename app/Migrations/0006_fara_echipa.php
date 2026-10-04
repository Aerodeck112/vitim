<?php
declare(strict_types=1);

use App\Core\DB;

/**
 * v1.7.1: site-ul nu mai prezintă echipa și nu folosește nume de persoane.
 * Șterge setările echipei adăugate în 1.7.0 (nume, roluri, fotografii) unde au apucat să fie salvate.
 */
return function (): void {
    DB::delete('settings', "skey IN ('about_team', 'team_photo_1', 'team_photo_2', 'team_photo_3')");
    // titlul și descrierea paginii „Despre noi” din 1.7.0 (doar dacă nu au fost schimbate din panou)
    DB::q("UPDATE pages SET meta_title = ? WHERE slug = 'despre-noi' AND meta_title = ?", ['Despre VITIM – departamentul extern de IT & AI din Târgu Mureș', 'Despre VITIM – echipa de IT & AI din Târgu Mureș']);
    DB::q("UPDATE pages SET meta_description = ? WHERE slug = 'despre-noi' AND meta_description LIKE 'Cine suntem: echipa VITIM%'", ['Cine este VITIM: firma din Târgu Mureș care funcționează ca departamentul extern de IT & AI al companiilor. Mentenanță IT, securitate, backup, automatizări, AI, website și marketing.']);
    \App\Core\Cache::clear();
};
