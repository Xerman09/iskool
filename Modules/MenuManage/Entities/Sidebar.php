<?php

namespace Modules\MenuManage\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\RolePermission\Entities\Permission;

class Sidebar extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * The role whose layout is being read or written, when it is not the signed-in
     * user's own. The Sidebar Manager sets this so a super admin can arrange the menu
     * for any role; left null everywhere else, so the live sidebar keeps serving the
     * viewer their own role. Read through sidebarRoleId().
     */
    public static $layoutRoleId = null;
 
    public function permissionInfo()
    {
        return $this->belongsTo(Permission::class, 'permission_id', 'id')->withDefault();
    }
    public function parentMenu()
    {
        return $this->belongsTo(Permission::class, 'parent', 'id')->withDefault();
    }
    public function deActiveChild()
    {
        return $this->hasMany('Modules\MenuManage\Entities\Sidebar','parent','permission_id')
        ->orderBy('position', 'ASC')
        ->whereNull('user_id')->where('role_id', sidebarRoleId())->where('active_status', 0);
    }
    public function userChildMenu()
    {
        return $this->hasMany('Modules\MenuManage\Entities\Sidebar','parent','permission_id')->whereNull('user_id')->where('role_id', sidebarRoleId());
    }
    public function subModule()
    { 
        $user = auth()->user();
        return $this->hasMany('Modules\MenuManage\Entities\Sidebar','parent','permission_id')
        // ->whereNotIn('permission_id', deActivePermissions())
        ->with('permissionInfo', 'subModule')
        ->whereHas('permissionInfo', function($q) use ($user){
            $q->where('menu_status', 1)->when(sidebarRoleId() == 2, function($q){
                $q->where('is_student', 1);
            })->when(sidebarRoleId() == 3, function($q){
                $q->where('is_parent', 1);
            })->when(!in_array(sidebarRoleId(), [2,3]) , function($q){
                /* Grouped: ungrouped this reads as (menu_status = 1 AND is_admin = 1)
                   OR is_teacher = 1, so any entry flagged for teachers rendered for
                   everyone regardless of whether its menu was switched off at all. */
                $q->where(function($q){
                    $q->where('is_admin', 1)->orWhere('is_teacher', 1);
                });
            });
        })
        ->whereNull('user_id')->where('role_id', sidebarRoleId())->where('active_status', 1)->orderBy('position', 'ASC');
    }
    public function deActiveSubMenu()
    { 
        $user = auth()->user();
        return $this->hasMany('Modules\MenuManage\Entities\Sidebar','parent','permission_id')
        // ->whereNotIn('permission_id', deActivePermissions())
        ->with('permissionInfo', 'deActiveSubMenu')
        ->whereHas('permissionInfo', function($q) use ($user){
            $q->where('menu_status', 1)->when(sidebarRoleId() == 2, function($q){
                $q->where('is_student', 1);
            })->when(sidebarRoleId() == 3, function($q){
                $q->where('is_parent', 1);
            })->when(!in_array(sidebarRoleId(), [2,3]) , function($q){
                $q->where('is_admin', 1);
            });
        })
        ->whereNull('user_id')->where('role_id', sidebarRoleId())->where('active_status', 0)->orderBy('position', 'ASC');
    }
    public function permissionSection()
    {
        return $this->belongsTo(PermissionSection::class, 'permission_id', 'id')->whereNotNull('parent_section')->withDefault();
    }
    public function scopeDeActiveMenuUser($q)
    {
        return $q->where('ignore', 0)       
        ->where('active_status', 0)
        ->whereNull('user_id')->where('role_id', sidebarRoleId());
    }
    public function scopeActiveMenuUser($q)
    {
        return $q->where('ignore', 0)       
        ->where('active_status', 1)
        ->whereNull('user_id')->where('role_id', sidebarRoleId());
    }
   
}
