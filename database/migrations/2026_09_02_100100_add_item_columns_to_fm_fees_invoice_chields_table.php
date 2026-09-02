<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fm_fees_invoice_chields', function (Blueprint $table) {
            $table->integer('sm_item_id')->nullable()->after('fees_type');
            $table->integer('quantity')->nullable()->after('sm_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fm_fees_invoice_chields', function (Blueprint $table) {
            $table->dropColumn(['sm_item_id', 'quantity']);
        });
    }
};
