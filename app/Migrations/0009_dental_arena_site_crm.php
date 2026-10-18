<?php
declare(strict_types=1);

use App\Core\Cache;
use App\Core\DB;
use App\Core\ProjectSync;

/**
 * v1.10: studiul de caz Dental Arena (site nou + CRM), primul pe prima pagină.
 * Înlocuiește vechiul proiect „Dental Arena: marketing digital…”; adresa veche trimite spre cea nouă.
 */
return function (): void {
    ProjectSync::run();
    $old = 'dental-arena-marketing-si-comunicare';
    $new = 'dental-arena-site-si-crm';
    DB::delete('projects', 'slug = ?', [$old]);
    $from = '/proiecte/' . $old;
    if (!DB::val('SELECT id FROM redirects WHERE from_path = ?', [$from])) {
        DB::insert('redirects', ['from_path' => $from, 'to_path' => '/proiecte/' . $new, 'code' => 301, 'hits' => 0, 'created_at' => DB::now()]);
    }
    Cache::clear();
};
