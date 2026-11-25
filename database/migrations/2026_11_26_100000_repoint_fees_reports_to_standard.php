<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Point the five fees reports at pages built on classify().
     *
     * Fees Due, Balance, Fine, Payment and Waiver were five reports with five
     * queries of their own, each a flat per-student list with one figure on it.
     * They are kept - a cashier chasing one question should not have to read the
     * whole school first - but they now read from the same arithmetic the
     * Standard Fees Report uses, as pages of it rather than as rivals to it.
     *
     * The reason this matters is not tidiness. The old Balance report computed
     * sub_total - paid + fine and never subtracted the waiver, so a learner given
     * a waiver was reported as still owing it. Two queries for one number is two
     * numbers, and this is what that costs.
     *
     * What each one now asks:
     *   Fees Due  - owed AND already past its date, longest overdue first
     *   Balance   - everything owed, late or not
     *   Fine      - fines charged
     *   Payment   - collected
     *   Waiver    - waived
     *
     * Fees Due and Balance are no longer the same list. Before this they were:
     * both read every unpaid invoice, and only the column shown differed.
     *
     * Nothing is deleted. The module's own routes and controllers stay exactly
     * where they are; only what the menu entry points at changes, and down()
     * puts it back.
     */
    private const MOVES = [
        /* The names the school already knows these by, kept. The metric labels
           ("Outstanding", "Collected") are a card's wording, where the heading
           above says what is being counted; a menu entry has to say so itself. */
        'fees.due-fees' => ['route' => 'fees-due-report', 'name' => 'Fees Due Report', 'lang' => 'reports.fees_due_report'],
        'fees.balance-report' => ['route' => 'fees-balance-report', 'name' => 'Balance Report', 'lang' => 'reports.fees_balance_report'],
        'fees.fine-report' => ['route' => 'fees-fine-report', 'name' => 'Fine Report', 'lang' => 'reports.fees_fine_report'],
        'fees.payment-report' => ['route' => 'fees-payment-report', 'name' => 'Payment Report', 'lang' => 'reports.fees_payment_report'],
        'fees.waiver-report' => ['route' => 'fees-waiver-report', 'name' => 'Waiver Report', 'lang' => 'reports.fees_waiver_report'],
    ];

    public function up(): void
    {
        foreach (self::MOVES as $oldRoute => $new) {
            $row = DB::table('permissions')->where('route', $oldRoute)->first();

            if (!$row) {
                continue;
            }

            /* The permission row keeps its id, so every grant already handed out
               in Role & Permission follows it across. Only where it leads
               changes - which is why this is an update and not a delete and
               insert. */
            DB::table('permissions')->where('id', $row->id)->update([
                'route' => $new['route'],
                'name' => $new['name'],
                'lang_name' => $new['lang'],
                /* These pages are the report's own, not the Fees module's. Left
                   under the module, access() in Basic.php would gate them on
                   fees_status along with the module's screens. */
                'module' => null,
                'updated_at' => now(),
            ]);
        }

        /* The sidebar is a cached build of the rows above, so it has to be dropped
           for the new destinations to take. */
        DB::table('sidebars')->truncate();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::MOVES as $oldRoute => $new) {
            DB::table('permissions')->where('route', $new['route'])->update([
                'route' => $oldRoute,
                'module' => 'Fees',
                'updated_at' => now(),
            ]);
        }

        DB::table('sidebars')->truncate();
    }
};
