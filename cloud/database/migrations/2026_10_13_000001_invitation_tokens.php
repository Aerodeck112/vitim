<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Invitațiile (setarea primei parole) au tabelul lor: sunt valabile 7 zile, resetarea parolei rămâne la 60 de minute. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitation_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitation_tokens');
    }
};
