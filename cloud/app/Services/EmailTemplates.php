<?php

declare(strict_types=1);

namespace App\Services;

/** Biblioteca de designuri gata făcute (texte de exemplu în română; imaginile se înlocuiesc cu ale firmei). */
final class EmailTemplates
{
    /** @return array<string, array{name: string, description: string, blocks: list<array<string, mixed>>}> */
    public static function all(): array
    {
        return [
            'simple' => ['name' => 'Simplu', 'description' => 'Logo, titlu, text și un buton.', 'blocks' => [
                ['type' => 'logo', 'align' => 'center'],
                ['type' => 'heading', 'text' => 'Bună {{prenume}},', 'size' => 'h1', 'align' => 'left'],
                ['type' => 'text', 'text' => "Scrie aici mesajul tău.\n\nPăstrează-l scurt și clar: un singur lucru important pe email.", 'align' => 'left'],
                ['type' => 'button', 'label' => 'Află mai mult', 'url' => '', 'align' => 'left'],
            ]],
            'offer' => ['name' => 'Ofertă / reducere', 'description' => 'Imagine mare, procentul de reducere, cod și buton.', 'blocks' => [
                ['type' => 'logo', 'align' => 'center'],
                ['type' => 'image', 'url' => '', 'alt' => 'Oferta lunii', 'link' => '', 'width' => 100],
                ['type' => 'heading', 'text' => '-20% la toată gama, până duminică', 'size' => 'h1', 'align' => 'center'],
                ['type' => 'text', 'text' => "{{prenume}}, folosește codul **PRIMAVARA20** la finalizarea comenzii.\nOferta e valabilă până duminică la miezul nopții.", 'align' => 'center'],
                ['type' => 'button', 'label' => 'Vezi produsele', 'url' => '', 'align' => 'center'],
                ['type' => 'social'],
            ]],
            'product' => ['name' => 'Produs nou', 'description' => 'Prezentarea unui produs sau serviciu, cu preț și buton.', 'blocks' => [
                ['type' => 'logo', 'align' => 'left'],
                ['type' => 'heading', 'text' => 'Nou la noi', 'size' => 'h1', 'align' => 'left'],
                ['type' => 'text', 'text' => 'Bună {{prenume}}, ți-l prezentăm pe cel mai nou membru al familiei:', 'align' => 'left'],
                ['type' => 'product', 'name' => 'Numele produsului', 'price' => 'de la 0 lei', 'image' => null, 'url' => null, 'label' => 'Vezi detalii', 'description' => 'Două rânduri despre ce îl face special.'],
                ['type' => 'divider'],
                ['type' => 'text', 'text' => 'Ai întrebări? Răspunde la acest email, te ajutăm cu drag.', 'align' => 'left'],
            ]],
            'newsletter' => ['name' => 'Newsletter', 'description' => 'Noutățile lunii în două coloane, cu imagini.', 'blocks' => [
                ['type' => 'logo', 'align' => 'center'],
                ['type' => 'heading', 'text' => 'Noutățile lunii', 'size' => 'h1', 'align' => 'center'],
                ['type' => 'text', 'text' => 'Bună {{prenume}}, iată ce s-a întâmplat la {{firma}} luna aceasta.', 'align' => 'center'],
                ['type' => 'columns', 'left_image' => null, 'left_text' => "**Proiect finalizat**\nPe scurt despre un proiect recent.", 'left_link' => null, 'right_image' => null, 'right_text' => "**Sfatul lunii**\nUn sfat util pentru clienți.", 'right_link' => null],
                ['type' => 'divider'],
                ['type' => 'heading', 'text' => 'Oferta lunii', 'size' => 'h2', 'align' => 'left'],
                ['type' => 'text', 'text' => 'Descrie oferta în două-trei rânduri.', 'align' => 'left'],
                ['type' => 'button', 'label' => 'Vezi oferta', 'url' => '', 'align' => 'left'],
                ['type' => 'social'],
            ]],
            'event' => ['name' => 'Eveniment / invitație', 'description' => 'Invitație cu dată, loc și buton de înscriere.', 'blocks' => [
                ['type' => 'logo', 'align' => 'center'],
                ['type' => 'heading', 'text' => 'Ești invitat, {{prenume}}!', 'size' => 'h1', 'align' => 'center'],
                ['type' => 'text', 'text' => "**Când:** sâmbătă, 15 noiembrie, ora 10:00\n**Unde:** showroom-ul nostru, Str. …\n\nTe așteptăm cu demonstrații, cafea și surprize.", 'align' => 'center'],
                ['type' => 'button', 'label' => 'Confirm participarea', 'url' => '', 'align' => 'center'],
            ]],
        ];
    }
}
