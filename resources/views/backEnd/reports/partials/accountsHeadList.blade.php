{{-- Every head of one side, which is what the card cannot show: it has room for
     four, and a school with thirty would otherwise see four and a line saying
     "26 others" with no way to reach them.

     Rendered by AccountsStandardReportController@metricList from the very rows
     the figure was summed from, so the total below cannot disagree with it. --}}
@php
    $currency = generalSetting()->currency_symbol ?: '$';
    $total = (float) $heads->sum('amount');
@endphp
<div class="modal-header">
    <h4 class="modal-title">
        {{ $title }}
        <span class="badge badge-info">{{ __('reports.accounts_heads_counted', ['count' => $heads->count()]) }}</span>
    </h4>
    <div>
        @if ($heads->count() > 0)
            <a href="{{ $printUrl }}" target="_blank" class="primary-btn small fix-gr-bg mr-15">
                <span class="ti-printer pr-2"></span>@lang('common.print')
            </a>
        @endif
        <button type="button" class="close" data-dismiss="modal">&times;</button>
    </div>
</div>
<div class="modal-body">
    @if ($heads->count() == 0)
        <p class="text-center text-muted mb-0">@lang('reports.accounts_no_entries')</p>
    @else
        <div class="table-responsive">
            <table class="table school-table-style shadow-none p-0" cellspacing="0" width="100%">
                <thead>
                    <tr>
                        <th>@lang('reports.accounts_head')</th>
                        <th class="text-right">@lang('reports.accounts_entry_type')</th>
                        <th class="text-right">@lang('accounts.amount')</th>
                        {{-- Share is what makes a long list readable: thirty amounts say
                             little, but "this one is 40% of it" says where to look. --}}
                        <th class="text-right">@lang('reports.pl_share_col')</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($heads as $head)
                        <tr>
                            <td>{{ $head['name'] }}</td>
                            <td class="text-right">{{ $head['entries'] }}</td>
                            <td class="text-right">{{ number_format($head['amount'], 2) }}</td>
                            <td class="text-right text-muted">
                                {{ $total > 0 ? round($head['amount'] / $total * 100, 1) : 0 }}%
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th class="text-right">@lang('dashboard.total')</th>
                        <th class="text-right">{{ $heads->sum('entries') }}</th>
                        <th class="text-right">{{ $currency }}{{ number_format($total, 2) }}</th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
