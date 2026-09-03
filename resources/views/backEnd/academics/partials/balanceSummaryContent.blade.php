@php
    $setting = generalSetting();
@endphp
<div class="invoice_wrapper">
    <div class="invoice_print">
        <div class="invoice_part_iner">
            <table class="table">
                <thead>
                    <tr>
                        <td>
                            @if(!empty(optional($setting)->letterhead_logo) || optional($setting)->logo)
                            <div class="logo_img">
                                <img src="{{asset(optional($setting)->letterhead_logo ?: $setting->logo)}}" alt="{{optional($setting)->school_name}}">
                            </div>
                            @endif
                        </td>
                        <td class="virtical_middle address_text">
                            <p>{{optional($setting)->school_name}}</p>
                            <p>{{optional($setting)->phone}}</p>
                            <p>{{optional($setting)->email}}</p>
                            <p>{{optional($setting)->address}}</p>
                        </td>
                    </tr>
                </thead>
            </table>

            <table class="table">
                <tbody>
                    <tr>
                        <td>
                            <table class="mb_30">
                                <tbody>
                                    <tr>
                                        <td>
                                            <div class="addressleft_text">
                                                <p>@lang('fees.fees_invoice_issued_to')</p>
                                                <p><span><strong>@lang('student.student_name')</strong></span> <span class="nowrap">: {{$student->first_name}} {{$student->last_name}}</span></p>
                                                <p><span>@lang('academics.program')</span> <span>: {{optional($course)->course_name}}</span></p>
                                                <p><span>@lang('student.admission_no')</span> <span>: {{$student->admission_no}}</span></p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                        <td>
                            <table class="mb_30 margin_auto">
                                <tbody>
                                    <tr>
                                        <td>
                                            <div class="addressright_text">
                                                <p><span><strong>@lang('academics.balance_summary')</strong></span></p>
                                                <p><span>@lang('fees.create_date')</span> <span>: {{now()->format('M d, Y')}}</span></p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <table class="table border_table mb_30 description_table">
        <thead>
            <tr>
                <th>@lang('common.sl')</th>
                <th>@lang('academics.description')</th>
                <th class="text-right-print">@lang('accounts.amount')</th>
            </tr>
        </thead>
        <tbody>
            @php
                $tuitionLines = $lines->filter(fn ($l) => str_starts_with($l['label'], 'Tuition'));
                $miscLines = $lines->filter(fn ($l) => !str_starts_with($l['label'], 'Tuition'));
                $groupedLines = collect([
                    'academics.tuition' => $tuitionLines,
                    'academics.miscellaneous_fees' => $miscLines,
                ])->filter(fn ($group) => $group->count() > 0);
                $rowNo = 0;
            @endphp
            @foreach($groupedLines as $sectionLabel => $sectionLines)
            <tr class="table-group-header">
                <td colspan="3"><strong>@lang($sectionLabel)</strong></td>
            </tr>
            @foreach($sectionLines as $line)
            <tr>
                <td>{{ ++$rowNo }}</td>
                <td>{{$line['label']}}</td>
                <td class="text-right-print">{{currency_format($line['amount']) ?: number_format($line['amount'], 2)}}</td>
            </tr>
            @endforeach
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2"></td>
                <td>
                    <p class="total_count"><span>@lang('academics.total_amount_due')</span> <span>{{currency_format($totalAmount) ?: number_format($totalAmount, 2)}}</span></p>
                </td>
            </tr>
            <tr>
                <td colspan="2"></td>
                <td>
                    <p class="total_count"><span>@lang('academics.down_payment_required')</span> <span>{{currency_format($downPayment) ?: number_format($downPayment, 2)}}</span></p>
                </td>
            </tr>
            <tr>
                <td colspan="2"></td>
                <td>
                    <p class="total_count"><span>@lang('academics.total_paid')</span> <span>&ndash; {{currency_format($amountPaid) ?: number_format($amountPaid, 2)}}</span></p>
                </td>
            </tr>
            <tr>
                <td colspan="2"></td>
                <td>
                    <p class="total_count">
                        <span><strong>@lang('academics.remaining_balance')</strong></span>
                        <span><strong>{{currency_format($remainingBalance) ?: number_format($remainingBalance, 2)}}</strong></span>
                    </p>
                    @if($paymentPlan)
                    <p class="text-muted">
                        {{ __('academics.on_payment_plan', ['plan' => $paymentPlan['name'], 'paid' => $paymentPlan['paid'], 'total' => $paymentPlan['total']]) }}
                    </p>
                    @endif
                </td>
            </tr>
        </tfoot>
    </table>

    @if(isset($itemLines) && $itemLines->count())
    <div class="mt-30">
        <h4 class="mb-15">@lang('academics.items_purchased')</h4>
        <table class="table border_table mb_30 description_table">
            <thead>
                <tr>
                    <th>@lang('common.sl')</th>
                    <th>@lang('academics.description')</th>
                    <th>@lang('inventory.quantity')</th>
                    <th class="text-right-print">@lang('accounts.amount')</th>
                </tr>
            </thead>
            <tbody>
                @foreach($itemLines as $i => $itemLine)
                <tr>
                    <td>{{$i + 1}}</td>
                    <td>{{ optional($itemLine->item)->item_name ?? $itemLine->note }}</td>
                    <td>{{$itemLine->quantity}}</td>
                    <td class="text-right-print">{{currency_format($itemLine->amount) ?: number_format($itemLine->amount, 2)}}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3"></td>
                    <td>
                        <p class="total_count"><span>@lang('academics.items_total')</span> <span>{{currency_format($itemsTotal) ?: number_format($itemsTotal, 2)}}</span></p>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
    @endif

    @if(isset($dueNow) && $dueNow > 0)
    <div class="mt-30">
        <p class="total_count"><span><strong>@lang('academics.due_now')</strong></span> <span><strong>{{currency_format($dueNow) ?: number_format($dueNow, 2)}}</strong></span></p>
        <p class="text-muted">@lang('academics.due_now_hint')</p>
    </div>
    @endif

    @if($paymentPlan)
    <div class="mt-30">
        <h4 class="mb-15">@lang('academics.payment_schedule') &mdash; {{$paymentPlan['name']}}</h4>
        <table class="table border_table mb_30 description_table">
            <thead>
                <tr>
                    <th>@lang('academics.installment_no')</th>
                    <th>@lang('academics.due_date')</th>
                    <th class="text-right-print">@lang('accounts.amount')</th>
                    <th>@lang('student.status')</th>
                </tr>
            </thead>
            <tbody>
                @foreach($paymentPlan['schedule'] as $installment)
                <tr @if($installment['is_next']) style="font-weight:bold;" @endif>
                    <td>
                        {{$installment['installment_no']}} @lang('academics.of') {{$paymentPlan['total']}}
                        @if($installment['is_next'])
                        <span class="badge badge-warning">@lang('academics.next_payment')</span>
                        @endif
                    </td>
                    <td>{{ \Carbon\Carbon::parse($installment['due_date'])->format('M d, Y') }}</td>
                    <td class="text-right-print">
                        {{currency_format($installment['amount']) ?: number_format($installment['amount'], 2)}}
                        @if($installment['status'] == 'partial')
                            <br><small class="text-muted">{{ __('academics.installment_remaining_note', ['amount' => currency_format($installment['remaining']) ?: number_format($installment['remaining'], 2)]) }}</small>
                        @endif
                    </td>
                    <td>
                        @if($installment['status'] == 'paid')
                            <span class="badge badge-success">@lang('academics.paid_status')</span>
                        @elseif($installment['status'] == 'partial')
                            <span class="badge badge-warning">@lang('academics.partial_status')</span>
                        @else
                            <span class="badge badge-secondary">@lang('academics.unpaid_status')</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <p class="text-muted">@lang('academics.balance_summary_hint')</p>
</div>
