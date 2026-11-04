<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Marks the menus a school made for itself in the Sidebar Manager, as opposed
     * to the hundreds that ship with the system or arrive with a module.
     *
     * Only a self-made menu can be deleted. Everything else has a screen behind it
     * - or will have, once its module is installed - so removing its permission row
     * would take a working part of the system away from every role at once and
     * leave nothing to put it back. Being unused is already how a built-in menu is
     * hidden; it needs no delete.
     *
     * The flag is written from here on by SidebarManagerController@menuCreate. The
     * backfill below finds the ones made before it existed by the shape that method
     * leaves behind, which nothing else produces: a dropdown (type 1) inside a
     * section, carrying no module, no translation key and no old_id - menuCreate is
     * the only writer that sets none of those three. On this database it matches
     * exactly the two that were made by hand and none of the other 943.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('permissions', 'is_custom')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->tinyInteger('is_custom')->default(0)->after('is_alumni');
            });
        }

        DB::table('permissions')
            ->whereNull('old_id')
            ->whereNull('module')
            ->whereNull('lang_name')
            ->whereNull('user_id')
            ->where('type', 1)
            ->whereNotNull('parent_route')
            ->where('parent_route', '!=', '')
            ->update(['is_custom' => 1]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('permissions', 'is_custom')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->dropColumn('is_custom');
            });
        }
    }
};
