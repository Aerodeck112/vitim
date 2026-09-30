<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AgentStatus;
use App\Enums\Channel;
use App\Enums\ConsentPurpose;
use App\Enums\ConsentStatus;
use App\Enums\ContactSource;
use App\Enums\OrgRole;
use App\Enums\SitePlatform;
use App\Models\Organization;
use App\Models\User;
use App\Services\AgentService;
use App\Services\ConsentService;
use App\Services\ContactService;
use App\Services\LeadService;
use App\Services\MembershipService;
use App\Services\OrganizationService;
use App\Services\SiteService;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Organizație demo pentru dezvoltare: `php artisan db:seed` (sau --class=DemoSeeder).
 * Toate datele sunt FICTIVE (domenii .test / .local, numere 0700 000 xxx). Refuză să ruleze în producție.
 * Idempotent: dacă organizația demo există, nu face nimic.
 */
class DemoSeeder extends Seeder
{
    public const SLUG = 'vitim-demo-auto';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder nu rulează în producție.');
        }
        if (Organization::where('slug', self::SLUG)->exists()) {
            $this->command?->info('Organizația demo există deja.');

            return;
        }

        $password = Str::password(16, symbols: false);
        $owner = User::create(['name' => 'Proprietar Demo', 'email' => 'owner@demo-auto.test', 'password' => $password]);
        $organization = app(OrganizationService::class)->create('VITIM Demo Auto', 'pro', $owner, [
            'company_name' => 'Demo Auto SRL (fictiv)', 'vat_id' => 'RO00000000', 'country' => 'RO',
        ]);

        app(TenantContext::class)->runAs($organization, function () use ($owner): void {
            [$site] = app(SiteService::class)->create('demo-auto.local', SitePlatform::WooCommerce, [], 'Demo Auto');
            app(AgentService::class)->create([
                'name' => 'VITIM Auto Assistant',
                'site_id' => $site->id,
                'status' => AgentStatus::Draft->value,
                'system_configuration' => [
                    'tone' => 'friendly',
                    'languages' => ['ro', 'en'],
                    'greeting' => 'Bună! Sunt asistentul service-ului Demo Auto. Cu ce te pot ajuta?',
                    'business_hours' => ['timezone' => 'Europe/Bucharest', 'days' => [
                        'mon' => [['08:00', '17:00']], 'tue' => [['08:00', '17:00']], 'wed' => [['08:00', '17:00']],
                        'thu' => [['08:00', '17:00']], 'fri' => [['08:00', '17:00']], 'sat' => [['09:00', '13:00']],
                    ]],
                    'lead_rules' => ['required_fields' => ['name', 'phone', 'requested_service']],
                ],
            ]);
            app(MembershipService::class)->invite($owner, 'operator@demo-auto.test', 'Operator Demo', OrgRole::Agent);

            $contacts = app(ContactService::class);
            $leads = app(LeadService::class);
            $consents = app(ConsentService::class);
            $people = [
                ['Andrei', 'Test', 'andrei@example.test', '0700 000 001', 'quote_request', 'Schimb distribuție Ford Kuga 2.0 TDCI, 2016.', 'new'],
                ['Maria', 'Exemplu', 'maria@example.test', '0700 000 002', 'appointment', 'Revizie anuală, preferă sâmbătă dimineața.', 'contacted'],
                ['Ioan', 'Demo', null, '0700 000 003', 'product_information', 'Întreabă de anvelope de iarnă 205/55 R16.', 'qualified'],
                ['Elena', 'Fictiv', 'elena@example.test', null, 'complaint', 'Nemulțumită de termenul de livrare a unei piese.', 'new'],
                ['Radu', 'Proba', 'radu@example.test', '0700 000 005', 'buy_intent', 'Vrea ofertă pentru 4 anvelope și montaj.', 'won'],
            ];
            foreach ($people as [$first, $last, $email, $phone, $intent, $summary, $status]) {
                $contact = $contacts->create(array_filter(['first_name' => $first, 'last_name' => $last, 'email' => $email, 'phone' => $phone]), ContactSource::WebsiteAi);
                $leads->create($contact, ['intent' => $intent, 'summary' => $summary, 'status' => $status, 'source' => 'website_ai', 'score' => random_int(40, 95)]);
                if ($email) {
                    $consents->record($contact, Channel::Email, ConsentPurpose::Marketing, $email === 'elena@example.test' ? ConsentStatus::Revoked : ConsentStatus::Granted, 'demo-seed');
                }
            }
        });

        $this->command?->info('Organizație demo creată: /app/'.self::SLUG);
        $this->command?->info("Proprietar: owner@demo-auto.test / parolă: {$password}");
    }
}
