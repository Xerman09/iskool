<?php

namespace App\Http\Controllers\Admin\Report;

use App\SmAddIncome;
use App\SmAddExpense;
use App\SmIncomeHead;
use App\SmExpenseHead;
use App\SmBankAccount;
use App\SmPaymentMethhod;
use App\SmHrPayrollGenerate;
use App\Scopes\AcademicSchoolScope;
use App\Scopes\ActiveStatusSchoolScope;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Log;

/**
 * One page answering, for a date range: what came in, what went out, and what
 * is left.
 *
 * The accounts counterpart of StudentReportController,
 * FeesStandardReportController and StaffStandardReportController, and built the
 * same way and for the same reason: a figure and the list behind it must be the
 * same answer to the same question, so the population is resolved and totalled
 * exactly once, in classify(), and both the cards and the modal read that.
 *
 * Two things it does that the existing accounts screens do not. It puts income,
 * expense and payroll on one page - payroll is cash out that never reaches
 * sm_add_expenses, so a cash position read off the expense list alone is short
 * by the wage bill every month. And it is driven by a date range rather than by
 * the academic year, because a set of books closes on a date.
 */
class AccountsStandardReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('PM');
    }

    /** How many heads each by-head card lists before the rest become "others". */
    private const TOP_HEADS = 4;

    /** The fixed metrics a figure on a card can be clicked through to. */
    private const METRICS = [
        'all', 'income', 'expense', 'payroll', 'net',
        'income_entries', 'expense_entries', 'no_head', 'no_account',
    ];

    public function index(Request $request)
    {
        try {
            $criteria = $this->criteria($request);
            $rows = $this->classify($criteria);

            return view('backEnd.reports.accountsReport', array_merge($criteria['lists'], [
                'criteria' => $criteria,
                'rows' => $rows,
                'summary' => $this->summarise($rows),
                'incomeHeadTotals' => $this->headTotals($rows, 'income'),
                'expenseHeadTotals' => $this->headTotals($rows, 'expense'),
            ]));
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    /**
     * The entries behind one figure on a card, for the modal that opens when it
     * is clicked. Same criteria, same classify() - so the list can only ever be
     * the rows that were counted.
     */
    public function metricList(Request $request)
    {
        /* A head metric carries the head's own id, so the set of valid metrics is
           not a fixed list and cannot be written as one. */
        $request->validate([
            'metric' => ['required', function ($attribute, $value, $fail) {
                if (!in_array($value, self::METRICS, true)
                    && !preg_match('/^head_(income|expense)_(\d+|none)$/', (string) $value)) {
                    $fail(__('validation.in', ['attribute' => $attribute]));
                }
            }],
        ]);

        try {
            $criteria = $this->criteria($request);
            $rows = $this->filterByMetric($this->classify($criteria), $request->metric);

            $data = [
                'rows' => $rows,
                'metric' => $request->metric,
                'title' => $this->metricTitle($request->metric, $criteria),
                'totals' => $this->summarise($rows),
                'labels' => $this->criteriaLabels($criteria),
                /* The same request with print switched on, handed to the button in the
                   modal header - so the sheet is of exactly the list on screen rather
                   than of criteria reassembled a second time. */
                'printUrl' => route('accounts-standard-report-metric', array_merge(
                    $request->except('print'),
                    ['print' => 1]
                )),
            ];

            if ($request->boolean('print')) {
                return view('backEnd.reports.accountsReportPrint', $data);
            }

            return view('backEnd.reports.partials.accountsReportList', $data)->render();
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['msg' => __('common.Operation failed')], 500);
        }
    }

    /**
     * What a metric is called, for the modal heading. A head metric is named
     * after the head itself, which is the only name that would mean anything.
     */
    private function metricTitle($metric, array $criteria)
    {
        if (in_array($metric, self::METRICS, true)) {
            return __('reports.accounts_metric_' . $metric);
        }

        [, $kind, $id] = explode('_', $metric, 3);

        /* optional(), not ?? - a head deleted since the page was rendered makes
           firstWhere() return null, and reading ->name off that is a fatal error
           the null coalesce never gets to see. */
        $heads = $criteria['lists'][$kind === 'income' ? 'incomeHeads' : 'expenseHeads'];

        $name = $id === 'none'
            ? __('reports.accounts_no_head')
            : ($this->headLabel($heads->keyBy('id'), (int) $id) ?: __('reports.accounts_no_head'));

        return __('reports.accounts_metric_' . $kind) . ' - ' . $name;
    }

    /**
     * The criteria written out in words, for the heading of a printed sheet - a
     * page of figures is not worth much without saying what period it covers.
     */
    private function criteriaLabels(array $criteria)
    {
        $labels = [
            __('reports.accounts_period') . ': '
                . date('M d, Y', strtotime($criteria['dateFrom']))
                . ' - ' . date('M d, Y', strtotime($criteria['dateTo'])),
        ];

        if ($criteria['kind']) {
            $labels[] = __('reports.accounts_entry_type') . ': ' . __('reports.accounts_metric_' . $criteria['kind']);
        }

        if ($criteria['accountId']) {
            $account = $criteria['lists']['accounts']->firstWhere('id', $criteria['accountId']);
            $labels[] = __('accounts.bank_account') . ': ' . ($account ? $account->bank_name : '-');
        }

        return array_filter($labels);
    }

    private function criteria(Request $request)
    {
        $schoolId = auth()->user()->school_id;

        $incomeHeads = SmIncomeHead::where('school_id', $schoolId)->orderBy('name')->get();
        $expenseHeads = SmExpenseHead::where('school_id', $schoolId)->orderBy('name')->get();

        /* Scopes stripped on both lookups: sm_bank_accounts.active_status defaults
           to 0 while the scope admits only 1, so an unstripped list of accounts can
           come back empty and leave the Account column blank on every row for no
           visible reason. The same scope sits on payment methods. */
        $accounts = SmBankAccount::withoutGlobalScope(ActiveStatusSchoolScope::class)
            ->where('school_id', $schoolId)->orderBy('bank_name')->get();

        $methods = SmPaymentMethhod::withoutGlobalScope(ActiveStatusSchoolScope::class)
            ->where('school_id', $schoolId)->get();

        /* The month to date is what a bookkeeper is most often looking at, so it is
           what the page opens on - and a range, not an academic year, because a set
           of books closes on a date. */
        $dateFrom = $this->parseDate($request->get('date_from')) ?: date('Y-m-01');
        $dateTo = $this->parseDate($request->get('date_to')) ?: date('Y-m-d');

        /* Reversed rather than rejected: a range typed backwards is an obvious slip,
           and answering it is kinder than an error that loses the other criteria. */
        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $kind = in_array($request->get('kind'), ['income', 'expense', 'payroll'], true)
            ? $request->get('kind')
            : null;

        return [
            'schoolId' => $schoolId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'kind' => $kind,
            'accountId' => $request->filled('account_id') ? (int) $request->account_id : null,
            'search' => trim((string) $request->get('search')),
            'lists' => compact('incomeHeads', 'expenseHeads', 'accounts', 'methods'),
        ];
    }

    /**
     * What to call an entry's head.
     *
     * Three cases, and they are not the same thing. No head id at all means the
     * entry was never filed, and the view says so. A head id that resolves names
     * the head. A head id that resolves to nothing means the head was deleted or
     * belongs to another school, and the entry is still filed - against something
     * that is gone.
     *
     * The third used to read as the first, which was a live report of this
     * database: sm_income_heads is empty while every income row carries
     * income_head_id 1, so the card said "No head assigned" over an unfiled count
     * of zero. Naming the dangling id is what makes that fixable rather than
     * merely confusing.
     */
    private function headLabel($heads, $headId)
    {
        if (!$headId) {
            return null;
        }

        $name = optional($heads->get($headId))->name;

        return $name ?: __('reports.accounts_unknown_head', ['id' => $headId]);
    }

    /** A date the pickers can hand back, or null - never a guess at what was meant. */
    private function parseDate($value)
    {
        if (!$value) {
            return null;
        }

        $time = strtotime($value);

        return $time ? date('Y-m-d', $time) : null;
    }

    /**
     * Resolve the period's entries and price every one of them.
     *
     * Income, expense and paid payroll, fetched in one query each and matched to
     * their heads, accounts and methods in memory. The lookups are read once and
     * keyed rather than eager-loaded, because the models behind them carry the
     * active-status scope and would hand back null for anything it hides.
     */
    private function classify(array $criteria)
    {
        $schoolId = $criteria['schoolId'];
        $from = $criteria['dateFrom'];
        $to = $criteria['dateTo'];

        $incomeHeads = $criteria['lists']['incomeHeads']->keyBy('id');
        $expenseHeads = $criteria['lists']['expenseHeads']->keyBy('id');
        $accounts = $criteria['lists']['accounts']->keyBy('id');
        $methods = $criteria['lists']['methods']->keyBy('id');

        $rows = collect();

        /* The active-status scope is left on income and expense: active_status 0
           there means the entry was deleted, and a deleted entry is not money that
           moved. */
        if ($criteria['kind'] === null || $criteria['kind'] === 'income') {
            $incomes = SmAddIncome::whereBetween('date', [$from, $to])
                ->when($criteria['accountId'], fn ($q) => $q->where('account_id', $criteria['accountId']))
                ->get();

            foreach ($incomes as $income) {
                $rows->push([
                    'kind' => 'income',
                    'id' => $income->id,
                    'date' => $income->date,
                    'name' => $income->name,
                    'head_id' => $income->income_head_id,
                    'head_name' => $this->headLabel($incomeHeads, $income->income_head_id),
                    'account_name' => optional($accounts->get($income->account_id))->bank_name,
                    'account_id' => $income->account_id,
                    'method' => optional($methods->get($income->payment_method_id))->method,
                    'amount' => (float) $income->amount,
                    'description' => $income->description,
                ]);
            }
        }

        if ($criteria['kind'] === null || $criteria['kind'] === 'expense') {
            $expenses = SmAddExpense::whereBetween('date', [$from, $to])
                ->when($criteria['accountId'], fn ($q) => $q->where('account_id', $criteria['accountId']))
                ->get();

            foreach ($expenses as $expense) {
                $rows->push([
                    'kind' => 'expense',
                    'id' => $expense->id,
                    'date' => $expense->date,
                    'name' => $expense->name,
                    'head_id' => $expense->expense_head_id,
                    'head_name' => $this->headLabel($expenseHeads, $expense->expense_head_id),
                    'account_name' => optional($accounts->get($expense->account_id))->bank_name,
                    'account_id' => $expense->account_id,
                    'method' => optional($methods->get($expense->payment_method_id))->method,
                    'amount' => (float) $expense->amount,
                    'description' => $expense->description,
                ]);
            }
        }

        /* Wages actually paid in the period. Only payroll_status P counts: a
           generated payslip is a liability, not a payment, and counting it would
           report cash as gone before it left.

           Scope stripped because it pins the model to the session's academic year
           while this report is asked about a date range, which may cross one. */
        if ($criteria['kind'] === null || $criteria['kind'] === 'payroll') {
            $payrolls = SmHrPayrollGenerate::withoutGlobalScope(AcademicSchoolScope::class)
                ->where('school_id', $schoolId)
                ->where('payroll_status', 'P')
                ->whereNotNull('payment_date')
                ->whereBetween('payment_date', [$from, $to])
                /* Scope stripped on the staff too: it admits only active_status 1, so
                   a wage paid to somebody who has since left would come back with no
                   name against it while its amount still counted. */
                ->with(['staffs' => fn ($q) => $q->withoutGlobalScope(ActiveStatusSchoolScope::class)])
                ->get();

            foreach ($payrolls as $payroll) {
                $rows->push([
                    'kind' => 'payroll',
                    'id' => $payroll->id,
                    'date' => $payroll->payment_date,
                    'name' => optional($payroll->staffs)->full_name
                        . ' (' . $payroll->payroll_month . ' ' . $payroll->payroll_year . ')',
                    /* Payroll answers to no income or expense head - it is its own
                       line, and giving it a borrowed head would misfile the wage bill. */
                    'head_id' => null,
                    'head_name' => __('reports.accounts_payroll_head'),
                    'account_name' => optional($accounts->get($payroll->bank_id))->bank_name,
                    'account_id' => $payroll->bank_id,
                    'method' => $payroll->payment_mode,
                    'amount' => (float) $payroll->net_salary,
                    'description' => $payroll->note,
                ]);
            }
        }

        if ($criteria['search'] !== '') {
            $needle = mb_strtolower($criteria['search']);
            $rows = $rows->filter(function ($r) use ($needle) {
                return str_contains(mb_strtolower((string) $r['name']), $needle)
                    || str_contains(mb_strtolower((string) $r['head_name']), $needle)
                    || str_contains(mb_strtolower((string) $r['description']), $needle);
            });
        }

        /* Newest first: a set of books is read from the latest entry backwards. */
        return $rows->sortByDesc('date')->values();
    }

    /**
     * One side's totals per head, largest first, with everything past the top few
     * gathered into one line.
     *
     * Entries with no head are kept as a head of their own rather than dropped -
     * unfiled money is exactly what a report like this is for finding.
     */
    private function headTotals($rows, $kind)
    {
        $grouped = $rows->where('kind', $kind)
            ->groupBy(fn ($r) => $r['head_id'] ?: 'none')
            ->map(fn ($group, $key) => [
                'metric' => 'head_' . $kind . '_' . $key,
                'name' => $key === 'none'
                    ? __('reports.accounts_no_head')
                    : ($group->first()['head_name'] ?: __('reports.accounts_no_head')),
                'amount' => round((float) $group->sum('amount'), 2),
                'entries' => $group->count(),
            ])
            ->sortByDesc('amount')
            ->values();

        if ($grouped->count() <= self::TOP_HEADS + 1) {
            return ['top' => $grouped, 'others' => null];
        }

        $top = $grouped->take(self::TOP_HEADS);
        $rest = $grouped->slice(self::TOP_HEADS);

        return [
            'top' => $top,
            /* Not clickable: "others" is not a head, and a list behind it would be a
               list of several answers to one figure. */
            'others' => [
                'name' => __('reports.accounts_other_heads', ['count' => $rest->count()]),
                'amount' => round((float) $rest->sum('amount'), 2),
                'entries' => (int) $rest->sum('entries'),
            ],
        ];
    }

    /**
     * Both halves of every figure on the page: the money, and how many entries it
     * is made of. A total on its own does not say whether it is one payment or
     * forty.
     */
    private function summarise($rows)
    {
        $byKind = fn ($kind) => round((float) $rows->where('kind', $kind)->sum('amount'), 2);

        $summary = [
            'entries' => $rows->count(),
            'count_all' => $rows->count(),
            'income' => $byKind('income'),
            'expense' => $byKind('expense'),
            'payroll' => $byKind('payroll'),
        ];

        /* Payroll is cash out alongside the expense list, not inside it - so it is
           subtracted here as well, or the net would count the wage bill as money
           still in hand. */
        $summary['net'] = round($summary['income'] - $summary['expense'] - $summary['payroll'], 2);
        $summary['out'] = round($summary['expense'] + $summary['payroll'], 2);

        $summary['count_income'] = $rows->where('kind', 'income')->count();
        $summary['count_expense'] = $rows->where('kind', 'expense')->count();
        $summary['count_payroll'] = $rows->where('kind', 'payroll')->count();
        $summary['count_net'] = $rows->count();

        $summary['income_entries'] = $summary['count_income'];
        $summary['expense_entries'] = $summary['count_expense'];
        $summary['count_income_entries'] = $summary['count_income'];
        $summary['count_expense_entries'] = $summary['count_expense'];

        /* Unfiled money. Payroll is excluded from both: it answers to no head and
           is paid from whichever account the run names, so counting it here would
           report a fault that is not one. */
        $unfiled = $rows->whereIn('kind', ['income', 'expense']);
        $summary['no_head'] = $unfiled->filter(fn ($r) => !$r['head_id'])->count();
        $summary['no_account'] = $unfiled->filter(fn ($r) => !$r['account_id'])->count();
        $summary['count_no_head'] = $summary['no_head'];
        $summary['count_no_account'] = $summary['no_account'];

        $summary['all'] = $rows->count();

        return $summary;
    }

    /** The counterpart of summarise(): one figure, the rows it was counted from. */
    private function filterByMetric($rows, $metric)
    {
        if (preg_match('/^head_(income|expense)_(\d+|none)$/', (string) $metric, $m)) {
            $kind = $m[1];
            $headId = $m[2] === 'none' ? null : (int) $m[2];

            return $rows->where('kind', $kind)
                ->filter(fn ($r) => $headId === null ? !$r['head_id'] : (int) $r['head_id'] === $headId)
                ->values();
        }

        switch ($metric) {
            case 'all':
            case 'net':
                return $rows;
            case 'income':
            case 'income_entries':
                return $rows->where('kind', 'income')->values();
            case 'expense':
            case 'expense_entries':
                return $rows->where('kind', 'expense')->values();
            case 'payroll':
                return $rows->where('kind', 'payroll')->values();
            case 'no_head':
                return $rows->whereIn('kind', ['income', 'expense'])
                    ->filter(fn ($r) => !$r['head_id'])->values();
            default:
                return $rows->whereIn('kind', ['income', 'expense'])
                    ->filter(fn ($r) => !$r['account_id'])->values();
        }
    }
}
