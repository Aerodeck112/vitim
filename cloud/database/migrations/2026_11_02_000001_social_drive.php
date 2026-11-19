<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_assets', function (Blueprint $table) {
            $table->string('kind', 8)->default('image')->after('source'); // image | video
        });
    }

    public function down(): void
    {
        Schema::table('social_assets', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
