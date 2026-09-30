<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fundația multi-tenant: organizații, membri, abonamente, site-uri, chei de site, audit.
 * Orice tabel cu date de client are organization_id (vezi BelongsToOrganization).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('platform_role', 32)->nullable()->after('password');
            $table->timestamp('last_login_at')->nullable();
        });

        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 80)->unique();
            $table->string('status', 16)->default('active'); // active | suspended
            $table->string('locale', 8)->default('ro');
            $table->string('timezone', 64)->default('Europe/Bucharest');
            $table->unsignedSmallInteger('data_retention_days')->default(365);
            $table->timestamps();
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('plan', 16);
            $table->string('status', 16);
            $table->json('limits');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamps();
        });

        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->unique(); // un domeniu aparține unei singure organizații
            $table->json('allowed_origins');
            $table->string('platform', 16)->default('generic'); // wordpress | generic
            $table->string('connector_version', 32)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('status', 16)->default('active'); // active | disabled
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });

        Schema::create('site_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('public_key', 64)->unique();
            $table->text('secret'); // criptat (cast encrypted); necesar în clar la verificarea HMAC
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'site_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_type', 16); // user | platform | system | site
            $table->string('action', 64);
            $table->string('target_type', 64)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['organization_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('site_keys');
        Schema::dropIfExists('sites');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('organizations');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['platform_role', 'last_login_at']);
        });
    }
};
