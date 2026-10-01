<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Problemele găsite de scanarea pluginului și comenzile de remediere trimise din panou. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->timestamp('last_scan_at')->nullable()->after('last_seen_at');
        });

        Schema::create('site_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('code', 120);
            $table->string('severity', 12); // critical | warning | info
            $table->string('title', 255);
            $table->text('details')->nullable();
            $table->string('fix', 160)->nullable(); // acțiunea de remediere propusă de plugin (din lista permisă)
            $table->string('status', 12)->default('open'); // open | resolved
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['site_id', 'code']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('site_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('action', 40);
            $table->string('target', 190)->nullable();
            $table->string('status', 12); // running | done | failed
            $table->text('result')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamps();
            $table->index(['site_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_commands');
        Schema::dropIfExists('site_issues');
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('last_scan_at');
        });
    }
};
