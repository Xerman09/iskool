@extends('backEnd.master')
@section('title')
    @lang('reports.activity_audit_report')
@endsection

@section('mainContent')
    @php
        $currency = generalSetting()->currency_symbol ?: '$';
        $tone = ['invoice_raised' => 'info', 'line_added' => 'secondary', 'payment' => 'success',
                 'income' => 'success', 'expense' => 'danger', 'payroll' => 'warning'];

        /* Three questions, in the order money moves through a school: what was
           promised, what came in against it, what went out. */
        $cards = [
            ['title' => __('reports.activity_billing_summary'), 'accent' => '#2f6fed',
             'total' => 'billing', 'rows' => ['invoice_raised', 'line_added'],
             'hint' => __('reports.activity_billing_hint')],
            ['title' => __('reports.activity_in_summary'), 'accent' => '#1a9d6a',
             'total' => 'money_in', 'rows' => ['payment', 'income'],
             'hint' => __('reports.activity_in_hint')],
            ['title' => __('reports.activity_out_summary'), 'accent' => '#c0392b',
             'total' => 'money_out', 'rows' => ['expense', 'payroll'],
             'hint' => __('reports.activity_out_hint')],
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
        .criteria-actions { display: flex; align-items: stretch; gap: 8px; }
        .criteria-actions > .primary-btn {
            display: inline-flex; align-items: center; justify-content: center;
            height: 40px; margin: 0; line-height: 1; white-space: nowrap;
        }
        .breakdown-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .breakdown-scroll table td, .breakdown-scroll table th { white-space: nowrap; }
        .amount-cell { text-align: right; font-variant-numeric: tabular-nums; }
        .amount-in { color: #15794f; font-weight: 600; }
        .amount-out { color: #b03024; font-weight: 600; }
        /* A posting is on the page but out of every total, so it reads quieter than
           the money it merely records a second time. */
        tr.is-posting td { opacity: .65; }
    </style>

    <section class="sms-breadcrumb mb-20">
        <div class="container-fluid">
            <div class="row justify-content-between">
                <h1>@lang('reports.activity_audit_report')</h1>
                <div class="bc-pages">
                    <a href="{{ route('dashboard') }}">@lang('common.dashboard')</a>
                    <a href="#">@lang('reports.report')</a>
                    <a href="#">@lang('reports.activity_audit_report')</a>
                </div>
            </div>
        </div>
    </section>

    <section class="admin-visitor-area up_st_admin_visitor">
        <div class="container-fluid p-0">

            <div class="row mb-20">
                <div class="col-lg-12">
                    <div class="white-box d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <strong>{{ date('M d, Y', strtotime($criteria['dateFrom'])) }}
                                &ndash; {{ date('M d, Y', strtotime($criteria['dateTo'])) }}</strong>
                            &nbsp;|&nbsp; {{ __('reports.activity_counted', ['count' => $summary['all']]) }}
                            &nbsp;|&nbsp; @lang('reports.activity_metric_money_in'):
                            <strong class="amount-in">{{ $currency }}{{ number_format($summary['money_in'], 2) }}</strong>
                            &nbsp;|&nbsp; @lang('reports.activity_metric_money_out'):
                            <strong class="amount-out">{{ $currency }}{{ number_format($summary['money_out'], 2) }}</strong>
                        </div>
                        <a href="#" class="primary-btn small fix-gr-bg" onclick="window.print(); return false;">
                            <span class="ti-printer pr-2"></span>@lang('common.print')
                        </a>
                    </div>
                </div>
            </div>

            {{ Form::open(['class' => 'form-horizontal', 'route' => 'activity-audit-report', 'method' => 'GET', 'id' => 'activityForm']) }}
            <div class="row">
                <div class="col-lg-12">
                    <div class="white-box">
                        <div class="row mb-15">
                            <div class="col-lg-12">
                                <input type="text" name="search" value="{{ request('search') }}"
                                    class="primary_input_field form-control" placeholder="@lang('common.search')">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-3 mb-3 mb-lg-0">
                                <label class="primary_input_label">@lang('reports.accounts_date_from')</label>
                                <input type="date" name="date_from" value="{{ $criteria['dateFrom'] }}"
                                    class="primary_input_field form-control">
                            </div>
                            <div class="col-lg-3 mb-3 mb-lg-0">
                                <label class="primary_input_label">@lang('reports.accounts_date_to')</label>
                                <input type="date" name="date_to" value="{{ $criteria['dateTo'] }}"
                                    class="primary_input_field form-control">
                            </div>
                            <div class="col-lg-3 mb-3 mb-lg-0">
                                <label class="primary_input_label">@lang('reports.activity_kind')</label>
                                <select class="primary_select form-control" name="kind">
                                    <option value="">@lang('reports.activity_all_kinds')</option>
                                    @foreach ($criteria['lists']['kinds'] as $kind)
                                        <option value="{{ $kind }}" {{ $criteria['kind'] === $kind ? 'selected' : '' }}>
                                            @lang('reports.activity_metric_' . $kind)
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-3">
                                <label class="primary_input_label">&nbsp;</label>
                                <div class="criteria-actions">
                                    <button type="submit" class="primary-btn small fix-gr-bg">
                                        <span class="ti-search pr-2"></span>@lang('common.search')
                                    </button>
                                    <a href="{{ route('activity-audit-report') }}" class="primary-btn small tr-bg">
                                        @lang('reports.reset')
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            {{ Form::close() }}

            {{-- Said once, plainly: the page reports what happened and when, and
                 cannot say who, because the tables do not record it. --}}
            <div class="alert alert-info mt-15 mb-0" style="font-size: 13px;">
                @lang('reports.activity_no_actor_note')
            </div>

            <p class="text-muted mt-15 mb-5">@lang('reports.click_to_view_activity')</p>

            <div class="row mt-10">
                @foreach ($cards as $card)
                    <div class="col-lg-4 col-md-6 mb-20">
                        <div class="white-box h-100" style="border-top:3px solid {{ $card['accent'] }};">
                            <div class="d-flex justify-content-between align-items-center metric-head"
                                style="--metric-accent: {{ $card['accent'] }};">
                                <h5 class="mb-0">{{ $card['title'] }}</h5>
                                <a href="#" class="activity-metric metric-total" data-metric="{{ $card['total'] }}">
                                    {{ $currency }}{{ number_format($summary[$card['total']], 2) }}
                                </a>
                            </div>
                            <p class="text-muted mb-10" style="font-size: 12px;">{{ $card['hint'] }}</p>
                            @foreach ($card['rows'] as $metric)
                                <a href="#" class="metric-row activity-metric d-flex justify-content-between align-items-center"
                                    style="--metric-accent: {{ $card['accent'] }};" data-metric="{{ $metric }}">
                                    <span class="metric-label">@lang('reports.activity_metric_' . $metric)</span>
                                    <span>
                                        <span class="metric-value">{{ $currency }}{{ number_format($summary[$metric], 2) }}</span>
                                        <span class="metric-count">{{ __('reports.activity_counted', ['count' => $summary['count_' . $metric]]) }}</span>
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
                                @lang('reports.activity_all_breakdown')
                                <span class="badge badge-info">{{ __('reports.activity_counted', ['count' => $rows->count()]) }}</span>
                                @if ($summary['duplicates'] > 0)
                                    <span class="badge badge-light">
                                        {{ $summary['duplicates'] }} @lang('reports.activity_posting')
                                    </span>
                                @endif
                            </h3>
                        </div>

                        @if ($rows->count() == 0)
                            <p class="text-center text-muted mb-0">@lang('reports.activity_none')</p>
                        @else
                            <x-table>
                                <div class="breakdown-scroll">
                                <table id="table_id" class="table data-table Crm_table_active3 no-footer dtr-inline collapsed"
                                    cellspacing="0" width="100%">
                                    <thead>
                                        <tr>
                                            <th>@lang('common.date')</th>
                                            <th>@lang('reports.activity_kind')</th>
                                            <th>@lang('reports.open_invoice_no')</th>
                                            <th>@lang('common.name')</th>
                                            <th>@lang('reports.accounts_head')</th>
                                            <th>@lang('accounts.amount') ({{ $currency }})</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($rows as $row)
                                            <tr class="{{ !empty($row['duplicate_of_receipt']) ? 'is-posting' : '' }}">
                                                <td>{{ $row['at'] ? $row['at']->format('M d, Y H:i') : '-' }}</td>
                                                <td>
                                                    <span class="badge badge-{{ $tone[$row['kind']] ?? 'secondary' }}">
                                                        @lang('reports.activity_metric_' . $row['kind'])
                                                    </span>
                                                    @if (!empty($row['duplicate_of_receipt']))
                                                        <span class="badge badge-light">@lang('reports.activity_posting')</span>
                                                    @endif
                                                </td>
                                                <td>{{ $row['reference'] ?: '-' }}</td>
                                                <td>{{ $row['who'] ?: '-' }}</td>
                                                <td>{{ $row['detail'] ?: '-' }}</td>
                                                <td class="amount-cell {{ $row['direction'] === 'out' ? 'amount-out' : ($row['direction'] === 'in' ? 'amount-in' : '') }}">
                                                    {{ $row['direction'] === 'out' ? '-' : '' }}{{ number_format($row['amount'], 2) }}
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

    <div class="modal fade" id="activityModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered student-details" role="document">
            <div class="modal-content"></div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        $(document).on('click', '.activity-metric', function (e) {
            e.preventDefault();

            $('.activity-metric').removeClass('is-selected');
            $(this).addClass('is-selected');

            /* The criteria are read back off the form rather than stamped onto every
               figure, so a list can never be filtered differently from the figure
               that was clicked - there is only one source for both. */
            var criteria = $('#activityForm').serialize();

            $('#activityModal .modal-content').html('');
            $('#activityModal').modal('show');

            $.get("{{ route('activity-audit-report-metric') }}", criteria + '&metric=' + $(this).data('metric'))
                .done(function (html) {
                    $('#activityModal .modal-content').html(html);
                })
                .fail(function () {
                    toastr.error(window.jsLang('Operation failed'));
                    $('#activityModal').modal('hide');
                });
        });

        $(document).on('hidden.bs.modal', '#activityModal', function () {
            $('.activity-metric').removeClass('is-selected');
        });
    </script>
@endpush
