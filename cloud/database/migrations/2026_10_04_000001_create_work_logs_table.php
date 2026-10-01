<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Jurnalul lucrărilor făcute de echipa VITIM pentru fiecare client (vizibil clientului în panoul lui). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('performed_at');
            $table->string('category', 24);
            $table->string('title', 190);
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->boolean('visible_to_client')->default(true);
            $table->string('source', 16)->default('manual'); // manual | plugin | system
            $table->timestamps();
            $table->index(['organization_id', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_logs');
    }
};
