<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Marketing (etapa E): catalogul magazinului WooCommerce, sincronizat de pluginul VITIM Connector. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('external_id', 40); // ID-ul produsului în WooCommerce
            $table->string('name', 200);
            $table->decimal('price', 12, 2)->nullable();
            $table->string('currency', 3)->default('RON');
            $table->string('url', 500)->nullable();
            $table->string('image', 500)->nullable();
            $table->json('categories')->nullable();
            $table->boolean('in_stock')->default(true);
            $table->timestamp('synced_at');
            $table->unique(['site_id', 'external_id']);
            $table->index(['organization_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
