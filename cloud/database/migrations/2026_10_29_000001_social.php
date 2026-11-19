<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->json('social')->nullable()->after('public_profile');
        });
        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->string('topic', 160);
            $table->string('hook', 200);
            $table->text('caption');
            $table->string('hashtags', 500)->nullable();
            $table->text('visual')->nullable();
            $table->json('scenes')->nullable();
            $table->string('status', 12)->default('draft'); // draft | approved | published | skipped
            $table->string('source', 12)->default('ai'); // ai | manual
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_posts');
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('social');
        });
    }
};
