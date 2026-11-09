{{-- The entries behind one figure on a card. Rendered by
     AccountsStandardReportController@metricList from the very rows that figure
     was counted from, so the two cannot disagree. --}}
@php
    $currency = generalSetting()->currency_symbol ?: '$';
@endphp
<div class="modal-header">
    <h4 class="modal-title">
        {{ $title }}
        <span class="badge badge-info">{{ __('reports.accounts_counted', ['count' => $rows->count()]) }}</span>
    </h4>
    <div>
        {{-- Carries the criteria this list was built from, so the sheet is of what is
             on screen. Opens as its own page and prints itself - printing the modal in
             place would bring the whole report behind it. Nothing to print when the
             list is empty. --}}
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
        <p class="text-center text-muted mb-0">@lang('reports.accounts_no_entries')</p>
    @else
        <div class="table-responsive">
            <table class="table school-table-style shadow-none p-0" cellspacing="0" width="100%">
                <thead>
                    <tr>
                        <th>@lang('common.sl')</th>
                        <th>@lang('common.date')</th>
                        <th>@lang('reports.accounts_entry_type')</th>
                        <th>@lang('reports.accounts_head')</th>
                        <th>@lang('common.name')</th>
                        <th>@lang('accounts.bank_account')</th>
                        <th>@lang('fees.payment_method')</th>
                        <th class="text-right">@lang('accounts.amount')</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $key => $row)
                        <tr>
                            <td>{{ $key + 1 }}</td>
                            <td>{{ $row['date'] ? date('M d, Y', strtotime($row['date'])) : '-' }}</td>
                            <td>
                                @php
                                    $tone = ['income' => 'success', 'expense' => 'danger', 'payroll' => 'warning'][$row['kind']] ?? 'secondary';
                                @endphp
                                <span class="badge badge-{{ $tone }}">@lang('reports.accounts_metric_' . $row['kind'])</span>
                            </td>
                            <td>{{ $row['head_name'] ?: __('reports.accounts_no_head') }}</td>
                            <td>{{ $row['name'] ?: '-' }}</td>
                            <td>{{ $row['account_name'] ?: '-' }}</td>
                            <td>{{ $row['method'] ?: '-' }}</td>
                            <td class="text-right">
                                {{ $row['kind'] === 'income' ? '' : '-' }}{{ number_format($row['amount'], 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                {{-- A list of money has to add up - a page of amounts with no total asks
                     the reader to do the arithmetic the report was for. In and out are
                     given separately because one figure for a mixed list would say
                     nothing; totalled from these rows alone, which is why they match
                     the figure that was clicked. --}}
                <tfoot>
                    <tr>
                        <th colspan="7" class="text-right">@lang('reports.accounts_metric_income')</th>
                        <th class="text-right">{{ $currency }}{{ number_format($totals['income'], 2) }}</th>
                    </tr>
                    <tr>
                        <th colspan="7" class="text-right">@lang('reports.accounts_out')</th>
                        <th class="text-right">{{ $currency }}{{ number_format($totals['out'], 2) }}</th>
                    </tr>
                    <tr>
                        <th colspan="7" class="text-right">@lang('reports.accounts_metric_net')</th>
                        <th class="text-right">{{ $currency }}{{ number_format($totals['net'], 2) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
