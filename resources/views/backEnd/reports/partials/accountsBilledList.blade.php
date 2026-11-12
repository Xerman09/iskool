{{-- The invoices behind the billed card.

     A different grain from the rest of this page, which lists cash movements -
     one line here is one invoice. The heading says so rather than leaving the
     reader to notice the totals do not match the entries table.

     Rendered by AccountsStandardReportController@metricList. --}}
@php
    $currency = generalSetting()->currency_symbol ?: '$';
@endphp
<div class="modal-header">
    <h4 class="modal-title">
        {{ $title }}
        <span class="badge badge-info">{{ __('reports.open_counted', ['count' => $invoices->count()]) }}</span>
    </h4>
    <div>
        @if ($invoices->count() > 0)
            <a href="{{ $printUrl }}" target="_blank" class="primary-btn small fix-gr-bg mr-15">
                <span class="ti-printer pr-2"></span>@lang('common.print')
            </a>
        @endif
        <button type="button" class="close" data-dismiss="modal">&times;</button>
    </div>
</div>
<div class="modal-body">
    <p class="text-muted" style="font-size: 12px;">@lang('reports.accounts_billed_grain')</p>

    @if ($invoices->count() == 0)
        <p class="text-center text-muted mb-0">@lang('reports.open_none')</p>
    @else
        <div class="table-responsive">
            <table class="table school-table-style shadow-none p-0" cellspacing="0" width="100%">
                <thead>
                    <tr>
                        <th>@lang('common.sl')</th>
                        <th>@lang('reports.open_invoice_no')</th>
                        <th>@lang('fees.create_date')</th>
                        <th>@lang('academics.student')</th>
                        <th>@lang('reports.col_charge_type')</th>
                        <th class="text-right">@lang('reports.fees_metric_assessed')</th>
                        <th class="text-right">@lang('reports.fees_metric_waiver')</th>
                        <th class="text-right">@lang('reports.fees_metric_collected')</th>
                        <th class="text-right">@lang('reports.col_balance')</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoices as $key => $row)
                        <tr>
                            <td>{{ $key + 1 }}</td>
                            <td>{{ $row['invoice']->invoice_id ?: '#' . $row['invoice']->id }}</td>
                            <td>
                                {{ $row['invoice']->create_date ? \Carbon\Carbon::parse($row['invoice']->create_date)->format('M d, Y') : '-' }}
                            </td>
                            <td>{{ optional($row['student'])->full_name ?: '-' }}</td>
                            <td>@lang('reports.fees_type_' . ($row['invoice']->type ?: 'fees'))</td>
                            <td class="text-right">{{ number_format($row['billed'], 2) }}</td>
                            <td class="text-right">{{ number_format($row['waived'], 2) }}</td>
                            <td class="text-right">{{ number_format($row['paid'], 2) }}</td>
                            <td class="text-right">{{ number_format($row['unpaid'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="5" class="text-right">@lang('dashboard.total')</th>
                        <th class="text-right">{{ $currency }}{{ number_format($invoices->sum('billed'), 2) }}</th>
                        <th class="text-right">{{ $currency }}{{ number_format($invoices->sum('waived'), 2) }}</th>
                        <th class="text-right">{{ $currency }}{{ number_format($invoices->sum('paid'), 2) }}</th>
                        <th class="text-right">{{ $currency }}{{ number_format($invoices->sum('unpaid'), 2) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
