<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Units are entered on the subject catalog now instead of on each curriculum entry, so
     * seed every catalog subject from the curriculum entry that was built from it. The
     * curriculum entries keep their own copy of units - tuition, the layout totals and the
     * schedule all read that one column - and Curriculum Builder now fills it from the
     * catalog subject rather than from a typed-in field.
     */
    public function up(): void
    {
        $unitsBySourceSubject = DB::table('sm_subjects')
            ->whereNotNull('course_id')
            ->whereNotNull('source_subject_id')
            ->whereNotNull('units')
            ->orderBy('id')
            ->get(['source_subject_id', 'units']);

        $seeded = [];
        foreach ($unitsBySourceSubject as $row) {
            // First curriculum entry wins, so a subject used at two different unit counts
            // takes the oldest one and the registrar can correct it on the Subjects screen.
            if (array_key_exists($row->source_subject_id, $seeded)) {
                continue;
            }
            $seeded[$row->source_subject_id] = $row->units;
        }

        foreach ($seeded as $subjectId => $units) {
            DB::table('sm_subjects')
                ->where('id', $subjectId)
                ->whereNull('course_id')
                ->whereNull('units')
                ->update(['units' => $units, 'updated_at' => now()]);
        }
    }

    /**
     * Before this, a catalog subject never carried units - only the curriculum entries did,
     * and those are left untouched here.
     */
    public function down(): void
    {
        DB::table('sm_subjects')
            ->whereNull('course_id')
            ->whereNotNull('units')
            ->update(['units' => null, 'updated_at' => now()]);
    }
};
