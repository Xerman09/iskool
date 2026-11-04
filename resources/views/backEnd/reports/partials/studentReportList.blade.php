{{-- The students behind one figure on a card. Rendered by
     StudentReportController@metricList from the very rows that figure was counted
     from, so the two cannot disagree. --}}
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
                        <th>@lang('reports.col_enrollment')</th>
                        <th>@lang('reports.col_method')</th>
                        <th>@lang('reports.col_status')</th>
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
                            <td>
                                @if ($row['enrolled'])
                                    <span class="badge badge-success">@lang('reports.student_metric_enrolled')</span>
                                @else
                                    <span class="badge badge-warning">@lang('reports.student_metric_not_enrolled')</span>
                                @endif
                            </td>
                            <td>@lang('reports.student_metric_' . $row['method'])</td>
                            <td>
                                @php
                                    $tone = ['paid' => 'success', 'partial' => 'warning', 'unpaid' => 'danger'][$row['status']] ?? 'secondary';
                                @endphp
                                <span class="badge badge-{{ $tone }}">@lang('reports.student_metric_' . $row['status'])</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
