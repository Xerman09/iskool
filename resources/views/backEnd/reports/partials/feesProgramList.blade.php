{{-- The split behind the revenue card's own figure: one line per programme, not
     one per student. Every programme is here, not the largest few the card has
     room for - a breakdown that hid half of itself would be the card again.

     Rendered by FeesStandardReportController@metricList from the very rows the
     figure was summed from, so the total below cannot disagree with it. --}}
@php
    $currency = generalSetting()->currency_symbol ?: '$';
    $showProgram = !isBasicEd();
@endphp
<div class="modal-header">
    <h4 class="modal-title">
        {{ $title }}
        <span class="badge badge-info">{{ __('reports.fees_programs_counted', ['count' => $programs->count()]) }}</span>
    </h4>
    <div>
        @if ($programs->count() > 0)
            <a href="{{ $printUrl }}" target="_blank" class="primary-btn small fix-gr-bg mr-15">
                <span class="ti-printer pr-2"></span>@lang('common.print')
            </a>
        @endif
        <button type="button" class="close" data-dismiss="modal">&times;</button>
    </div>
</div>
<div class="modal-body">
    @if ($programs->count() == 0)
        <p class="text-center text-muted mb-0">@lang('reports.no_students_found')</p>
    @else
        <div class="table-responsive">
            <table class="table school-table-style shadow-none p-0" cellspacing="0" width="100%">
                <thead>
                    <tr>
                        <th>{{ $showProgram ? __('academics.program') : __('academics.year_level') }}</th>
                        <th class="text-right">@lang('academics.student')</th>
                        <th class="text-right">@lang('reports.fees_metric_assessed')</th>
                        <th class="text-right">@lang('reports.fees_metric_collected')</th>
                        <th class="text-right">@lang('reports.fees_metric_outstanding')</th>
                        <th class="text-right">@lang('reports.fees_per_student')</th>
                        <th class="text-right">@lang('reports.fees_collected_label')</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($programs as $program)
                        @php
                            /* Collection rate per programme, which is the comparison the
                               card has no room for: two programmes can bill the same and
                               be collecting very differently. */
                            $rate = $program['billed'] > 0
                                ? round($program['collected'] / $program['billed'] * 100, 1)
                                : 0;
                        @endphp
                        <tr>
                            <td>{{ $program['name'] }}</td>
                            <td class="text-right">{{ $program['students'] }}</td>
                            <td class="text-right">{{ number_format($program['billed'], 2) }}</td>
                            <td class="text-right">{{ number_format($program['collected'], 2) }}</td>
                            <td class="text-right">{{ number_format($program['outstanding'], 2) }}</td>
                            <td class="text-right">{{ number_format($program['per_student'], 2) }}</td>
                            <td class="text-right">{{ $rate }}%</td>
                        </tr>
                    @endforeach
                </tbody>
                {{-- Tuition and store cut from the same figures as billed and
                     collected, so the three add back up to the row below them. --}}
                <tfoot>
                    <tr>
                        <th colspan="2" class="text-right">@lang('reports.fees_tuition')</th>
                        <th class="text-right">{{ number_format($totals['tuition_assessed'], 2) }}</th>
                        <th class="text-right">{{ number_format($totals['tuition_paid'], 2) }}</th>
                        <th colspan="3"></th>
                    </tr>
                    <tr>
                        <th colspan="2" class="text-right">@lang('reports.fees_item_store')</th>
                        <th class="text-right">{{ number_format($totals['item_assessed'], 2) }}</th>
                        <th class="text-right">{{ number_format($totals['item_paid'], 2) }}</th>
                        <th colspan="3"></th>
                    </tr>
                    <tr>
                        <th colspan="2" class="text-right">@lang('dashboard.total')</th>
                        <th class="text-right">{{ $currency }}{{ number_format($totals['assessed'], 2) }}</th>
                        <th class="text-right">{{ $currency }}{{ number_format($totals['collected'], 2) }}</th>
                        <th class="text-right">{{ $currency }}{{ number_format($totals['outstanding'], 2) }}</th>
                        <th></th>
                        <th class="text-right">{{ $totals['collection_rate'] }}%</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
