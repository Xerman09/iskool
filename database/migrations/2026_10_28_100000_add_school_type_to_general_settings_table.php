<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Which kind of school this deployment serves. One client, one deployment, one
     * value - set once at setup time and not expected to change afterwards.
     *
     * 'tertiary' is everything the 2026 work added: programs, semesters, per-unit
     * tuition, student-selected subjects, a professor per subject and block.
     * 'basic_ed' is what eskooly was before that: the year level identifies the
     * learner so there are no programs and no term dropdown, tuition is flat, the
     * curriculum decides the subject load, and a section gets a class adviser.
     *
     * Defaults to 'tertiary' so existing deployments keep behaving exactly as they
     * do now until someone switches them deliberately. Sits beside result_type,
     * which stays independent of this: that one is percentage-vs-GPA, which follows
     * the country rather than the kind of school, so a PH college and a US
     * elementary can disagree about it without disagreeing about this.
     */
    public function up(): void
    {
        Schema::table('sm_general_settings', function (Blueprint $table) {
            $table->string('school_type')->default('tertiary')->after('result_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sm_general_settings', function (Blueprint $table) {
            $table->dropColumn('school_type');
        });
    }
};
