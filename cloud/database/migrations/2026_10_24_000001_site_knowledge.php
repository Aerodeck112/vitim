<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Agentul învață din site: paginile publice citite de panou (text, titlu, preț) și starea citirii pe fiecare site. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('url', 700);
            $table->char('url_hash', 40);
            $table->string('title', 300)->nullable();
            $table->text('description')->nullable();
            $table->mediumText('content')->nullable();
            $table->text('terms')->nullable(); // termenii normalizați ai paginii, pentru căutare rapidă
            $table->string('kind', 20)->default('page'); // page | product
            $table->string('price', 80)->nullable();
            $table->string('status', 12)->default('pending'); // pending | ok | skipped | error
            $table->boolean('excluded')->default(false); // scoasă de firmă: agentul nu o folosește
            $table->unsignedInteger('run')->default(0);
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
            $table->unique(['site_id', 'url_hash']);
            $table->index(['site_id', 'status']);
        });
        Schema::table('sites', function (Blueprint $table) {
            $table->string('learn_status', 12)->nullable()->after('ai_enabled'); // queued | running | done | failed
            $table->unsignedInteger('learn_run')->default(0)->after('learn_status');
            $table->timestamp('learned_at')->nullable()->after('learn_run');
            $table->string('learn_error', 300)->nullable()->after('learned_at');
            $table->boolean('learn_auto')->default(true)->after('learn_error');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_pages');
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['learn_status', 'learn_run', 'learned_at', 'learn_error', 'learn_auto']);
        });
    }
};
