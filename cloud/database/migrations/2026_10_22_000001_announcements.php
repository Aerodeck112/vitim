<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Noutățile VITIM către clienți: anunțuri despre platformă (create de echipă sau automat din CHANGELOG la actualizare),
 * rezumatul lunar „ce am făcut pentru firma ta”, livrările pe utilizator și dezabonarea de la aceste emailuri.
 */
return new class extends Migration
{
    public function up(): void
    {
        // date de platformă (nu ale unei firme): le scrie doar echipa VITIM
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10)->default('news'); // update, news, digest
            $table->string('title', 160);
            $table->string('subject', 200);
            $table->text('intro')->nullable();
            $table->mediumText('body')->nullable();
            $table->string('cta_label', 60)->nullable();
            $table->string('cta_url', 300)->nullable();
            $table->boolean('include_work')->default(true);
            $table->json('audience')->nullable(); // services[], roles: owners|all
            $table->string('status', 12)->default('draft'); // draft, scheduled, sending, sent, cancelled
            $table->string('version', 20)->nullable()->unique();
            $table->string('period', 7)->nullable()->unique(); // rezumatul lunar: YYYY-MM
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'scheduled_at']);
        });
        Schema::create('announcement_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('email', 190);
            $table->string('status', 10); // sent, failed
            $table->string('error', 300)->nullable();
            $table->string('code', 24)->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->unique(['announcement_id', 'user_id']);
        });
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->json('value')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('product_updates')->default(true);
        });
        // punctul de pornire: noutățile versiunii care aduce acest modul apar ca ciornă în Admin → Noutăți către clienți
        DB::table('platform_settings')->insert(['key' => 'announcements_version', 'value' => json_encode(['v' => '0.18.0']), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('product_updates'));
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('announcement_deliveries');
        Schema::dropIfExists('announcements');
    }
};
