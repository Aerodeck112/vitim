<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing (etapa B): automatizări (ca „Flows” din Klaviyo). Un flux are un declanșator, pași în arbore
 * (așteaptă, email, SMS, WhatsApp, condiție cu ramuri da / nu, adaugă în listă) și o „rulare” pe fiecare contact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('status', 12)->default('draft'); // draft | live | paused
            $table->json('trigger');
            $table->json('settings')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('live_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });

        Schema::create('flow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('flow_steps')->cascadeOnDelete();
            $table->string('branch', 3)->nullable(); // yes | no (în ramurile unei condiții)
            $table->unsignedInteger('position')->default(0);
            $table->string('type', 12); // wait | email | sms | whatsapp | condition | list_add
            $table->json('config')->nullable();
            $table->timestamps();
            $table->index(['flow_id', 'parent_id', 'branch', 'position']);
        });

        Schema::create('flow_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('step_id')->nullable()->constrained('flow_steps')->nullOnDelete();
            $table->string('status', 12)->default('active'); // active | completed | exited | failed
            $table->timestamp('wake_at')->nullable();
            $table->json('trigger_data')->nullable();
            $table->string('note', 300)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->index(['status', 'wake_at']);
            $table->index(['flow_id', 'contact_id']);
        });

        Schema::create('flow_segment_members', function (Blueprint $table) {
            $table->foreignId('flow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->primary(['flow_id', 'contact_id']);
        });

        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->change();
            $table->unsignedBigInteger('flow_id')->nullable()->after('campaign_id');
            $table->unsignedBigInteger('flow_step_id')->nullable()->after('flow_id');
            $table->unsignedBigInteger('flow_run_id')->nullable()->after('flow_step_id');
            $table->index(['flow_step_id']);
        });
    }

    public function down(): void
    {
        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->dropIndex(['flow_step_id']);
            $table->dropColumn(['flow_id', 'flow_step_id', 'flow_run_id']);
        });
        Schema::dropIfExists('flow_segment_members');
        Schema::dropIfExists('flow_runs');
        Schema::dropIfExists('flow_steps');
        Schema::dropIfExists('flows');
    }
};
