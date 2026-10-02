<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing (etapa D): formularele de abonare de pe site (popup, flyout, bară, încorporat) și înscrierile,
 * care sunt și dovada acordului (IP, pagină, text afișat, confirmarea prin email la dublă confirmare).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signup_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete(); // null = toate site-urile firmei
            $table->string('name', 120);
            $table->string('type', 10); // popup, flyout, bar, embed
            $table->string('status', 10)->default('draft'); // draft, live
            $table->json('content');
            $table->json('behavior');
            $table->unsignedBigInteger('contact_list_id')->nullable();
            $table->boolean('double_opt_in')->default(true);
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('submissions')->default(0);
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });

        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('signup_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->json('data'); // ce a completat + textul de acord afișat
            $table->string('page', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('confirm_hash', 64)->nullable()->unique(); // sha256 din codul din emailul de confirmare
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('created_at');
            $table->index(['organization_id', 'signup_form_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('signup_forms');
    }
};
