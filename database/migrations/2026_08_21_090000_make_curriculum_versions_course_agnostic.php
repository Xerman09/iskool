<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A curriculum version (e.g. "2026") represents a school-wide batch/edition,
     * not something scoped to one program. Having `course_id` on curriculum_versions
     * meant every program created its own duplicate "2026" row instead of sharing one.
     * This merges any duplicate labels within the same school into a single surviving
     * row (repointing every sm_subjects/sm_students reference first), then drops the
     * now-redundant course_id column entirely.
     */
    public function up(): void
    {
        $duplicateGroups = DB::table('curriculum_versions')
            ->select('school_id', 'version_label')
            ->groupBy('school_id', 'version_label')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateGroups as $group) {
            $rows = DB::table('curriculum_versions')
                ->where('school_id', $group->school_id)
                ->where('version_label', $group->version_label)
                ->orderBy('id')
                ->get();

            $survivor = $rows->first();
            $losers = $rows->slice(1);

            foreach ($losers as $loser) {
                DB::table('sm_subjects')
                    ->where('curriculum_version_id', $loser->id)
                    ->update(['curriculum_version_id' => $survivor->id]);

                DB::table('sm_students')
                    ->where('curriculum_version_id', $loser->id)
                    ->update(['curriculum_version_id' => $survivor->id]);

                DB::table('curriculum_versions')->where('id', $loser->id)->delete();
            }

            // Keep the survivor active if any of the merged rows were active.
            $anyActive = $rows->contains('is_active', 1);
            if ($anyActive) {
                DB::table('curriculum_versions')->where('id', $survivor->id)->update(['is_active' => 1]);
            }
        }

        Schema::table('curriculum_versions', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropColumn('course_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('curriculum_versions', function (Blueprint $table) {
            $table->integer('course_id')->nullable()->unsigned();
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
        });
    }
};
