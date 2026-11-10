<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * How much a student may owe before the school wants to know about it.
     *
     * Sits beside due_fees_login, which asks a different question of the same
     * money: that one is "has a due date passed", regardless of amount, and this
     * one is "how much is owed", regardless of when. A student 2 days late for
     * 500 trips the first and not the second; a student owing 50,000 that is not
     * due yet trips the second and not the first. Both are worth knowing and
     * neither replaces the other, so they are separate settings.
     *
     * Off by default and, for now, reporting only: exceeding the limit marks a
     * student on the standard fees report and nothing else - no login refused, no
     * enrolment blocked. Enforcement is a policy decision about what should happen
     * at the gate, and the figure wants to be watched before it is acted on.
     *
     * School-wide. A per-student or per-programme limit is a reasonable thing to
     * want next and would hang off this one as an override; it is left out rather
     * than guessed at.
     */
    public function up(): void
    {
        Schema::table('sm_general_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('sm_general_settings', 'credit_limit_enabled')) {
                $table->tinyInteger('credit_limit_enabled')->default(0)->after('due_fees_login');
            }

            if (!Schema::hasColumn('sm_general_settings', 'credit_limit')) {
                $table->decimal('credit_limit', 12, 2)->default(0)->after('credit_limit_enabled');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sm_general_settings', function (Blueprint $table) {
            $table->dropColumn(['credit_limit_enabled', 'credit_limit']);
        });
    }
};
