<?php

namespace App\Console\Commands;

use App\SmSchool;
use App\Models\User;
use App\PaymentPlanAssign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use App\Traits\NotificationSend;
use App\Traits\EnrollmentBalanceBreakdown;

class InstallmentPaymentReminder extends Command
{
    use NotificationSend, EnrollmentBalanceBreakdown;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'payment:installment-reminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify students (dashboard + email) whose next tuition installment is due today or overdue.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        foreach (SmSchool::all() as $school) {
            $admin = User::where('role_id', 1)->where('school_id', $school->id)->first();

            if (!$admin) {
                continue;
            }

            // sent_notifications() and its send_web()/send_email()/send_app() targets
            // all read auth()->user()->school_id - there's no console-command-safe
            // variant, so impersonate the school's admin for the duration of its loop.
            Auth::loginUsingId($admin->id);

            $this->remindForSchool($school);

            Auth::logout();
        }

        return true;
    }

    private function remindForSchool(SmSchool $school)
    {
        $today = now()->toDateString();

        $assigns = PaymentPlanAssign::where('school_id', $school->id)
            ->where('context', 'tuition')
            ->where('active_status', 1)
            ->with('student', 'installments', 'invoice.invoiceDetails')
            ->get();

        foreach ($assigns as $assign) {
            $student = $assign->student;
            if (!$student || !$student->user_id) {
                continue;
            }

            $schedule = $this->installmentSchedule($assign);
            $next = $schedule->firstWhere('is_next', true);

            if (!$next || $next['status'] === 'paid' || $next['due_date'] > $today) {
                continue;
            }

            $fees = sprintf(
                'Tuition Installment #%d of %d — %s due %s',
                $next['installment_no'],
                $assign->installments->count(),
                number_format($next['amount'], 2),
                \Carbon\Carbon::parse($next['due_date'])->format('M d, Y')
            );

            $this->sent_notifications('Fees_Reminder', [$student->user_id], ['fees' => $fees], ['Student', 'Parent']);
        }
    }
}
