<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let a notice be aimed at one program and one term, not just at whole roles.
 *
 * inform_to already narrows a notice to a set of roles, but a year level in a tertiary
 * school is shared by every program and holds both terms at once, so "all students" was
 * the only audience available below role. A dean announcing a BSIT shifting schedule had
 * to send it to every student in the school.
 *
 * Both columns are nullable and null means what it meant before this migration: everyone
 * in the chosen roles. Nothing is backfilled, so every existing notice keeps its audience.
 */
return new class extends Migration
{
    /* Each column is added only if it is not already there. One of these was applied
       to a database before this file was recorded as run, and an unguarded add aborts
       the whole migrate on "Duplicate column name" - taking every later migration in
       the batch down with it, which is a steep price for a column that is already in
       the state being asked for. */
    public function up(): void
    {
        Schema::table('sm_notice_boards', function (Blueprint $table) {
            if (!Schema::hasColumn('sm_notice_boards', 'course_id')) {
                $table->integer('course_id')->nullable()->unsigned()->after('inform_to');
            }

            if (!Schema::hasColumn('sm_notice_boards', 'semester_id')) {
                $table->integer('semester_id')->nullable()->unsigned()->after('course_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sm_notice_boards', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['course_id', 'semester_id'],
                fn ($column) => Schema::hasColumn('sm_notice_boards', $column)
            ));

            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
