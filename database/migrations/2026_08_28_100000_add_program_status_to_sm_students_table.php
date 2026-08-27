<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sm_students', function (Blueprint $table) {
            $table->tinyInteger('program_status')->nullable()->default(0)->comment('0=ongoing,1=completed')->after('enrollment_status');
            $table->tinyInteger('alumni_active_status')->nullable()->default(1)->comment('1=active,0=inactive; only meaningful when program_status=1')->after('program_status');
        });
    }

    public function down(): void
    {
        Schema::table('sm_students', function (Blueprint $table) {
            $table->dropColumn(['program_status', 'alumni_active_status']);
        });
    }
};
