<?php

use App\Events\CreateClassGroupChat;
use App\Scopes\StatusAcademicSchoolScope;
use App\SmAssignSubject;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Puts students into the class group chats they should already have been in.
     *
     * ListenCreateClassGroupChat used to look for members by the class and section
     * on a student's record. In a college that section is advisory and is null, so
     * the lookup found nobody: groups were raised with their teacher and the admins
     * in them and no students at all, which is why a student who had registered for
     * subjects opened an empty chat list. The listener now reads the block
     * registrations, and re-syncs members on every event rather than only when the
     * group is first created - so replaying the event over every block repairs what
     * is already there.
     *
     * Nothing is truncated. The 2022 migration of this kind emptied the groups
     * table first, which would take every existing conversation's group with it;
     * createGroupUser() is idempotent, so replaying is enough.
     */
    public function up(): void
    {
        /* This year's blocks only, per school.

           A user's chat list is every group they have ever been added to - Chat's
           groups() relation carries no year - so replaying over past years would
           bury today's classes under the groups of every year a student has
           studied, for conversations that never happened. Groups that were in use
           already exist and are left alone; from here on, registration raises the
           group for the year it happens in.

           The year is read from sm_general_settings.session_id, which is where
           getAcademicId() itself falls back to - there is no session to ask in a
           migration, and active_status is a delete flag, not a "this is the
           current year" flag: all three years here carry it. */
        $currentYearPerSchool = DB::table('sm_general_settings')
            ->whereNotNull('session_id')
            ->pluck('session_id', 'school_id');

        if ($currentYearPerSchool->isEmpty()) {
            return;
        }

        $blocks = SmAssignSubject::withoutGlobalScope(StatusAcademicSchoolScope::class)
            ->get()
            ->filter(fn ($block) => (int) ($currentYearPerSchool[$block->school_id] ?? 0) === (int) $block->academic_id);

        foreach ($blocks as $block) {
            try {
                event(new CreateClassGroupChat($block));
            } catch (\Exception $e) {
                /* One malformed block - a subject or section since deleted - should
                   not stop the rest of the school being repaired. */
                Log::error('Class group chat backfill failed for block ' . $block->id . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * Nothing to undo: this adds memberships that should have existed, and removing
     * them would take real conversations with them.
     */
    public function down(): void
    {
        //
    }
};
