<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sm_students', function (Blueprint $table) {
            $table->enum('enrollment_status', ['pending', 'enrolled'])->default('pending')->after('curriculum_version_id');
            $table->timestamp('enrolled_at')->nullable()->after('enrollment_status');
        });
    }

    public function down(): void
    {
        Schema::table('sm_students', function (Blueprint $table) {
            $table->dropColumn(['enrollment_status', 'enrolled_at']);
        });
    }
};
