<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Put Student Account in the sidebar.
     *
     * It was seeded out of the menu on the reasoning that a page about one
     * student has nothing to show until one is named. That was true of the page
     * and wrong about the need: somebody at the counter has a name, not a report
     * to click through, and with no entry there was no way in for them at all.
     *
     * The route now answers both - without an id it asks who, with one it shows
     * the account - so the entry leads somewhere useful and the deep links from
     * the reports still work.
     *
     * Position 23, after the three fees reports it sits beside.
     */
    public function up(): void
    {
        DB::table('permissions')
            ->where('route', 'student-account')
            ->update(['is_menu' => 1, 'position' => 23]);

        /* The sidebar is a cached build of the permissions table, so it has to be
           dropped for the change to show. */
        DB::table('sidebars')->truncate();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('permissions')
            ->where('route', 'student-account')
            ->update(['is_menu' => 0]);

        DB::table('sidebars')->truncate();
    }
};
