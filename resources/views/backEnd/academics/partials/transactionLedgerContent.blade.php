@php
    $setting = generalSetting();
@endphp
<div class="invoice_wrapper">
    <table class="table border_table mb_30 description_table">
        <tfoot>
            <tr>
                <td colspan="2"></td>
                <td>
                    <p class="total_count"><span>@lang('academics.total_billed')</span> <span>{{currency_format($totalBilled) ?: number_format($totalBilled, 2)}}</span></p>
                </td>
            </tr>
            <tr>
                <td colspan="2"></td>
                <td>
                    <p class="total_count"><span>@lang('academics.total_paid')</span> <span>&ndash; {{currency_format($totalPaid) ?: number_format($totalPaid, 2)}}</span></p>
                </td>
            </tr>
            <tr>
                <td colspan="2"></td>
                <td>
                    <p class="total_count"><span><strong>@lang('academics.total_balance')</strong></span> <span><strong>{{currency_format($totalDue) ?: number_format($totalDue, 2)}}</strong></span></p>
                </td>
            </tr>
        </tfoot>
    </table>

    @foreach($invoices->sortBy('id') as $invoice)
    @php
        $invoiceDetails = $invoice->invoiceDetails;
        $invoiceAmount = (float) $invoiceDetails->sum('amount');
        $invoicePaid = (float) $invoiceDetails->sum('paid_amount');
        $invoiceDue = (float) $invoiceDetails->sum('due_amount');

        $orderSections = collect([
            'academics.items_purchased' => $invoiceDetails->filter(fn ($d) => $d->sm_item_id),
            'academics.tuition' => $invoiceDetails->filter(fn ($d) => !$d->sm_item_id && optional($d->feesType)->name === 'Tuition'),
            'academics.miscellaneous_fees' => $invoiceDetails->filter(fn ($d) => !$d->sm_item_id && optional($d->feesType)->name !== 'Tuition'),
        ])->filter(fn ($group) => $group->count() > 0);
    @endphp
    <div class="mt-30">
        <h4 class="mb-15">@lang('academics.order') &mdash; {{$invoice->invoice_id}} <small class="text-muted">({{ $invoice->create_date ? \Carbon\Carbon::parse($invoice->create_date)->format('M d, Y') : '' }}{{ optional($invoice->semester)->semester_name ? ' — ' . $invoice->semester->semester_name : '' }})</small></h4>
        <table class="table border_table mb_30 description_table">
            <thead>
                <tr>
                    <th>@lang('common.sl')</th>
                    <th>@lang('academics.description')</th>
                    <th class="text-right-print">@lang('accounts.amount')</th>
                </tr>
            </thead>
            <tbody>
                @php $rowNo = 0; @endphp
                @foreach($orderSections as $sectionLabel => $sectionLines)
                <tr class="table-group-header">
                    <td colspan="3"><strong>@lang($sectionLabel)</strong></td>
                </tr>
                @foreach($sectionLines as $line)
                <tr>
                    <td>{{ ++$rowNo }}</td>
                    <td>{{ $line->sm_item_id ? optional($line->item)->item_name : optional($line->feesType)->name }}</td>
                    <td class="text-right-print">{{currency_format($line->amount) ?: number_format($line->amount, 2)}}</td>
                </tr>
                @endforeach
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2"></td>
                    <td><p class="total_count"><span>@lang('academics.total_amount_due')</span> <span>{{currency_format($invoiceAmount) ?: number_format($invoiceAmount, 2)}}</span></p></td>
                </tr>
                <tr>
                    <td colspan="2"></td>
                    <td><p class="total_count"><span>@lang('academics.total_paid')</span> <span>&ndash; {{currency_format($invoicePaid) ?: number_format($invoicePaid, 2)}}</span></p></td>
                </tr>
                <tr>
                    <td colspan="2"></td>
                    <td><p class="total_count"><span><strong>@lang('academics.total_balance')</strong></span> <span><strong>{{currency_format($invoiceDue) ?: number_format($invoiceDue, 2)}}</strong></span></p></td>
                </tr>
            </tfoot>
        </table>
    </div>
    @endforeach

    <h4 class="mb-15">@lang('academics.payment_history')</h4>
    <table class="table border_table mb_30 description_table">
        <thead>
            <tr>
                <th>@lang('common.sl')</th>
                <th>@lang('fees.create_date')</th>
                <th>@lang('academics.order')</th>
                <th class="text-right-print">@lang('accounts.amount')</th>
                <th>@lang('fees.payment_method')</th>
                <th>@lang('common.action')</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transactions as $i => $transaction)
            <tr>
                <td>{{$i + 1}}</td>
                <td>{{ optional($transaction->created_at)->format('M d, Y') }}</td>
                <td>{{ optional($transaction->feesInvoiceInfo)->invoice_id }}</td>
                <td class="text-right-print">{{currency_format($transaction->paid_amount) ?: number_format($transaction->paid_amount, 2)}}</td>
                <td>{{$transaction->payment_method}}</td>
                <td>
                    <a href="{{route('fees.single-payment-view', ['id' => $transaction->id, 'type' => 'view'])}}" target="_blank" class="primary-btn small fix-gr-bg">
                        @lang('common.view')
                    </a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="text-center">@lang('academics.no_transactions_yet')</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
