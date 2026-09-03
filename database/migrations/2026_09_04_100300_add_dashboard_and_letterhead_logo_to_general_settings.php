<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The single `logo` column was being reused for three visually different
     * spots - the dashboard sidebar (dark background, wants a white/light logo),
     * the login page (white background), and printed documents (white paper) -
     * so a white logo made for the sidebar would disappear on the other two.
     * These are optional overrides; both fall back to the regular `logo` when
     * a school hasn't uploaded one.
     */
    public function up(): void
    {
        Schema::table('sm_general_settings', function (Blueprint $table) {
            $table->string('dashboard_logo')->nullable()->after('logo');
            $table->string('letterhead_logo')->nullable()->after('dashboard_logo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sm_general_settings', function (Blueprint $table) {
            $table->dropColumn(['dashboard_logo', 'letterhead_logo']);
        });
    }
};
