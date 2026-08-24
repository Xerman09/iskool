<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sm_assign_subjects', function (Blueprint $table) {
            $table->unsignedInteger('max_slots')->nullable()->after('subject_id');
        });
    }

    public function down(): void
    {
        Schema::table('sm_assign_subjects', function (Blueprint $table) {
            $table->dropColumn('max_slots');
        });
    }
};
