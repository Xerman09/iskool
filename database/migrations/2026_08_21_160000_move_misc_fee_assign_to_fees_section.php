<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->where('route', 'misc-fee-assign')->update([
            'module' => 'Fees',
            'sidebar_menu' => 'fees',
            'parent_route' => 'fees',
            'position' => 5,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('permissions')->where('route', 'misc-fee-assign')->update([
            'module' => null,
            'sidebar_menu' => 'academics',
            'parent_route' => 'academics',
            'position' => 15,
            'updated_at' => now(),
        ]);
    }
};
