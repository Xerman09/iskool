<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * A student submits picks for several items in one go ("Submit Order"),
     * but each item is still stored as its own row - order_batch ties those
     * rows back together so the pending list (and reception's approval queue)
     * can show "one order, N items" instead of a flat, ungrouped item list.
     */
    public function up(): void
    {
        Schema::table('sm_item_orders', function (Blueprint $table) {
            $table->string('order_batch')->nullable()->after('record_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('sm_item_orders', function (Blueprint $table) {
            $table->dropColumn('order_batch');
        });
    }
};
