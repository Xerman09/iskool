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
            $table->decimal('down_payment_amount', 10, 2)->nullable()->after('due_fees_login');
        });

        if (Schema::hasColumn('courses', 'down_payment_amount')) {
            $existing = DB::table('courses')->whereNotNull('down_payment_amount')->where('down_payment_amount', '>', 0)->orderByDesc('id')->first();
            if ($existing) {
                DB::table('sm_general_settings')->where('school_id', $existing->school_id)->update(['down_payment_amount' => $existing->down_payment_amount]);
            }

            Schema::table('courses', function (Blueprint $table) {
                $table->dropColumn('down_payment_amount');
            });
        }
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->decimal('down_payment_amount', 10, 2)->nullable()->after('price_per_unit');
        });

        Schema::table('sm_general_settings', function (Blueprint $table) {
            $table->dropColumn('down_payment_amount');
        });
    }
};
