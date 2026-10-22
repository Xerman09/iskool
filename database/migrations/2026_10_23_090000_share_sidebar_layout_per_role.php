<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * A sidebar layout now belongs to a role, not to one account.
     *
     * Rows in sidebars with user_id NULL are the template for their role_id, and that is
     * what every user of the role is served. The old rows were stamped with the id of
     * whoever happened to open the Sidebar Manager, which is why regrouping the menu as
     * admin changed it for that one login and nobody else.
     *
     * Those per-account rows are no longer read, so they are cleared here rather than left
     * behind as dead weight. The template is rebuilt from the permissions table the next
     * time someone of that role loads a page, by defaultSidebarStore().
     *
     * Anything hidden or rearranged under the old scheme is lost and has to be redone once -
     * but from here it sticks for the whole role.
     */
    public function up(): void
    {
        DB::table('sidebars')->whereNotNull('user_id')->delete();
        $this->forgetCaches();
    }

    /**
     * The per-account rows cannot be brought back - they were deleted, not moved. Clearing
     * the templates lets the old code path rebuild per-account rows from scratch.
     */
    public function down(): void
    {
        DB::table('sidebars')->whereNull('user_id')->delete();
        $this->forgetCaches();
    }

    private function forgetCaches()
    {
        foreach (DB::table('users')->pluck('id') as $userId) {
            Cache::forget('sidebars' . $userId);
        }

        foreach (DB::table('roles')->pluck('id') as $roleId) {
            Cache::forget('sidebars_role' . $roleId);
        }
    }
};
