<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fm_fees_types', function (Blueprint $table) {
            $table->integer('class_id')->nullable()->unsigned()->after('course_id');
            $table->decimal('amount', 10, 2)->nullable()->after('class_id');
        });
    }

    public function down(): void
    {
        Schema::table('fm_fees_types', function (Blueprint $table) {
            $table->dropColumn(['class_id', 'amount']);
        });
    }
};
