<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@lang('reports.fees_statement_title')</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #222; margin: 0; }
        .sheet { padding: 20px 26px; }

        /* One student per sheet. A statement is handed to a person, so two of them
           sharing a page is not a saving. The last one takes no break, or every run
           ends on a blank page. */
        .statement + .statement { page-break-before: always; }

        .letterhead { border-bottom: 2px solid #333; padding-bottom: 8px; margin-bottom: 14px; }
        .letterhead h2 { margin: 0 0 2px; font-size: 19px; }
        .letterhead .addr { font-size: 11px; color: #555; }
        .letterhead h3 { margin: 8px 0 0; font-size: 14px; font-weight: 700; letter-spacing: .4px; }

        .who { width: 100%; border-collapse: collapse; margin-bottom: 14px; font-size: 12px; }
        .who td { padding: 2px 0; vertical-align: top; }
        .who .k { color: #555; width: 110px; }
        .who .right { text-align: right; }

        h4.section { margin: 16px 0 6px; font-size: 13px; }

        table.lines { width: 100%; border-collapse: collapse; font-size: 11.5px; }
        table.lines th, table.lines td {
            border: 1px solid #c9c9c9; padding: 5px 7px; text-align: left; vertical-align: top;
        }
        table.lines thead th { background: #f1f1f1; font-weight: 700; }
        table.lines .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        table.lines thead { display: table-header-group; }
        table.lines tr { page-break-inside: avoid; }
        /* The invoice a group of lines belongs to, so a statement covering several
           charges does not read as one undifferentiated bill. */
        table.lines tr.group td { background: #fafafa; font-weight: 700; }
        table.lines tfoot td { background: #f7f7f7; font-weight: 700; }

        .summary { margin-top: 16px; width: 320px; margin-left: auto; border-collapse: collapse; font-size: 12px; }
        .summary td { padding: 4px 8px; border-bottom: 1px solid #e3e3e3; }
        .summary td.num { text-align: right; font-variant-numeric: tabular-nums; }
        .summary tr.total td { border-top: 2px solid #333; border-bottom: 2px solid #333; font-size: 13px; font-weight: 700; }
        .summary tr.owing td { color: #b03024; }
        .summary tr.credit td { color: #15794f; }

        .note { margin-top: 12px; font-size: 11px; color: #555; }
        /* Said on the statement rather than left for the reader to work out, because
           wallet money looks like payment and is not. */
        .note strong { color: #222; }
        .empty { text-align: center; color: #777; padding: 18px 0; font-size: 12px; }
        .foot { margin-top: 22px; font-size: 10.5px; color: #777; border-top: 1px solid #ddd; padding-top: 6px; }

        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .sheet { padding: 0; }
        }
    </style>
</head>
<body>
@php
    $setting = generalSetting();
    $money = fn ($v) => currency_format($v) ?: number_format((float) $v, 2);
@endphp
<div class="sheet">
    @foreach ($statements as $s)
        @php $student = $s['student']; @endphp
        <div class="statement">
            <div class="letterhead">
                <h2>{{ $setting->school_name }}</h2>
                @if ($setting->address)
                    <div class="addr">{{ $setting->address }}</div>
                @endif
                <h3>@lang('reports.fees_statement_title')</h3>
            </div>

            <table class="who">
                <tr>
                    <td class="k">@lang('academics.student'):</td>
                    <td><strong>{{ $student->full_name }}</strong></td>
                    <td class="k">@lang('student.admission_no'):</td>
                    <td class="right">{{ $student->admission_no ?: '-' }}</td>
                </tr>
                <tr>
                    @if (!isBasicEd())
                        <td class="k">@lang('academics.program'):</td>
                        <td>{{ optional($student->course)->course_name ?: '-' }}</td>
                    @else
                        <td class="k">@lang('academics.year_level'):</td>
                        <td>{{ optional($student->class)->class_name ?: '-' }}</td>
                    @endif
                    <td class="k">@lang('common.date'):</td>
                    <td class="right">{{ date('M d, Y') }}</td>
                </tr>
            </table>

            {{-- The criteria this statement covers, so a balance of zero cannot be
                 mistaken for "owes nothing at all" when it only means "owes nothing
                 this term". --}}
            <div class="note" style="margin-top:0; margin-bottom:10px;">
                @foreach ($labels as $label)
                    {{ $label }}@if (!$loop->last) &nbsp;&middot;&nbsp; @endif
                @endforeach
            </div>

            <h4 class="section">@lang('reports.fees_statement_charges')</h4>
            @if ($s['breakdown']->isEmpty())
                <p class="empty">@lang('reports.fees_statement_no_charges')</p>
            @else
                <table class="lines">
                    <thead>
                        <tr>
                            <th>@lang('common.sl')</th>
                            <th>@lang('academics.description')</th>
                            <th class="num">@lang('reports.fees_metric_assessed')</th>
                            <th class="num">@lang('reports.fees_metric_waiver')</th>
                            <th class="num">@lang('reports.fees_metric_fine')</th>
                            <th class="num">@lang('reports.fees_metric_collected')</th>
                            <th class="num">@lang('reports.col_balance')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($s['breakdown'] as $group)
                            @php $invoice = $group['invoice']; @endphp
                            <tr class="group">
                                <td colspan="7">
                                    {{ $invoice->invoice_id ?: '#' . $invoice->id }}
                                    @if ($invoice->create_date)
                                        &mdash; {{ \Carbon\Carbon::parse($invoice->create_date)->format('M d, Y') }}
                                    @endif
                                    @if ($invoice->due_date)
                                        &nbsp;&middot;&nbsp; @lang('fees::feesModule.due_date'):
                                        {{ \Carbon\Carbon::parse($invoice->due_date)->format('M d, Y') }}
                                    @endif
                                </td>
                            </tr>
                            @forelse ($group['lines'] as $i => $line)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $line->sm_item_id ? optional($line->item)->item_name : optional($line->feesType)->name }}</td>
                                    <td class="num">{{ $money($line->amount) }}</td>
                                    <td class="num">{{ $money($line->weaver) }}</td>
                                    <td class="num">{{ $money($line->fine) }}</td>
                                    <td class="num">{{ $money($line->paid_amount) }}</td>
                                    <td class="num">
                                        {{ $money(((float) $line->amount + (float) $line->fine) - ((float) $line->paid_amount + (float) $line->weaver)) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="empty">@lang('reports.fees_statement_no_lines')</td>
                                </tr>
                            @endforelse
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" class="num">@lang('dashboard.total')</td>
                            <td class="num">{{ $money($s['assessed']) }}</td>
                            <td class="num">{{ $money($s['waiver']) }}</td>
                            <td class="num">{{ $money($s['fine']) }}</td>
                            <td class="num">{{ $money($s['paid']) }}</td>
                            <td class="num">{{ $money($s['balance']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            @endif

            @if ($s['schedules']->isNotEmpty())
                <h4 class="section">@lang('reports.fees_statement_schedule')</h4>
                @foreach ($s['schedules'] as $plan)
                    <table class="lines" style="margin-bottom:10px;">
                        <thead>
                            <tr>
                                <th colspan="5" style="background:#fafafa;">
                                    {{ optional($plan['assign']->planType)->name ?: __('reports.fees_statement_plan') }}
                                    @if (optional($plan['invoice'])->invoice_id)
                                        &mdash; {{ $plan['invoice']->invoice_id }}
                                    @endif
                                </th>
                            </tr>
                            <tr>
                                <th>@lang('reports.fees_installment_no')</th>
                                <th>@lang('fees::feesModule.due_date')</th>
                                <th class="num">@lang('accounts.amount')</th>
                                <th class="num">@lang('reports.fees_installment_remaining')</th>
                                <th>@lang('reports.col_status')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($plan['schedule'] as $installment)
                                <tr>
                                    <td>{{ $installment['no'] }}</td>
                                    <td>
                                        {{ $installment['due_date'] ? \Carbon\Carbon::parse($installment['due_date'])->format('M d, Y') : '-' }}
                                    </td>
                                    <td class="num">{{ $money($installment['amount']) }}</td>
                                    <td class="num">{{ $installment['remaining'] > 0 ? $money($installment['remaining']) : '-' }}</td>
                                    <td>
                                        @lang('reports.fees_installment_' . $installment['status'])
                                        @if ($installment['is_next'])
                                            &nbsp;&larr; @lang('reports.fees_installment_next')
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endforeach
                {{-- Said outright, because a schedule beside a payment list reads as
                     though the two were matched up, and nothing in the data matches
                     them: payment_plan_installments holds a number, a date and an
                     amount, and no payment ever points at one. --}}
                <p class="note">@lang('reports.fees_statement_schedule_note')</p>
            @endif

            <h4 class="section">@lang('academics.payment_history')</h4>
            @if ($s['payments']->isEmpty())
                <p class="empty">@lang('academics.no_transactions_yet')</p>
            @else
                <table class="lines">
                    <thead>
                        <tr>
                            <th>@lang('common.sl')</th>
                            <th>@lang('fees.create_date')</th>
                            <th>@lang('academics.order')</th>
                            <th>@lang('fees.payment_method')</th>
                            <th class="num">@lang('accounts.amount')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($s['payments'] as $i => $payment)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $payment->created_at ? $payment->created_at->format('M d, Y') : '-' }}</td>
                                <td>{{ $payment->invoice_number ?: optional($payment->feesInvoiceInfo)->invoice_id ?: '-' }}</td>
                                <td>{{ $payment->payment_method ?: '-' }}</td>
                                {{-- The loaded lines, not the paid_amount accessor: that one
                                     re-queries the relation on every read, which on a bulk run
                                     is a SUM per payment per student. ledgerFor() has already
                                     fetched them. --}}
                                <td class="num">{{ $money($payment->transcationDetails->sum('paid_amount')) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="num">@lang('dashboard.total')</td>
                            <td class="num">{{ $money($s['paid']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            @endif

            <table class="summary">
                <tr>
                    <td>@lang('reports.fees_metric_assessed')</td>
                    <td class="num">{{ $money($s['assessed']) }}</td>
                </tr>
                @if ($s['fine'] > 0)
                    <tr>
                        <td>@lang('reports.fees_metric_fine')</td>
                        <td class="num">{{ $money($s['fine']) }}</td>
                    </tr>
                @endif
                @if ($s['waiver'] > 0)
                    <tr>
                        <td>@lang('reports.fees_metric_waiver')</td>
                        <td class="num">&ndash; {{ $money($s['waiver']) }}</td>
                    </tr>
                @endif
                <tr>
                    <td>@lang('reports.fees_metric_net')</td>
                    <td class="num">{{ $money($s['net']) }}</td>
                </tr>
                <tr>
                    <td>@lang('reports.fees_metric_collected')</td>
                    <td class="num">&ndash; {{ $money($s['paid']) }}</td>
                </tr>
                <tr class="total {{ $s['balance'] > 0 ? 'owing' : ($s['balance'] < 0 ? 'credit' : '') }}">
                    <td>
                        @if ($s['balance'] < 0)
                            @lang('reports.fees_statement_credit')
                        @else
                            @lang('reports.col_balance')
                        @endif
                    </td>
                    <td class="num">{{ $money(abs($s['balance'])) }}</td>
                </tr>
                @if ($s['overdue_days'] > 0)
                    <tr class="owing">
                        <td>@lang('reports.col_overdue')</td>
                        <td class="num">{{ $s['overdue_days'] }} ({{ __('reports.fees_metric_' . $s['aging']) }})</td>
                    </tr>
                @endif
            </table>

            @if ($s['prior_balance'] !== null && $s['prior_balance'] > 0)
                <p class="note">
                    <strong>@lang('reports.fees_statement_prior', ['amount' => $money($s['prior_balance'])])</strong>
                </p>
            @endif

            @if ($s['wallet'] > 0)
                <p class="note">
                    @lang('reports.fees_statement_wallet', ['amount' => $money($s['wallet'])])
                </p>
            @endif

            <div class="foot">
                @lang('reports.fees_statement_footer', ['datetime' => date('M d, Y h:i A')])
            </div>
        </div>
    @endforeach
</div>
<script>
    window.print();
</script>
</body>
</html>
