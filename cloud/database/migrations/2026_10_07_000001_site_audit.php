<?php

declare(strict_types=1);

use App\Audit\Guidance;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Auditul extern al site-urilor (SEO, securitate, legal): categoria și sursa problemelor, scorurile pe categorii. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_issues', function (Blueprint $table) {
            $table->string('category', 16)->default('security')->after('code'); // seo | security | legal | updates | performance
            $table->string('source', 8)->default('plugin')->after('category'); // plugin | audit
        });
        // problemele existente (din scanarea pluginului) primesc categoria corectă
        foreach (DB::table('site_issues')->select('id', 'code')->get() as $issue) {
            DB::table('site_issues')->where('id', $issue->id)->update(['category' => Guidance::category($issue->code)]);
        }
        Schema::table('sites', function (Blueprint $table) {
            $table->timestamp('last_audit_at')->nullable()->after('last_scan_at');
            $table->json('scores')->nullable()->after('health');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['last_audit_at', 'scores']);
        });
        Schema::table('site_issues', function (Blueprint $table) {
            $table->dropColumn(['category', 'source']);
        });
    }
};
