<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contact;
use App\Models\ContactList;
use App\Models\ContactListMember;

/** Membrii listelor statice; fiecare adăugare intră în activitatea contactului (poate porni automatizări). */
final class ListService
{
    public function __construct(private readonly ContactActivity $activity) {}

    public function add(ContactList $list, Contact $contact, string $source = 'manual'): bool
    {
        $member = ContactListMember::query()->firstOrCreate(
            ['contact_list_id' => $list->id, 'contact_id' => $contact->id],
            ['source' => $source, 'created_at' => now()],
        );
        if ($member->wasRecentlyCreated) {
            $this->activity->record($contact, 'joined_list', ['list_id' => $list->id, 'list' => $list->name, 'source' => $source]);
        }

        return $member->wasRecentlyCreated;
    }

    public function remove(ContactList $list, Contact $contact): void
    {
        ContactListMember::query()->where('contact_list_id', $list->id)->where('contact_id', $contact->id)->delete();
    }
}
