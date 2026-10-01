<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Enums\Channel;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentStatus;
use App\Enums\ContactSource;
use App\Enums\IdentityType;
use App\Enums\LeadIntent;
use App\Models\Agent;
use App\Models\Lead;
use App\Services\ConsentService;
use App\Services\ContactService;
use App\Services\IdentityNormalizer;
use App\Services\LeadService;
use Illuminate\Support\Facades\DB;

/**
 * Salvează cererea vizitatorului: contact (deduplicat după email / telefon), acordul de a fi contactat
 * (istoric de consimțământ) și lead-ul legat de conversație. Fără acord explicit nu se salvează nimic.
 */
final class CreateLeadTool implements Tool
{
    private const LABELS = [
        'name' => 'numele', 'phone' => 'telefonul', 'email' => 'emailul', 'company' => 'firma',
        'requested_service' => 'serviciul dorit', 'product' => 'produsul', 'budget' => 'bugetul',
        'preferred_date' => 'data sau intervalul preferat', 'notes' => 'detaliile',
    ];

    public function __construct(
        private readonly ContactService $contacts,
        private readonly ConsentService $consents,
        private readonly LeadService $leads,
    ) {}

    public function name(): string
    {
        return 'create_lead';
    }

    public function definition(Agent $agent): array
    {
        $required = $agent->system_configuration['lead_rules']['required_fields'] ?? [];
        $string = fn (string $d) => ['type' => 'string', 'description' => $d.' Șir gol dacă nu a fost dat.'];

        return [
            'name' => 'create_lead',
            'description' => 'Salvează cererea vizitatorului ca să fie contactat de firmă. Apelează doar după ce vizitatorul a dat datele cerute'
                .($required ? ' ('.implode(', ', array_map(fn ($f) => self::LABELS[$f] ?? $f, $required)).')' : '')
                .' și a confirmat explicit că este de acord să fie contactat. O singură dată pe conversație; un nou apel actualizează cererea.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'name' => $string('Numele persoanei.'),
                    'phone' => $string('Telefonul.'),
                    'email' => $string('Emailul.'),
                    'company' => $string('Firma vizitatorului.'),
                    'requested_service' => $string('Serviciul dorit.'),
                    'product' => $string('Produsul sau modelul (ex. mașina: marcă, model, an, motorizare).'),
                    'budget' => $string('Bugetul menționat.'),
                    'preferred_date' => $string('Data sau intervalul preferat.'),
                    'notes' => $string('Alte detalii utile.'),
                    'intent' => ['type' => 'string', 'enum' => array_column(LeadIntent::cases(), 'value'), 'description' => 'Ce vrea vizitatorul.'],
                    'summary' => ['type' => 'string', 'description' => 'Rezumatul cererii în 2–4 propoziții, pentru echipa firmei.'],
                    'consent' => ['type' => 'boolean', 'description' => 'true doar dacă vizitatorul a confirmat explicit că poate fi contactat.'],
                ],
                'required' => ['name', 'phone', 'email', 'company', 'requested_service', 'product', 'budget', 'preferred_date', 'notes', 'intent', 'summary', 'consent'],
                'additionalProperties' => false,
            ],
        ];
    }

    public function handle(ToolContext $context, array $input): ToolResult
    {
        if (($input['consent'] ?? false) !== true) {
            return ToolResult::rejected('Nu am salvat: vizitatorul nu a confirmat explicit că este de acord să fie contactat. Întreabă-l.');
        }
        $d = [];
        foreach (array_keys(self::LABELS) as $field) {
            $d[$field] = mb_substr(trim((string) ($input[$field] ?? '')), 0, $field === 'notes' ? 1000 : 190);
        }
        $summary = mb_substr(trim((string) ($input['summary'] ?? '')), 0, 3000);
        $intent = LeadIntent::tryFrom((string) ($input['intent'] ?? '')) ?? LeadIntent::Other;

        if ($d['email'] !== '' && IdentityNormalizer::email($d['email']) === null) {
            return ToolResult::rejected('Emailul nu pare valid. Roagă vizitatorul să-l verifice.');
        }
        if ($d['phone'] !== '' && IdentityNormalizer::phone($d['phone']) === null) {
            return ToolResult::rejected('Numărul de telefon nu pare valid. Roagă vizitatorul să-l verifice.');
        }
        $missing = array_filter(
            $context->agent->system_configuration['lead_rules']['required_fields'] ?? [],
            fn (string $f) => ($d[$f] ?? '') === '',
        );
        if ($missing) {
            return ToolResult::rejected('Nu am salvat: lipsesc '.implode(', ', array_map(fn ($f) => self::LABELS[$f] ?? $f, $missing)).'. Cere-le politicos.');
        }
        if ($d['phone'] === '' && $d['email'] === '') {
            return ToolResult::rejected('Nu am salvat: e nevoie de un telefon sau de un email ca firma să poată reveni.');
        }

        if ($context->isTest()) {
            return ToolResult::dryRun('Cererea a fost înregistrată (conversație de test din panou: nu s-a creat un lead real).');
        }

        $details = array_filter([
            'Serviciu' => $d['requested_service'], 'Produs' => $d['product'], 'Buget' => $d['budget'],
            'Data preferată' => $d['preferred_date'], 'Detalii' => $d['notes'],
        ]);
        $text = trim($summary."\n\n".implode("\n", array_map(fn ($k, $v) => "{$k}: {$v}", array_keys($details), $details)));

        $updated = DB::transaction(function () use ($context, $d, $intent, $text): bool {
            $conversation = $context->conversation;
            $contact = ($d['email'] !== '' ? $this->contacts->findByIdentity(IdentityType::Email, $d['email']) : null)
                ?? ($d['phone'] !== '' ? $this->contacts->findByIdentity(IdentityType::Phone, $d['phone']) : null)
                ?? $this->contacts->create([
                    'first_name' => $d['name'] ?: null, 'email' => $d['email'] ?: null,
                    'phone' => $d['phone'] ?: null, 'company' => $d['company'] ?: null,
                ], ContactSource::WebsiteAi);

            $this->consents->record(
                $contact,
                $d['phone'] !== '' ? Channel::Phone : Channel::Email,
                ConsentPurpose::Service,
                ConsentStatus::Granted,
                'website_ai',
                ['conversation_id' => $conversation->getKey()],
                $context->ip,
                $context->userAgent,
            );
            $conversation->forceFill(['contact_id' => $contact->getKey()])->save();

            $existing = Lead::query()->where('conversation_id', $conversation->getKey())->first();
            if ($existing) {
                $this->leads->update($existing, ['summary' => $text, 'intent' => $intent->value]);

                return true;
            }
            $this->leads->create($contact, [
                'site_id' => $conversation->site_id,
                'agent_id' => $context->agent->getKey(),
                'conversation_id' => $conversation->getKey(),
                'source' => ContactSource::WebsiteAi->value,
                'intent' => $intent->value,
                'summary' => $text,
            ]);

            return false;
        });

        return ToolResult::ok($updated ? 'Cererea era deja salvată; am actualizat detaliile.' : 'Cererea a fost salvată. Echipa firmei revine către vizitator.');
    }
}
