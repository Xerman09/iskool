<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Renames the student "My Schedule" route to match the app's existing
     * "class routine" naming family (class-routine-new, view-teacher-routine, etc.).
     */
    public function up(): void
    {
        $exists = DB::table('permissions')->where('route', 'student-my-schedule')->exists();
        if ($exists) {
            DB::table('permissions')->where('route', 'student-my-schedule')->update([
                'route' => 'student_class_routine',
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('permissions')->where('route', 'student_class_routine')->update([
            'route' => 'student-my-schedule',
            'updated_at' => now(),
        ]);
    }
};
