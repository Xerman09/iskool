{{-- The events behind one figure on a card. Rendered by
     ActivityAuditReportController@metricList from the very rows that figure was
     counted from, so the two cannot disagree. --}}
@php
    $currency = generalSetting()->currency_symbol ?: '$';
    $tone = ['invoice_raised' => 'info', 'line_added' => 'secondary', 'payment' => 'success',
             'income' => 'success', 'expense' => 'danger', 'payroll' => 'warning'];
@endphp
<div class="modal-header">
    <h4 class="modal-title">
        {{ $title }}
        <span class="badge badge-info">{{ __('reports.activity_counted', ['count' => $rows->count()]) }}</span>
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
        <p class="text-center text-muted mb-0">@lang('reports.activity_none')</p>
    @else
        <div class="table-responsive">
            <table class="table school-table-style shadow-none p-0" cellspacing="0" width="100%">
                <thead>
                    <tr>
                        <th>@lang('common.date')</th>
                        <th>@lang('reports.activity_kind')</th>
                        <th>@lang('reports.open_invoice_no')</th>
                        <th>@lang('common.name')</th>
                        <th>@lang('reports.accounts_head')</th>
                        <th class="text-right">@lang('accounts.amount')</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $row['at'] ? $row['at']->format('M d, Y H:i') : '-' }}</td>
                            <td>
                                <span class="badge badge-{{ $tone[$row['kind']] ?? 'secondary' }}">
                                    @lang('reports.activity_metric_' . $row['kind'])
                                </span>
                                @if (!empty($row['duplicate_of_receipt']))
                                    {{-- On the page but out of every total; see the controller. --}}
                                    <span class="badge badge-light">@lang('reports.activity_posting')</span>
                                @endif
                            </td>
                            <td>{{ $row['reference'] ?: '-' }}</td>
                            <td>{{ $row['who'] ?: '-' }}</td>
                            <td>{{ $row['detail'] ?: '-' }}</td>
                            <td class="text-right">
                                {{ $row['direction'] === 'out' ? '-' : '' }}{{ number_format($row['amount'], 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="5" class="text-right">@lang('reports.activity_metric_money_in')</th>
                        <th class="text-right">{{ $currency }}{{ number_format($totals['money_in'], 2) }}</th>
                    </tr>
                    <tr>
                        <th colspan="5" class="text-right">@lang('reports.activity_metric_money_out')</th>
                        <th class="text-right">{{ $currency }}{{ number_format($totals['money_out'], 2) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
