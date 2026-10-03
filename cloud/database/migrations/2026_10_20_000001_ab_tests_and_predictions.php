<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing (etapa F): testele A/B la campanii (varianta fiecărui destinatar, câștigătorul ales automat)
 * și predicțiile calculate zilnic pe fiecare contact (valoare estimată, următoarea comandă, risc de pierdere).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->json('ab')->nullable(); // subject_b, preheader_b, test_percent, metric, wait_hours
            $table->string('ab_winner', 1)->nullable();
            $table->timestamp('ab_decided_at')->nullable();
        });
        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->string('variant', 1)->nullable(); // a, b = grupele de test; h = restul, primesc câștigătorul
            $table->index(['campaign_id', 'variant', 'status']);
        });
        Schema::table('contacts', function (Blueprint $table) {
            $table->decimal('predicted_clv', 12, 2)->nullable();
            $table->timestamp('predicted_next_order_at')->nullable();
            $table->string('churn_risk', 10)->nullable(); // low, medium, high
            $table->timestamp('predicted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', fn (Blueprint $table) => $table->dropColumn(['predicted_clv', 'predicted_next_order_at', 'churn_risk', 'predicted_at']));
        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->dropIndex(['campaign_id', 'variant', 'status']);
            $table->dropColumn('variant');
        });
        Schema::table('campaigns', fn (Blueprint $table) => $table->dropColumn(['ab', 'ab_winner', 'ab_decided_at']));
    }
};
