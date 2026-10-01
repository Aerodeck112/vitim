<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Chat live: când a fost vizitatorul ultima dată pe pagină și când a citit echipa conversația. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->timestamp('visitor_seen_at')->nullable()->after('visitor_page');
            $table->timestamp('staff_read_at')->nullable()->after('visitor_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['visitor_seen_at', 'staff_read_at']);
        });
    }
};
