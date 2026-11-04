<?php

use App\SmSubject;
use App\Scopes\StatusAcademicSchoolScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Records which term a class group chat belongs to.
     *
     * A group already carries the class, section, subject and year it was raised
     * for, but not the term - and a student takes a fresh set of subjects every
     * term while their chat list keeps every group they have ever been added to.
     * By the second term the list is twice as long as the timetable, and by the
     * second year four times.
     *
     * The term is a property of the subject, not of the block: sm_assign_subjects
     * has no semester column. Stamped onto the group at creation (see
     * ListenCreateClassGroupChat) so the chat list can filter on it without
     * joining out to the subject on every page load.
     *
     * Nullable: a group someone created by hand through Chat has no subject and so
     * no term, and those are never filtered out.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('chat_groups', 'semester_id')) {
            Schema::table('chat_groups', function (Blueprint $table) {
                $table->integer('semester_id')->nullable()->unsigned()->after('subject_id');
            });
        }

        /* Fill in what the existing class groups belong to, reading the term off
           each one's subject. A group whose subject has since been deleted keeps a
           null term and goes on showing, which is the safer way round: losing a
           conversation is worse than carrying one too many. */
        $semesterBySubject = SmSubject::withoutGlobalScope(StatusAcademicSchoolScope::class)
            ->whereNotNull('semester_id')
            ->pluck('semester_id', 'id');

        foreach ($semesterBySubject as $subjectId => $semesterId) {
            DB::table('chat_groups')
                ->where('subject_id', $subjectId)
                ->whereNull('semester_id')
                ->update(['semester_id' => $semesterId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('chat_groups', 'semester_id')) {
            Schema::table('chat_groups', function (Blueprint $table) {
                $table->dropColumn('semester_id');
            });
        }
    }
};
