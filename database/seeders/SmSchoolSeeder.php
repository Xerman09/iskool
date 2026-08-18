<?php

namespace Database\Seeders;

use App\User;
use App\SmBook;
use App\SmItem;
use App\SmNews;
use App\SmPage;
use App\SmClass;
use App\SmRoute;
use App\SmStaff;
use App\SmStyle;
use App\SmCourse;
use App\SmParent;
use App\SmSchool;
use App\SmSection;
use App\SmStudent;
use App\SmSubject;
use App\SmVehicle;
use App\SmVisitor;
use App\SmWeekend;
use Carbon\Carbon;
use App\SmExamType;
use App\SmFeesType;
use App\SmHomework;
use App\SmNewsPage;
use App\SmRoomList;
use App\SmRoomType;
use App\SmSupplier;
use App\SmAboutPage;
use App\SmAddIncome;
use App\SmBaseSetup;
use App\SmBookIssue;
use App\SmClassRoom;
use App\SmComplaint;
use App\SmExamSetup;
use App\SmFeesGroup;
use App\SmItemStore;
use App\SmLeaveType;
use App\Models\Theme;
use App\SmAddExpense;
use App\SmCoursePage;
use App\SmCustomLink;
use App\SmFeesMaster;
use App\SmMarksGrade;
use App\SmSetupAdmin;
use App\SmBankAccount;
use App\SmDesignation;
use App\SmLeaveDefine;
use App\SmTestimonial;
use App\LibrarySubject;
use App\SmAcademicYear;
use App\SmBookCategory;
use App\SmClassTeacher;
use App\SmFeesDiscount;
use App\SmItemCategory;
use App\SmLeaveRequest;
use App\SmNewsCategory;
use App\SmStudentGroup;
use App\SmAssignSubject;
use App\SmAssignVehicle;
use App\SmDormitoryList;
use App\SmLibraryMember;
use App\SmPostalReceive;
use App\SmAdmissionQuery;
use App\SmChartOfAccount;
use App\SmContactMessage;
use App\SmCourseCategory;
use App\SmExamAttendance;
use App\SmPaymentMethhod;
use App\SmPostalDispatch;
use App\SmGeneralSettings;
use App\SmHomePageSetting;
use App\SmHomeworkStudent;
use App\SmHumanDepartment;
use App\SmStaffAttendence;
use App\SmStudentCategory;
use App\SmBackgroundSetting;
use App\SmHeaderMenuManager;
use App\SmHrPayrollGenerate;
use App\SmStudentAttendance;
use App\SmAssignClassTeacher;
use App\SmClassRoutineUpdate;
use App\SmStudentCertificate;
use App\SmTeacherUploadContent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Lesson\Entities\SmLesson;
use Modules\RolePermission\Entities\InfixRole;
use Modules\Saas\Events\InstituteRegistration;
use Modules\RolePermission\Entities\InfixPermissionAssign;
use Modules\Saas\Entities\SaasSchoolModulePermissionAssign;

class SmSchoolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        SmSchool::factory()->times(2)->create()->each(
            function ($school) {
                $role_id = User::where('school_id', $school->id)->where('role_id', 1)->first() ? 5 :1;
                //school admin user
                User::factory()->times(1)->create([
                    'school_id' => $school->id,
                    'username' => $school->email,
                    'email' => $school->email,
                    'role_id' => $role_id,
                ])->each(function ($user) use($role_id){
                    SmStaff::factory()->times(1)->create([
                        'role_id' => $role_id,
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'full_name' => $user->full_name,
                    ]);
                });
                event(new InstituteRegistration($school));
            });
        
    }
}
