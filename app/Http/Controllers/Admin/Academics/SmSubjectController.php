<?php

namespace App\Http\Controllers\Admin\Academics;

use App\SmSubject;
use App\tableList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use App\Http\Requests\Admin\Academics\SmSubjectRequest;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SmSubjectController extends Controller
{
    public function __construct()
	{
        $this->middleware('PM');
        // User::checkAuth();
	}

    public function index(Request $request)
    {

        try {
            $subjects = SmSubject::orderBy('id', 'DESC')->get();

            return view('backEnd.academics.subject', compact('subjects'));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
    public function store(SmSubjectRequest $request)
    {
        try {
            $subject = new SmSubject();
            $subject->subject_name = $request->subject_name;
            $subject->subject_type = $request->subject_type;
            $subject->subject_code = $request->subject_code;
            if (@generalSetting()->result_type == 'mark'){
                $subject->pass_mark = $request->pass_mark;
            }
            $subject->created_by   = auth()->user()->id;
            $subject->school_id    = auth()->user()->school_id;
            $subject->academic_id  = getAcademicId();
            $result = $subject->save();
            Toastr::success('Operation successful', 'Success');
            return redirect()->back();
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function importForm()
    {
        return view('backEnd.academics.import_subject');
    }

    public function downloadImportSample()
    {
        $usesMarks = @generalSetting()->result_type == 'mark';

        return Excel::download(new class($usesMarks) implements FromArray, WithStyles, ShouldAutoSize {
            private $usesMarks;

            public function __construct($usesMarks)
            {
                $this->usesMarks = $usesMarks;
            }

            public function array(): array
            {
                $headings = ['subject_name', 'subject_code', 'subject_type'];
                $example = ['Mathematics', 'MATH-101', 'T'];

                if ($this->usesMarks) {
                    $headings[] = 'pass_mark';
                    $example[] = 40;
                }

                $practicalExample = ['Chemistry Lab', 'CHEM-LAB', 'P'];
                if ($this->usesMarks) {
                    $practicalExample[] = 40;
                }

                return [$headings, $example, $practicalExample];
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
        }, 'subjects-import-sample.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

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
            $requiredHeadings = ['subject_name', 'subject_code', 'subject_type'];

            if (array_diff($requiredHeadings, $headings)) {
                Toastr::error('Required columns: subject_name, subject_code, subject_type.', 'Failed');
                return redirect()->back();
            }

            $existingNames = SmSubject::pluck('subject_name')->map(function ($name) {
                return strtolower(trim($name));
            })->all();
            $existingCodes = SmSubject::pluck('subject_code')->map(function ($code) {
                return strtolower(trim($code));
            })->all();
            $names = array_flip($existingNames);
            $codes = array_flip($existingCodes);
            $subjects = [];
            $errors = [];

            foreach ($rows as $index => $row) {
                $data = array_combine($headings, array_pad(array_slice($row, 0, count($headings)), count($headings), null));
                if (!array_filter($data, function ($value) { return $value !== null && $value !== ''; })) {
                    continue;
                }

                $line = $index + 2;
                $name = trim((string) ($data['subject_name'] ?? ''));
                $code = trim((string) ($data['subject_code'] ?? ''));
                $type = strtoupper(trim((string) ($data['subject_type'] ?? '')));
                $passMark = $data['pass_mark'] ?? null;

                if (!$name || !$code || !in_array($type, ['T', 'P'], true)) {
                    $errors[] = "row {$line}";
                    continue;
                }
                if (isset($names[strtolower($name)]) || isset($codes[strtolower($code)])) {
                    $errors[] = "row {$line} (duplicate name or code)";
                    continue;
                }
                if (@generalSetting()->result_type == 'mark' && ($passMark === null || $passMark === '')) {
                    $errors[] = "row {$line} (pass_mark is required)";
                    continue;
                }

                $names[strtolower($name)] = true;
                $codes[strtolower($code)] = true;
                $subjects[] = [
                    'subject_name' => $name,
                    'subject_code' => $code,
                    'subject_type' => $type,
                    'pass_mark' => @generalSetting()->result_type == 'mark' ? $passMark : null,
                    'created_by' => auth()->id(),
                    'school_id' => auth()->user()->school_id,
                    'academic_id' => getAcademicId(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($errors) {
                Toastr::error('Nothing was imported. Fix ' . implode(', ', array_slice($errors, 0, 5)) . (count($errors) > 5 ? ' and more.' : '.'), 'Failed');
                return redirect()->back();
            }
            if (!$subjects) {
                Toastr::error('The import file does not contain any subjects.', 'Failed');
                return redirect()->back();
            }

            DB::transaction(function () use ($subjects) {
                SmSubject::withoutGlobalScopes()->insert($subjects);
            });
            Toastr::success(count($subjects) . ' subjects imported successfully.', 'Success');
            return redirect()->route('subject');
        } catch (\Throwable $e) {
            Toastr::error('The subject import could not be completed.', 'Failed');
            return redirect()->back();
        }
    }

    public function edit(Request $request, $id)
    {
        try {
            $subject = SmSubject::find($id);
            $subjects = SmSubject::orderBy('id', 'DESC')->get();
            return view('backEnd.academics.subject', compact('subject', 'subjects'));
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
    public function update(SmSubjectRequest $request)
    {
        try {
            $subject = SmSubject::find($request->id);
            $subject->subject_name = $request->subject_name;
            $subject->subject_type = $request->subject_type;
            $subject->subject_code = $request->subject_code;
            if (@generalSetting()->result_type == 'mark'){
                $subject->pass_mark = $request->pass_mark;
            }
            $subject->save();

            Toastr::success('Operation successful', 'Success');
            return redirect()->route('subject');
        } catch (\Exception $e) {
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
    public function delete(Request $request, $id)
    {
        try {
            $tables = tableList::getTableList('subject_id', $id);
            try {
                if ($tables == null) {
                    // $delete_query = $section = SmSubject::destroy($id);
                         SmSubject::destroy($id);
                         Toastr::success('Operation successful', 'Success');
                         return redirect('subject');
                } else {
                    $msg = 'This data already used in  : ' . $tables . ' Please remove those data first';
                    Toastr::error($msg, 'Failed');
                    return redirect()->back();
                }
            } catch (\Illuminate\Database\QueryException $e) {

                $msg = 'This data already used in  : ' . $tables . ' Please remove those data first';
                Toastr::error($msg, 'Failed');
                return redirect()->back();
            }
        } catch (\Exception $e) {
           
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
}
