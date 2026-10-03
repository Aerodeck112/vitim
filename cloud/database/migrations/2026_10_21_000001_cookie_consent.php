<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bannerul de cookie-uri pentru site-urile clienților (Legea 506/2004 art. 4 alin. 5, GDPR):
 * setările pe site și registrul consimțămintelor (dovada: ce a ales vizitatorul, când, pe ce pagină, pentru ce versiune a politicii).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->json('cookie_config')->nullable();
        });
        Schema::create('cookie_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('consent_id', 36); // identificator aleator al browserului, din cookie-ul vitim_consent
            $table->string('action', 12); // accept_all, reject_all, custom
            $table->boolean('preferences')->default(false);
            $table->boolean('statistics')->default(false);
            $table->boolean('marketing')->default(false);
            $table->unsignedInteger('policy_version');
            $table->string('ip_hash', 32)->nullable(); // HMAC al IP-ului, nu IP-ul în clar
            $table->string('user_agent', 255)->nullable();
            $table->string('page', 255)->nullable();
            $table->timestamp('created_at');
            $table->index(['site_id', 'created_at']);
            $table->index(['site_id', 'consent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cookie_consents');
        Schema::table('sites', fn (Blueprint $table) => $table->dropColumn('cookie_config'));
    }
};
