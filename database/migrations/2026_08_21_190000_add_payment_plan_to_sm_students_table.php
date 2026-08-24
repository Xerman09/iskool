<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sm_students', function (Blueprint $table) {
            $table->enum('payment_plan', ['per_semester', 'per_year'])->nullable()->after('enrolled_at');
        });
    }

    public function down(): void
    {
        Schema::table('sm_students', function (Blueprint $table) {
            $table->dropColumn('payment_plan');
        });
    }
};
