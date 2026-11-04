@extends('backEnd.master')
@section('title')
    @lang('reports.standard_student_report')
@endsection

@section('mainContent')
    @php
        $showProgram = !isBasicEd();
        /* Each card is a question, and each row under it one of that question's
           answers. Held here rather than repeated four times below, so a metric
           cannot end up labelled one way on the card and another in its modal -
           both read the same key. */
        $cards = [
            ['title' => __('reports.student_summary'), 'accent' => '#0f8b8d', 'total' => 'total',
             'rows' => ['active', 'inactive']],
            /* Graduated sits here rather than with the student counts: it is a
               standing in the school, which is what this card is about. The other
               reasons a student is inactive are still counted and still reachable
               by their own metric - they are simply not given a row. */
            ['title' => __('reports.enrollment_summary'), 'accent' => '#2f6fed', 'total' => 'total',
             'rows' => ['enrolled', 'not_enrolled', 'inactive_graduated']],
            ['title' => __('reports.payment_method_summary'), 'accent' => '#e08b1e', 'total' => 'total',
             'rows' => ['cash', 'plan', 'not_billed']],
            ['title' => __('reports.payment_status_summary'), 'accent' => '#1a9d6a', 'total' => 'total',
             'rows' => ['paid', 'partial', 'unpaid', 'no_invoice']],
        ];
    @endphp

    <style>
        /* Each card passes its own colour down as --metric-accent, so a row picks up
           the colour of the question it belongs to rather than one shared highlight. */
        .metric-head {
            padding-bottom: 10px;
            margin-bottom: 6px;
            border-bottom: 1px solid rgba(0, 0, 0, .06);
        }

        .metric-row,
        .metric-total {
            text-decoration: none;
            transition: background-color .15s ease, box-shadow .15s ease, transform .08s ease;
        }

        .metric-row {
            padding: 9px 10px;
            margin: 0 -10px;
            border-radius: 4px;
            color: inherit;
            /* Transparent rather than absent, so the bar on hover does not shift the
               text sideways when it appears. */
            box-shadow: inset 3px 0 0 transparent;
            cursor: pointer;
        }

        .metric-row .metric-label {
            color: #6c757d;
        }

        .metric-row .metric-value {
            font-weight: 700;
            color: #2b2b2b;
        }

        .metric-row:hover,
        .metric-row:focus-visible {
            background-color: rgba(0, 0, 0, .045);
            box-shadow: inset 3px 0 0 var(--metric-accent, #0f8b8d);
            text-decoration: none;
        }

        .metric-row:hover .metric-label,
        .metric-row:hover .metric-value {
            color: var(--metric-accent, #0f8b8d);
        }

        /* A reason under the figure it explains - indented, quieter, and never
           mistaken for a count of its own. */
        .metric-row-sub {
            padding-left: 22px;
            font-size: 13px;
        }

        .metric-row-sub .metric-value {
            font-weight: 500;
        }

        /* Pressed. Kept small - a row that moves far reads as something being dragged. */
        .metric-row:active {
            background-color: rgba(0, 0, 0, .085);
            transform: translateY(1px);
        }

        /* The row whose list is currently open, so it stays obvious which figure the
           modal is answering once the pointer has moved away. */
        .metric-row.is-selected {
            background-color: rgba(0, 0, 0, .06);
            box-shadow: inset 3px 0 0 var(--metric-accent, #0f8b8d);
        }

        .metric-row.is-selected .metric-label,
        .metric-row.is-selected .metric-value {
            color: var(--metric-accent, #0f8b8d);
        }

        .metric-total {
            display: inline-block;
            min-width: 34px;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            text-align: center;
            color: #fff;
            background-color: var(--metric-accent, #0f8b8d);
            opacity: .9;
        }

        .metric-total:hover,
        .metric-total:focus-visible {
            opacity: 1;
            color: #fff;
            text-decoration: none;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .18);
        }

        .metric-total:active {
            transform: translateY(1px);
            box-shadow: none;
        }

        .metric-total.is-selected {
            opacity: 1;
            box-shadow: 0 0 0 3px rgba(0, 0, 0, .12);
        }

        /* Search and Reset are a <button> and an <a>: left to their own defaults they
           take different heights and sit on different baselines. One flex row gives
           both the same height and centres them against each other. */
        .criteria-actions {
            display: flex;
            align-items: stretch;
            gap: 8px;
        }

        .criteria-actions > .primary-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            /* Matches the select beside them, so the whole criteria row lines up. */
            height: 40px;
            margin: 0;
            line-height: 1;
            white-space: nowrap;
        }

        /* Eight columns is wider than this card on a laptop, and the DataTable here
           is started without the Responsive extension (see developer.js), so nothing
           folds the extra ones away - the table simply ran past the card's right
           edge and the last column was cut off. Scrolling it sideways keeps every
           column readable and the card whole. */
        .breakdown-scroll {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Names and programme titles were wrapping onto three lines while the table
           still overflowed, which cost height without buying any width back. They
           stay on one line and the scroll handles the rest. */
        .breakdown-scroll table td,
        .breakdown-scroll table th {
            white-space: nowrap;
        }
    </style>

    <section class="sms-breadcrumb mb-20">
        <div class="container-fluid">
            <div class="row justify-content-between">
                <h1>@lang('reports.standard_student_report')</h1>
                <div class="bc-pages">
                    <a href="{{ route('dashboard') }}">@lang('common.dashboard')</a>
                    <a href="#">@lang('reports.report')</a>
                    <a href="#">@lang('reports.standard_student_report')</a>
                </div>
            </div>
        </div>
    </section>

    <section class="admin-visitor-area up_st_admin_visitor">
        <div class="container-fluid p-0">

            {{-- The one-line answer, so the page says what it found before anyone reads
                 a card. --}}
            <div class="row mb-20">
                <div class="col-lg-12">
                    <div class="white-box d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <strong>{{ __('reports.students_counted', ['count' => $summary['total']]) }}</strong>
                            &nbsp;|&nbsp; {{ $summary['enrolled'] }} @lang('reports.student_metric_enrolled')
                            &nbsp;|&nbsp; {{ $summary['not_enrolled'] }} @lang('reports.student_metric_not_enrolled')
                            &nbsp;|&nbsp; {{ $summary['paid'] }} @lang('reports.student_metric_paid')
                            &nbsp;|&nbsp; {{ $summary['unpaid'] }} @lang('reports.student_metric_unpaid')
                        </div>
                        <a href="#" class="primary-btn small fix-gr-bg" onclick="window.print(); return false;">
                            <span class="ti-printer pr-2"></span>@lang('common.print')
                        </a>
                    </div>
                </div>
            </div>

            {{-- One GET form drives the whole page, and the modal reads its values back
                 out - so the list behind a figure is filtered exactly as the figure was. --}}
            {{ Form::open(['class' => 'form-horizontal', 'route' => 'student-standard-report', 'method' => 'GET', 'id' => 'studentReportForm']) }}
            <div class="row">
                <div class="col-lg-12">
                    <div class="white-box">
                        <div class="row mb-15">
                            <div class="col-lg-12">
                                <input type="text" name="search" value="{{ request('search') }}"
                                    class="primary_input_field form-control"
                                    placeholder="@lang('common.search')">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-3 mb-3 mb-lg-0">
                                <label class="primary_input_label">@lang('reports.school_year')</label>
                                <select class="primary_select form-control" name="academic_id">
                                    @foreach ($years as $year)
                                        <option value="{{ $year->id }}"
                                            {{ (int) $criteria['academicId'] === (int) $year->id ? 'selected' : '' }}>
                                            {{ $year->year }}{{ $year->title ? ' - ' . $year->title : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-3 mb-3 mb-lg-0">
                                <label class="primary_input_label">{{ termLabel() }}</label>
                                <select class="primary_select form-control" name="semester_id">
                                    <option value="">@lang('reports.whole_year')</option>
                                    @foreach ($semesters as $semester)
                                        <option value="{{ $semester->id }}"
                                            {{ (int) $criteria['semesterId'] === (int) $semester->id ? 'selected' : '' }}>
                                            {{ $semester->semester_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @if ($showProgram)
                                <div class="col-lg-3 mb-3 mb-lg-0">
                                    <label class="primary_input_label">@lang('academics.program')</label>
                                    <select class="primary_select form-control" name="course_id">
                                        <option value="">@lang('reports.all_programs')</option>
                                        @foreach ($courses as $course)
                                            <option value="{{ $course->id }}"
                                                {{ (int) $criteria['courseId'] === (int) $course->id ? 'selected' : '' }}>
                                                {{ $course->course_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div class="{{ $showProgram ? 'col-lg-3' : 'col-lg-6' }}">
                                <label class="primary_input_label">&nbsp;</label>
                                {{-- Search is a <button> and Reset an <a>, which carry different
                                     default heights and baselines - side by side they sat a few
                                     pixels apart. The row sets the height for both instead. --}}
                                <div class="criteria-actions">
                                    <button type="submit" class="primary-btn small fix-gr-bg">
                                        <span class="ti-search pr-2"></span>@lang('common.search')
                                    </button>
                                    <a href="{{ route('student-standard-report') }}" class="primary-btn small tr-bg">
                                        @lang('reports.reset')
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            {{ Form::close() }}

            <p class="text-muted mt-15 mb-5">@lang('reports.click_to_view_students')</p>

            <div class="row mt-10">
                @foreach ($cards as $card)
                    <div class="col-lg-3 col-md-6 mb-20">
                        <div class="white-box h-100" style="border-top:3px solid {{ $card['accent'] }};">
                            <div class="d-flex justify-content-between align-items-center metric-head"
                                style="--metric-accent: {{ $card['accent'] }};">
                                <h5 class="mb-0">{{ $card['title'] }}</h5>
                                <a href="#" class="metric-total student-metric"
                                    data-metric="{{ $card['total'] }}">{{ $summary[$card['total']] }}</a>
                            </div>
                            @if (!empty($card['hint']))
                                <p class="text-muted mb-10" style="font-size: 12px;">{{ $card['hint'] }}</p>
                            @endif
                            {{-- The whole row is the target, not just the figure: a two-digit
                                 number is a small thing to ask anyone to hit, and the label
                                 beside it is part of the same answer. --}}
                            @foreach ($card['rows'] as $metric)
                                <a href="#" class="metric-row student-metric d-flex justify-content-between align-items-center"
                                    style="--metric-accent: {{ $card['accent'] }};"
                                    data-metric="{{ $metric }}">
                                    <span class="metric-label">@lang('reports.student_metric_' . $metric)</span>
                                    <span class="metric-value">{{ $summary[$metric] }}</span>
                                </a>
                            @endforeach
                            @foreach ($card['subRows'] ?? [] as $metric)
                                <a href="#" class="metric-row metric-row-sub student-metric d-flex justify-content-between align-items-center"
                                    style="--metric-accent: {{ $card['accent'] }};"
                                    data-metric="{{ $metric }}">
                                    <span class="metric-label">&#8627; @lang('reports.student_metric_' . $metric)</span>
                                    <span class="metric-value">{{ $summary[$metric] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="row mt-10">
                <div class="col-lg-12">
                    <div class="white-box">
                        <div class="main-title">
                            <h3 class="mb-15">
                                @lang('reports.all_students_breakdown')
                                <span class="badge badge-info">{{ __('reports.students_counted', ['count' => $rows->count()]) }}</span>
                            </h3>
                        </div>

                        @if ($rows->count() == 0)
                            <p class="text-center text-muted mb-0">@lang('reports.no_students_found')</p>
                        @else
                            <x-table>
                                <div class="breakdown-scroll">
                                <table id="table_id" class="table data-table Crm_table_active3 no-footer dtr-inline collapsed"
                                    cellspacing="0" width="100%">
                                    <thead>
                                        <tr>
                                            <th>@lang('common.sl')</th>
                                            <th>@lang('academics.student')</th>
                                            <th>@lang('student.admission_no')</th>
                                            @if ($showProgram)
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
                                                @if ($showProgram)
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
                            </x-table>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </section>

    <div class="modal fade" id="studentMetricModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered student-details" role="document">
            <div class="modal-content"></div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        $(document).on('click', '.student-metric', function (e) {
            e.preventDefault();

            /* Only one figure is being answered at a time, so the mark moves rather
               than accumulating. */
            $('.student-metric').removeClass('is-selected');
            $(this).addClass('is-selected');

            /* The criteria are read back off the form rather than stamped onto every
               figure, so a list can never be filtered differently from the count that
               was clicked - there is only one source for both. */
            var criteria = $('#studentReportForm').serialize();

            $('#studentMetricModal .modal-content').html('');
            $('#studentMetricModal').modal('show');

            $.get("{{ route('student-standard-report-metric') }}", criteria + '&metric=' + $(this).data('metric'))
                .done(function (html) {
                    $('#studentMetricModal .modal-content').html(html);
                })
                .fail(function () {
                    toastr.error(window.jsLang('Operation failed'));
                    $('#studentMetricModal').modal('hide');
                });
        });

        /* The mark says which figure the open list belongs to, so it goes when the
           list does - otherwise a closed modal leaves a row looking selected with
           nothing behind it. */
        $(document).on('hidden.bs.modal', '#studentMetricModal', function () {
            $('.student-metric').removeClass('is-selected');
        });
    </script>
@endpush
