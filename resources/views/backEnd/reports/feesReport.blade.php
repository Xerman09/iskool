@extends('backEnd.master')
@section('title')
    @lang('reports.standard_fees_report')
@endsection

@section('mainContent')
    @php
        $showProgram = !isBasicEd();
        $currency = generalSetting()->currency_symbol ?: '$';

        /* Each card is a question, and each row under it one of that question's
           answers. Held here rather than repeated below, so a metric cannot end up
           labelled one way on the card and another in its modal - both read the
           same key.

           money: the rows carry pesos, and the count of students behind each is
           shown beside them. A card without it carries student counts instead, and
           reads them from count_<metric>. */
        $cards = [
            ['title' => __('reports.fees_assessment_summary'), 'accent' => '#2f6fed', 'money' => true,
             'total' => 'net', 'rows' => ['assessed', 'fine', 'waiver'],
             'hint' => __('reports.fees_assessment_hint')],
            ['title' => __('reports.fees_collection_summary'), 'accent' => '#1a9d6a', 'money' => true,
             'total' => 'collected', 'rows' => ['collected', 'outstanding', 'wallet'],
             'hint' => __('reports.fees_collection_hint', ['rate' => $summary['collection_rate']])],
            ['title' => __('reports.payment_status_summary'), 'accent' => '#e08b1e', 'money' => false,
             'total' => 'all', 'rows' => ['paid', 'partial', 'unpaid', 'no_invoice']],
            ['title' => __('reports.fees_aging_summary'), 'accent' => '#c0392b', 'money' => true,
             'total' => 'outstanding',
             'rows' => ['aging_current', 'aging_1_30', 'aging_31_60', 'aging_61_90', 'aging_90_plus'],
             'hint' => __('reports.fees_aging_hint')],
            /* Missed an Instalment is the figure to act on: a plan being kept is a
               balance nobody needs to chase, however large it looks beside it. */
            ['title' => __('reports.fees_plan_summary'), 'accent' => '#7048a8', 'money' => true,
             'total' => 'outstanding', 'rows' => ['on_plan', 'not_on_plan', 'plan_overdue'],
             'hint' => __('reports.fees_plan_hint')],
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

        /* How many students a peso figure is spread across - quieter than the money,
           because it answers the second question, not the first. */
        .metric-row .metric-count {
            display: block;
            font-size: 11px;
            font-weight: 400;
            color: #9aa0a6;
            text-align: right;
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

        /* Ten money columns is wider than this card on a laptop, and the DataTable
           here is started without the Responsive extension (see developer.js), so
           nothing folds the extra ones away. Scrolling sideways keeps every column
           readable and the card whole. */
        .breakdown-scroll {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .breakdown-scroll table td,
        .breakdown-scroll table th {
            white-space: nowrap;
        }

        /* Money reads as a column of figures, not as prose. */
        .amount-cell {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .amount-owing {
            color: #c0392b;
            font-weight: 600;
        }

        /* An overpayment is not a debt, and should not be read as one in a column
           where every other figure is money owed. */
        .amount-credit {
            color: #1a9d6a;
            font-weight: 600;
        }
    </style>

    <section class="sms-breadcrumb mb-20">
        <div class="container-fluid">
            <div class="row justify-content-between">
                <h1>@lang('reports.standard_fees_report')</h1>
                <div class="bc-pages">
                    <a href="{{ route('dashboard') }}">@lang('common.dashboard')</a>
                    <a href="#">@lang('reports.report')</a>
                    <a href="#">@lang('reports.standard_fees_report')</a>
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
                            <strong>{{ __('reports.students_counted', ['count' => $summary['students']]) }}</strong>
                            &nbsp;|&nbsp; @lang('reports.fees_metric_net'):
                            <strong>{{ $currency }}{{ number_format($summary['net'], 2) }}</strong>
                            &nbsp;|&nbsp; @lang('reports.fees_metric_collected'):
                            <strong>{{ $currency }}{{ number_format($summary['collected'], 2) }}</strong>
                            &nbsp;|&nbsp; @lang('reports.fees_metric_outstanding'):
                            <strong class="amount-owing">{{ $currency }}{{ number_format($summary['outstanding'], 2) }}</strong>
                            &nbsp;|&nbsp; {{ $summary['collection_rate'] }}% @lang('reports.fees_collected_label')
                        </div>
                        <div>
                            {{-- Statements for everyone the criteria currently select. Built
                                 from the form rather than from this page's markup, so the run
                                 is of the same population the figures above were counted from. --}}
                            <a href="#" id="printStatements" class="primary-btn small tr-bg mr-15">
                                <span class="ti-printer pr-2"></span>@lang('reports.fees_statement_print_all')
                            </a>
                            <a href="#" class="primary-btn small fix-gr-bg" onclick="window.print(); return false;">
                                <span class="ti-printer pr-2"></span>@lang('common.print')
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- One GET form drives the whole page, and the modal reads its values back
                 out - so the list behind a figure is filtered exactly as the figure was. --}}
            {{ Form::open(['class' => 'form-horizontal', 'route' => 'fees-standard-report', 'method' => 'GET', 'id' => 'feesReportForm']) }}
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
                            <div class="col-lg-2 mb-3 mb-lg-0">
                                <label class="primary_input_label">{{ termLabel() }}</label>
                                <select class="primary_select form-control" name="semester_id">
                                    <option value="">@lang('reports.whole_year')</option>
                                    {{-- Reaches past the school year named beside it, and so overrides
                                         it - the year select is left enabled rather than greyed out,
                                         because switching back has to be one click. --}}
                                    <option value="whole_stay"
                                        {{ $criteria['scope'] === 'whole_stay' ? 'selected' : '' }}>
                                        @lang('academics.whole_stay')
                                    </option>
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
                            <div class="col-lg-2 mb-3 mb-lg-0">
                                <label class="primary_input_label">@lang('reports.col_charge_type')</label>
                                <select class="primary_select form-control" name="type">
                                    <option value="">@lang('reports.fees_type_all')</option>
                                    <option value="fees" {{ $criteria['type'] === 'fees' ? 'selected' : '' }}>
                                        @lang('reports.fees_type_fees')
                                    </option>
                                    <option value="store" {{ $criteria['type'] === 'store' ? 'selected' : '' }}>
                                        @lang('reports.fees_type_store')
                                    </option>
                                </select>
                            </div>
                            <div class="{{ $showProgram ? 'col-lg-2' : 'col-lg-5' }}">
                                <label class="primary_input_label">&nbsp;</label>
                                <div class="criteria-actions">
                                    <button type="submit" class="primary-btn small fix-gr-bg">
                                        <span class="ti-search pr-2"></span>@lang('common.search')
                                    </button>
                                    <a href="{{ route('fees-standard-report') }}" class="primary-btn small tr-bg">
                                        @lang('reports.reset')
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            {{ Form::close() }}

            {{-- Said plainly, because the year select stays filled in while being
                 ignored - and a figure covering four years under a heading naming one
                 of them would be read as that year's. --}}
            @if ($criteria['scope'] === 'whole_stay')
                <div class="alert alert-info mt-15 mb-0" style="font-size: 13px;">
                    <strong>@lang('academics.whole_stay')</strong> &mdash; @lang('reports.fees_whole_stay_note')
                </div>
            @endif

            <p class="text-muted mt-15 mb-5">@lang('reports.click_to_view_students')</p>

            <div class="row mt-10">
                @foreach ($cards as $card)
                    <div class="col-lg-3 col-md-6 mb-20">
                        <div class="white-box h-100" style="border-top:3px solid {{ $card['accent'] }};">
                            <div class="d-flex justify-content-between align-items-center metric-head"
                                style="--metric-accent: {{ $card['accent'] }};">
                                <h5 class="mb-0">{{ $card['title'] }}</h5>
                                <a href="#" class="metric-total fees-metric"
                                    data-metric="{{ $card['total'] }}">
                                    @if ($card['money'])
                                        {{ $currency }}{{ number_format($summary[$card['total']], 2) }}
                                    @else
                                        {{ $summary['count_' . $card['total']] }}
                                    @endif
                                </a>
                            </div>
                            @if (!empty($card['hint']))
                                <p class="text-muted mb-10" style="font-size: 12px;">{{ $card['hint'] }}</p>
                            @endif
                            {{-- The whole row is the target, not just the figure: a money
                                 amount is a small thing to ask anyone to hit, and the label
                                 beside it is part of the same answer. --}}
                            @foreach ($card['rows'] as $metric)
                                <a href="#" class="metric-row fees-metric d-flex justify-content-between align-items-center"
                                    style="--metric-accent: {{ $card['accent'] }};"
                                    data-metric="{{ $metric }}">
                                    <span class="metric-label">@lang('reports.fees_metric_' . $metric)</span>
                                    <span>
                                        <span class="metric-value">
                                            @if ($card['money'])
                                                {{ $currency }}{{ number_format($summary[$metric], 2) }}
                                            @else
                                                {{ $summary['count_' . $metric] }}
                                            @endif
                                        </span>
                                        @if ($card['money'])
                                            <span class="metric-count">{{ __('reports.students_counted', ['count' => $summary['count_' . $metric]]) }}</span>
                                        @endif
                                    </span>
                                </a>
                            @endforeach
                            {{-- Not a figure anyone can collect, so it is stated rather than
                                 made clickable - and never folded into the card's total. --}}
                            @if ($card['total'] === 'collected' && $summary['credit'] > 0)
                                <p class="text-muted mb-0 mt-10" style="font-size: 12px;">
                                    @lang('reports.fees_credit_note', [
                                        'amount' => $currency . number_format($summary['credit'], 2),
                                    ])
                                </p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="row mt-10">
                <div class="col-lg-12">
                    <div class="white-box">
                        <div class="main-title">
                            <h3 class="mb-15">
                                @lang('reports.fees_all_students_breakdown')
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
                                            <th>@lang('reports.fees_metric_assessed') ({{ $currency }})</th>
                                            <th>@lang('reports.fees_metric_waiver') ({{ $currency }})</th>
                                            <th>@lang('reports.fees_metric_fine') ({{ $currency }})</th>
                                            <th>@lang('reports.fees_metric_collected') ({{ $currency }})</th>
                                            <th>@lang('reports.col_balance') ({{ $currency }})</th>
                                            <th>@lang('reports.col_overdue')</th>
                                            <th>@lang('reports.col_plan')</th>
                                            <th>@lang('reports.col_next_due')</th>
                                            <th>@lang('reports.fees_metric_wallet') ({{ $currency }})</th>
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
                                                @if ($showProgram)
                                                    <td>{{ optional($row['student']->course)->course_name ?: '-' }}</td>
                                                @endif
                                                <td>{{ optional($row['record']->class)->class_name ?: '-' }}</td>
                                                <td class="amount-cell">{{ number_format($row['assessed'], 2) }}</td>
                                                <td class="amount-cell">{{ number_format($row['waiver'], 2) }}</td>
                                                <td class="amount-cell">{{ number_format($row['fine'], 2) }}</td>
                                                <td class="amount-cell">{{ number_format($row['paid'], 2) }}</td>
                                                <td class="amount-cell {{ $row['balance'] > 0 ? 'amount-owing' : ($row['balance'] < 0 ? 'amount-credit' : '') }}">
                                                    {{ number_format($row['balance'], 2) }}
                                                </td>
                                                <td class="amount-cell">
                                                    {{ $row['overdue_days'] > 0 ? $row['overdue_days'] : '-' }}
                                                </td>
                                                <td>
                                                    @if ($row['on_plan'])
                                                        <span class="badge badge-info">@lang('reports.fees_statement_plan')</span>
                                                    @else
                                                        <span class="text-muted">&ndash;</span>
                                                    @endif
                                                </td>
                                                {{-- The dated promise, and what is still owed on it. Empty for
                                                     a cash payer, and for a plan already paid off - neither
                                                     has a next instalment to name. --}}
                                                <td class="{{ $row['next_due'] && $row['overdue_days'] > 0 ? 'amount-owing' : '' }}">
                                                    @if ($row['next_due'])
                                                        {{ \Carbon\Carbon::parse($row['next_due'])->format('M d, Y') }}
                                                        <small class="d-block text-muted">
                                                            {{ $currency }}{{ number_format($row['next_due_amount'], 2) }}
                                                        </small>
                                                    @else
                                                        <span class="text-muted">&ndash;</span>
                                                    @endif
                                                </td>
                                                <td class="amount-cell">{{ number_format($row['wallet'], 2) }}</td>
                                                <td>
                                                    @php
                                                        $tone = ['paid' => 'success', 'partial' => 'warning', 'unpaid' => 'danger'][$row['status']] ?? 'secondary';
                                                    @endphp
                                                    <span class="badge badge-{{ $tone }}">@lang('reports.fees_metric_' . $row['status'])</span>
                                                </td>
                                                {{-- Carries the page's criteria, so the statement covers the
                                                     same year, term and charge type the row was priced at. --}}
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
                                </table>
                                </div>
                            </x-table>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </section>

    <div class="modal fade" id="feesMetricModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered student-details" role="document">
            <div class="modal-content"></div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        $(document).on('click', '.fees-metric', function (e) {
            e.preventDefault();

            /* Only one figure is being answered at a time, so the mark moves rather
               than accumulating. */
            $('.fees-metric').removeClass('is-selected');
            $(this).addClass('is-selected');

            /* The criteria are read back off the form rather than stamped onto every
               figure, so a list can never be filtered differently from the count that
               was clicked - there is only one source for both. */
            var criteria = $('#feesReportForm').serialize();

            $('#feesMetricModal .modal-content').html('');
            $('#feesMetricModal').modal('show');

            $.get("{{ route('fees-standard-report-metric') }}", criteria + '&metric=' + $(this).data('metric'))
                .done(function (html) {
                    $('#feesMetricModal .modal-content').html(html);
                })
                .fail(function () {
                    toastr.error(window.jsLang('Operation failed'));
                    $('#feesMetricModal').modal('hide');
                });
        });

        /* The mark says which figure the open list belongs to, so it goes when the
           list does - otherwise a closed modal leaves a row looking selected with
           nothing behind it. */
        $(document).on('hidden.bs.modal', '#feesMetricModal', function () {
            $('.fees-metric').removeClass('is-selected');
        });

        /* Statements for the whole filtered population. Opened in its own tab, which
           prints itself - the report has to stay where it is, because a run of
           statements is something the user comes back from. */
        $(document).on('click', '#printStatements', function (e) {
            e.preventDefault();
            var criteria = $('#feesReportForm').serialize();
            window.open("{{ route('fees-standard-report-statements') }}?" + criteria, '_blank');
        });
    </script>
@endpush
