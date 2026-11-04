<?php

namespace App\Http\Controllers\Admin\Communicate;

use App\GlobalVariable;
use App\User;
use Carbon\Carbon;
use App\SmNoticeBoard;
use App\SmNotification;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\NoticeRequestForm;
use App\Traits\NotificationSend;
use Modules\RolePermission\Entities\InfixRole;
use Modules\Saas\Entities\SmAdministratorNotice;

class SmNoticeController extends Controller
{
    use NotificationSend;
    public function __construct()
	{
        $this->middleware('PM');
	}

    public function sendMessage(Request $request)
    {
        try {
            $roles = InfixRole::when((generalSetting()->with_guardian !=1), function ($query) {
                $query->where('id', '!=', 3);
            })->where(function ($q) {
                $q->where('school_id', Auth::user()->school_id)->orWhere('type', 'System');
            })->get();
            /* Below role there was no way to narrow the audience, and a tertiary year level
               is shared by every program and holds both terms at once. */
            $courses = courses();
            $semesters = semesters();
            [$classes, $sections] = $this->audienceChoices();
            return view('backEnd.communicate.sendMessage', compact('roles', 'courses', 'semesters', 'classes', 'sections'));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    /**
     * A multi-select's value, ready to store.
     *
     * Null rather than an empty array when nothing is picked, because null is what
     * "everyone in the chosen roles" has always been stored as and what every
     * notice written before these fields existed holds. Cast to integers so the
     * JSON holds ids and not the strings a form posts - whereJsonContains compares
     * types, and "3" is not 3.
     */
    /**
     * The year levels and sections a notice can be aimed at.
     *
     * Every section in the school, not the sections of one class: a notice is
     * written before anybody picks a year level, and in a college a section belongs
     * to a subject's block rather than to a single class anyway. Sections are
     * recorded per academic year and the blocks point at the current year's rows, so
     * the model's own scope - which cuts to that year - is left in place.
     *
     * Year levels are not. They belong to the school and are shared across academic
     * years, so the helper's academic_id filter would hand back an empty list the
     * moment a new year is opened: the rows stay behind under the year they were
     * first entered, which is where every student's class_id still points.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection}
     */
    private function audienceChoices()
    {
        $schoolId = Auth::user()->school_id;

        return [
            \App\SmClass::withoutGlobalScopes()
                ->where('school_id', $schoolId)
                ->where('active_status', 1)
                ->orderBy('id')
                ->get(),
            \App\SmSection::where('school_id', $schoolId)
                ->where('active_status', 1)
                ->orderBy('section_name')
                ->get(),
        ];
    }

    private function audienceList($values)
    {
        $ids = collect((array) $values)->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();

        return $ids ?: null;
    }

    public function saveNoticeData(NoticeRequestForm $request)
    {
        try {
            $noticeData = new SmNoticeBoard();
            if (isset($request->is_published)) {
                $noticeData->is_published = $request->is_published;
            }
            $noticeData->notice_title = $request->notice_title;
            $noticeData->notice_message = $request->notice_message;
            $noticeData->notice_date = date('Y-m-d', strtotime($request->notice_date));
            $noticeData->publish_on = date('Y-m-d', strtotime($request->publish_on));
            $noticeData->inform_to = json_encode($request->role);
            /* Empty means every student in the chosen roles, which is what a notice
               meant before these existed and what basic education always wants. */
            $noticeData->course_ids = $this->audienceList($request->course_ids);
            $noticeData->class_ids = $this->audienceList($request->class_ids);
            $noticeData->section_ids = $this->audienceList($request->section_ids);
            $noticeData->semester_id = $request->semester_id ?: null;
            $noticeData->created_by = Auth::user()->id;
            $noticeData->school_id = Auth::user()->school_id;
            if(moduleStatusCheck('University')){
                $noticeData->un_academic_id = getAcademicId();
            }else{
                $noticeData->academic_id = getAcademicId();
            }
            $noticeData->save();

            $data['title'] = $request->notice_title;
            $data['notice'] = $request->notice_title;
            foreach($request->role as $role_id){
                $userIds = User::where('role_id', $role_id)->where('active_status', 1)->pluck('id')->toArray();
                if($role_id == 4){
                    $this->sent_notifications('Notice', $userIds, $data, ['Teacher']);
                }elseif($role_id == 2){
                    $this->sent_notifications('Notice', $userIds, $data, ['Student']);
                }elseif($role_id == 3){
                    $this->sent_notifications('Notice', $userIds, $data, ['Parent']);
                }elseif($role_id == GlobalVariable::isAlumni()){
                    $this->sent_notifications('Notice', $userIds, $data, ['Alumni']);
                }
            }

            if ($request->role != null) {
                foreach ($request->role as $key => $role) {
                    $users = User::where('role_id', $role)->where('active_status', 1)->get();
                    foreach ($users as $key => $user) {
                        $notification = new SmNotification();
                        $notification->role_id = $role;
                        $notification->message = "Notice for you";
                        $notification->date = $noticeData->notice_date;
                        $notification->user_id = $user->id;
                        $notification->url = "notice-list";
                        $notification->school_id = Auth::user()->school_id;
                        if(moduleStatusCheck('University')){
                            $notification->un_academic_id = getAcademicId();
                        }else{
                            $notification->academic_id = getAcademicId();
                        }
                        $notification->save();
                    }
                }
            }
            Toastr::success('Operation successful', 'Success');
            return redirect('notice-list');

        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function noticeList(Request $request)
    {
        try {
            $allNotices = SmNoticeBoard::with('users')
                                        ->orderBy('id', 'DESC')
                                        ->get();
            return view('backEnd.communicate.noticeList', compact('allNotices'));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function administratorNotice(Request $request)
    {
        try {
            $allNotices = SmAdministratorNotice::where('inform_to', Auth::user()->school_id)
                        ->where('active_status', 1)
                        ->get();
          
            return view('backEnd.communicate.administratorNotice', compact('allNotices'));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function editNotice(Request $request, $notice_id)
    {

        try {
            $roles = InfixRole::where(function ($q) {
                $q->where('school_id', Auth::user()->school_id)->orWhere('type', 'System');
            })->get();
            $noticeDataDetails = SmNoticeBoard::find($notice_id);
            $courses = courses();
            $semesters = semesters();
            [$classes, $sections] = $this->audienceChoices();
            return view('backEnd.communicate.editSendMessage', compact('noticeDataDetails', 'roles', 'courses', 'semesters', 'classes', 'sections'));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function updateNoticeData(NoticeRequestForm $request)
    {
        try {
            
            $noticeData = SmNoticeBoard::find($request->notice_id);

            if (isset($request->is_published)) {
                $noticeData->is_published = $request->is_published;
            }
            $noticeData->notice_title = $request->notice_title;
            $noticeData->notice_message = $request->notice_message;

            $noticeData->notice_date = date('Y-m-d', strtotime($request->notice_date));
            $noticeData->publish_on = date('Y-m-d', strtotime($request->publish_on));
            $noticeData->notice_date = Carbon::createFromFormat('m/d/Y', $request->notice_date)->format('Y-m-d');
            $noticeData->publish_on = Carbon::createFromFormat('m/d/Y', $request->publish_on)->format('Y-m-d');
            $noticeData->inform_to = json_encode($request->role);
            $noticeData->course_ids = $this->audienceList($request->course_ids);
            $noticeData->class_ids = $this->audienceList($request->class_ids);
            $noticeData->section_ids = $this->audienceList($request->section_ids);
            $noticeData->semester_id = $request->semester_id ?: null;
            $noticeData->updated_by = auth()->user()->id;
            if ($request->is_published) {
               $noticeData->is_published = 1;
            } else {
               $noticeData->is_published = 0;
            }
            $noticeData->update();

            if ($request->role != null) {

                foreach ($request->role as $key => $role) {
                    $users = User::where('role_id', $role)->get();
                    foreach ($users as $key => $user) {
                        $notification = new SmNotification();
                        $notification->role_id = $role;
                        $notification->message = $request->notice_title;
                        $notification->date = $noticeData->notice_date;
                        $notification->user_id = $user->id;
                        $notification->url = "notice-list";
                        $notification->school_id = Auth::user()->school_id;
                        if(moduleStatusCheck('University')){
                            $notification->un_academic_id = getAcademicId();
                        }else{
                            $notification->academic_id = getAcademicId();
                        }
                        $notification->save();
                    }
                }
            }

            Toastr::success('Operation successful', 'Success');
            return redirect('notice-list');
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function deleteNoticeView(Request $request, $id)
    {
        try {
            return view('backEnd.communicate.deleteNoticeView', compact('id'));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function deleteNotice(Request $request, $id)
    {
        try {
            SmNoticeBoard::destroy($id);
            Toastr::success('Operation successful', 'Success');
            return redirect()->back();
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
}
