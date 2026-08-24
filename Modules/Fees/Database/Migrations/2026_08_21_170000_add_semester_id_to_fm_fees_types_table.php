<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fm_fees_types', function (Blueprint $table) {
            $table->integer('semester_id')->nullable()->unsigned()->after('class_id');
        });
    }

    public function down(): void
    {
        Schema::table('fm_fees_types', function (Blueprint $table) {
            $table->dropColumn('semester_id');
        });
    }
};
