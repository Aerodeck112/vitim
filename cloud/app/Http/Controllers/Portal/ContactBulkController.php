<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\Channel;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentStatus;
use App\Enums\Permission;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\Suppression;
use App\Services\AuditLogger;
use App\Services\ConsentService;
use App\Services\ListService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Acțiuni pe mai multe contacte deodată: adăugare într-o listă (existentă sau nouă) și, opțional,
 * acordul de marketing declarat o singură dată pentru toate (cu sursa acordului, ca la import).
 * Contactele vin doar din firma curentă; un acord retras sau o adresă dezabonată nu se suprascrie.
 */
final class ContactBulkController extends PortalController
{
    public const MAX = 10000;

    public function __invoke(Request $request, ListService $lists, ConsentService $consents, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'scope' => ['required', 'in:selected,all'],
            'ids' => ['required_if:scope,selected', 'array', 'max:500'], 'ids.*' => ['integer'],
            'q' => ['nullable', 'string', 'max:120'],
            'list_id' => ['nullable', 'integer'],
            'new_list' => ['nullable', 'string', 'max:120'],
            'consent' => ['nullable', 'array'], 'consent.*' => ['in:email,sms,whatsapp'],
            'evidence' => ['required_with:consent', 'nullable', 'string', 'max:300'],
            'declare' => ['exclude_without:consent', 'required', 'accepted'],
        ], [
            'ids.required_if' => 'Bifează cel puțin un contact.',
            'evidence.required_with' => 'Scrie de unde ai acordul contactelor (ex. „clienți care au cerut ofertă pe site”).',
            'declare.required' => 'Confirmă că ai acordul documentat al acestor persoane.',
            'declare.accepted' => 'Confirmă că ai acordul documentat al acestor persoane.',
        ]);
        $channels = array_map(fn ($c) => Channel::from($c), (array) ($data['consent'] ?? []));
        $newList = trim((string) ($data['new_list'] ?? ''));
        if (empty($data['list_id']) && $newList === '' && ! $channels) {
            throw ValidationException::withMessages(['list_id' => 'Alege lista în care adaugi contactele sau bifează acordul de marketing.']);
        }
        if (! empty($data['list_id']) || $newList !== '') {
            Gate::authorize(Permission::ManageCampaigns->value);
        }
        if ($channels) {
            Gate::authorize(Permission::ManageConsent->value);
        }

        $query = $data['scope'] === 'all' ? self::search(Contact::query(), (string) ($data['q'] ?? '')) : Contact::query()->whereIn('id', $data['ids']);
        $total = (clone $query)->count();
        if ($total === 0) {
            throw ValidationException::withMessages(['ids' => 'Niciun contact selectat.']);
        }
        if ($total > self::MAX) {
            throw ValidationException::withMessages(['ids' => 'Sunt prea multe contacte deodată (maximum '.number_format(self::MAX, 0, ',', '.').'). Restrânge căutarea.']);
        }

        $list = null;
        if (! empty($data['list_id'])) {
            $list = ContactList::query()->findOrFail($data['list_id']);
        } elseif ($newList !== '') {
            $list = ContactList::query()->firstOrCreate(['name' => $newList]);
            if ($list->wasRecentlyCreated) {
                $audit->record('list.created', $list);
            }
        }

        $stats = ['added' => 0, 'already' => 0, 'consents' => 0, 'kept' => 0, 'no_address' => 0];
        $query->chunkById(500, function ($contacts) use ($list, $lists, $consents, $channels, $data, $request, &$stats): void {
            foreach ($contacts as $contact) {
                if ($list) {
                    $lists->add($list, $contact, 'manual') ? $stats['added']++ : $stats['already']++;
                }
                foreach ($channels as $channel) {
                    $address = $channel === Channel::Email ? $contact->email : $contact->phone;
                    if (! $address) {
                        $stats['no_address']++;

                        continue;
                    }
                    $current = $consents->current($contact, $channel, ConsentPurpose::Marketing);
                    if ($current === ConsentStatus::Granted) {
                        continue;
                    }
                    // cine a refuzat sau s-a dezabonat rămâne așa: acordul general nu îl readuce
                    if ($current === ConsentStatus::Revoked
                        || Suppression::query()->where('channel', $channel->value)->where('value_hash', Suppression::hash($address))->exists()) {
                        $stats['kept']++;

                        continue;
                    }
                    $consents->record($contact, $channel, ConsentPurpose::Marketing, ConsentStatus::Granted, 'bulk',
                        ['evidence' => mb_substr((string) $data['evidence'], 0, 300)], $request->ip(), $request->userAgent(), $request->user());
                    $stats['consents']++;
                }
            }
        });

        $audit->record('contacts.bulk_updated', $list, $stats + ['contacts' => $total, 'channels' => implode(',', $data['consent'] ?? [])]);

        $parts = [];
        if ($list) {
            $parts[] = "{$stats['added']} adăugate în lista „{$list->name}”".($stats['already'] ? " ({$stats['already']} erau deja)" : '');
        }
        if ($channels) {
            $parts[] = "{$stats['consents']} acorduri de marketing înregistrate";
            if ($stats['kept']) {
                $parts[] = "{$stats['kept']} au refuzat sau s-au dezabonat (rămân fără acord)";
            }
            if ($stats['no_address']) {
                $parts[] = "{$stats['no_address']} fără adresă pe canalul ales";
            }
        }

        return back()->with('ok', ucfirst(implode(', ', $parts)).'.');
    }

    /** @param Builder<Contact> $query @return Builder<Contact> */
    public static function search(Builder $query, string $q): Builder
    {
        $q = trim($q);
        if ($q === '') {
            return $query;
        }
        $like = '%'.addcslashes($q, '%_\\').'%';

        return $query->where(fn ($w) => $w->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)
            ->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('company', 'like', $like));
    }
}
