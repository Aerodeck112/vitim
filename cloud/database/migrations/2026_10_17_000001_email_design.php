<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Marketing (etapa C): emailuri din blocuri (editorul vizual), preheader și șabloanele salvate ale firmei. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->json('blocks')->nullable()->after('body');
            $table->string('preheader', 150)->nullable()->after('subject');
        });
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->json('blocks');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['blocks', 'preheader']);
        });
    }
};
