<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The permission's `route` column doubles as both the middleware permission
     * key AND, via validRouteUrl(), the Laravel route name the sidebar link
     * points to. It was seeded as 'student-balance-summary', which IS a real
     * route name - but the one requiring a {state} segment
     * (student-balance-summary/{state}, gated by student-subject-registration
     * instead). route('student-balance-summary') with no params throws,
     * validRouteUrl() swallows it, and the sidebar link gets an empty href.
     * Point it at the param-less route actually meant for this menu entry.
     */
    public function up(): void
    {
        DB::table('permissions')->where('route', 'student-balance-summary')->update([
            'route' => 'student-balance-summary-menu',
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('permissions')->where('route', 'student-balance-summary-menu')->update([
            'route' => 'student-balance-summary',
            'updated_at' => now(),
        ]);
    }
};
