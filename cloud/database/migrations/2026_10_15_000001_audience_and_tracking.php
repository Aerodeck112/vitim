<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing (etapa A): activitatea fiecărui contact (deschideri, click-uri, formulare, comenzi...),
 * listele statice, segmentele dinamice și urmărirea deschiderilor / click-urilor pe destinatar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->json('data')->nullable();
            $table->decimal('value', 12, 2)->nullable(); // venit (comenzi)
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->unsignedBigInteger('flow_id')->nullable();
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->timestamp('occurred_at');
            $table->index(['organization_id', 'contact_id', 'occurred_at']);
            $table->index(['organization_id', 'type', 'occurred_at']);
            $table->index(['contact_id', 'type']);
        });

        Schema::create('contact_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('description', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('contact_list_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('source', 40)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['contact_list_id', 'contact_id']);
            $table->index(['contact_id']);
        });

        Schema::create('segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->json('definition');
            $table->unsignedInteger('contacts_count')->default(0);
            $table->timestamp('refreshed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->timestamp('opened_at')->nullable()->after('sent_at');
            $table->timestamp('clicked_at')->nullable()->after('opened_at');
            $table->unsignedInteger('open_count')->default(0)->after('clicked_at');
            $table->unsignedInteger('click_count')->default(0)->after('open_count');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->dropColumn(['opened_at', 'clicked_at', 'open_count', 'click_count']);
        });
        Schema::dropIfExists('segments');
        Schema::dropIfExists('contact_list_members');
        Schema::dropIfExists('contact_lists');
        Schema::dropIfExists('contact_events');
    }
};
