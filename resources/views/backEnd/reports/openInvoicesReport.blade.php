@extends('backEnd.master')
@section('title')
    @lang('reports.open_invoices_report')
@endsection

@section('mainContent')
    @php
        $currency = generalSetting()->currency_symbol ?: '$';
        $showProgram = !isBasicEd();

        $cards = [
            ['title' => __('reports.open_summary'), 'accent' => '#c0392b', 'money' => true,
             'total' => 'outstanding', 'rows' => ['billed', 'collected', 'outstanding'],
             'hint' => __('reports.open_hint')],
            ['title' => __('reports.fees_aging_summary'), 'accent' => '#e08b1e', 'money' => true,
             'total' => 'outstanding',
             'rows' => ['aging_current', 'aging_1_30', 'aging_31_60', 'aging_61_90', 'aging_90_plus'],
             'hint' => __('reports.fees_aging_hint')],
            ['title' => __('reports.open_kind_summary'), 'accent' => '#2f6fed', 'money' => true,
             'total' => 'outstanding', 'rows' => ['fees', 'store', 'on_plan'],
             'hint' => __('reports.open_kind_hint')],
        ];
    @endphp

    <style>
        .metric-head { padding-bottom: 10px; margin-bottom: 6px; border-bottom: 1px solid rgba(0,0,0,.06); }
        .metric-row, .metric-total { text-decoration: none; transition: background-color .15s ease, box-shadow .15s ease, transform .08s ease; }
        .metric-row {
            padding: 9px 10px; margin: 0 -10px; border-radius: 4px; color: inherit;
            /* Transparent rather than absent, so the bar on hover does not shift the
               text sideways when it appears. */
            box-shadow: inset 3px 0 0 transparent; cursor: pointer;
        }
        .metric-row .metric-label { color: #6c757d; }
        .metric-row .metric-value { font-weight: 700; color: #2b2b2b; }
        /* How many documents a figure is made of - quieter than the money. */
        .metric-row .metric-count { display: block; font-size: 11px; font-weight: 400; color: #9aa0a6; text-align: right; }
        .metric-row:hover, .metric-row:focus-visible {
            background-color: rgba(0,0,0,.045); box-shadow: inset 3px 0 0 var(--metric-accent,#0f8b8d); text-decoration: none;
        }
        .metric-row:hover .metric-label, .metric-row:hover .metric-value { color: var(--metric-accent,#0f8b8d); }
        .metric-row:active { background-color: rgba(0,0,0,.085); transform: translateY(1px); }
        .metric-row.is-selected { background-color: rgba(0,0,0,.06); box-shadow: inset 3px 0 0 var(--metric-accent,#0f8b8d); }
        .metric-row.is-selected .metric-label, .metric-row.is-selected .metric-value { color: var(--metric-accent,#0f8b8d); }
        .metric-total {
            display: inline-block; min-width: 34px; padding: 3px 10px; border-radius: 12px;
            font-size: 13px; font-weight: 700; text-align: center; color: #fff;
            background-color: var(--metric-accent,#0f8b8d); opacity: .9;
        }
        .metric-total:hover, .metric-total:focus-visible { opacity: 1; color: #fff; text-decoration: none; box-shadow: 0 2px 6px rgba(0,0,0,.18); }
        .metric-total:active { transform: translateY(1px); box-shadow: none; }
        .metric-total.is-selected { opacity: 1; box-shadow: 0 0 0 3px rgba(0,0,0,.12); }
        /* Search and Reset are a <button> and an <a>: one flex row gives both the
           same height and centres them against each other. */
        .criteria-actions { display: flex; align-items: stretch; gap: 8px; }
        .criteria-actions > .primary-btn {
            display: inline-flex; align-items: center; justify-content: center;
            height: 40px; margin: 0; line-height: 1; white-space: nowrap;
        }
        .breakdown-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .breakdown-scroll table td, .breakdown-scroll table th { white-space: nowrap; }
        .amount-cell { text-align: right; font-variant-numeric: tabular-nums; }
        .amount-owing { color: #c0392b; font-weight: 600; }
    </style>

    <section class="sms-breadcrumb mb-20">
        <div class="container-fluid">
            <div class="row justify-content-between">
                <h1>@lang('reports.open_invoices_report')</h1>
                <div class="bc-pages">
                    <a href="{{ route('dashboard') }}">@lang('common.dashboard')</a>
                    <a href="#">@lang('reports.report')</a>
                    <a href="#">@lang('reports.open_invoices_report')</a>
                </div>
            </div>
        </div>
    </section>

    <section class="admin-visitor-area up_st_admin_visitor">
        <div class="container-fluid p-0">

            {{-- How many documents and how much, which is what a trial balance is
                 asked for before anything else on the page is read. --}}
            <div class="row mb-20">
                <div class="col-lg-12">
                    <div class="white-box d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <strong>{{ __('reports.open_counted', ['count' => $summary['count_outstanding']]) }}</strong>
                            @lang('reports.open_still_owing')
                            &nbsp;|&nbsp;
                            <strong class="amount-owing">{{ $currency }}{{ number_format($summary['outstanding'], 2) }}</strong>
                            &nbsp;|&nbsp; {{ $summary['students'] }} @lang('reports.receipts_metric_students')
                            &nbsp;|&nbsp; @lang('reports.fees_as_of'):
                            <strong>{{ date('M d, Y', strtotime($criteria['asOf'] ?: date('Y-m-d'))) }}</strong>
                            @if ($summary['count_unbilled'] > 0)
                                &nbsp;|&nbsp;
                                <strong class="text-warning">{{ $summary['count_unbilled'] }} @lang('reports.open_metric_unbilled')</strong>
                            @endif
                        </div>
                        <a href="#" class="primary-btn small fix-gr-bg" onclick="window.print(); return false;">
                            <span class="ti-printer pr-2"></span>@lang('common.print')
                        </a>
                    </div>
                </div>
            </div>

            {{ Form::open(['class' => 'form-horizontal', 'route' => 'open-invoices-report', 'method' => 'GET', 'id' => 'openInvoicesForm']) }}
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
                                <label class="primary_input_label">@lang('reports.fees_as_of')</label>
                                <input type="date" name="as_of" value="{{ $criteria['asOf'] }}"
                                    max="{{ date('Y-m-d') }}" class="primary_input_field form-control">
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
                                    <a href="{{ route('open-invoices-report') }}" class="primary-btn small tr-bg">
                                        @lang('reports.reset')
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-15">
                            <div class="col-lg-12">
                                <label class="primary_input_label" for="includeSettled">
                                    <input type="checkbox" name="include_settled" id="includeSettled" value="1"
                                        {{ $criteria['includeSettled'] ? 'checked' : '' }}>
                                    @lang('reports.open_include_settled')
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            {{ Form::close() }}

            <p class="text-muted mt-15 mb-5">@lang('reports.click_to_view_invoices')</p>

            <div class="row mt-10">
                @foreach ($cards as $card)
                    <div class="col-lg-4 col-md-6 mb-20">
                        <div class="white-box h-100" style="border-top:3px solid {{ $card['accent'] }};">
                            <div class="d-flex justify-content-between align-items-center metric-head"
                                style="--metric-accent: {{ $card['accent'] }};">
                                <h5 class="mb-0">{{ $card['title'] }}</h5>
                                <a href="#" class="metric-total open-metric" data-metric="{{ $card['total'] }}">
                                    {{ $currency }}{{ number_format($summary[$card['total']], 2) }}
                                </a>
                            </div>
                            @if (!empty($card['hint']))
                                <p class="text-muted mb-10" style="font-size: 12px;">{{ $card['hint'] }}</p>
                            @endif
                            @foreach ($card['rows'] as $metric)
                                <a href="#" class="metric-row open-metric d-flex justify-content-between align-items-center"
                                    style="--metric-accent: {{ $card['accent'] }};"
                                    data-metric="{{ $metric }}">
                                    <span class="metric-label">@lang('reports.open_metric_' . $metric)</span>
                                    <span>
                                        <span class="metric-value">{{ $currency }}{{ number_format($summary[$metric], 2) }}</span>
                                        <span class="metric-count">{{ __('reports.open_counted', ['count' => $summary['count_' . $metric]]) }}</span>
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
                                @lang('reports.open_all_breakdown')
                                <span class="badge badge-info">{{ __('reports.open_counted', ['count' => $rows->count()]) }}</span>
                            </h3>
                        </div>

                        @if ($rows->count() == 0)
                            <p class="text-center text-muted mb-0">@lang('reports.open_none')</p>
                        @else
                            <x-table>
                                <div class="breakdown-scroll">
                                <table id="table_id" class="table data-table Crm_table_active3 no-footer dtr-inline collapsed"
                                    cellspacing="0" width="100%">
                                    <thead>
                                        <tr>
                                            <th>@lang('common.sl')</th>
                                            <th>@lang('reports.open_invoice_no')</th>
                                            <th>@lang('fees.create_date')</th>
                                            <th>@lang('fees::feesModule.due_date')</th>
                                            <th>@lang('academics.student')</th>
                                            @if ($showProgram)
                                                <th>@lang('academics.program')</th>
                                            @endif
                                            <th>@lang('reports.col_charge_type')</th>
                                            <th>@lang('reports.fees_metric_assessed') ({{ $currency }})</th>
                                            <th>@lang('reports.fees_metric_waiver') ({{ $currency }})</th>
                                            <th>@lang('reports.fees_metric_collected') ({{ $currency }})</th>
                                            <th>@lang('reports.col_balance') ({{ $currency }})</th>
                                            <th>@lang('reports.col_overdue')</th>
                                            <th>@lang('reports.col_plan')</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($rows as $key => $row)
                                            <tr>
                                                <td>{{ $key + 1 }}</td>
                                                <td>{{ $row['invoice']->invoice_id ?: '#' . $row['invoice']->id }}</td>
                                                <td>
                                                    {{ $row['invoice']->create_date ? \Carbon\Carbon::parse($row['invoice']->create_date)->format('M d, Y') : '-' }}
                                                </td>
                                                <td>
                                                    {{ $row['invoice']->due_date ? \Carbon\Carbon::parse($row['invoice']->due_date)->format('M d, Y') : '-' }}
                                                </td>
                                                <td>{{ optional($row['student'])->full_name ?: '-' }}</td>
                                                @if ($showProgram)
                                                    <td>{{ $row['course_name'] ?: '-' }}</td>
                                                @endif
                                                <td>
                                                    @if ($row['unbilled'])
                                                        {{-- Raised with nothing on it: not a receivable, and
                                                             worth seeing because it should not be sitting there. --}}
                                                        <span class="badge badge-warning">@lang('reports.open_metric_unbilled')</span>
                                                    @else
                                                        @lang('reports.fees_type_' . ($row['invoice']->type ?: 'fees'))
                                                    @endif
                                                </td>
                                                <td class="amount-cell">{{ number_format($row['billed'], 2) }}</td>
                                                <td class="amount-cell">{{ number_format($row['waiver'], 2) }}</td>
                                                <td class="amount-cell">{{ number_format($row['paid'], 2) }}</td>
                                                <td class="amount-cell {{ $row['balance'] > 0 ? 'amount-owing' : '' }}">
                                                    {{ number_format($row['balance'], 2) }}
                                                </td>
                                                <td class="amount-cell">{{ $row['overdue_days'] > 0 ? $row['overdue_days'] : '-' }}</td>
                                                <td>
                                                    @if ($row['on_plan'])
                                                        <span class="badge badge-info">@lang('reports.fees_statement_plan')</span>
                                                    @else
                                                        <span class="text-muted">&ndash;</span>
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

    <div class="modal fade" id="openMetricModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered student-details" role="document">
            <div class="modal-content"></div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        $(document).on('click', '.open-metric', function (e) {
            e.preventDefault();

            $('.open-metric').removeClass('is-selected');
            $(this).addClass('is-selected');

            /* The criteria are read back off the form rather than stamped onto every
               figure, so a list can never be filtered differently from the figure
               that was clicked - there is only one source for both. */
            var criteria = $('#openInvoicesForm').serialize();

            $('#openMetricModal .modal-content').html('');
            $('#openMetricModal').modal('show');

            $.get("{{ route('open-invoices-report-metric') }}", criteria + '&metric=' + $(this).data('metric'))
                .done(function (html) {
                    $('#openMetricModal .modal-content').html(html);
                })
                .fail(function () {
                    toastr.error(window.jsLang('Operation failed'));
                    $('#openMetricModal').modal('hide');
                });
        });

        $(document).on('hidden.bs.modal', '#openMetricModal', function () {
            $('.open-metric').removeClass('is-selected');
        });
    </script>
@endpush
