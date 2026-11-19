<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // materialele firmei pentru postări: poze din site, din pagina de Facebook / Instagram conectată sau încărcate
        Schema::create('social_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('source', 12); // site | meta | upload
            $table->string('origin', 1000)->nullable();
            $table->char('origin_hash', 40);
            $table->string('path', 200);
            $table->string('title', 300)->nullable();
            $table->unsignedInteger('width')->default(0);
            $table->unsignedInteger('height')->default(0);
            $table->unsignedInteger('bytes')->default(0);
            $table->timestamps();
            $table->unique(['organization_id', 'origin_hash']);
        });
        // postările deja publicate de firmă, citite prin interfața oficială Meta: din ele agentul învață stilul și ce a mers
        Schema::create('social_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('network', 12); // facebook | instagram
            $table->string('external_id', 80);
            $table->text('message')->nullable();
            $table->string('permalink', 500)->nullable();
            $table->string('media_type', 20)->nullable();
            $table->unsignedInteger('engagement')->default(0);
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'network', 'external_id']);
        });
        Schema::create('social_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('network', 12)->default('meta');
            $table->text('config'); // criptat: ID-ul paginii, codul de acces al paginii, ID-ul contului de Instagram
            $table->string('status', 12)->default('new'); // new | ok | error
            $table->string('last_error', 500)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'network']);
        });
        Schema::table('social_posts', function (Blueprint $table) {
            $table->foreignId('asset_id')->nullable()->after('scenes')->constrained('social_assets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('asset_id');
        });
        Schema::dropIfExists('social_connections');
        Schema::dropIfExists('social_references');
        Schema::dropIfExists('social_assets');
    }
};
