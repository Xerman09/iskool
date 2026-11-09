{{-- The students behind one figure on a card. Rendered by
     FeesStandardReportController@metricList from the very rows that figure was
     counted from, so the two cannot disagree. --}}
@php
    $currency = generalSetting()->currency_symbol ?: '$';
@endphp
<div class="modal-header">
    <h4 class="modal-title">
        {{ $title }}
        <span class="badge badge-info">{{ __('reports.students_counted', ['count' => $rows->count()]) }}</span>
    </h4>
    <div>
        {{-- Carries the criteria this list was built from, so the sheet is of what is
             on screen. Opens as its own page and prints itself - printing the modal in
             place would bring the whole report behind it. Nothing to print when the
             list is empty. --}}
        @if ($rows->count() > 0)
            {{-- Statements for exactly the students in this list, which is what makes
                 the Outstanding figure printable as a stack of letters. --}}
            {{-- fix-gr-bg, not tr-bg: the modal header is dark, and tr-bg's dark text
                 on it is all but invisible. Both buttons print, so both read alike. --}}
            <a href="{{ $statementsUrl }}" target="_blank" class="primary-btn small fix-gr-bg mr-15">
                <span class="ti-printer pr-2"></span>@lang('reports.fees_statement_print_all')
            </a>
            <a href="{{ $printUrl }}" target="_blank" class="primary-btn small fix-gr-bg mr-15">
                <span class="ti-printer pr-2"></span>@lang('common.print')
            </a>
        @endif
        <button type="button" class="close" data-dismiss="modal">&times;</button>
    </div>
</div>
<div class="modal-body">
    @if ($rows->count() == 0)
        <p class="text-center text-muted mb-0">@lang('reports.no_students_found')</p>
    @else
        <div class="table-responsive">
            <table class="table school-table-style shadow-none p-0" cellspacing="0" width="100%">
                <thead>
                    <tr>
                        <th>@lang('common.sl')</th>
                        <th>@lang('academics.student')</th>
                        <th>@lang('student.admission_no')</th>
                        @if (!isBasicEd())
                            <th>@lang('academics.program')</th>
                        @endif
                        <th>@lang('academics.year_level')</th>
                        <th class="text-right">@lang('reports.fees_metric_assessed')</th>
                        <th class="text-right">@lang('reports.fees_metric_waiver')</th>
                        <th class="text-right">@lang('reports.fees_metric_fine')</th>
                        <th class="text-right">@lang('reports.fees_metric_collected')</th>
                        <th class="text-right">@lang('reports.col_balance')</th>
                        <th class="text-right">@lang('reports.col_prior')</th>
                        <th class="text-right">@lang('reports.col_overdue')</th>
                        <th>@lang('reports.col_next_due')</th>
                        <th>@lang('reports.col_status')</th>
                        <th>@lang('common.action')</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $key => $row)
                        <tr>
                            <td>{{ $key + 1 }}</td>
                            <td>{{ $row['student']->full_name }}</td>
                            <td>{{ $row['student']->admission_no }}</td>
                            @if (!isBasicEd())
                                <td>{{ optional($row['student']->course)->course_name ?: '-' }}</td>
                            @endif
                            <td>{{ optional($row['record']->class)->class_name ?: '-' }}</td>
                            <td class="text-right">{{ number_format($row['assessed'], 2) }}</td>
                            <td class="text-right">{{ number_format($row['waiver'], 2) }}</td>
                            <td class="text-right">{{ number_format($row['fine'], 2) }}</td>
                            <td class="text-right">{{ number_format($row['paid'], 2) }}</td>
                            <td class="text-right">{{ number_format($row['balance'], 2) }}</td>
                            <td class="text-right">{{ $row['prior_balance'] != 0 ? number_format($row['prior_balance'], 2) : '-' }}</td>
                            <td class="text-right">{{ $row['overdue_days'] > 0 ? $row['overdue_days'] : '-' }}</td>
                            <td>
                                @if ($row['next_due'])
                                    {{ \Carbon\Carbon::parse($row['next_due'])->format('M d, Y') }}
                                    <small class="d-block text-muted">
                                        {{ $currency }}{{ number_format($row['next_due_amount'], 2) }}
                                    </small>
                                @else
                                    &ndash;
                                @endif
                            </td>
                            <td>
                                @php
                                    $tone = ['paid' => 'success', 'partial' => 'warning', 'unpaid' => 'danger'][$row['status']] ?? 'secondary';
                                @endphp
                                <span class="badge badge-{{ $tone }}">@lang('reports.fees_metric_' . $row['status'])</span>
                            </td>
                            <td>
                                <a class="primary-btn small fix-gr-bg"
                                    href="{{ route('fees-standard-report-statement', array_merge(['student' => $row['student']->id], request()->only(['academic_id', 'semester_id', 'course_id', 'type']))) }}"
                                    target="_blank">
                                    <span class="ti-printer pr-2"></span>@lang('reports.fees_statement_print')
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                {{-- The list is of money, so it has to add up - a page of amounts with no
                     total asks the reader to do the arithmetic the report was for.
                     Totalled from these rows alone, which is why it matches the figure
                     that was clicked. --}}
                <tfoot>
                    <tr>
                        <th colspan="{{ isBasicEd() ? 4 : 5 }}" class="text-right">@lang('dashboard.total')</th>
                        <th class="text-right">{{ $currency }}{{ number_format($totals['assessed'], 2) }}</th>
                        <th class="text-right">{{ $currency }}{{ number_format($totals['waiver'], 2) }}</th>
                        <th class="text-right">{{ $currency }}{{ number_format($totals['fine'], 2) }}</th>
                        <th class="text-right">{{ $currency }}{{ number_format($totals['collected'], 2) }}</th>
                        <th class="text-right">{{ $currency }}{{ number_format($totals['outstanding'], 2) }}</th>
                        <th class="text-right">{{ $currency }}{{ number_format($totals['prior_balance'], 2) }}</th>
                        {{-- Overdue, Next Due, Status and Action: four columns the totals
                             row has nothing to say about. --}}
                        <th colspan="4"></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
