<?php

namespace App\Http\Controllers\Admin\SystemSettings;

use App\SmClass;
use App\SmSection;
use App\tableList;
use App\YearCheck;
use App\ApiBaseMethod;
use App\SmAcademicYear;
use App\SmClassSection;
use App\SmGeneralSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Scopes\AcademicSchoolScope;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Auth;

use function PHPUnit\Framework\isNull;
use App\Scopes\ActiveStatusSchoolScope;
use App\Scopes\StatusAcademicSchoolScope;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\Admin\GeneralSettings\SmAcademicYearRequest;
use Illuminate\Support\Facades\Log;

class SmAcademicYearController extends Controller
{
    public function __construct()
    {
        $this->middleware('PM');
        // User::checkAuth();
    }
    
    public function index(Request $request)
    {
        try {
            $academic_years = SmAcademicYear::where('active_status', 1)->orderBy('year', 'ASC')->where('school_id', Auth::user()->school_id)->get();
            if (ApiBaseMethod::checkUrl($request->fullUrl())) {
                return ApiBaseMethod::sendResponse($academic_years, null);
            }
            return view('backEnd.systemSettings.academic_year', compact('academic_years'));
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function store(SmAcademicYearRequest $request)
    {
        /* Copy from the year the admin is working in, not from whichever row happens to hold
           the highest id. The two stop agreeing the moment a year is created for planning and
           left unselected: the newest year is then an empty shell, and "copy with academic
           year" faithfully copied nothing out of it. Falls back to the newest year for a
           school with no active year set yet. */
        $yr = SmAcademicYear::where('id', getAcademicId())->where('school_id', Auth::user()->school_id)->first()
            ?: SmAcademicYear::orderBy('id', 'desc')->where('school_id', Auth::user()->school_id)->first();
        $created_year = $request->starting_date;
       
        DB::beginTransaction();
        $academic_year = new SmAcademicYear();
        $academic_year->year = $request->year;
        $academic_year->title = $request->title;
        $academic_year->starting_date = date('Y-m-d', strtotime($request->starting_date));
        $academic_year->ending_date = date('Y-m-d', strtotime($request->ending_date));
        if ($request->copy_with_academic_year != null) {
                $academic_year->copy_with_academic_year =implode(",",$request->copy_with_academic_year);
            }
        $academic_year->created_at = $created_year;
        $academic_year->school_id = Auth::user()->school_id;
        $result = $academic_year->save();
        if($result){
            session()->forget('academic_years');
            $academic_years = SmAcademicYear::where('active_status', 1)->where('school_id', Auth::user()->school_id)->get();
            session()->put('academic_years',$academic_years);   
        }
        /* Creating a year no longer switches the system into it. Setting next year's
           calendar up in advance used to move every screen onto a year that had no assign
           subjects, no student records and no enrollments yet, so the work entered under the
           previous year looked like it had disappeared - and the switch was silent, which
           made it look like a bug in whatever screen was opened next. The admin chooses the
           active year in General Settings when they are ready for it. A school with none set
           yet is the one exception: there the first year created has to become active. */
        $sm_Gs = SmGeneralSettings::where('school_id', Auth::user()->school_id)->first();
        if ($sm_Gs && !$sm_Gs->session_id) {
            $sm_Gs->session_id = $academic_year->id;
            $sm_Gs->academic_id = $academic_year->id;
            $sm_Gs->session_year = $academic_year->year;
            $sm_Gs->save();
            session()->forget('sessionId');
            session()->put('sessionId', $sm_Gs->session_id);
            session()->forget('generalSetting');
            session()->put('generalSetting', SmGeneralSettings::where('school_id', Auth::user()->school_id)->first());
        }

        $marksGradeSourceId = $this->copySourceAcademicId(\App\SmMarksGrade::class, $academic_year->id);
        $data = \App\SmMarksGrade::where('academic_id', $marksGradeSourceId)->where('school_id', Auth::user()->school_id)->get();

        if (!empty($data)) {
            foreach ($data as $k0ey => $value) {
                $newClient = $value->replicate();
                $newClient->created_at = $created_year;
                $newClient->updated_at = $created_year;
                $newClient->academic_id = $academic_year->id;
                $newClient->save();
            }
        }

        // The subject catalog, the curriculum subjects built from it and the year levels are
        // school-level data now (see App\Scopes\StatusSchoolScope), so they are already
        // visible in this new year and nothing is copied. Copying them used to mint new ids
        // every year, which silently broke prerequisites, minor-subject equivalence and the
        // marks already recorded against the previous year's rows. Teacher assignments
        // (SmAssignSubject) still never carry over - those must be redone by the admin
        // (e.g. a teacher may have resigned since).

        if ($request->copy_with_academic_year != null) {
            $tables = $request->copy_with_academic_year;
            // Year levels are shared across academic years, so replicating them would only
            // duplicate every entry in the year-level dropdowns.
            $tables = array_diff(array_filter($tables), [SmClass::class]);
            /* These two carry a section_id, so they cannot go through the generic replicate
               loop below - it would copy the source year's section ids verbatim. They are
               handled after the new sections exist, with the ids remapped by name. */
            $copyAssignSubjects = in_array(\App\SmAssignSubject::class, $tables);
            $copyStudentRecords = in_array(\App\Models\StudentRecord::class, $tables);
            $tables = array_diff($tables, [\App\SmAssignSubject::class, \App\Models\StudentRecord::class]);

            foreach ($tables as $table_name) {
                $sourceId = $this->copySourceAcademicId($table_name, $academic_year->id);
                if (!$sourceId) {
                    continue;
                }
                $data = $table_name::where('academic_id', $sourceId)->where('school_id', Auth::user()->school_id)->withoutGlobalScopes([
                    StatusAcademicSchoolScope::class,
                    AcademicSchoolScope::class,
                    ActiveStatusSchoolScope::class
                ])->get();

                foreach ($data as $value) {
                    $newClient = $value->replicate();
                    $newClient->created_at = $created_year;
                    $newClient->updated_at = $created_year;
                    $newClient->academic_id = $academic_year->id;
                    $newClient->save();
                }
            }

            $classes = SmClass::where('school_id', Auth::user()->school_id)->get();
            $sections = SmSection::where('academic_id', $academic_year->id)->where('school_id', Auth::user()->school_id)->withoutGlobalScope(StatusAcademicSchoolScope::class)->get();
            foreach ($classes as $class) {
                foreach ($sections as $section) {
                    $class_section = new SmClassSection();
                    $class_section->class_id = $class->id;
                    $class_section->section_id = $section->id;
                    $class_section->created_at = $created_year;
                    $class_section->school_id = Auth::user()->school_id;
                    $class_section->academic_id = $academic_year->id;
                    $class_section->save();
                }
            }

            /* Assign subjects and student records are rebuilt rather than replicated as-is,
               because the sections they point at were just copied under new ids. A plain
               replicate leaves them aimed at the source year's sections, which belong to no
               class-section pair in the new year, so every screen that resolves students
               through a section - attendance, exams, marks, homework - comes up empty while
               looking correctly filled in.

               Sections are paired by name, resolved from the old row's own section whatever
               year that section belongs to, so this still holds when the sections and the
               assignments were carried over from two different years.

               Subject registrations are deliberately not copied: which subjects a student
               carries is their own decision each term, and it has to pass the block slot
               limits and the prerequisite gate that the registration screen enforces. */
            $newSections = SmSection::withoutGlobalScope(StatusAcademicSchoolScope::class)
                ->where('academic_id', $academic_year->id)
                ->where('school_id', Auth::user()->school_id)
                ->get()
                ->keyBy(function ($section) {
                    return mb_strtolower(trim($section->section_name));
                });

            if (($copyAssignSubjects || $copyStudentRecords) && $newSections->isEmpty()) {
                Toastr::warning('Tick Section as well: assign subjects and student records need sections in the new year to attach to.', 'Skipped');
            }

            $sectionCache = [];
            $remapSection = function ($oldSectionId) use ($newSections, &$sectionCache) {
                if (!$oldSectionId) {
                    return null;
                }
                if (!array_key_exists($oldSectionId, $sectionCache)) {
                    $oldSection = SmSection::withoutGlobalScope(StatusAcademicSchoolScope::class)->find($oldSectionId);
                    $key = $oldSection ? mb_strtolower(trim($oldSection->section_name)) : null;
                    $sectionCache[$oldSectionId] = ($key !== null && isset($newSections[$key]))
                        ? $newSections[$key]->id
                        : null;
                }
                return $sectionCache[$oldSectionId];
            };

            if ($copyAssignSubjects && $newSections->isNotEmpty()) {
                $sourceId = $this->copySourceAcademicId(\App\SmAssignSubject::class, $academic_year->id);
                if ($sourceId) {
                    $assignSubjects = \App\SmAssignSubject::withoutGlobalScope(StatusAcademicSchoolScope::class)
                        ->where('academic_id', $sourceId)
                        ->where('school_id', Auth::user()->school_id)
                        ->get();

                    /* Who teaches what is decided again each year, so a carried-over
                       teacher is only a starting point. One that no longer works here
                       is not even that: the block would show their name on the class
                       routine and their dashboard would list a class they do not have.
                       The block is still carried - the subject is still offered - but
                       with the teacher cleared, so it shows up as needing one. */
                    $activeStaffIds = \App\SmStaff::where('school_id', Auth::user()->school_id)
                        ->where('active_status', 1)
                        ->pluck('id');

                    $orphaned = 0;

                    foreach ($assignSubjects as $assignSubject) {
                        $newSectionId = $remapSection($assignSubject->section_id);
                        // A section with no counterpart by name is skipped rather than
                        // carried over pointing nowhere.
                        if (!$newSectionId) {
                            continue;
                        }
                        $copy = $assignSubject->replicate();
                        $copy->section_id = $newSectionId;

                        if ($copy->teacher_id && !$activeStaffIds->contains($copy->teacher_id)) {
                            $copy->teacher_id = null;
                            $orphaned++;
                        }

                        $copy->academic_id = $academic_year->id;
                        $copy->created_at = $created_year;
                        $copy->updated_at = $created_year;
                        $copy->save();
                    }

                    if ($orphaned) {
                        Toastr::warning($orphaned . ' subject(s) came over without a teacher - the one assigned last year no longer works here. Assign a teacher before classes start.', 'Check Assign Subject');
                    }
                }
            }

            if ($copyStudentRecords && $newSections->isNotEmpty()) {
                $sourceId = $this->copySourceAcademicId(\App\Models\StudentRecord::class, $academic_year->id);
                if ($sourceId) {
                    $studentRecords = \App\Models\StudentRecord::where('academic_id', $sourceId)
                        ->where('school_id', Auth::user()->school_id)
                        ->get();

                    foreach ($studentRecords as $studentRecord) {
                        $copy = $studentRecord->replicate();
                        // A college record carries no advisory section, so an unset or
                        // unmatched one stays null instead of pointing at another year.
                        $copy->section_id = $remapSection($studentRecord->section_id);
                        $copy->academic_id = $academic_year->id;
                        $copy->session_id = $academic_year->id;
                        $copy->is_promote = 0;
                        $copy->created_at = $created_year;
                        $copy->updated_at = $created_year;
                        $copy->save();
                    }
                }
            }
        }



        DB::commit();
        Toastr::success('Operation successful', 'Success');
        return redirect()->back();
    }

    /**
     * Which year a table should be copied from when a new academic year is created.
     *
     * The active year when it actually holds rows for this school, otherwise the newest
     * year that does. Resolving this per table, instead of fixing one source year up
     * front, is what keeps the carry-over working year after year: a year created for
     * planning and never selected holds nothing, so copying from it produced nothing at
     * all, and once an older year is the only one still holding the assignments, that is
     * what the new year has to be built from. Nothing here depends on any one particular
     * year still being around.
     */
    private function copySourceAcademicId($modelClass, $newAcademicId)
    {
        $rowsIn = function ($academicId) use ($modelClass) {
            return $modelClass::withoutGlobalScopes([
                StatusAcademicSchoolScope::class,
                AcademicSchoolScope::class,
                ActiveStatusSchoolScope::class,
            ])->where('school_id', Auth::user()->school_id)->where('academic_id', $academicId);
        };

        $activeId = getAcademicId();
        if ($activeId && $activeId != $newAcademicId && $rowsIn($activeId)->exists()) {
            return $activeId;
        }

        return $modelClass::withoutGlobalScopes([
            StatusAcademicSchoolScope::class,
            AcademicSchoolScope::class,
            ActiveStatusSchoolScope::class,
        ])->where('school_id', Auth::user()->school_id)
            ->where('academic_id', '!=', $newAcademicId)
            ->max('academic_id');
    }

    public function show(Request $request, $id)
    {
        try {
             if (checkAdmin()) {
                $academic_year = SmAcademicYear::find($id);
            }else{
                $academic_year = SmAcademicYear::where('id',$id)->where('school_id',Auth::user()->school_id)->first();
            }
            $academic_years = SmAcademicYear::where('school_id', Auth::user()->school_id)->get();
            if (ApiBaseMethod::checkUrl($request->fullUrl())) {
                $data = [];
                $data['academic_year'] = $academic_year->toArray();
                $data['academic_years'] = $academic_years->toArray();
                return ApiBaseMethod::sendResponse($data, null);
            }
            return view('backEnd.systemSettings.academic_year', compact('academic_year', 'academic_years'));
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }


    public function edit($id)
    {
        Toastr::error('Operation Failed', 'Failed');
        return redirect()->back();
    }


    public function update(Request $request, $id)
    {
        $input = $request->all();
        $validator = Validator::make($input, [
            'year' => 'required|numeric|digits:4',
            'title' => "required|max:150",
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        try {
            $yr = SmAcademicYear::where('id', getAcademicId())->where('school_id', Auth::user()->school_id)->first();
            $created_year = $request->starting_date;
            if ($yr->year == $request->year) {
                Toastr::warning('You cannot copy current academic year info.', 'Warning');
                return redirect('academic-year');
            }

            if (checkAdmin()) {
                $academic_year = SmAcademicYear::find($request->id);
            }else{
                $academic_year = SmAcademicYear::where('id',$request->id)->where('school_id',Auth::user()->school_id)->first();
            }
            $academic_year->year = $request->year;
            $academic_year->title = $request->title;
            $academic_year->starting_date = date('Y-m-d', strtotime($request->starting_date));
            $academic_year->ending_date = date('Y-m-d', strtotime($request->ending_date));
            $academic_year->created_at = $created_year;
            if ($yr->year != $request->year) {
                if ($request->copy_with_academic_year != null) {
                    $academic_year->copy_with_academic_year =implode(",",$request->copy_with_academic_year);
                }
            }
            $result = $academic_year->save();
            if($result){
                session()->forget('academic_years');
                $academic_years = SmAcademicYear::where('active_status', 1)->where('school_id', Auth::user()->school_id)->get();
                session()->put('academic_years',$academic_years);
            }
            if (ApiBaseMethod::checkUrl($request->fullUrl())) {
                if ($result) {
                    return ApiBaseMethod::sendResponse(null, 'Year has been updated successfully');
                } else {
                    return ApiBaseMethod::sendError('Something went wrong, please try again');
                }
            } else {
                if ($result) {
                    Toastr::success('Operation successful', 'Success');
                    return redirect('academic-year');
                } else {
                    Toastr::error('Operation Failed', 'Failed');
                    return redirect()->back();
                }
            }
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            // $session_id = 'academic_id';
            // $tables = tableList::getTableList($session_id, $id);
            try {

                if (getAcademicId() != $id) {
                    if (checkAdmin()) {
                        $delete_query = SmAcademicYear::find($id);
                    }else{
                        $delete_query = SmAcademicYear::where('id',$id)->where('school_id',Auth::user()->school_id)->first();
                    }


                    $del_tables=explode(',',@$delete_query->copy_with_academic_year);
               

                    if(!is_null($del_tables)){
                        foreach ($del_tables as $del_table_name) {
                            if($del_table_name){
                                $del_data = new $del_table_name();
                                $del_data = $del_data->where('academic_id', $id)->delete();
                            }
                        }
                    }

                    SmClassSection::where('academic_id', $request->id)->where('school_id', Auth::user()->school_id)->delete();
                  
                    $delete_query->delete();

                   
                    if ($delete_query) {
                        session()->forget('academic_years');
                        $academic_years = SmAcademicYear::where('active_status', 1)->where('school_id', Auth::user()->school_id)->get();
                        session()->put('academic_years', $academic_years);
                        
                        Toastr::success('Operation successful', 'Success');
                        return redirect()->back();
                    } else {
                        Toastr::error('Operation Failed', 'Failed');
                        return redirect()->back();
                    }
                    
                } else {
                    Toastr::warning('You cannot delete current academic year.', 'Warning');
                    return redirect()->back();
                }
                
                
            } catch (\Illuminate\Database\QueryException $e) {
                Toastr::error('This item already used', 'Failed');
                return redirect()->back();
            }
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
}