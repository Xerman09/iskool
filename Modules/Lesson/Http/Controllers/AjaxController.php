<?php

namespace Modules\Lesson\Http\Controllers;

use App\SmStaff;
use App\SmSubject;
use App\SmAssignSubject;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\Lesson\Entities\SmLesson;;
use Illuminate\Contracts\Support\Renderable;
use Modules\Lesson\Entities\SmLessonTopicDetail;

class AjaxController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
        public function ajaxSelectLesson(Request $request){
        try {
           
            $lesson_all=SmLesson::where('class_id',$request->class)
            ->where('section_id','=',$request->section)
            ->where('subject_id','=',$request->subject)
            ->get(['id', 'lesson_title']);

            $lessons=[];
            foreach ($lesson_all as $lesson) {
                $lessons[]=$lesson;
            }
            return response()->json([$lessons]);
        } catch (\Exception $e) {
           Toastr::error('Operation Failed','Failed');
           return redirect()->back(); 
        }
    }
    //get topic from lesson 
    public function ajaxSelectTopic(Request $request)
    {
        try {
           
            $topic_all = SmLessonTopicDetail::where('lesson_id', $request->lesson_id)
                                        ->distinct('topic_id')
                                        ->get();
            $topics=[];
            foreach ($topic_all as $topic) {
                $topics[]=$topic;
            }
            return response()->json([$topics]);
        } catch (\Exception $e) {

           Toastr::error('Operation Failed', 'Failed');
           return redirect()->back(); 
        }
    }

    public function ajaxGetTopicRow(Request $request)
    {
        $topics = SmLessonTopicDetail::where('lesson_id', $request->lesson_id)
                                        ->distinct('topic_id')
                                        ->get();
        return view('lesson::topic_row', compact('topics'));
    }
    //edit lesson plan
    public function getTopicRow($lessonPlanDetail)
    {
       return $topics = SmLessonTopicDetail::where('lesson_id', $lessonPlanDetail->lesson_detail_id)
                                        ->distinct('topic_id')
                                        ->get();
                                     
        return view('lesson::topic_row', compact('topics', 'lessonPlanDetail'));
    }
    public function getSubject(Request $request){


        $class_id = $request->class;
        $selectedSections = $request->message_to_section;

        $subjectId=SmSubject::query();
        $subjectId=$subjectId->where('class_id', $class_id);
         foreach ($selectedSections as $key => $value) {            
            $subjectId=$subjectId->where('section_id', $value);  
           
         }
         return $subjectId->get();
    
        

    }
    public function getSubjectLesson(Request $request){
        try {
            $assigned = SmAssignSubject::where('class_id', '=', $request->class_id);

            if (teacherAccess()) {
                $staff_info = SmStaff::where('user_id', Auth::user()->id)->first();
                $assigned->where('teacher_id', $staff_info->id);
            }

            /* distinct('subject_id') compiles to "select distinct *", which de-duplicates
               whole rows and so removes nothing: assign rows differ by id and section_id.
               A teacher holding one subject in two sections saw it listed twice. */
            $subject_ids = $assigned->pluck('subject_id')->unique();

            $subjects = SmSubject::with('course')
                ->whereIn('id', $subject_ids)
                ->when($request->filled('course_id'), function ($q) use ($request) {
                    $q->where('course_id', $request->course_id);
                })
                ->when($request->filled('semester_id'), function ($q) use ($request) {
                    $q->where('semester_id', $request->semester_id);
                })
                ->orderBy('subject_name')
                ->get();

            /* The program travels with every option. A subject row belongs to exactly one
               program, but the dropdown showed only the name, so two programs sharing a
               subject name - every GE subject, eventually - were two identical lines with
               nothing to choose between them. */
            $subjects = $subjects->map(function ($subject) {
                $course_code = optional($subject->course)->course_code;

                return [
                    'id' => $subject->id,
                    'subject_name' => $subject->subject_name,
                    'subject_type' => $subject->subject_type,
                    'course_code' => $course_code === '__BASIC_ED__' ? null : $course_code,
                ];
            })->values();

            return response()->json([$subjects]);
        } catch (\Exception $e) {
            Log::error('getSubjectLesson failed: ' . $e->getMessage());
            return response()->json([[]]);
        }
    }
  
}
