<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sm_optional_subject_assigns', function (Blueprint $table) {
            $table->unsignedInteger('assign_subject_id')->nullable()->after('subject_id');
            $table->foreign('assign_subject_id')->references('id')->on('sm_assign_subjects')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('sm_optional_subject_assigns', function (Blueprint $table) {
            $table->dropForeign(['assign_subject_id']);
            $table->dropColumn('assign_subject_id');
        });
    }
};
