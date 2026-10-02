<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** O înscriere printr-un formular; păstrează dovada acordului (ce a văzut, de unde, când a confirmat). */
#[Fillable(['signup_form_id', 'contact_id', 'data', 'page', 'ip_address', 'user_agent', 'confirm_hash', 'confirmed_at', 'created_at'])]
class FormSubmission extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['data' => 'array', 'confirmed_at' => 'datetime'];
    }

    /** @return BelongsTo<SignupForm, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(SignupForm::class, 'signup_form_id');
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
