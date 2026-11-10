{{-- The invoices behind one figure on a card. Rendered by
     OpenInvoicesReportController@metricList from the very rows that figure was
     counted from, so the two cannot disagree. --}}
@php
    $currency = generalSetting()->currency_symbol ?: '$';
@endphp
<div class="modal-header">
    <h4 class="modal-title">
        {{ $title }}
        <span class="badge badge-info">{{ __('reports.open_counted', ['count' => $rows->count()]) }}</span>
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
        <p class="text-center text-muted mb-0">@lang('reports.open_none')</p>
    @else
        <div class="table-responsive">
            <table class="table school-table-style shadow-none p-0" cellspacing="0" width="100%">
                <thead>
                    <tr>
                        <th>@lang('common.sl')</th>
                        <th>@lang('reports.open_invoice_no')</th>
                        <th>@lang('fees::feesModule.due_date')</th>
                        <th>@lang('academics.student')</th>
                        <th class="text-right">@lang('reports.fees_metric_assessed')</th>
                        <th class="text-right">@lang('reports.fees_metric_collected')</th>
                        <th class="text-right">@lang('reports.col_balance')</th>
                        <th class="text-right">@lang('reports.col_overdue')</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $key => $row)
                        <tr>
                            <td>{{ $key + 1 }}</td>
                            <td>{{ $row['invoice']->invoice_id ?: '#' . $row['invoice']->id }}</td>
                            <td>
                                {{ $row['invoice']->due_date ? \Carbon\Carbon::parse($row['invoice']->due_date)->format('M d, Y') : '-' }}
                            </td>
                            <td>{{ optional($row['student'])->full_name ?: '-' }}</td>
                            <td class="text-right">{{ number_format($row['billed'], 2) }}</td>
                            <td class="text-right">{{ number_format($row['paid'], 2) }}</td>
                            <td class="text-right">{{ number_format($row['balance'], 2) }}</td>
                            <td class="text-right">{{ $row['overdue_days'] > 0 ? $row['overdue_days'] : '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="4" class="text-right">@lang('dashboard.total')</th>
                        <th class="text-right">{{ $currency }}{{ number_format($totals['billed'], 2) }}</th>
                        <th class="text-right">{{ $currency }}{{ number_format($totals['collected'], 2) }}</th>
                        <th class="text-right">{{ $currency }}{{ number_format($totals['outstanding'], 2) }}</th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
