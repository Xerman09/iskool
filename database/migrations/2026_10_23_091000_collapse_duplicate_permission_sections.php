<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Collapses the per-account copies of the stock sidebar section headers.
     *
     * resetSidebarStore() used to seed Dashboard, Administration, Student, Exam ... once per
     * account, so the permissions table held one set of ten headers for every user who had
     * ever logged in. Nothing noticed while each account read only its own rows. Now that a
     * role shares one layout, all of those copies land in the same tree and the sidebar
     * shows Administration, Student and Exam several times over.
     *
     * The lowest id of each route wins and becomes shared (user_id NULL); role grants
     * pointing at a copy are moved onto it, then the copies go. The layout itself is
     * cleared so it rebuilds against the single set.
     */
    public function up(): void
    {
        $sections = DB::table('permissions')
            ->where('permission_section', 1)
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->get(['id', 'route']);

        $canonical = [];
        $duplicates = [];
        foreach ($sections as $section) {
            if (!array_key_exists($section->route, $canonical)) {
                $canonical[$section->route] = $section->id;
                continue;
            }
            $duplicates[$section->id] = $canonical[$section->route];
        }

        if (!$canonical) {
            return;
        }

        // Keep every role grant, but hang it on the surviving row.
        foreach ($duplicates as $duplicateId => $keepId) {
            foreach (DB::table('assign_permissions')->where('permission_id', $duplicateId)->get() as $grant) {
                $alreadyGranted = DB::table('assign_permissions')
                    ->where('permission_id', $keepId)
                    ->where('role_id', $grant->role_id)
                    ->where('school_id', $grant->school_id)
                    ->exists();

                if ($alreadyGranted) {
                    DB::table('assign_permissions')->where('id', $grant->id)->delete();
                } else {
                    DB::table('assign_permissions')->where('id', $grant->id)->update(['permission_id' => $keepId]);
                }
            }
        }

        DB::table('permissions')->whereIn('id', array_values($canonical))->update([
            'user_id' => null,
            'updated_at' => now(),
        ]);

        if ($duplicates) {
            DB::table('sidebars')->whereIn('permission_id', array_keys($duplicates))->delete();
            DB::table('permissions')->whereIn('id', array_keys($duplicates))->delete();
        }

        // Rebuilt on the next page load, against the one shared set of headers.
        DB::table('sidebars')->delete();
        $this->forgetCaches();
    }

    /**
     * The deleted copies cannot be recreated, and they do not need to be: resetSidebarStore()
     * mints whatever headers it finds missing. Handing the survivors back to no particular
     * account is the closest reversal, so clear the layout and let it rebuild.
     */
    public function down(): void
    {
        DB::table('sidebars')->delete();
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
