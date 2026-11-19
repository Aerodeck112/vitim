<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            // actualizările automate ale pluginurilor: pornite explicit de echipa VITIM, pe fiecare site
            $table->boolean('update_autopilot')->default(false)->after('seo_autopilot_at');
            $table->timestamp('update_autopilot_at')->nullable()->after('update_autopilot');
            $table->string('update_autopilot_result', 500)->nullable()->after('update_autopilot_at');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['update_autopilot', 'update_autopilot_at', 'update_autopilot_result']);
        });
    }
};
