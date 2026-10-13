<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cine e pe site acum: o filă deschisă = un rând, cu un identificator aleator generat în pagină (nu se salvează
 * nimic pe dispozitivul vizitatorului, nu se păstrează IP-ul). Rândurile vechi se șterg după câteva minute.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_visitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('visitor', 32);
            $table->string('page', 255)->nullable();
            $table->string('title', 160)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('seen_at');
            $table->unique(['site_id', 'visitor']);
            $table->index(['organization_id', 'seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_visitors');
    }
};
