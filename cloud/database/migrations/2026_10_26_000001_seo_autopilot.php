<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Pilotul automat SEO: remedierile sigure se aplică singure, o dată pe săptămână, pe site-urile WordPress cu pluginul VITIM. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->boolean('seo_autopilot')->default(true)->after('learn_auto');
            $table->timestamp('seo_autopilot_at')->nullable()->after('seo_autopilot');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['seo_autopilot', 'seo_autopilot_at']);
        });
    }
};
