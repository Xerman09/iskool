<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Lets a notice name several programmes, year levels and sections rather than
     * one of each.
     *
     * One programme per notice was the wrong shape from the start: a dean
     * announcing a shifting schedule means BSIT and BSCS, not BSIT alone, and the
     * only way to reach both was to write the notice twice. Year level and section
     * were not there at all, so "second years in section A" had to be sent to
     * every student in the school.
     *
     * JSON, the same way inform_to already holds the roles a notice is for - and
     * queried with whereJsonContains for the same reason: a LIKE against a list of
     * ids matches 2 inside 12.
     *
     * Each is nullable and null keeps its old meaning - everyone in the roles that
     * were ticked.
     */
    public function up(): void
    {
        Schema::table('sm_notice_boards', function (Blueprint $table) {
            if (!Schema::hasColumn('sm_notice_boards', 'course_ids')) {
                $table->json('course_ids')->nullable()->after('inform_to');
            }
            if (!Schema::hasColumn('sm_notice_boards', 'class_ids')) {
                $table->json('class_ids')->nullable()->after('course_ids');
            }
            if (!Schema::hasColumn('sm_notice_boards', 'section_ids')) {
                $table->json('section_ids')->nullable()->after('class_ids');
            }
        });

        /* Whatever single programme a notice already names becomes a list of one,
           so nothing that has been sent changes who it was for. */
        if (Schema::hasColumn('sm_notice_boards', 'course_id')) {
            foreach (DB::table('sm_notice_boards')->whereNotNull('course_id')->get(['id', 'course_id']) as $notice) {
                DB::table('sm_notice_boards')
                    ->where('id', $notice->id)
                    ->update(['course_ids' => json_encode([(int) $notice->course_id])]);
            }

            /* Dropped rather than left beside its replacement: two columns holding
               the same answer is how they come to disagree. Nothing outside this
               feature reads it - the column is days old and was added for it. */
            Schema::table('sm_notice_boards', function (Blueprint $table) {
                $table->dropColumn('course_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sm_notice_boards', function (Blueprint $table) {
            if (!Schema::hasColumn('sm_notice_boards', 'course_id')) {
                $table->integer('course_id')->nullable()->unsigned()->after('inform_to');
            }
        });

        foreach (DB::table('sm_notice_boards')->whereNotNull('course_ids')->get(['id', 'course_ids']) as $notice) {
            $ids = json_decode($notice->course_ids, true);

            if (is_array($ids) && count($ids)) {
                DB::table('sm_notice_boards')->where('id', $notice->id)->update(['course_id' => $ids[0]]);
            }
        }

        Schema::table('sm_notice_boards', function (Blueprint $table) {
            foreach (['course_ids', 'class_ids', 'section_ids'] as $column) {
                if (Schema::hasColumn('sm_notice_boards', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
