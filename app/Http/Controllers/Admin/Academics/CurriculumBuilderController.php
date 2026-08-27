<?php

namespace App\Http\Controllers\Admin\Academics;

use App\Course;
use App\Semester;
use App\SmClass;
use App\SmSubject;
use App\tableList;
use App\CurriculumVersion;
use App\SubjectPrerequisite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use App\Http\Requests\Admin\Academics\CurriculumBuilderRequest;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CurriculumBuilderController extends Controller
{
    public function __construct()
    {
        $this->middleware('PM');
    }

    private $criteriaFields = ['course_id', 'class_id', 'semester_id', 'curriculum_version_id'];

    private function formOptions()
    {
        return [
            'courses' => Course::where('school_id', auth()->user()->school_id)->get(),
            'curriculumVersions' => CurriculumVersion::where('school_id', auth()->user()->school_id)->get(),
            'classes' => SmClass::where('school_id', auth()->user()->school_id)->get(),
            'semesters' => Semester::where('school_id', auth()->user()->school_id)->get(),
            'prerequisiteOptions' => SmSubject::whereNotNull('course_id')->get(),
            'baseSubjects' => SmSubject::whereNull('course_id')->get(),
        ];
    }

    private function syncPrerequisites(Request $request, SmSubject $subject)
    {
        SubjectPrerequisite::where('subject_id', $subject->id)
            ->where('school_id', auth()->user()->school_id)->delete();

        if ($request->boolean('has_prerequisite') && $request->prerequisite_subject_ids) {
            foreach ($request->prerequisite_subject_ids as $prerequisiteId) {
                SubjectPrerequisite::create([
                    'subject_id' => $subject->id,
                    'prerequisite_subject_id' => $prerequisiteId,
                    'school_id' => auth()->user()->school_id,
                ]);
            }
        }
    }

    public function index(Request $request)
    {
        try {
            $data = $this->formOptions();
            $subjects = null;
            $criteria = null;

            if ($request->hasAny($this->criteriaFields)) {
                $validator = \Validator::make($request->all(), [
                    'course_id' => ['required', 'exists:courses,id'],
                    'curriculum_version_id' => ['required', 'exists:curriculum_versions,id'],
                    'class_id' => ['required', 'exists:sm_classes,id'],
                    'semester_id' => ['required', 'exists:semesters,id'],
                ]);

                if ($validator->fails()) {
                    return view('backEnd.academics.curriculumBuilder', array_merge($data, compact('subjects', 'criteria')))
                        ->withErrors($validator);
                }

                $criteria = $request->only($this->criteriaFields);
                $subjects = SmSubject::where($criteria)
                    ->with(['prerequisites.prerequisiteSubject'])
                    ->orderBy('id', 'DESC')->get();
                $data = array_merge($data, $this->formOptions());
            }

            return view('backEnd.academics.curriculumBuilder', array_merge($data, compact('subjects', 'criteria')));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function store(CurriculumBuilderRequest $request)
    {
        try {
            $sourceSubject = SmSubject::whereNull('course_id')->findOrFail($request->source_subject_id);

            $subject = new SmSubject();
            $subject->course_id = $request->course_id;
            $subject->curriculum_version_id = $request->curriculum_version_id;
            $subject->class_id = $request->class_id;
            $subject->semester_id = $request->semester_id;
            $subject->source_subject_id = $sourceSubject->id;
            $subject->subject_name = $sourceSubject->subject_name;
            $subject->subject_code = $sourceSubject->subject_code;
            $subject->units = $request->units;
            $subject->subject_classification = $request->subject_classification;
            $subject->subject_type = 'T';
            $subject->created_by = auth()->user()->id;
            $subject->school_id = auth()->user()->school_id;
            $subject->academic_id = getAcademicId();
            $subject->save();
            $this->syncPrerequisites($request, $subject);

            Toastr::success('Operation successful', 'Success');
            return redirect()->route('curriculum-builder', $request->only($this->criteriaFields));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function update(CurriculumBuilderRequest $request)
    {
        try {
            $sourceSubject = SmSubject::whereNull('course_id')->findOrFail($request->source_subject_id);
            $subject = SmSubject::whereNotNull('course_id')->findOrFail($request->id);
            $subject->course_id = $request->course_id;
            $subject->curriculum_version_id = $request->curriculum_version_id;
            $subject->class_id = $request->class_id;
            $subject->semester_id = $request->semester_id;
            $subject->source_subject_id = $sourceSubject->id;
            $subject->subject_name = $sourceSubject->subject_name;
            $subject->subject_code = $sourceSubject->subject_code;
            $subject->units = $request->units;
            $subject->subject_classification = $request->subject_classification;
            $subject->updated_by = auth()->user()->id;
            $subject->save();
            $this->syncPrerequisites($request, $subject);

            Toastr::success('Operation successful', 'Success');
            return redirect()->route('curriculum-builder', $request->only($this->criteriaFields));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function importForm(Request $request)
    {
        $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'curriculum_version_id' => ['required', 'exists:curriculum_versions,id'],
        ]);

        $course = Course::where('school_id', auth()->user()->school_id)->findOrFail($request->course_id);
        $curriculumVersion = CurriculumVersion::where('school_id', auth()->user()->school_id)->findOrFail($request->curriculum_version_id);

        return view('backEnd.academics.import_curriculum_builder', compact('course', 'curriculumVersion'));
    }

    public function downloadImportSample()
    {
        return Excel::download(new class implements FromArray, WithStyles, ShouldAutoSize {
            public function array(): array
            {
                return [
                    ['subject_code', 'class', 'semester', 'units', 'subject_classification', 'prerequisite_codes'],
                    ['MATH-101', 'Year 1', 'Semester 1', 3, 'major', ''],
                    ['MATH-102', 'Year 1', 'Semester 2', 3, 'major', 'MATH-101'],
                    ['MATH-201', 'Year 2', 'Semester 1', 3, 'major', 'MATH-101,MATH-102'],
                ];
            }

            public function styles(Worksheet $sheet)
            {
                $range = 'A1:' . $sheet->getHighestColumn() . $sheet->getHighestRow();
                $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

                return [
                    1 => [
                        'font' => ['bold' => true, 'size' => 12],
                        'alignment' => ['horizontal' => 'center'],
                    ],
                ];
            }
        }, 'curriculum-import-sample.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'curriculum_version_id' => ['required', 'exists:curriculum_versions,id'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        $courseId = $request->course_id;
        $curriculumVersionId = $request->curriculum_version_id;
        $schoolId = auth()->user()->school_id;

        try {
            $sheets = Excel::toArray([], $request->file('file'));
            $rows = $sheets[0] ?? [];

            if (count($rows) < 2) {
                Toastr::error('The import file does not contain any subjects.', 'Failed');
                return redirect()->back();
            }

            $headings = array_map(function ($heading) {
                return strtolower(trim(str_replace(' ', '_', (string) $heading)));
            }, array_shift($rows));
            $requiredHeadings = ['subject_code', 'class', 'semester', 'units', 'subject_classification'];

            if (array_diff($requiredHeadings, $headings)) {
                Toastr::error('Required columns: subject_code, class, semester, units, subject_classification.', 'Failed');
                return redirect()->back();
            }

            $baseSubjects = SmSubject::whereNull('course_id')->get()
                ->keyBy(function ($subject) { return strtolower(trim($subject->subject_code)); });
            $classes = SmClass::where('school_id', $schoolId)->get()
                ->keyBy(function ($class) { return strtolower(trim($class->class_name)); });
            $semesters = Semester::where('school_id', $schoolId)->get()
                ->keyBy(function ($semester) { return strtolower(trim($semester->semester_name)); });
            $existingCodes = SmSubject::where('course_id', $courseId)
                ->where('curriculum_version_id', $curriculumVersionId)
                ->pluck('subject_code')
                ->map(function ($code) { return strtolower(trim($code)); })
                ->all();

            $parsedRows = [];
            $fileCodes = [];
            $errors = [];

            foreach ($rows as $index => $row) {
                $data = array_combine($headings, array_pad(array_slice($row, 0, count($headings)), count($headings), null));
                if (!array_filter($data, function ($value) { return $value !== null && $value !== ''; })) {
                    continue;
                }

                $line = $index + 2;
                $code = trim((string) ($data['subject_code'] ?? ''));
                $className = trim((string) ($data['class'] ?? ''));
                $semesterName = trim((string) ($data['semester'] ?? ''));
                $units = $data['units'] ?? null;
                $classification = strtolower(trim((string) ($data['subject_classification'] ?? '')));
                $prerequisiteCodes = array_filter(array_map('trim', preg_split('/[,;]/', (string) ($data['prerequisite_codes'] ?? ''))));

                $codeKey = strtolower($code);

                if (!$code || !$className || !$semesterName || !is_numeric($units) || !in_array($classification, ['major', 'minor'], true)) {
                    $errors[] = "row {$line}";
                    continue;
                }
                if (!isset($baseSubjects[$codeKey])) {
                    $errors[] = "row {$line} (unknown subject_code)";
                    continue;
                }
                if (!isset($classes[strtolower($className)])) {
                    $errors[] = "row {$line} (unknown class)";
                    continue;
                }
                if (!isset($semesters[strtolower($semesterName)])) {
                    $errors[] = "row {$line} (unknown semester)";
                    continue;
                }
                if (in_array($codeKey, $existingCodes, true) || isset($fileCodes[$codeKey])) {
                    $errors[] = "row {$line} (duplicate subject_code)";
                    continue;
                }

                $fileCodes[$codeKey] = true;
                $parsedRows[] = [
                    'line' => $line,
                    'code' => $code,
                    'codeKey' => $codeKey,
                    'class_id' => $classes[strtolower($className)]->id,
                    'semester_id' => $semesters[strtolower($semesterName)]->id,
                    'units' => $units,
                    'classification' => $classification,
                    'prerequisite_codes' => $prerequisiteCodes,
                    'source_subject' => $baseSubjects[$codeKey],
                ];
            }

            if ($errors) {
                Toastr::error('Nothing was imported. Fix ' . implode(', ', array_slice($errors, 0, 5)) . (count($errors) > 5 ? ' and more.' : '.'), 'Failed');
                return redirect()->back();
            }
            if (!$parsedRows) {
                Toastr::error('The import file does not contain any subjects.', 'Failed');
                return redirect()->back();
            }

            $knownCodes = array_flip($existingCodes) + $fileCodes;
            foreach ($parsedRows as $parsedRow) {
                foreach ($parsedRow['prerequisite_codes'] as $prerequisiteCode) {
                    $prerequisiteKey = strtolower($prerequisiteCode);
                    if ($prerequisiteKey === $parsedRow['codeKey'] || !isset($knownCodes[$prerequisiteKey])) {
                        $errors[] = "row {$parsedRow['line']} (unknown prerequisite_codes: {$prerequisiteCode})";
                    }
                }
            }
            if ($errors) {
                Toastr::error('Nothing was imported. Fix ' . implode(', ', array_slice($errors, 0, 5)) . (count($errors) > 5 ? ' and more.' : '.'), 'Failed');
                return redirect()->back();
            }

            DB::transaction(function () use ($parsedRows, $courseId, $curriculumVersionId, $schoolId, $existingCodes) {
                $codeToId = SmSubject::where('course_id', $courseId)
                    ->where('curriculum_version_id', $curriculumVersionId)
                    ->get()
                    ->keyBy(function ($subject) { return strtolower(trim($subject->subject_code)); })
                    ->map(function ($subject) { return $subject->id; })
                    ->all();

                foreach ($parsedRows as $parsedRow) {
                    $subject = new SmSubject();
                    $subject->course_id = $courseId;
                    $subject->curriculum_version_id = $curriculumVersionId;
                    $subject->class_id = $parsedRow['class_id'];
                    $subject->semester_id = $parsedRow['semester_id'];
                    $subject->source_subject_id = $parsedRow['source_subject']->id;
                    $subject->subject_name = $parsedRow['source_subject']->subject_name;
                    $subject->subject_code = $parsedRow['source_subject']->subject_code;
                    $subject->units = $parsedRow['units'];
                    $subject->subject_classification = $parsedRow['classification'];
                    $subject->subject_type = 'T';
                    $subject->created_by = auth()->user()->id;
                    $subject->school_id = $schoolId;
                    $subject->academic_id = getAcademicId();
                    $subject->save();

                    $codeToId[$parsedRow['codeKey']] = $subject->id;
                }

                foreach ($parsedRows as $parsedRow) {
                    foreach ($parsedRow['prerequisite_codes'] as $prerequisiteCode) {
                        SubjectPrerequisite::create([
                            'subject_id' => $codeToId[$parsedRow['codeKey']],
                            'prerequisite_subject_id' => $codeToId[strtolower($prerequisiteCode)],
                            'school_id' => $schoolId,
                        ]);
                    }
                }
            });

            Toastr::success(count($parsedRows) . ' curriculum subjects imported successfully.', 'Success');
            return redirect()->route('curriculum-layout', ['course_id' => $courseId, 'curriculum_version_id' => $curriculumVersionId]);
        } catch (\Throwable $e) {
            Toastr::error('The curriculum import could not be completed.', 'Failed');
            return redirect()->back();
        }
    }

    public function delete(Request $request, $id)
    {
        try {
            $subject = SmSubject::whereNotNull('course_id')->findOrFail($id);
            $criteria = $subject->only($this->criteriaFields);

            $tables = tableList::getTableList('subject_id', $id);
            if ($tables == null) {
                $subject->delete();
                Toastr::success('Operation successful', 'Success');
                return redirect()->route('curriculum-builder', $criteria);
            }

            $msg = 'This data already used in : ' . $tables . ' Please remove those data first';
            Toastr::error($msg, 'Failed');
            return redirect()->back();
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
}
