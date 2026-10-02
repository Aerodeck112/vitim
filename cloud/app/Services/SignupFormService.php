<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Channel;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentStatus;
use App\Enums\ContactSource;
use App\Enums\IdentityType;
use App\Messaging\OutboundMessage;
use App\Models\ChannelAccount;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\FormSubmission;
use App\Models\Organization;
use App\Models\SignupForm;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Formularele de abonare: setări, ce primește scriptul de pe site, înscrierea și dubla confirmare.
 * Acordul de marketing pe email se dă la înscriere (confirmare simplă) sau la click pe linkul din email (dublă confirmare);
 * acordul pentru SMS doar cu bifa separată. Fiecare înscriere păstrează textul de acord afișat, pagina, IP-ul și browserul.
 */
final class SignupFormService
{
    public const CONFIRM_DAYS = 30;

    public function __construct(
        private readonly ContactService $contacts,
        private readonly ConsentService $consents,
        private readonly ListService $lists,
        private readonly ContactActivity $activity,
        private readonly AuditLogger $audit,
    ) {}

    /** @return array{content: array<string, mixed>, behavior: array<string, mixed>} */
    public static function defaults(string $type, Organization $organization): array
    {
        $color = (string) (($organization->branding ?? [])['color'] ?? '#2f6bff');

        return [
            'content' => [
                'title' => match ($type) {
                    'bar' => 'Abonează-te și primești 10% reducere la prima comandă',
                    'embed' => 'Abonează-te la noutăți',
                    default => 'Primești 10% reducere',
                },
                'text' => $type === 'bar' ? '' : 'Lasă-ne emailul și îți trimitem codul de reducere, plus ofertele și noutățile noastre.',
                'button' => 'Vreau reducerea',
                'fields' => ['email'],
                'sms' => false,
                'image_url' => null,
                'color' => preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#2f6bff',
                'background' => '#ffffff',
                'position' => $type === 'bar' ? 'top' : 'right',
                'success_title' => 'Mulțumim!',
                'success_text' => '',
                'coupon' => '',
            ],
            'behavior' => [
                'trigger' => in_array($type, ['bar', 'embed'], true) ? 'immediate' : 'delay',
                'delay' => 6,
                'scroll' => 40,
                'devices' => 'all',
                'frequency_days' => 7,
                'include' => '',
                'exclude' => '',
            ],
        ];
    }

    public function create(string $type, string $name, Organization $organization): SignupForm
    {
        $type = array_key_exists($type, SignupForm::TYPES) ? $type : 'popup';
        $defaults = self::defaults($type, $organization);
        $form = SignupForm::create(['name' => mb_substr($name, 0, 120) ?: SignupForm::TYPES[$type][0], 'type' => $type, 'status' => 'draft',
            'content' => $defaults['content'], 'behavior' => $defaults['behavior'], 'double_opt_in' => true]);
        $this->audit->record('signup_form.created', $form);

        return $form;
    }

