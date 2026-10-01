<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — Agent: versiuni de configurație, istoricul API al conversațiilor (append-only),
 * jurnalul de execuție a tool-urilor și costul AI per conversație.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('template', 32)->nullable()->after('default_language');
        });

        Schema::create('agent_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('model_configuration');
            $table->json('system_configuration');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['agent_id', 'version']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->boolean('is_test')->default(false)->after('mode');
            $table->unsignedBigInteger('ai_cost_micro_usd')->default(0)->after('is_test');
        });

        // conversația exact cum a fost trimisă la API (inclusiv blocurile de gândire), retrimisă neschimbată
        Schema::create('ai_turns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role', 12); // user | assistant
            $table->longText('payload');
            $table->timestamp('created_at')->nullable();
            $table->index(['conversation_id', 'id']);
        });

        Schema::create('tool_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tool', 40);
            $table->json('input')->nullable();
            $table->text('result')->nullable();
            $table->string('status', 12); // ok | rejected | error | dry_run
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->index(['organization_id', 'created_at']);
            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_executions');
        Schema::dropIfExists('ai_turns');
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['is_test', 'ai_cost_micro_usd']);
        });
        Schema::dropIfExists('agent_versions');
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('template');
        });
    }
};
