<?php

namespace App\Http\Controllers\Admin\Report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;

/**
 * The way in to the receivables reports, which are four views of one thing.
 *
 * The standard fees report, open invoices, the receipts register and the
 * activity timeline are the same money at four grains - per student, per
 * invoice, per receipt, per event - and they reconcile with each other. Four
 * sidebar entries said they were four subjects; one entry and a strip of tabs
 * says what they are.
 *
 * This holds no report of its own. It sends the visitor to the first view they
 * are allowed, which is what makes a per-tab grant work: somebody given only
 * Open Invoices lands there, sees that tab alone, and never meets a page they
 * cannot read.
 *
 * The four keep their own routes and their own permissions. The tabs are links
 * rather than panes deliberately - each view is a heavy page with its own
 * criteria, printing and modals, and loading three of them to show one would
 * cost every visitor three times what they asked for. Switching is one click
 * either way, and the criteria travel in the query string.
 */
class ReceivablesController extends Controller
{
    public function __construct()
    {
        $this->middleware('PM');
    }

    /**
     * The tabs, in the order money moves: what is owed, what documents are open,
     * what came in, what happened. Keyed by the permission each one needs, which
     * is also its route.
     */
    public const TABS = [
        'fees-standard-report' => 'reports.tab_by_student',
        'open-invoices-report' => 'reports.tab_by_invoice',
        'cash-receipts-report' => 'reports.tab_receipts',
        'activity-audit-report' => 'reports.tab_activity',
    ];

    public function index(Request $request)
    {
        foreach (array_keys(self::TABS) as $route) {
            if (userPermission($route)) {
                /* The criteria travel, so arriving here from a bookmark carrying a
                   year or a date lands on that view already filtered. */
                return redirect()->route($route, $request->query());
            }
        }

        /* Granted the section but none of its views - worth saying plainly rather
           than redirecting into a page they would be turned away from. */
        Toastr::error(__('reports.receivables_no_tabs'), 'Failed');

        return redirect()->route('dashboard');
    }
}
