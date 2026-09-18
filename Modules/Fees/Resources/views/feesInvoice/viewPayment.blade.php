<style>
    .school-table-style tr th {
        padding: 10px 18px 10px 10px !important;
    }

    .school-table-style tr td {
        padding: 20px 10px 20px 10px !important;
    }
</style>
<div class="modal-header">
    <h4 class="modal-title">@lang('fees::feesModule.view_payment_of') - ({{ $feesinvoice->owner_name ?: $feesinvoice->invoice_id }})</h4>
    <button type="button" class="close" data-dismiss="modal">&times;</button>
</div>
<div class="modal-body">
    <div class="table-responsive">
    <table class="table school-table-style shadow-none p-0" cellspacing="0" width="100%">
        <thead>
            <tr>
                <th>@lang('common.sl')</th>
                <th>@lang('common.date')</th>
                <th>@lang('fees::feesModule.payment_method')</th>
                <th>@lang('academics.payment_plan')</th>
                <th>@lang('common.status')</th>
                <th>@lang('fees::feesModule.paid_amount')</th>
                <th>@lang('fees::feesModule.waiver')</th>
                <th>@lang('fees.fine')</th>
                <th>@lang('common.action')</th>
            </tr>
        </thead>
        <tbody>

            @foreach ($feesTranscations as $key => $feesTranscation)
                @if($feesTranscation->payment_method)
                    <tr>
                        <td>{{ $key }}</td>
                        <td>{{ dateConvert($feesTranscation->created_at) }}</td>
                        <td>{{ $feesTranscation->payment_method }}</td>
                        <td>
                            @if ($paymentPlanAssign)
                                {{ optional($paymentPlanAssign->planType)->name }}
                                <br>
                                <small class="text-muted">
                                    {{ optional($paymentPlanAssign->planType)->number_of_installments }} @lang('academics.number_of_installments')
                                </small>
                            @else
                                <span class="text-muted">@lang('academics.no_payment_plan')</span>
                            @endif
                        </td>
                        <td>
                            @if ($feesinvoice->payment_status == 'paid')
                                <button class="primary-btn small bg-success text-white border-0">@lang('fees.paid')</button>
                            @elseif ($feesinvoice->payment_status == 'partial')
                                <button class="primary-btn small bg-warning text-white border-0">@lang('fees.partial')</button>
                            @else
                                <button class="primary-btn small bg-danger text-white border-0">@lang('fees.unpaid')</button>
                            @endif
                        </td>
                        <td>{{ $feesTranscation->paid_amount }}</td>
                        <td>{{ $feesTranscation->weaver }}</td>
                        <td>{{ $feesTranscation->fine }}</td>
                        <td>
                            <a class="primary-btn icon-only fix-gr-bg" type="button"
                                href="{{ route('fees.single-payment-view', ['id' => $feesTranscation->id, 'type' => 'view']) }}"
                                title="@lang('common.view')">
                                <span class="ti-eye"></span>
                            </a>
                            @if (
                                $feesTranscation->payment_method == 'Cash' ||
                                    $feesTranscation->payment_method == 'Cheque' ||
                                    $feesTranscation->payment_method == 'Bank' ||
                                    $feesTranscation->payment_method == 'Wallet')
                                <a class="primary-btn icon-only fix-gr-bg" type="button"
                                    href="{{ route('fees.delete-single-fees-transcation', $feesTranscation->id) }}"
                                    data-tooltip="tooltip" title="@lang('common.delete')">
                                    <span class="ti-trash"></span>
                                </a>
                            @endif
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
    </div>

    @if($paymentPlanAssign && $planSchedule)
    <div class="mt-30">
        <h4 class="mb-15">@lang('academics.payment_schedule') &mdash; {{ optional($paymentPlanAssign->planType)->name }}</h4>
        <table class="table border_table mb_30 description_table">
            <thead>
                <tr>
                    <th>@lang('academics.installment_no')</th>
                    <th>@lang('academics.due_date')</th>
                    <th class="text-right">@lang('accounts.amount')</th>
                    <th>@lang('student.status')</th>
                </tr>
            </thead>
            <tbody>
                @foreach($planSchedule as $installment)
                <tr @if($installment['is_next']) style="font-weight:bold;" @endif>
                    <td>
                        {{$installment['installment_no']}} @lang('academics.of') {{$paymentPlanAssign->number_of_installments}}
                        @if($installment['is_next'])
                        <span class="badge badge-warning">@lang('academics.next_payment')</span>
                        @endif
                    </td>
                    <td>{{ \Carbon\Carbon::parse($installment['due_date'])->format('M d, Y') }}</td>
                    <td class="text-right">
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
</div>
