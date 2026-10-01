<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Serviciile VITIM contractate de fiecare client și rapoartele lunare. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('service', 24); // maintenance | seo | google_ads | google_business
            $table->string('status', 12)->default('active'); // active | paused
            $table->date('started_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'service']);
        });

        Schema::create('monthly_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->char('period', 7); // YYYY-MM
            $table->json('data')->nullable(); // {serviciu: {metrics: {...}, summary: "..."}}
            $table->text('summary')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_reports');
        Schema::dropIfExists('client_services');
    }
};
