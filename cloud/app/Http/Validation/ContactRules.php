<?php

declare(strict_types=1);

namespace App\Http\Validation;

use App\Enums\Channel;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentStatus;
use App\Enums\ContactSource;
use Illuminate\Validation\Rule;

/** Reguli comune API + interfață pentru contacte și consimțământ. */
final class ContactRules
{
    /** @return array<string, mixed> */
    public static function contact(bool $creating): array
    {
        return [
            'first_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'email' => ['sometimes', 'nullable', 'string', 'max:190'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'whatsapp' => [$creating ? 'sometimes' : 'prohibited', 'nullable', 'string', 'max:40'],
            'company' => ['sometimes', 'nullable', 'string', 'max:190'],
            'language' => ['sometimes', 'nullable', 'string', 'size:2', 'alpha'],
            'status' => ['sometimes', Rule::in(['active', 'archived'])],
            'source' => [$creating ? 'sometimes' : 'prohibited', Rule::enum(ContactSource::class)],
            'custom_fields' => ['sometimes', 'nullable', 'array', 'max:50'],
            'custom_fields.*' => ['nullable', 'scalar'],
            'external_ids' => [$creating ? 'sometimes' : 'prohibited', 'array', 'max:10'],
            'external_ids.*.provider' => ['required', 'string', 'max:40', 'alpha_dash'],
            'external_ids.*.id' => ['required', 'string', 'max:190'],
        ];
    }

    /** @return array<string, mixed> */
    public static function consent(): array
    {
        return [
            'channel' => ['required', Rule::in(array_map(fn ($c) => $c->value, Channel::consentChannels()))],
            'purpose' => ['required', Rule::enum(ConsentPurpose::class)],
            'status' => ['required', Rule::enum(ConsentStatus::class)],
            'source' => ['required', 'string', 'max:40'],
            'occurred_at' => ['sometimes', 'date', 'before_or_equal:now'],
            'metadata' => ['sometimes', 'array', 'max:20'],
            'metadata.*' => ['nullable', 'scalar'],
        ];
    }
}
