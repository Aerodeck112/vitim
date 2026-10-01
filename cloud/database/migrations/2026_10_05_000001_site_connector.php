<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Conectarea site-urilor: starea raportată de plugin / conector și lucrările trimise automat (fără dubluri). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->json('health')->nullable()->after('widget_config');
        });
        Schema::table('work_logs', function (Blueprint $table) {
            $table->string('external_ref', 64)->nullable()->after('source');
            $table->unique(['organization_id', 'external_ref']);
        });
    }

    public function down(): void
    {
        Schema::table('work_logs', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'external_ref']);
            $table->dropColumn('external_ref');
        });
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('health');
        });
    }
};
