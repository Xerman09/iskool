<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sm_general_settings', function (Blueprint $table) {
            $table->dropColumn('misc_fee');
        });
    }

    public function down(): void
    {
        Schema::table('sm_general_settings', function (Blueprint $table) {
            $table->decimal('misc_fee', 10, 2)->nullable()->after('due_fees_login');
        });
    }
};
