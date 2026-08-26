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
        Schema::table('sm_optional_subject_assigns', function (Blueprint $table) {
            // null = not yet graded, 1 = passed, 0 = failed. Set by the teacher, not computed.
            $table->tinyInteger('is_pass')->nullable()->after('assign_subject_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sm_optional_subject_assigns', function (Blueprint $table) {
            $table->dropColumn('is_pass');
        });
    }
};
