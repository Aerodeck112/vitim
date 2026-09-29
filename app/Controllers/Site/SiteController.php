<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Seo;
use App\Core\View;

abstract class SiteController
{
    protected function view(string $view, array $data, Seo $seo): string
    {
        return View::render('site/' . $view, $data + ['seo' => $seo], 'site/layout');
    }

    protected function seo(string $title, string $description = '', array $breadcrumbs = []): Seo
    {
        $s = new Seo();
        $s->title = $title;
        $s->description = $description;
        $s->breadcrumbs = $breadcrumbs;
        return $s;
    }

    protected function applyMeta(Seo $seo, array $row): void
    {
        if (!empty($row['meta_title'])) {
            $seo->title = $row['meta_title'];
        }
        if (!empty($row['meta_description'])) {
            $seo->description = $row['meta_description'];
        }
        if (!empty($row['canonical'])) {
            $seo->canonical = abs_url($row['canonical']);
        }
        if (!empty($row['og_image'])) {
            $seo->image = abs_url(upload_url($row['og_image']));
        }
        if (!empty($row['noindex'])) {
            $seo->noindex = true;
        }
        if (!empty($row['updated_at'])) {
            $seo->modified = $row['updated_at'];
        }
    }

    protected function notFound(): string
    {
        return (new PageController())->notFound();
    }
}
