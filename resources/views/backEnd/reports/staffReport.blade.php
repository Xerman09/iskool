@extends('backEnd.master')
@section('title')
    @lang('reports.standard_staff_report')
@endsection

@section('mainContent')
    @php
        $currency = generalSetting()->currency_symbol ?: '$';

        /* Each card is a question, and each row under it one of that question's
           answers. Held here rather than repeated below, so a metric cannot end up
           labelled one way on the card and another in its modal - both read the
           same key.

           unit says what the figures are: money takes the currency symbol, days
           takes a day count with the number of staff behind it, and count is a
           headcount that stands on its own. */
        $cards = [
            ['title' => __('reports.staff_headcount_summary'), 'accent' => '#0f8b8d', 'unit' => 'count',
             'total' => 'all', 'rows' => ['active', 'inactive']],
            ['title' => __('reports.staff_attendance_summary'), 'accent' => '#2f6fed', 'unit' => 'days',
             'total' => 'att_present',
             'rows' => ['att_present', 'att_late', 'att_half', 'att_absent', 'att_holiday'],
             'hint' => __('reports.staff_attendance_hint', ['rate' => $summary['attendance_rate']])],
            ['title' => __('reports.staff_payroll_status_summary'), 'accent' => '#e08b1e', 'unit' => 'count',
             'total' => 'all', 'rows' => ['pay_paid', 'pay_generated', 'pay_not_generated']],
            ['title' => __('reports.staff_payroll_summary'), 'accent' => '#1a9d6a', 'unit' => 'money',
             'total' => 'net', 'rows' => ['gross', 'earning', 'deduction', 'tax'],
             'hint' => __('reports.staff_payroll_hint')],
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

        /* How many staff a figure is spread across - quieter than the figure, because
           it answers the second question, not the first. */
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

        /* Fourteen columns is wider than this card on a laptop, and the DataTable
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

        /* Figures read as a column, not as prose. */
        .amount-cell {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .rate-low {
            color: #c0392b;
            font-weight: 600;
        }

        /* No days recorded is not a bad rate - it is no answer, and must not be read
           as one. */
        .rate-none {
            color: #9aa0a6;
        }
    </style>

    <section class="sms-breadcrumb mb-20">
        <div class="container-fluid">
            <div class="row justify-content-between">
                <h1>@lang('reports.standard_staff_report')</h1>
                <div class="bc-pages">
                    <a href="{{ route('dashboard') }}">@lang('common.dashboard')</a>
                    <a href="#">@lang('reports.report')</a>
                    <a href="#">@lang('reports.standard_staff_report')</a>
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
                            <strong>{{ __('reports.staff_counted', ['count' => $summary['staff']]) }}</strong>
                            &nbsp;|&nbsp; {{ $criteria['month'] }} {{ $criteria['year'] }}
                            &nbsp;|&nbsp; {{ $summary['attendance_rate'] }}% @lang('reports.staff_attendance_label')
                            &nbsp;|&nbsp; @lang('reports.staff_metric_net'):
                            <strong>{{ $currency }}{{ number_format($summary['net'], 2) }}</strong>
                            &nbsp;|&nbsp; {{ $summary['pay_not_generated'] }} @lang('reports.staff_metric_pay_not_generated')
                        </div>
                        <a href="#" class="primary-btn small fix-gr-bg" onclick="window.print(); return false;">
                            <span class="ti-printer pr-2"></span>@lang('common.print')
                        </a>
                    </div>
                </div>
            </div>

            {{-- One GET form drives the whole page, and the modal reads its values back
                 out - so the list behind a figure is filtered exactly as the figure was. --}}
            {{ Form::open(['class' => 'form-horizontal', 'route' => 'staff-standard-report', 'method' => 'GET', 'id' => 'staffReportForm']) }}
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
                            <div class="col-lg-2 mb-3 mb-lg-0">
                                <label class="primary_input_label">@lang('hr.month')</label>
                                <select class="primary_select form-control" name="month">
                                    @foreach ($months as $month)
                                        <option value="{{ $month }}"
                                            {{ $criteria['month'] === $month ? 'selected' : '' }}>
                                            {{ $month }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-2 mb-3 mb-lg-0">
                                <label class="primary_input_label">@lang('common.year')</label>
                                <select class="primary_select form-control" name="year">
                                    @foreach ($years as $year)
                                        <option value="{{ $year }}"
                                            {{ (string) $criteria['year'] === (string) $year ? 'selected' : '' }}>
                                            {{ $year }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-3 mb-3 mb-lg-0">
                                <label class="primary_input_label">@lang('hr.role')</label>
                                <select class="primary_select form-control" name="role_id">
                                    <option value="">@lang('reports.staff_all_roles')</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}"
                                            {{ (int) $criteria['roleId'] === (int) $role->id ? 'selected' : '' }}>
                                            {{ $role->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-3 mb-3 mb-lg-0">
                                <label class="primary_input_label">@lang('hr.department')</label>
                                <select class="primary_select form-control" name="department_id">
                                    <option value="">@lang('reports.staff_all_departments')</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->id }}"
                                            {{ (int) $criteria['departmentId'] === (int) $department->id ? 'selected' : '' }}>
                                            {{ $department->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-2">
                                <label class="primary_input_label">&nbsp;</label>
                                <div class="criteria-actions">
                                    <button type="submit" class="primary-btn small fix-gr-bg">
                                        <span class="ti-search pr-2"></span>@lang('common.search')
                                    </button>
                                    <a href="{{ route('staff-standard-report') }}" class="primary-btn small tr-bg">
                                        @lang('reports.reset')
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-15">
                            <div class="col-lg-3">
                                <label class="primary_input_label">@lang('reports.col_status')</label>
                                <select class="primary_select form-control" name="status">
                                    <option value="">@lang('reports.staff_all_statuses')</option>
                                    <option value="active" {{ $criteria['status'] === 'active' ? 'selected' : '' }}>
                                        @lang('reports.staff_metric_active')
                                    </option>
                                    <option value="inactive" {{ $criteria['status'] === 'inactive' ? 'selected' : '' }}>
                                        @lang('reports.staff_metric_inactive')
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            {{ Form::close() }}

            <p class="text-muted mt-15 mb-5">@lang('reports.click_to_view_staff')</p>

            <div class="row mt-10">
                @foreach ($cards as $card)
                    <div class="col-lg-3 col-md-6 mb-20">
                        <div class="white-box h-100" style="border-top:3px solid {{ $card['accent'] }};">
                            <div class="d-flex justify-content-between align-items-center metric-head"
                                style="--metric-accent: {{ $card['accent'] }};">
                                <h5 class="mb-0">{{ $card['title'] }}</h5>
                                <a href="#" class="metric-total staff-metric"
                                    data-metric="{{ $card['total'] }}">
                                    @if ($card['unit'] === 'money')
                                        {{ $currency }}{{ number_format($summary[$card['total']], 2) }}
                                    @else
                                        {{ $summary[$card['total']] }}
                                    @endif
                                </a>
                            </div>
                            @if (!empty($card['hint']))
                                <p class="text-muted mb-10" style="font-size: 12px;">{{ $card['hint'] }}</p>
                            @endif
                            {{-- The whole row is the target, not just the figure: a number is a
                                 small thing to ask anyone to hit, and the label beside it is
                                 part of the same answer. --}}
                            @foreach ($card['rows'] as $metric)
                                <a href="#" class="metric-row staff-metric d-flex justify-content-between align-items-center"
                                    style="--metric-accent: {{ $card['accent'] }};"
                                    data-metric="{{ $metric }}">
                                    <span class="metric-label">@lang('reports.staff_metric_' . $metric)</span>
                                    <span>
                                        <span class="metric-value">
                                            @if ($card['unit'] === 'money')
                                                {{ $currency }}{{ number_format($summary[$metric], 2) }}
                                            @elseif ($card['unit'] === 'days')
                                                {{ $summary[$metric] }}
                                            @else
                                                {{ $summary[$metric] }}
                                            @endif
                                        </span>
                                        @if ($card['unit'] !== 'count')
                                            <span class="metric-count">{{ __('reports.staff_counted', ['count' => $summary['count_' . $metric]]) }}</span>
                                        @endif
                                    </span>
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
                                @lang('reports.staff_all_breakdown')
                                <span class="badge badge-info">{{ __('reports.staff_counted', ['count' => $rows->count()]) }}</span>
                            </h3>
                        </div>

                        @if ($rows->count() == 0)
                            <p class="text-center text-muted mb-0">@lang('reports.no_staff_found')</p>
                        @else
                            <x-table>
                                <div class="breakdown-scroll">
                                <table id="table_id" class="table data-table Crm_table_active3 no-footer dtr-inline collapsed"
                                    cellspacing="0" width="100%">
                                    <thead>
                                        <tr>
                                            <th>@lang('common.sl')</th>
                                            <th>@lang('hr.staff')</th>
                                            <th>@lang('hr.staff_no')</th>
                                            <th>@lang('hr.role')</th>
                                            <th>@lang('hr.department')</th>
                                            <th>@lang('reports.staff_metric_att_present')</th>
                                            <th>@lang('reports.staff_metric_att_late')</th>
                                            <th>@lang('reports.staff_metric_att_half')</th>
                                            <th>@lang('reports.staff_metric_att_absent')</th>
                                            <th>@lang('reports.col_attendance_rate')</th>
                                            <th>@lang('reports.staff_metric_gross') ({{ $currency }})</th>
                                            <th>@lang('reports.staff_metric_deduction') ({{ $currency }})</th>
                                            <th>@lang('reports.staff_metric_net') ({{ $currency }})</th>
                                            <th>@lang('reports.col_payroll')</th>
                                            <th>@lang('reports.col_status')</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($rows as $key => $row)
                                            <tr>
                                                <td>{{ $key + 1 }}</td>
                                                <td>{{ $row['staff']->full_name }}</td>
                                                <td>{{ $row['staff']->staff_no ?: '-' }}</td>
                                                <td>{{ optional($row['staff']->roles)->name ?: '-' }}</td>
                                                <td>{{ optional($row['staff']->departments)->name ?: '-' }}</td>
                                                <td class="amount-cell">{{ $row['att_present'] }}</td>
                                                <td class="amount-cell">{{ $row['att_late'] }}</td>
                                                <td class="amount-cell">{{ $row['att_half'] }}</td>
                                                <td class="amount-cell">{{ $row['att_absent'] }}</td>
                                                <td class="amount-cell {{ is_null($row['attendance_rate']) ? 'rate-none' : ($row['attendance_rate'] < 80 ? 'rate-low' : '') }}">
                                                    {{ is_null($row['attendance_rate']) ? '-' : $row['attendance_rate'] . '%' }}
                                                </td>
                                                <td class="amount-cell">{{ number_format($row['gross'], 2) }}</td>
                                                <td class="amount-cell">{{ number_format($row['deduction'] + $row['tax'], 2) }}</td>
                                                <td class="amount-cell">{{ number_format($row['net'], 2) }}</td>
                                                <td>
                                                    @php
                                                        $payTone = [
                                                            'pay_paid' => 'success',
                                                            'pay_generated' => 'warning',
                                                            'pay_not_generated' => 'secondary',
                                                        ][$row['payroll_status']] ?? 'secondary';
                                                    @endphp
                                                    <span class="badge badge-{{ $payTone }}">@lang('reports.staff_metric_' . $row['payroll_status'])</span>
                                                </td>
                                                <td>
                                                    @if ($row['active'])
                                                        <span class="badge badge-success">@lang('reports.staff_metric_active')</span>
                                                    @else
                                                        <span class="badge badge-danger">@lang('reports.staff_metric_inactive')</span>
                                                    @endif
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

    <div class="modal fade" id="staffMetricModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered student-details" role="document">
            <div class="modal-content"></div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        $(document).on('click', '.staff-metric', function (e) {
            e.preventDefault();

            /* Only one figure is being answered at a time, so the mark moves rather
               than accumulating. */
            $('.staff-metric').removeClass('is-selected');
            $(this).addClass('is-selected');

            /* The criteria are read back off the form rather than stamped onto every
               figure, so a list can never be filtered differently from the count that
               was clicked - there is only one source for both. */
            var criteria = $('#staffReportForm').serialize();

            $('#staffMetricModal .modal-content').html('');
            $('#staffMetricModal').modal('show');

            $.get("{{ route('staff-standard-report-metric') }}", criteria + '&metric=' + $(this).data('metric'))
                .done(function (html) {
                    $('#staffMetricModal .modal-content').html(html);
                })
                .fail(function () {
                    toastr.error(window.jsLang('Operation failed'));
                    $('#staffMetricModal').modal('hide');
                });
        });

        /* The mark says which figure the open list belongs to, so it goes when the
           list does - otherwise a closed modal leaves a row looking selected with
           nothing behind it. */
        $(document).on('hidden.bs.modal', '#staffMetricModal', function () {
            $('.staff-metric').removeClass('is-selected');
        });
    </script>
@endpush
