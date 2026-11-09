{{-- The receipts behind one figure on a card. Rendered by
     CashReceiptsReportController@metricList from the very rows that figure was
     counted from, so the two cannot disagree. --}}
@php
    $currency = generalSetting()->currency_symbol ?: '$';
@endphp
<div class="modal-header">
    <h4 class="modal-title">
        {{ $title }}
        <span class="badge badge-info">{{ __('reports.receipts_counted', ['count' => $rows->count()]) }}</span>
    </h4>
    <div>
        @if ($rows->count() > 0)
            <a href="{{ $printUrl }}" target="_blank" class="primary-btn small fix-gr-bg mr-15">
                <span class="ti-printer pr-2"></span>@lang('common.print')
            </a>
        @endif
        <button type="button" class="close" data-dismiss="modal">&times;</button>
    </div>
</div>
<div class="modal-body">
    @if ($rows->count() == 0)
        <p class="text-center text-muted mb-0">@lang('reports.receipts_none')</p>
    @else
        <div class="table-responsive">
            <table class="table school-table-style shadow-none p-0" cellspacing="0" width="100%">
                <thead>
                    <tr>
                        <th>@lang('common.sl')</th>
                        <th>@lang('common.date')</th>
                        <th>@lang('academics.student')</th>
                        <th>@lang('reports.receipts_applied_to')</th>
                        <th>@lang('fees.payment_method')</th>
                        <th class="text-right">@lang('accounts.amount')</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $key => $row)
                        <tr>
                            <td>{{ $key + 1 }}</td>
                            <td>{{ $row['date'] ? $row['date']->format('M d, Y') : '-' }}</td>
                            <td>{{ optional($row['student'])->full_name ?: '-' }}</td>
                            <td>
                                @if ($row['unapplied'])
                                    {{-- Named, not blank: blank reads as a rendering slip, and this
                                         is money on the books applied to nothing. --}}
                                    <span class="badge badge-danger">@lang('reports.receipts_metric_unapplied')</span>
                                @else
                                    {{ $row['invoice']->invoice_id ?: '#' . $row['invoice']->id }}
                                @endif
                            </td>
                            <td>{{ $row['method'] }}</td>
                            <td class="text-right">{{ number_format($row['amount'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="5" class="text-right">@lang('dashboard.total')</th>
                        <th class="text-right">{{ $currency }}{{ number_format($totals['received'], 2) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
