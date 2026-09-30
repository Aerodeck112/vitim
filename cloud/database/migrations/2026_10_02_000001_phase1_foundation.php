<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — fundația VITIM AI Business Platform.
 *
 * Aditivă față de 0.1.0 (poate rula pe o instalare existentă):
 * - rolurile existente se convertesc la noile nume (fără pierdere de acces);
 * - organizațiile și site-urile primesc câmpurile noi; audit_logs folosește entity_type/entity_id;
 * - tabele noi: agents, contacts, contact_identities, contact_consents, suppressions, leads,
 *   conversations, messages, domain_events, usage_records.
 * Detalii și ce e amânat: docs/VITIM-AI-DATABASE.md.
 */
return new class extends Migration
{
    private const ORG_ROLES = ['owner' => 'org_owner', 'manager' => 'org_admin', 'operator' => 'agent'];

    private const PLATFORM_ROLES = ['platform_admin' => 'super_admin', 'platform_support' => 'vitim_admin'];

    public function up(): void
    {
        foreach (self::ORG_ROLES as $old => $new) {
            DB::table('memberships')->where('role', $old)->update(['role' => $new]);
        }
        foreach (self::PLATFORM_ROLES as $old => $new) {
            DB::table('users')->where('platform_role', $old)->update(['platform_role' => $new]);
        }

        Schema::table('organizations', function (Blueprint $table) {
            $table->renameColumn('locale', 'default_language');
        });
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('country', 2)->default('RO')->after('status');
            $table->string('company_name')->nullable()->after('name');
            $table->string('vat_id', 32)->nullable()->after('company_name');
            $table->json('billing_details')->nullable();
            $table->json('branding')->nullable();
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->string('name')->nullable()->after('organization_id');
            $table->string('verification_status', 16)->default('unverified')->after('status');
            $table->json('widget_config')->nullable();
            $table->timestamp('last_sync_at')->nullable();
        });
        DB::table('sites')->where('platform', 'generic')->update(['platform' => 'custom']);
        DB::table('sites')->whereNull('name')->update(['name' => DB::raw('domain')]);

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->renameColumn('target_type', 'entity_type');
        });
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->renameColumn('target_id', 'entity_id');
        });

        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('status', 16)->default('draft');
            $table->string('default_language', 8)->default('ro');
            $table->json('model_configuration');
            $table->json('system_configuration');
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('first_name', 120)->nullable();
            $table->string('last_name', 120)->nullable();
            // copii de afișare ale identităților principale; sursa de adevăr pentru deduplicare e contact_identities
            $table->string('email', 190)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('company', 190)->nullable();
            $table->string('language', 8)->nullable();
            $table->string('source', 24);
            $table->string('status', 16)->default('active');
            $table->json('custom_fields')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'created_at']);
            $table->index(['organization_id', 'email']);
            $table->index(['organization_id', 'phone']);
        });

        Schema::create('contact_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('provider', 40)->default(''); // doar pentru external_id (ex. woocommerce)
            $table->string('value', 190);
            $table->string('normalized_value', 190);
            $table->boolean('verified')->default(false);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['organization_id', 'type', 'provider', 'normalized_value'], 'contact_identities_unique');
            $table->index(['organization_id', 'contact_id']);
        });

        // istoric append-only: starea curentă = ultimul rând pe (contact, canal, scop)
        Schema::create('contact_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 16);
            $table->string('purpose', 16);
            $table->string('status', 16);
            $table->string('source', 40);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['organization_id', 'contact_id', 'channel', 'purpose', 'id'], 'contact_consents_lookup');
        });

        // supraviețuiește ștergerii contactului (altfel un contact șters și reimportat ar primi din nou marketing);
        // se stochează doar un HMAC al adresei, nu adresa în clar
        Schema::create('suppressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 16);
            $table->string('value_hash', 64);
            $table->string('reason', 24); // unsubscribed | bounced | complaint | manual
            $table->string('source', 40)->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'channel', 'value_hash']);
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 16);
            $table->string('status', 16)->default('open');
            $table->string('mode', 8)->default('ai'); // ai | human
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'last_message_at']);
            $table->index(['organization_id', 'contact_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 8); // inbound | outbound
            $table->string('sender_type', 8);
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 16);
            $table->string('purpose', 16)->nullable(); // pentru outbound: marketing | transactional | service
            $table->text('content');
            $table->string('status', 16);
            $table->string('provider', 40)->nullable();
            $table->string('external_message_id', 190)->nullable();
            $table->text('error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'conversation_id', 'id']);
            $table->index(['provider', 'external_message_id']);
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 24);
            $table->string('status', 24)->default('new');
            $table->string('intent', 24)->default('other');
            $table->unsignedTinyInteger('score')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('summary')->nullable();
            $table->decimal('value_amount', 12, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'created_at']);
            $table->index(['organization_id', 'contact_id']);
        });

        // outbox de evenimente: scris în aceeași tranzacție cu modificarea; procesat din cron (vitim:events)
        Schema::create('domain_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 64);
            $table->string('subject_type', 40)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('processed_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->index(['processed_at', 'id']);
            $table->index(['organization_id', 'type', 'occurred_at']);
        });

        Schema::create('usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('metric', 40);
            $table->date('period_date');
            $table->unsignedBigInteger('quantity')->default(0);
            $table->timestamps();
            $table->unique(['organization_id', 'metric', 'period_date']);
        });
    }

    public function down(): void
    {
        foreach (['usage_records', 'domain_events', 'leads', 'messages', 'conversations', 'suppressions', 'contact_consents', 'contact_identities', 'contacts', 'agents'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->renameColumn('entity_type', 'target_type');
        });
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->renameColumn('entity_id', 'target_id');
        });
        DB::table('sites')->where('platform', 'custom')->update(['platform' => 'generic']);
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['name', 'verification_status', 'widget_config', 'last_sync_at']);
        });
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['country', 'company_name', 'vat_id', 'billing_details', 'branding']);
        });
        Schema::table('organizations', function (Blueprint $table) {
            $table->renameColumn('default_language', 'locale');
        });
        foreach (self::PLATFORM_ROLES as $old => $new) {
            DB::table('users')->where('platform_role', $new)->update(['platform_role' => $old]);
        }
        foreach (self::ORG_ROLES as $old => $new) {
            DB::table('memberships')->where('role', $new)->update(['role' => $old]);
        }
    }
};
