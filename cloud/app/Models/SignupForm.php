<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Formular de abonare afișat pe site prin scriptul VITIM (popup, flyout, bară sau încorporat în pagină). */
#[Fillable(['site_id', 'name', 'type', 'status', 'content', 'behavior', 'contact_list_id', 'double_opt_in'])]
class SignupForm extends Model
{
    use BelongsToOrganization;

    public const TYPES = [
        'popup' => ['Popup', 'Fereastră în mijlocul ecranului, peste pagină.'],
        'flyout' => ['Flyout', 'Casetă mică în colțul ecranului; nu acoperă pagina.'],
        'bar' => ['Bară', 'Bandă subțire sus sau jos, pe toată lățimea.'],
        'embed' => ['Încorporat', 'Formular în pagină (subsol, blog, pagina de contact), unde pui codul.'],
    ];

    protected function casts(): array
    {
        return ['content' => 'array', 'behavior' => 'array', 'double_opt_in' => 'boolean'];
    }

    /** @return BelongsTo<ContactList, $this> */
    public function list(): BelongsTo
    {
        return $this->belongsTo(ContactList::class, 'contact_list_id');
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function c(string $key, mixed $default = null): mixed
    {
        return ($this->content ?? [])[$key] ?? $default;
    }

    public function b(string $key, mixed $default = null): mixed
    {
        return ($this->behavior ?? [])[$key] ?? $default;
    }

    public function rate(): float
    {
        return $this->views > 0 ? round($this->submissions / $this->views * 100, 1) : 0.0;
    }
}
