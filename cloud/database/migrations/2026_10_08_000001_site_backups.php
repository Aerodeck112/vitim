<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Backup-urile făcute de plugin pe hostingul clientului, raportate și verificate în panou. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('status', 12); // ok | failed
            $table->boolean('verified')->default(false);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('db_bytes')->default(0);
            $table->unsignedBigInteger('files_bytes')->default(0);
            $table->unsignedInteger('files_count')->default(0);
            $table->string('location', 255)->nullable();
            $table->unsignedSmallInteger('kept')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['site_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_backups');
    }
};
