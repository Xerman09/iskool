<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Leaves exactly one sidebar section header per route.
     *
     * Two shared sets existed after the previous migration: the headers that carry the role
     * grants, and a grant-less set minted by resetSidebarStore() the first time it ran with
     * a shared owner. Whichever row holds the grants is the one the Role Permission screens
     * and the sidebar already agree on, so that one survives.
     *
     * Written to be safe to run again: it keys off whatever duplicates it finds rather than
     * fixed ids, and does nothing when a route has only one header.
     */
    public function up(): void
    {
        $rows = DB::table('permissions')
            ->where('permission_section', 1)
            ->whereNotNull('route')
            ->orderBy('id')
            ->get(['id', 'route']);

        $byRoute = [];
        foreach ($rows as $row) {
            $byRoute[$row->route][] = $row->id;
        }

        $removed = 0;
        foreach ($byRoute as $ids) {
            if (count($ids) < 2) {
                continue;
            }

            // The header the rest of the system already points at wins.
            $grants = DB::table('assign_permissions')
                ->whereIn('permission_id', $ids)
                ->selectRaw('permission_id, count(*) total')
                ->groupBy('permission_id')
                ->pluck('total', 'permission_id')
                ->all();

            usort($ids, function ($a, $b) use ($grants) {
                $diff = ($grants[$b] ?? 0) <=> ($grants[$a] ?? 0);
                return $diff !== 0 ? $diff : $a <=> $b;
            });

            $keep = array_shift($ids);

            DB::table('permissions')->where('id', $keep)->update(['user_id' => null, 'updated_at' => now()]);

            foreach ($ids as $drop) {
                foreach (DB::table('assign_permissions')->where('permission_id', $drop)->get() as $grant) {
                    $exists = DB::table('assign_permissions')
                        ->where('permission_id', $keep)
                        ->where('role_id', $grant->role_id)
                        ->where('school_id', $grant->school_id)
                        ->exists();

                    $exists
                        ? DB::table('assign_permissions')->where('id', $grant->id)->delete()
                        : DB::table('assign_permissions')->where('id', $grant->id)->update(['permission_id' => $keep]);
                }

                DB::table('sidebars')->where('permission_id', $drop)->delete();
                DB::table('sidebars')->where('parent', $drop)->update(['parent' => $keep]);
                DB::table('permissions')->where('id', $drop)->delete();
                $removed++;
            }
        }

        if ($removed) {
            DB::table('sidebars')->delete();
        }

        $this->forgetCaches();
    }

    /** Nothing to put back - the removed rows were duplicates of the ones that remain. */
    public function down(): void
    {
        DB::table('sidebars')->delete();
        $this->forgetCaches();
    }

    private function forgetCaches()
    {
        foreach (DB::table('roles')->pluck('id') as $roleId) {
            Cache::forget('sidebars_role' . $roleId);
        }
    }
};
