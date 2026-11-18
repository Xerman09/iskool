{{-- The rows behind one figure on the student account page.

     Included by both the modal fragment and the printed sheet, so the two can
     never drift apart - a column added here reaches the paper as well as the
     screen. The caller passes $tableClass because the modal and the sheet are
     styled differently; everything else about the table is the same.

     $part    which figure: paid | unpaid | incoming
     $s       the statement the page was drawn from
     $next    the next instalment across all plans, or null
--}}
@php
    $currency = generalSetting()->currency_symbol ?: '$';
    $tableClass = $tableClass ?? 'table school-table-style shadow-none p-0';
    $emptyClass = $emptyClass ?? 'text-center text-muted mb-0';
@endphp

@if ($part === 'paid')
    @php
        /* One row per thing paid for, not per receipt: the question the Paid
           figure raises is what the money went to, and a receipt that settled
           three heads answers it only once broken out. */
        $lines = collect();
        foreach ($s['payments'] as $payment) {
            foreach ($payment->transcationDetails as $line) {
                $lines->push(['payment' => $payment, 'line' => $line]);
            }
        }
    @endphp

    @if ($lines->isEmpty())
        <p class="{{ $emptyClass }}">@lang('academics.no_transactions_yet')</p>
    @else
        <table class="{{ $tableClass }}" cellspacing="0" width="100%">
            <thead>
                <tr>
                    <th>@lang('common.sl')</th>
                    <th>@lang('fees.create_date')</th>
                    <th>@lang('fees.invoice')</th>
                    <th>@lang('reports.pay_what_for')</th>
                    <th>@lang('fees.payment_method')</th>
                    <th>@lang('fees.receipt')</th>
                    <th class="num text-right">@lang('accounts.amount')</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lines as $key => $row)
                    @php
                        $payment = $row['payment'];
                        $line = $row['line'];
                    @endphp
                    <tr>
                        <td>{{ $key + 1 }}</td>
                        <td>{{ $payment->created_at ? $payment->created_at->format('M d, Y') : '-' }}</td>
                        <td>{{ optional($payment->feesInvoiceInfo)->invoice_id ?: '-' }}</td>
                        <td>
                            {{ optional($line->transcationFeesType)->name ?: ($line->note ?: __('reports.pay_unnamed')) }}
                        </td>
                        <td>{{ $payment->payment_method ?: '-' }}</td>
                        <td>{{ $payment->invoice_number ?: '-' }}</td>
                        <td class="num text-right">{{ number_format((float) $line->paid_amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="6" class="text-right">@lang('dashboard.total')</th>
                    <th class="num text-right">{{ $currency }}{{ number_format($s['paid'], 2) }}</th>
                </tr>
            </tfoot>
        </table>
    @endif

@elseif ($part === 'unpaid')
    @php
        /* Only the lines with something left on them. A settled line in a list
           headed by the amount owed is a line the reader has to subtract by eye. */
        $rows = collect();
        foreach ($s['breakdown'] as $g) {
            foreach ($g['lines'] as $line) {
                $balance = ((float) $line->amount + (float) $line->fine)
                    - ((float) $line->paid_amount + (float) $line->weaver);
                if (round($balance, 2) > 0) {
                    $rows->push(['invoice' => $g['invoice'], 'line' => $line, 'balance' => $balance]);
                }
            }
        }
    @endphp

    @if ($rows->isEmpty())
        <p class="{{ $emptyClass }}">@lang('reports.account_settled')</p>
    @else
        <table class="{{ $tableClass }}" cellspacing="0" width="100%">
            <thead>
                <tr>
                    <th>@lang('common.sl')</th>
                    <th>@lang('fees.invoice')</th>
                    <th>@lang('fees::feesModule.due_date')</th>
                    <th>@lang('academics.description')</th>
                    <th class="num text-right">@lang('reports.fees_metric_assessed')</th>
                    <th class="num text-right">@lang('reports.fees_metric_collected')</th>
                    <th class="num text-right">@lang('reports.col_balance')</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $key => $row)
                    @php
                        $line = $row['line'];
                        $due = $row['invoice']->due_date;
                        $late = $due && \Carbon\Carbon::parse($due)->startOfDay()->isPast();
                    @endphp
                    <tr>
                        <td>{{ $key + 1 }}</td>
                        <td>{{ $row['invoice']->invoice_id ?: '#' . $row['invoice']->id }}</td>
                        <td>
                            {{ $due ? \Carbon\Carbon::parse($due)->format('M d, Y') : '-' }}
                            @if ($late)
                                <span class="d-block" style="font-size: 11px; color: #b03024;">@lang('reports.col_overdue')</span>
                            @endif
                        </td>
                        <td>
                            {{ $line->sm_item_id ? optional($line->item)->item_name : optional($line->feesType)->name }}
                        </td>
                        <td class="num text-right">{{ number_format((float) $line->amount, 2) }}</td>
                        <td class="num text-right">{{ number_format((float) $line->paid_amount, 2) }}</td>
                        <td class="num text-right">{{ number_format($row['balance'], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="6" class="text-right">@lang('dashboard.total')</th>
                    <th class="num text-right">{{ $currency }}{{ number_format($s['balance'], 2) }}</th>
                </tr>
            </tfoot>
        </table>
    @endif

@else
    @php
        /* Every instalment still carrying a balance, across every plan on the
           account, in the order they fall due - which is the order they will be
           asked for. */
        $rows = collect();
        foreach ($s['schedules'] as $plan) {
            foreach ($plan['schedule'] as $i) {
                if (round((float) $i['remaining'], 2) > 0) {
                    $rows->push(['plan' => $plan, 'i' => $i]);
                }
            }
        }
        $rows = $rows->sortBy(fn ($r) => $r['i']['due_date'] ?: '9999-12-31')->values();
    @endphp

    @if ($rows->isEmpty())
        <p class="{{ $emptyClass }}">@lang('reports.account_nothing_due')</p>
    @else
        <table class="{{ $tableClass }}" cellspacing="0" width="100%">
            <thead>
                <tr>
                    <th>@lang('common.sl')</th>
                    <th>@lang('reports.fees_statement_plan')</th>
                    <th>@lang('fees.invoice')</th>
                    <th>@lang('reports.fees_installment_no')</th>
                    <th>@lang('fees::feesModule.due_date')</th>
                    <th class="num text-right">@lang('accounts.amount')</th>
                    <th class="num text-right">@lang('reports.fees_installment_remaining')</th>
                    <th>@lang('reports.col_status')</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $key => $row)
                    @php
                        $i = $row['i'];
                        $late = $i['due_date'] && \Carbon\Carbon::parse($i['due_date'])->startOfDay()->isPast();
                    @endphp
                    <tr>
                        <td>{{ $key + 1 }}</td>
                        <td>{{ optional($row['plan']['assign']->planType)->name ?: __('reports.fees_statement_plan') }}</td>
                        <td>{{ optional($row['plan']['invoice'])->invoice_id ?: '-' }}</td>
                        <td>{{ $i['no'] }}</td>
                        <td>{{ $i['due_date'] ? \Carbon\Carbon::parse($i['due_date'])->format('M d, Y') : '-' }}</td>
                        <td class="num text-right">{{ number_format((float) $i['amount'], 2) }}</td>
                        <td class="num text-right">{{ number_format((float) $i['remaining'], 2) }}</td>
                        <td @if ($late) style="color: #b03024;" @endif>
                            @lang('reports.fees_installment_' . $i['status'])
                            @if ($i['is_next'])
                                &nbsp;&larr; @lang('reports.fees_installment_next')
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="6" class="text-right">@lang('dashboard.total')</th>
                    <th class="num text-right">
                        {{ $currency }}{{ number_format($rows->sum(fn ($r) => (float) $r['i']['remaining']), 2) }}
                    </th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
        {{-- The card shows one figure, the nearest one; the list is everything
             still to come, so the two totals differ on purpose. --}}
        <p class="note mt-15 mb-0">@lang('reports.account_incoming_note')</p>
    @endif
@endif
