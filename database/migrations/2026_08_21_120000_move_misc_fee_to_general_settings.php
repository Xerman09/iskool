<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sm_general_settings', function (Blueprint $table) {
            $table->decimal('misc_fee', 10, 2)->nullable()->after('due_fees_login');
        });

        if (Schema::hasColumn('courses', 'misc_fee')) {
            $existing = DB::table('courses')->whereNotNull('misc_fee')->where('misc_fee', '>', 0)->orderByDesc('id')->first();
            if ($existing) {
                DB::table('sm_general_settings')->where('school_id', $existing->school_id)->update(['misc_fee' => $existing->misc_fee]);
            }

            Schema::table('courses', function (Blueprint $table) {
                $table->dropColumn('misc_fee');
            });
        }
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->decimal('misc_fee', 10, 2)->nullable()->after('price_per_unit');
        });

        Schema::table('sm_general_settings', function (Blueprint $table) {
            $table->dropColumn('misc_fee');
        });
    }
};
