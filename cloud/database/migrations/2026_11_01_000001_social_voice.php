<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            // textul citit de voce și fișierul audio generat din el
            $table->text('voiceover')->nullable()->after('scenes');
            $table->string('voice_path', 200)->nullable()->after('voiceover');
        });
    }

    public function down(): void
    {
        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropColumn(['voiceover', 'voice_path']);
        });
    }
};
