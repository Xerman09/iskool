<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Give the three money roles the same way in to all of the fees-side
     * reports.
     *
     * They were seeded from different templates and came out inconsistent: the
     * standard fees report went to Admin and Receptionist, while the receipts
     * register, open invoices and the student account went to Admin and
     * Accountant. A receptionist could therefore open the report, click a
     * student's name and be refused the account behind it; an accountant could
     * open the account but had no report to reach it from.
     *
     * Admin, Accountant and Receptionist all handle money at this school and all
     * three need to read what a student owes. Nothing is taken away here - roles
     * are only added - so a grant somebody made by hand survives.
     */
    private const ROUTES = [
        'fees-standard-report',
        'cash-receipts-report',
        'open-invoices-report',
        'student-account',
    ];

    private const ROLES = [5, 6, 7]; // Admin, Accountant, Receptionist

    public function up(): void
    {
        $schoolId = 1;

        foreach (self::ROUTES as $route) {
            $permissionId = DB::table('permissions')->where('route', $route)->value('id');

            if (!$permissionId) {
                continue;
            }

            foreach (self::ROLES as $roleId) {
                $already = DB::table('assign_permissions')
                    ->where('role_id', $roleId)
                    ->where('permission_id', $permissionId)
                    ->where('school_id', $schoolId)
                    ->exists();

                if (!$already) {
                    DB::table('assign_permissions')->insert([
                        'permission_id' => $permissionId,
                        'role_id' => $roleId,
                        'status' => 1,
                        'menu_status' => 1,
                        'saas_schools' => null,
                        'created_by' => 1,
                        'updated_by' => 1,
                        'school_id' => $schoolId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        DB::table('sidebars')->truncate();
    }

    /**
     * Reverse the migrations.
     *
     * Deliberately does nothing. Working out which of these grants this migration
     * added and which were already there - or were made by hand afterwards - is
     * not possible from the rows alone, and revoking somebody's access on a guess
     * is worse than leaving a grant in place.
     */
    public function down(): void
    {
        //
    }
};
