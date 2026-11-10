{{-- The store half of the revenue card: one line per item, not one per student.
     Every item is here, not the few the card has room for.

     Rendered by FeesStandardReportController@metricList from the very rows the
     figure was summed from, so the total below cannot disagree with it. --}}
@php
    $currency = generalSetting()->currency_symbol ?: '$';
@endphp
<div class="modal-header">
    <h4 class="modal-title">
        {{ $title }}
        <span class="badge badge-info">{{ __('reports.fees_items_counted', ['count' => $items->count()]) }}</span>
    </h4>
    <div>
        @if ($items->count() > 0)
            <a href="{{ $printUrl }}" target="_blank" class="primary-btn small fix-gr-bg mr-15">
                <span class="ti-printer pr-2"></span>@lang('common.print')
            </a>
        @endif
        <button type="button" class="close" data-dismiss="modal">&times;</button>
    </div>
</div>
<div class="modal-body">
    @if ($items->count() == 0)
        {{-- Said rather than left blank: nothing sold is an answer, and an empty
             table reads as something failing to load. --}}
        <p class="text-center text-muted mb-0">@lang('reports.fees_no_items_sold')</p>
    @else
        <div class="table-responsive">
            <table class="table school-table-style shadow-none p-0" cellspacing="0" width="100%">
                <thead>
                    <tr>
                        <th>@lang('reports.fees_item')</th>
                        <th class="text-right">@lang('academics.student')</th>
                        <th class="text-right">@lang('reports.fees_metric_assessed')</th>
                        <th class="text-right">@lang('reports.fees_per_student')</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td>{{ $item['name'] }}</td>
                            <td class="text-right">{{ $item['students'] }}</td>
                            <td class="text-right">{{ number_format($item['amount'], 2) }}</td>
                            <td class="text-right">
                                {{ number_format($item['students'] ? $item['amount'] / $item['students'] : 0, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="2" class="text-right">@lang('dashboard.total')</th>
                        <th class="text-right">{{ $currency }}{{ number_format($totals['item_assessed'], 2) }}</th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
