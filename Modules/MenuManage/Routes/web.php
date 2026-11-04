<?php
use Illuminate\Support\Facades\Route;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group(['middleware' => ['subdomain']], function () {
    Route::prefix('menumanage')->group(function () {
        Route::name('menumanage.')->middleware('auth')->group(function () {

            Route::get('/', 'MenuManageController@index')->name('index')->middleware('userRolePermission:menumanage.index');
            Route::post('menu-store', 'MenuManageController@store')->name('store.menu')->middleware('userRolePermission:menumanage.index');
            /* POST, not GET. Both of these delete a role's entire layout and build the
               default back over it - which is exactly what "I arranged the sidebar and
               it came back unset" turned out to be. As links they were one stray click,
               one middle-click or one browser prefetch away from wiping an arrangement,
               with nothing asked first. */
            Route::post('reset-with-section', 'SidebarManagerController@resetMenu')->name('reset-with-section')->middleware('userRolePermission:menumanage.index');
            Route::post('reset-default', 'SidebarManagerController@resetWithDefault')->name('reset-with-default')->middleware('userRolePermission:menumanage.index');

        });
    });
});
/* Every endpoint here rearranges or renames the shared, per-role sidebar layout, so it
   needs the same permission as the page that drives it - not merely a login. */
Route::prefix('sidebar-manager')->middleware(['auth', 'subdomain', 'userRolePermission:menumanage.index'])->group(function () {
    Route::get('/', 'SidebarManagerController@index')->name('sidebar-manager.index');
    //section store
    Route::post('/section/store', 'SidebarManagerController@sectionStore')->name('sidebar-manager.section.store');
    Route::get('/section-edit-form/{id}', 'SidebarManagerController@sectionEditForm')->name('sidebar-manager.section-edit-form');
    Route::post('/section-update', 'SidebarManagerController@sectionUpdate')->name('sidebar-manager.section-update');

    Route::post('/section/menu-store', 'SidebarManagerController@menuStore')->name('sidebar-manager.menu-store');
    Route::post('/section/menu-update', 'SidebarManagerController@menuUpdate')->name('sidebar-manager.menu-update');
    /* menu-edit removed: it pointed at a SidebarManagerController@menuEdit that was never
       written, so any call to it was a 500. Nothing posts to it. */
    // Creates a new dropdown inside a section.
    Route::post('/menu-create', 'SidebarManagerController@menuCreate')->name('sidebar-manager.menu-create');
    // Picks which role the manager arranges.
    Route::post('/select-role', 'SidebarManagerController@selectRole')->name('sidebar-manager.select-role');
    // Renames any entry, section header or menu item, for the whole school.
    Route::post('/menu-rename', 'SidebarManagerController@menuRename')->name('sidebar-manager.menu-rename');


    Route::post('/section/sort-section', 'SidebarManagerController@sortSection')->name('sidebar-manager.sort-section');
    Route::post('/section/delete-section', 'SidebarManagerController@deleteSection')->name('sidebar-manager.delete-section');
    Route::post('/section/remove-menu', 'SidebarManagerController@removeMenu')->name('sidebar-manager.menu-remove');
    /* Removes a self-made menu for good, from the Available list. remove-menu above only
       makes an entry unused; this deletes the permission behind it, and only ever one the
       manager created itself. */
    Route::post('/section/delete-menu', 'SidebarManagerController@deleteMenu')->name('sidebar-manager.menu-delete');



});
