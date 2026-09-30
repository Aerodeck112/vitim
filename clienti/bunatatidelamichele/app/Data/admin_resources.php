<?php
declare(strict_types=1);

/**
 * Resursele editate cu editorul generic din panou (Panou → /admin/c/<cheie>).
 */

use App\Core\DB;

$faq = [['key' => 'q', 'label' => 'Întrebare'], ['key' => 'a', 'label' => 'Răspuns', 'type' => 'textarea']];

return [
    'categorii' => [
        'label' => 'Categorii', 'singular' => 'categorie', 'table' => 'categories', 'url' => '/categorie/{slug}', 'seo' => true,
        'order' => 'sort, name', 'search' => ['name', 'slug'],
        'list' => ['name' => 'Categorie', 'slug' => 'Adresă', 'sort' => 'Ordine', 'published' => 'Stare'],
        'fields' => [
            ['name' => 'name', 'label' => 'Nume categorie', 'type' => 'text', 'required' => true],
            ['name' => 'slug', 'label' => 'Adresă', 'type' => 'slug', 'from' => 'name', 'prefix' => '/categorie/'],
            ['name' => 'h1', 'label' => 'Titlu mare pe pagină (H1, opțional)', 'type' => 'text', 'hint' => 'Ex: „Cafea boabe prăjită la foc de lemn”. Gol = numele categoriei.'],
            ['name' => 'intro', 'label' => 'Introducere scurtă (sub titlu)', 'type' => 'textarea'],
            ['name' => 'body', 'label' => 'Text SEO (sub lista de produse)', 'type' => 'richtext', 'hint' => '150–400 de cuvinte despre categorie ajută mult poziționarea în Google.'],
            ['name' => 'faq', 'label' => 'Întrebări frecvente', 'type' => 'repeater', 'fields' => $faq],
            ['name' => 'published', 'label' => 'Publicată', 'type' => 'checkbox', 'col' => 'side', 'default' => 1],
            ['name' => 'icon', 'label' => 'Iconiță (meniu)', 'type' => 'image', 'col' => 'side'],
            ['name' => 'image', 'label' => 'Imagine categorie', 'type' => 'image', 'col' => 'side'],
            ['name' => 'sort', 'label' => 'Ordine', 'type' => 'number', 'col' => 'side'],
        ],
    ],
    'pagini' => [
        'label' => 'Pagini', 'singular' => 'pagină', 'table' => 'pages', 'url' => '/{slug}', 'seo' => true,
        'order' => 'sort, title', 'search' => ['title', 'slug'], 'protected' => ['despre-noi', 'termeni-si-conditii', 'politica-de-confidentialitate', 'politica-cookies', 'livrare', 'politica-de-retur', 'metode-de-plata'],
        'note' => 'Variabile disponibile în texte: {{firma}}, {{cui}}, {{reg_com}}, {{adresa}}, {{email}}, {{telefon}}, {{livrare_cost}}, {{livrare_timp}}, {{retur_zile}}',
        'list' => ['title' => 'Titlu', 'slug' => 'Adresă', 'footer_group' => 'În subsol', 'updated_at' => 'Modificată', 'published' => 'Stare'],
        'fields' => [
            ['name' => 'title', 'label' => 'Titlu', 'type' => 'text', 'required' => true],
            ['name' => 'slug', 'label' => 'Adresă', 'type' => 'slug', 'from' => 'title', 'prefix' => '/'],
            ['name' => 'subtitle', 'label' => 'Subtitlu', 'type' => 'text'],
            ['name' => 'body', 'label' => 'Conținut', 'type' => 'richtext'],
            ['name' => 'published', 'label' => 'Publicată', 'type' => 'checkbox', 'col' => 'side', 'default' => 1],
            ['name' => 'in_footer', 'label' => 'Link în subsolul site-ului', 'type' => 'checkbox', 'col' => 'side'],
            ['name' => 'footer_group', 'label' => 'Coloana din subsol', 'type' => 'select', 'options' => ['info' => 'Informații', 'legal' => 'Legal'], 'col' => 'side'],
            ['name' => 'sort', 'label' => 'Ordine', 'type' => 'number', 'col' => 'side'],
        ],
    ],
    'cupoane' => [
        'label' => 'Coduri de reducere', 'singular' => 'cod de reducere', 'table' => 'coupons', 'url' => null, 'seo' => false,
        'order' => 'id DESC', 'search' => ['code', 'description'],
        'note' => 'Clienții introduc codul în coș',
        'list' => ['code' => 'Cod', 'type' => 'Reducere', 'min_total' => 'Minim comandă', 'used' => 'Folosit', 'expires_at' => 'Expiră', 'active' => 'Stare'],
        'fields' => [
            ['name' => 'code', 'label' => 'Cod (ex: CAFEA10)', 'type' => 'text', 'required' => true],
            ['name' => 'description', 'label' => 'Descriere internă', 'type' => 'text'],
            ['name' => 'type', 'label' => 'Tip reducere', 'type' => 'select', 'options' => ['percent' => 'Procent din valoarea produselor (%)', 'fixed' => 'Sumă fixă (lei)']],
            ['name' => 'value', 'label' => 'Valoare (procent sau lei)', 'type' => 'decimal'],
            ['name' => 'min_total', 'label' => 'Valoare minimă a comenzii (lei, 0 = fără)', 'type' => 'decimal'],
            ['name' => 'free_shipping', 'label' => 'Oferă și livrare gratuită', 'type' => 'checkbox'],
            ['name' => 'active', 'label' => 'Activ', 'type' => 'checkbox', 'col' => 'side', 'default' => 1],
            ['name' => 'max_uses', 'label' => 'Număr maxim de utilizări (0 = nelimitat)', 'type' => 'number', 'col' => 'side'],
            ['name' => 'starts_at', 'label' => 'Valabil de la (opțional)', 'type' => 'datetime', 'col' => 'side'],
            ['name' => 'expires_at', 'label' => 'Expiră la (opțional)', 'type' => 'datetime', 'col' => 'side'],
        ],
    ],
    'articole' => [
        'label' => 'Blog', 'singular' => 'articol', 'table' => 'posts', 'url' => '/blog/{slug}', 'seo' => true,
        'order' => 'COALESCE(published_at, created_at) DESC', 'search' => ['title', 'slug'],
        'note' => 'Blogul apare în meniu după primul articol publicat',
        'list' => ['title' => 'Titlu', 'status' => 'Stare', 'published_at' => 'Publicat'],
        'fields' => [
            ['name' => 'title', 'label' => 'Titlu', 'type' => 'text', 'required' => true],
            ['name' => 'slug', 'label' => 'Adresă', 'type' => 'slug', 'from' => 'title', 'prefix' => '/blog/'],
            ['name' => 'excerpt', 'label' => 'Rezumat', 'type' => 'textarea'],
            ['name' => 'body', 'label' => 'Conținut', 'type' => 'richtext'],
            ['name' => 'faq', 'label' => 'Întrebări frecvente', 'type' => 'repeater', 'fields' => $faq],
            ['name' => 'status', 'label' => 'Stare', 'type' => 'select', 'options' => ['draft' => 'Ciornă', 'published' => 'Publicat'], 'col' => 'side'],
            ['name' => 'published_at', 'label' => 'Data publicării (poate fi în viitor)', 'type' => 'datetime', 'col' => 'side'],
            ['name' => 'cover', 'label' => 'Imagine principală', 'type' => 'image', 'col' => 'side'],
        ],
    ],
    'recenzii' => [
        'label' => 'Recenzii', 'singular' => 'recenzie', 'table' => 'reviews', 'url' => null, 'seo' => false,
        'order' => 'published ASC, id DESC', 'search' => ['name', 'text'],
        'note' => 'Recenziile trimise de clienți apar pe site doar după ce le publici. Publică doar recenzii reale.',
        'filters' => ['published' => ['0' => 'De aprobat', '1' => 'Publicate']],
        'list' => ['name' => 'Nume', 'product_id' => 'Produs', 'rating' => 'Notă', 'text' => 'Text', 'verified' => 'Verificat', 'published' => 'Stare'],
        'fields' => [
            ['name' => 'name', 'label' => 'Nume client', 'type' => 'text', 'required' => true],
            ['name' => 'product_id', 'label' => 'Produs', 'type' => 'select', 'options' => fn() => array_column(DB::all('SELECT id, name FROM products ORDER BY name'), 'name', 'id')],
            ['name' => 'text', 'label' => 'Recenzie', 'type' => 'textarea', 'required' => true],
            ['name' => 'city', 'label' => 'Oraș', 'type' => 'text'],
            ['name' => 'published', 'label' => 'Publicată pe site', 'type' => 'checkbox', 'col' => 'side'],
            ['name' => 'verified', 'label' => 'Cumpărător verificat', 'type' => 'checkbox', 'col' => 'side'],
            ['name' => 'rating', 'label' => 'Notă', 'type' => 'select', 'options' => ['5' => '★★★★★', '4' => '★★★★', '3' => '★★★', '2' => '★★', '1' => '★'], 'col' => 'side'],
        ],
    ],
];
