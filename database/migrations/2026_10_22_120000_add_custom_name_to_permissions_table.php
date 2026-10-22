<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * A label the superadmin typed for a menu entry, overriding whatever name or lang_name
     * the entry shipped with. Kept in its own column rather than written over name so that
     * clearing it restores the stock label, the Role Permission screens keep listing the
     * canonical name, and storePermissionData() - which carries name in its update values -
     * cannot overwrite it when a module migration re-seeds its permissions.
     */
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('custom_name')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn('custom_name');
        });
    }
};
