<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sm_subjects', function (Blueprint $table) {
            $table->integer('source_subject_id')->nullable()->unsigned();
            $table->foreign('source_subject_id')->references('id')->on('sm_subjects')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sm_subjects', function (Blueprint $table) {
            $table->dropForeign(['source_subject_id']);
            $table->dropColumn('source_subject_id');
        });
    }
};
