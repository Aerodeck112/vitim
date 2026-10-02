<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['contact_list_id', 'contact_id', 'source', 'created_at'])]
class ContactListMember extends Model
{
    use BelongsToOrganization;

    public const UPDATED_AT = null;
}
