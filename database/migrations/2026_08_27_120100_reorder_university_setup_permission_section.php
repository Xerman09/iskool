<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    protected $order = [
        'academic-year' => 5001,
        'semester' => 5002,
        'program' => 5003,
        'section' => 5004,
        'class' => 5005,
        'curriculum-version' => 5006,
        'subject' => 5007,
        'curriculum-builder' => 5008,
        'class-room' => 5009,
        'assign-program' => 5010,
        'assign_subject' => 5011,
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->order as $route => $position) {
            DB::table('permissions')->where('route', $route)->update([
                'position' => $position,
                'updated_at' => now(),
            ]);
        }

        DB::table('sidebars')->truncate();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('sidebars')->truncate();
    }
};
