<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Rounds out the logo set to four independently-uploadable purposes: the
     * regular/default logo, dashboard (sidebar), login page, and letterhead
     * (print). Optional - falls back to the regular `logo` when not set.
     */
    public function up(): void
    {
        Schema::table('sm_general_settings', function (Blueprint $table) {
            $table->string('login_logo')->nullable()->after('dashboard_logo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sm_general_settings', function (Blueprint $table) {
            $table->dropColumn('login_logo');
        });
    }
};
