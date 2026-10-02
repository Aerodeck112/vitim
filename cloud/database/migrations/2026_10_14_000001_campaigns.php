<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing: conturile de trimitere ale fiecărei firme (SMTP, SMSLink, WhatsApp Cloud API), campaniile
 * și destinatarii lor (cu motivul excluderii, pentru dovada respectării consimțământului).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 12); // email | sms | whatsapp
            $table->string('provider', 20); // smtp | smslink | meta
            $table->text('config'); // criptat: parole, tokenuri
            $table->string('status', 12)->default('untested'); // untested | ok | error
            $table->string('last_error', 500)->nullable();
            $table->timestamp('tested_at')->nullable();
            $table->unsignedInteger('hourly_limit')->nullable();
            $table->string('webhook_token', 64)->nullable()->unique(); // identifică firma în webhook-ul WhatsApp
            $table->timestamps();
            $table->unique(['organization_id', 'channel']);
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('channel', 12);
            $table->string('status', 12)->default('draft'); // draft | scheduled | sending | paused | completed | cancelled
            $table->json('audience')->nullable();
            $table->string('subject', 200)->nullable();
            $table->text('body')->nullable();
            $table->json('template')->nullable(); // WhatsApp: nume, limbă, variabile
            $table->timestamp('scheduled_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address', 190)->nullable();
            $table->string('status', 12)->default('pending'); // pending | sent | delivered | read | failed | excluded | unsubscribed
            $table->string('reason', 300)->nullable();
            $table->string('external_id', 190)->nullable();
            $table->string('unsubscribe_code', 16)->nullable()->unique(); // linkul scurt de dezabonare (și în SMS)
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'contact_id']);
            $table->index(['campaign_id', 'status']);
            $table->index('external_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('channel_accounts');
    }
};