    /** @param array<string, mixed> $input */
    public function update(SignupForm $form, array $input): void
    {
        $text = fn (string $k, int $max, string $default = '') => mb_substr(trim((string) ($input[$k] ?? $default)), 0, $max);
        $hex = fn (string $k, string $default) => preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($input[$k] ?? '')) ? (string) $input[$k] : $default;
        $fields = array_values(array_unique(array_merge(['email'], array_intersect((array) ($input['fields'] ?? []), ['first_name', 'phone']))));
        $image = trim((string) ($input['image_url'] ?? ''));
        $form->fill([
            'name' => $text('name', 120, $form->name) ?: $form->name,
            'site_id' => ($siteId = (int) ($input['site_id'] ?? 0)) && Site::query()->whereKey($siteId)->exists() ? $siteId : null,
            'contact_list_id' => ContactList::query()->whereKey((int) ($input['contact_list_id'] ?? 0))->value('id'),
            'double_opt_in' => ! empty($input['double_opt_in']),
            'content' => [
                'title' => $text('title', 160), 'text' => $text('text', 600), 'button' => $text('button', 40, 'Abonează-mă') ?: 'Abonează-mă',
                'fields' => $fields, 'sms' => in_array('phone', $fields, true) && ! empty($input['sms']),
                'image_url' => str_starts_with($image, 'https://') && mb_strlen($image) <= 500 ? $image : null,
                'color' => $hex('color', '#2f6bff'), 'background' => $hex('background', '#ffffff'),
                'position' => in_array($input['position'] ?? '', ['left', 'right', 'top', 'bottom'], true) ? $input['position'] : 'right',
                'success_title' => $text('success_title', 160, 'Mulțumim!') ?: 'Mulțumim!', 'success_text' => $text('success_text', 600), 'coupon' => $text('coupon', 40),
            ],
            'behavior' => [
                'trigger' => in_array($input['trigger'] ?? '', ['immediate', 'delay', 'scroll', 'exit'], true) ? $input['trigger'] : 'delay',
                'delay' => max(0, min(600, (int) ($input['delay'] ?? 6))), 'scroll' => max(5, min(100, (int) ($input['scroll'] ?? 40))),
                'devices' => in_array($input['devices'] ?? '', ['all', 'desktop', 'mobile'], true) ? $input['devices'] : 'all',
                'frequency_days' => max(0, min(365, (int) ($input['frequency_days'] ?? 7))),
                'include' => $this->paths((string) ($input['include'] ?? '')), 'exclude' => $this->paths((string) ($input['exclude'] ?? '')),
            ],
        ])->save();
        $this->audit->record('signup_form.updated', $form);
    }

    public function setStatus(SignupForm $form, string $status): void
    {
        if ($status === 'live') {
            if ($form->double_opt_in && ! ChannelAccount::query()->where('channel', 'email')->exists()) {
                throw ValidationException::withMessages(['form' => 'Dubla confirmare trimite un email de confirmare: conectează întâi contul de email în Canale de trimitere (sau dezactivează dubla confirmare).']);
            }
            if (trim((string) $form->c('title')) === '' && trim((string) $form->c('text')) === '') {
                throw ValidationException::withMessages(['form' => 'Scrie un titlu sau un text pentru formular.']);
            }
        }
        $form->forceFill(['status' => $status === 'live' ? 'live' : 'draft'])->save();
        $this->audit->record('signup_form.'.($status === 'live' ? 'published' : 'unpublished'), $form);
    }

    /** Ce trimitem scriptului de pe site (fără liste, statistici sau alte date interne). @return array<string, mixed> */
    public static function publicPayload(SignupForm $form, Organization $organization, ?string $privacyUrl = null): array
    {
        return [
            'id' => $form->id, 'type' => $form->type,
            'content' => $form->content,
            'behavior' => $form->behavior,
            'consent' => self::consentText($organization),
            'sms_consent' => self::smsConsentText($organization),
            'privacy_url' => $privacyUrl,
        ];
    }

    public static function consentText(Organization $organization): string
    {
        return 'Prin abonare ești de acord să primești pe email noutăți și oferte de la '.CampaignRenderer::company($organization).'. Te poți dezabona oricând, dintr-un click.';
    }

    public static function smsConsentText(Organization $organization): string
    {
        return 'Vreau să primesc oferte și prin SMS de la '.CampaignRenderer::company($organization).'.';
    }

    /**
     * @param  array<string, mixed>  $input  email, first_name, phone, sms (bifa), page
     * @return 'confirm'|'subscribed'
     */
    public function submit(SignupForm $form, Organization $organization, array $input, ?string $ip, ?string $userAgent): string
    {
        $email = IdentityNormalizer::email(mb_substr(trim((string) ($input['email'] ?? '')), 0, 190));
        if ($email === null) {
            throw ValidationException::withMessages(['email' => 'Adresa de email nu e validă.']);
        }
        $phone = in_array('phone', (array) $form->c('fields', []), true) && trim((string) ($input['phone'] ?? '')) !== ''
            ? IdentityNormalizer::phone((string) $input['phone']) : null;
        if (in_array('phone', (array) $form->c('fields', []), true) && trim((string) ($input['phone'] ?? '')) !== '' && $phone === null) {
            throw ValidationException::withMessages(['phone' => 'Numărul de telefon nu e valid.']);
        }
        $name = in_array('first_name', (array) $form->c('fields', []), true) ? mb_substr(trim(strip_tags((string) ($input['first_name'] ?? ''))), 0, 80) : '';
        $sms = $phone !== null && $form->c('sms') && ($input['sms'] ?? false) === true;
        $page = mb_substr((string) ($input['page'] ?? ''), 0, 255) ?: null;

        $contact = $this->contactFor($email, $phone, $name);
        $double = $form->double_opt_in && $this->consents->current($contact, Channel::Email, ConsentPurpose::Marketing) !== ConsentStatus::Granted;
        $code = $double ? Str::random(40) : null;
        $submission = DB::transaction(function () use ($form, $organization, $contact, $email, $phone, $name, $sms, $page, $ip, $userAgent, $code): FormSubmission {
            $submission = FormSubmission::create([
                'signup_form_id' => $form->id, 'contact_id' => $contact->id, 'page' => $page, 'ip_address' => $ip, 'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
                'confirm_hash' => $code ? hash('sha256', $code) : null, 'created_at' => now(),
                'data' => array_filter(['email' => $email, 'phone' => $phone, 'first_name' => $name ?: null, 'sms' => $sms ?: null,
                    'consent_text' => self::consentText($organization), 'sms_consent_text' => $sms ? self::smsConsentText($organization) : null,
                    'form' => $form->name, 'double_opt_in' => $form->double_opt_in]),
            ]);
            SignupForm::query()->whereKey($form->id)->increment('submissions');
            $this->activity->record($contact, 'form_submitted', ['form_id' => $form->id, 'form' => $form->name, 'page' => $page]);
            if ($sms) {
                $this->consents->record($contact, Channel::Sms, ConsentPurpose::Marketing, ConsentStatus::Granted, 'signup_form',
                    ['form_id' => $form->id, 'submission_id' => $submission->id], $ip, $userAgent);
            }

            return $submission;
        });

        if ($code) {
            $this->sendConfirmation($organization, $email, $name, $code);

            return 'confirm';
        }
        $this->grant($submission, $form, $ip, $userAgent);

        return 'subscribed';
    }

    /** Linkul din emailul de confirmare. Întoarce înscrierea confirmată sau null (cod greșit / expirat). */
    public function confirm(FormSubmission $submission, ?string $ip, ?string $userAgent): bool
    {
        if ($submission->confirmed_at) {
            return true;
        }
        if ($submission->created_at->lt(now()->subDays(self::CONFIRM_DAYS)) || ! $submission->form) {
            return false;
        }
        $this->grant($submission, $submission->form, $ip, $userAgent, true);

        return true;
    }

    private function grant(FormSubmission $submission, SignupForm $form, ?string $ip, ?string $userAgent, bool $confirmed = false): void
    {
        DB::transaction(function () use ($submission, $form, $ip, $userAgent, $confirmed): void {
            $contact = $submission->contact;
            if (! $contact) {
                return;
            }
            $submission->forceFill(['confirmed_at' => now()])->save();
            if ($this->consents->current($contact, Channel::Email, ConsentPurpose::Marketing) !== ConsentStatus::Granted) {
                $this->consents->record($contact, Channel::Email, ConsentPurpose::Marketing, ConsentStatus::Granted, 'signup_form',
                    array_filter(['form_id' => $form->id, 'submission_id' => $submission->id, 'double_opt_in' => $confirmed ?: null]), $ip, $userAgent);
            }
            if ($form->list) {
                $this->lists->add($form->list, $contact, 'form');
            }
        });
    }

    private function contactFor(string $email, ?string $phone, string $name): Contact
    {
        $contact = $this->contacts->findByIdentity(IdentityType::Email, $email)
            ?? ($phone ? $this->contacts->findByIdentity(IdentityType::Phone, $phone) : null);
        if (! $contact) {
            return $this->contacts->create(array_filter(['first_name' => $name ?: null, 'email' => $email, 'phone' => $phone]), ContactSource::Form);
        }
        $missing = array_filter(['first_name' => ! $contact->first_name && $name ? $name : null, 'email' => ! $contact->email ? $email : null, 'phone' => ! $contact->phone && $phone ? $phone : null]);

        return $missing ? $this->contacts->update($contact, $missing) : $contact;
    }

    private function sendConfirmation(Organization $organization, string $email, string $name, string $code): void
    {
        $account = ChannelAccount::query()->where('channel', 'email')->first();
        if (! $account) {
            return; // înscrierea rămâne neconfirmată (fără acord) până se conectează contul și persoana se reabonează
        }
        $company = CampaignRenderer::company($organization);
        $url = route('forms.confirm', $code);
        $color = (string) (($organization->branding ?? [])['color'] ?? '#2f6bff');
        $greeting = $name !== '' ? 'Bună '.$name.',' : 'Bună,';
        $text = $greeting."\n\nConfirmă abonarea la noutățile și ofertele ".$company.' deschizând linkul de mai jos:'."\n".$url
            ."\n\nDacă nu tu te-ai abonat, ignoră acest email: nu vei primi nimic de la noi.\n\n--\n".CampaignRenderer::footer($organization);
        $html = view('emails.confirm-subscription', ['greeting' => $greeting, 'company' => $company, 'url' => $url,
            'color' => preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#2f6bff', 'footer' => CampaignRenderer::footer($organization),
            'logo' => ($organization->branding ?? [])['logo_url'] ?? null])->render();
        CampaignService::sender(Channel::Email)->send($account, new OutboundMessage(0, Channel::Email, $email, $text, 'Confirmă abonarea la '.$company, ['html' => $html]));
    }

    private function paths(string $value): string
    {
        $lines = array_slice(array_values(array_filter(array_map(fn ($l) => mb_substr(trim($l), 0, 120), preg_split('/\R/', $value) ?: []))), 0, 20);

        return implode("\n", $lines);
    }
}
