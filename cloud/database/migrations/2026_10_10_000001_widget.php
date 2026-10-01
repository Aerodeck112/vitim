<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Widgetul de chat: tokenul vizitatorului pentru conversație (stocat doar ca hash). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('visitor_token_hash', 64)->nullable()->after('is_test');
            $table->string('visitor_page', 255)->nullable()->after('visitor_token_hash');
            $table->index('visitor_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['visitor_token_hash']);
            $table->dropColumn(['visitor_token_hash', 'visitor_page']);
        });
    }
};
