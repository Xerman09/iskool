<?php

namespace App\Http\Controllers\Admin\Report;

use App\SmStaff;
use App\SmStaffAttendence;
use App\SmHumanDepartment;
use App\SmHrPayrollGenerate;
use App\Scopes\AcademicSchoolScope;
use App\Scopes\ActiveStatusSchoolScope;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Modules\RolePermission\Entities\InfixRole;
use Illuminate\Support\Facades\Log;

/**
 * One page answering, for a month: who is on the staff, how often they turned
 * up, and what they were paid.
 *
 * The staff counterpart of StudentReportController and
 * FeesStandardReportController, and built the same way and for the same reason:
 * a figure and the list behind it must be the same answer to the same question,
 * so the population is resolved and measured exactly once, in classify(), and
 * both the cards and the modal read that.
 *
 * Why this exists when there is already a staff attendance report and a payroll
 * report: those two ask separate questions of separate screens, neither totals
 * anything, and both run a query per member of staff. Attendance and pay are the
 * same question asked twice - whether somebody worked the month and what they
 * were paid for it - so they are answered together here.
 */
class StaffStandardReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('PM');
    }

    /**
     * The attendance codes, as sm_staff_attendences stores them (see that table's
     * own column comment). Held here because the letters are what is in the
     * database and the names are what the page says.
     */
    private const ATTENDANCE = [
        'att_present' => 'P',
        'att_late' => 'L',
        'att_absent' => 'A',
        'att_holiday' => 'H',
        'att_half' => 'F',
    ];

    /** The metrics a figure on a card can be clicked through to. */
    private const METRICS = [
        'all', 'active', 'inactive',
        'att_present', 'att_late', 'att_half', 'att_absent', 'att_holiday',
        'pay_paid', 'pay_generated', 'pay_not_generated',
        'gross', 'earning', 'deduction', 'tax', 'net',
    ];

    public function index(Request $request)
    {
        try {
            $criteria = $this->criteria($request);
            $rows = $this->classify($criteria);

            return view('backEnd.reports.staffReport', array_merge($criteria['lists'], [
                'criteria' => $criteria,
                'rows' => $rows,
                'summary' => $this->summarise($rows),
            ]));
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    /**
     * The staff behind one figure on a card, for the modal that opens when it is
     * clicked. Same criteria, same classify() - so the list can only ever be the
     * rows that were counted.
     */
    public function metricList(Request $request)
    {
        $request->validate(['metric' => 'required|in:' . implode(',', self::METRICS)]);

        try {
            $criteria = $this->criteria($request);
            $rows = $this->filterByMetric($this->classify($criteria), $request->metric);

            $data = [
                'rows' => $rows,
                'metric' => $request->metric,
                'title' => __('reports.staff_metric_' . $request->metric),
                'totals' => $this->summarise($rows),
                'labels' => $this->criteriaLabels($criteria),
                /* The same request with print switched on, handed to the button in the
                   modal header - so the sheet is of exactly the list on screen rather
                   than of criteria reassembled a second time. */
                'printUrl' => route('staff-standard-report-metric', array_merge(
                    $request->except('print'),
                    ['print' => 1]
                )),
            ];

            if ($request->boolean('print')) {
                return view('backEnd.reports.staffReportPrint', $data);
            }

            return view('backEnd.reports.partials.staffReportList', $data)->render();
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['msg' => __('common.Operation failed')], 500);
        }
    }

    /**
     * The criteria written out in words, for the heading of a printed sheet - a
     * page of figures is not worth much without saying which month it is of.
     */
    private function criteriaLabels(array $criteria)
    {
        $labels = [
            __('hr.month') . ': ' . $criteria['month'] . ' ' . $criteria['year'],
        ];

        $labels[] = __('hr.role') . ': ' . ($criteria['roleId']
            ? optional(InfixRole::find($criteria['roleId']))->name
            : __('reports.staff_all_roles'));

        $labels[] = __('hr.department') . ': ' . ($criteria['departmentId']
            ? optional(SmHumanDepartment::find($criteria['departmentId']))->name
            : __('reports.staff_all_departments'));

        return array_filter($labels);
    }

    private function criteria(Request $request)
    {
        $schoolId = auth()->user()->school_id;

        /* Roles 1-3 are super admin, student and parent: none of them is staff, and
           the attendance report excludes them from its own picker for the same
           reason. */
        $roles = InfixRole::whereNotIn('id', [1, 2, 3])
            ->where(function ($q) use ($schoolId) {
                $q->where('school_id', $schoolId)->orWhere('type', 'System');
            })
            ->orderBy('name')
            ->get();

        $departments = SmHumanDepartment::where('school_id', $schoolId)->orderBy('name')->get();

        /* The months and years that have something in them, rather than every month
           since the school opened. Payroll stores its month as the English name and
           its year as a string, so that is what the pickers hand back. */
        $payrollYears = SmHrPayrollGenerate::withoutGlobalScope(AcademicSchoolScope::class)
            ->where('school_id', $schoolId)
            ->distinct()
            ->pluck('payroll_year')
            ->filter();

        $attendanceYears = SmStaffAttendence::where('school_id', $schoolId)
            ->distinct()
            ->pluck('attendence_date')
            ->filter()
            ->map(fn ($d) => (string) date('Y', strtotime($d)))
            ->unique();

        $years = $payrollYears->merge($attendanceYears)
            ->push((string) date('Y'))
            ->unique()
            ->sortDesc()
            ->values();

        $months = collect(range(1, 12))->map(fn ($m) => date('F', mktime(0, 0, 0, $m, 1)));

        $month = $months->contains($request->get('month')) ? $request->get('month') : date('F');
        $year = $years->contains($request->get('year')) ? $request->get('year') : (string) date('Y');

        $roleId = $request->filled('role_id') ? (int) $request->role_id : null;
        $departmentId = $request->filled('department_id') ? (int) $request->department_id : null;

        /* Inactive staff are in the population by default: a month's payroll and
           attendance belong to whoever worked it, and somebody who has since left
           still did. The filter is there for the headcount question, not for this
           one. */
        $status = in_array($request->get('status'), ['active', 'inactive'], true) ? $request->get('status') : null;

        return [
            'schoolId' => $schoolId,
            'month' => $month,
            /* The numeric month, for matching attendence_date - which is a real date
               while payroll_month is a word. */
            'monthNumber' => date('m', strtotime($month . ' 1 2000')),
            'year' => $year,
            'roleId' => $roleId,
            'departmentId' => $departmentId,
            'status' => $status,
            'search' => trim((string) $request->get('search')),
            'lists' => compact('roles', 'departments', 'months', 'years'),
        ];
    }

    /**
     * Resolve the population and measure every member of staff.
     *
     * Three queries and no more: the staff, their attendance days grouped in the
     * database, and the month's payroll rows. The reports this replaces run one
     * query per member of staff - a query per person is what makes a staff list
     * of any size unusable.
     */
    private function classify(array $criteria)
    {
        $schoolId = $criteria['schoolId'];

        /* The default scope on SmStaff hides anyone whose active_status is not 1, so
           asking it for a headcount can only ever answer "all of them are active".
           Stripped, and the school filter the scope was also carrying is reapplied
           here - without it, stripping one would silently drop the other. */
        $staffs = SmStaff::withoutGlobalScope(ActiveStatusSchoolScope::class)
            ->where('school_id', $schoolId)
            ->when($criteria['roleId'], fn ($q) => $q->where('role_id', $criteria['roleId']))
            ->when($criteria['departmentId'], fn ($q) => $q->where('department_id', $criteria['departmentId']))
            ->whereNotIn('role_id', [2, 3])
            ->with(['roles', 'departments'])
            ->get();

        /* Counted in the database, grouped by person and code - one row per person
           per code, rather than a row per attendance day fetched and tallied here. */
        $attendance = SmStaffAttendence::where('school_id', $schoolId)
            ->where('attendence_date', 'like', $criteria['year'] . '-' . $criteria['monthNumber'] . '%')
            ->whereIn('staff_id', $staffs->pluck('id'))
            ->selectRaw('staff_id, attendence_type, COUNT(*) as days')
            ->groupBy('staff_id', 'attendence_type')
            ->get()
            ->groupBy('staff_id')
            ->map(fn ($rows) => $rows->pluck('days', 'attendence_type'));

        /* Scope stripped because it pins the model to the session's academic year
           while this report is asked about a named month of a named year - a payroll
           row generated under a different year would otherwise go missing. */
        $payrolls = SmHrPayrollGenerate::withoutGlobalScope(AcademicSchoolScope::class)
            ->where('school_id', $schoolId)
            ->where('payroll_month', $criteria['month'])
            ->where('payroll_year', $criteria['year'])
            ->whereIn('staff_id', $staffs->pluck('id'))
            ->get()
            ->keyBy('staff_id');

        $rows = $staffs->map(function ($staff) use ($attendance, $payrolls) {
            $days = $attendance->get($staff->id, collect());
            $payroll = $payrolls->get($staff->id);

            $counts = [];
            foreach (self::ATTENDANCE as $metric => $code) {
                $counts[$metric] = (int) ($days[$code] ?? 0);
            }

            /* Days the person was expected in. Holidays are left out - nobody is
               absent on a day the school was shut, and counting them would drag every
               attendance rate down by however many fell in the month. */
            $expected = $counts['att_present'] + $counts['att_late']
                + $counts['att_half'] + $counts['att_absent'];

            /* A half day is half a day worked. Late is still a day worked - it is
                reported separately because lateness is its own question, not because
                the day does not count. */
            $worked = $counts['att_present'] + $counts['att_late'] + ($counts['att_half'] * 0.5);

            return array_merge([
                'staff' => $staff,
                'active' => (int) $staff->active_status === 1,
                'payroll' => $payroll,
                'expected_days' => $expected,
                'worked_days' => $worked,
                /* No days recorded at all is not 0% attendance - it is an unanswered
                   question, and shown as such rather than as a failing rate. */
                'attendance_rate' => $expected > 0 ? round($worked / $expected * 100, 1) : null,
                'basic_salary' => (float) $staff->basic_salary,
                'gross' => (float) optional($payroll)->gross_salary,
                'earning' => (float) optional($payroll)->total_earning,
                'deduction' => (float) optional($payroll)->total_deduction,
                'tax' => (float) optional($payroll)->tax,
                'net' => (float) optional($payroll)->net_salary,
                'payroll_status' => $this->payrollStatusOf($payroll),
            ], $counts);
        });

        if ($criteria['status']) {
            $wanted = $criteria['status'] === 'active';
            $rows = $rows->filter(fn ($r) => $r['active'] === $wanted);
        }

        if ($criteria['search'] !== '') {
            $needle = mb_strtolower($criteria['search']);
            $rows = $rows->filter(function ($r) use ($needle) {
                return str_contains(mb_strtolower((string) $r['staff']->full_name), $needle)
                    || str_contains(mb_strtolower((string) $r['staff']->staff_no), $needle);
            });
        }

        return $rows->sortBy(fn ($r) => (string) $r['staff']->full_name)->values();
    }

    /**
     * Where this month's pay has got to for one person.
     *
     * payroll_status is stored as NG, G or P (see the table's column comment). No
     * row at all means the month has not been generated for them, which is the
     * same answer as NG and is reported as such rather than as a fourth state -
     * whether a row exists is bookkeeping, not news.
     */
    private function payrollStatusOf($payroll)
    {
        if (!$payroll) {
            return 'pay_not_generated';
        }

        if ($payroll->payroll_status === 'P') {
            return 'pay_paid';
        }

        return $payroll->payroll_status === 'G' ? 'pay_generated' : 'pay_not_generated';
    }

    /**
     * Both halves of every figure on the page: the quantity, and how many people
     * it is spread across. A total of absences does not say whether it is one
     * person off for a fortnight or ten people off for a day.
     */
    private function summarise($rows)
    {
        $sum = fn ($key) => round((float) $rows->sum($key), 2);

        $summary = [
            'staff' => $rows->count(),
            'count_all' => $rows->count(),
            'count_active' => $rows->where('active', true)->count(),
            'count_inactive' => $rows->where('active', false)->count(),
            'expected_days' => (float) $rows->sum('expected_days'),
            'worked_days' => (float) $rows->sum('worked_days'),
            'gross' => $sum('gross'),
            'earning' => $sum('earning'),
            'deduction' => $sum('deduction'),
            'tax' => $sum('tax'),
            'net' => $sum('net'),
        ];

        /* The figures the Headcount card needs under the same names the money cards
           use, so one card template can read either. */
        $summary['all'] = $summary['count_all'];
        $summary['active'] = $summary['count_active'];
        $summary['inactive'] = $summary['count_inactive'];

        foreach (array_keys(self::ATTENDANCE) as $metric) {
            $summary[$metric] = (int) $rows->sum($metric);
            $summary['count_' . $metric] = $rows->where($metric, '>', 0)->count();
        }

        foreach (['pay_paid', 'pay_generated', 'pay_not_generated'] as $status) {
            $summary[$status] = $rows->where('payroll_status', $status)->count();
            $summary['count_' . $status] = $summary[$status];
        }

        foreach (['gross', 'earning', 'deduction', 'tax', 'net'] as $money) {
            $summary['count_' . $money] = $rows->where($money, '>', 0)->count();
        }

        $summary['attendance_rate'] = $summary['expected_days'] > 0
            ? round($summary['worked_days'] / $summary['expected_days'] * 100, 1)
            : 0;

        return $summary;
    }

    /** The counterpart of summarise(): one figure, the rows it was counted from. */
    private function filterByMetric($rows, $metric)
    {
        switch ($metric) {
            case 'all':
                return $rows;
            case 'active':
                return $rows->where('active', true)->values();
            case 'inactive':
                return $rows->where('active', false)->values();
            case 'pay_paid':
            case 'pay_generated':
            case 'pay_not_generated':
                return $rows->where('payroll_status', $metric)->values();
            case 'gross':
            case 'earning':
            case 'deduction':
            case 'tax':
            case 'net':
                return $rows->where($metric, '>', 0)->values();
            default:
                /* An attendance metric: the people who have at least one such day,
                   which is who the figure was summed from. */
                return $rows->where($metric, '>', 0)->values();
        }
    }
}
