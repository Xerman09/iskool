@extends('backEnd.master')
@section('title')
    @lang('reports.standard_receipts_report')
@endsection

@section('mainContent')
    @php
        $currency = generalSetting()->currency_symbol ?: '$';

        /* One fixed card. The methods card beside it is built from the data, because
           what a school tenders is whatever it has taken. */
        $cards = [
            /* Two cards because there are two true totals. Applied is what settled
               invoices and ties to the fees report; Cash In is what actually arrived
               and ties to a drawer and a deposit. A wallet settlement is in the
               first and not the second. */
            ['title' => __('reports.receipts_summary'), 'accent' => '#1a9d6a', 'money' => true,
             'total' => 'received', 'rows' => ['received', 'from_wallet', 'fine', 'waiver', 'unapplied'],
             'hint' => __('reports.receipts_hint')],
            ['title' => __('reports.receipts_cash_summary'), 'accent' => '#7048a8', 'money' => true,
             'total' => 'cash_in', 'rows' => ['cash_in', 'topup'],
             'hint' => __('reports.receipts_cash_hint')],
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

        .metric-row .metric-label { color: #6c757d; }
        .metric-row .metric-value { font-weight: 700; color: #2b2b2b; }

        /* How many receipts a figure is made of - quieter than the money, because it
           answers the second question, not the first. */
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
        .metric-row:hover .metric-value { color: var(--metric-accent, #0f8b8d); }

        /* Pressed. Kept small - a row that moves far reads as something being dragged. */
        .metric-row:active {
            background-color: rgba(0, 0, 0, .085);
            transform: translateY(1px);
        }

        .metric-row.is-selected {
            background-color: rgba(0, 0, 0, .06);
            box-shadow: inset 3px 0 0 var(--metric-accent, #0f8b8d);
        }

        .metric-row.is-selected .metric-label,
        .metric-row.is-selected .metric-value { color: var(--metric-accent, #0f8b8d); }

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

        .metric-total:active { transform: translateY(1px); box-shadow: none; }
        .metric-total.is-selected { opacity: 1; box-shadow: 0 0 0 3px rgba(0, 0, 0, .12); }

        /* Search and Reset are a <button> and an <a>: left to their own defaults they
           take different heights and sit on different baselines. */
        .criteria-actions { display: flex; align-items: stretch; gap: 8px; }

        .criteria-actions > .primary-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 40px;
            margin: 0;
            line-height: 1;
            white-space: nowrap;
        }

        .breakdown-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .breakdown-scroll table td,
        .breakdown-scroll table th { white-space: nowrap; }

        /* Money reads as a column of figures, not as prose. */
        .amount-cell { text-align: right; font-variant-numeric: tabular-nums; }
    </style>

    <section class="sms-breadcrumb mb-20">
        <div class="container-fluid">
            <div class="row justify-content-between">
                <h1>@lang('reports.standard_receipts_report')</h1>
                <div class="bc-pages">
                    <a href="{{ route('dashboard') }}">@lang('common.dashboard')</a>
                    <a href="#">@lang('reports.report')</a>
                    <a href="#">@lang('reports.standard_receipts_report')</a>
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
                            <strong>{{ date('M d, Y', strtotime($criteria['dateFrom'])) }}
                                &ndash; {{ date('M d, Y', strtotime($criteria['dateTo'])) }}</strong>
                            &nbsp;|&nbsp; @lang('reports.receipts_metric_received'):
                            <strong>{{ $currency }}{{ number_format($summary['received'], 2) }}</strong>
                            &nbsp;|&nbsp; {{ __('reports.receipts_counted', ['count' => $summary['all']]) }}
                            &nbsp;|&nbsp; {{ $summary['students'] }} @lang('reports.receipts_metric_students')
                            @if ($summary['count_unapplied'] > 0)
                                &nbsp;|&nbsp;
                                <strong class="text-danger">
                                    {{ $summary['count_unapplied'] }} @lang('reports.receipts_metric_unapplied')
                                </strong>
                            @endif
                        </div>
                        <a href="#" class="primary-btn small fix-gr-bg" onclick="window.print(); return false;">
                            <span class="ti-printer pr-2"></span>@lang('common.print')
                        </a>
                    </div>
                </div>
            </div>

            {{ Form::open(['class' => 'form-horizontal', 'route' => 'cash-receipts-report', 'method' => 'GET', 'id' => 'receiptsReportForm']) }}
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
                                <label class="primary_input_label">@lang('fees.payment_method')</label>
                                <select class="primary_select form-control" name="method">
                                    <option value="">@lang('reports.receipts_all_methods')</option>
                                    @foreach ($methods as $method)
                                        <option value="{{ $method }}"
                                            {{ $criteria['method'] === $method ? 'selected' : '' }}>
                                            {{ $method }}
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
                                    <a href="{{ route('cash-receipts-report') }}" class="primary-btn small tr-bg">
                                        @lang('reports.reset')
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            {{ Form::close() }}

            <p class="text-muted mt-15 mb-5">@lang('reports.click_to_view_receipts')</p>

            <div class="row mt-10">
                @foreach ($cards as $card)
                    <div class="col-lg-4 col-md-6 mb-20">
                        <div class="white-box h-100" style="border-top:3px solid {{ $card['accent'] }};">
                            <div class="d-flex justify-content-between align-items-center metric-head"
                                style="--metric-accent: {{ $card['accent'] }};">
                                <h5 class="mb-0">{{ $card['title'] }}</h5>
                                <a href="#" class="metric-total receipts-metric"
                                    data-metric="{{ $card['total'] }}">
                                    {{ $currency }}{{ number_format($summary[$card['total']], 2) }}
                                </a>
                            </div>
                            @if (!empty($card['hint']))
                                <p class="text-muted mb-10" style="font-size: 12px;">{{ $card['hint'] }}</p>
                            @endif
                            @foreach ($card['rows'] as $metric)
                                <a href="#" class="metric-row receipts-metric d-flex justify-content-between align-items-center"
                                    style="--metric-accent: {{ $card['accent'] }};"
                                    data-metric="{{ $metric }}">
                                    <span class="metric-label">@lang('reports.receipts_metric_' . $metric)</span>
                                    <span>
                                        <span class="metric-value">{{ $currency }}{{ number_format($summary[$metric], 2) }}</span>
                                        <span class="metric-count">{{ __('reports.receipts_counted', ['count' => $summary['count_' . $metric]]) }}</span>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                {{-- Built from the data rather than from the settings list: a register is
                     of what was taken, and this is the figure a drawer and a bank
                     deposit are reconciled against. --}}
                <div class="col-lg-4 col-md-6 mb-20">
                    <div class="white-box h-100" style="border-top:3px solid #2f6fed;">
                        <div class="d-flex justify-content-between align-items-center metric-head"
                            style="--metric-accent: #2f6fed;">
                            <h5 class="mb-0">@lang('reports.receipts_by_method')</h5>
                            <a href="#" class="metric-total receipts-metric" data-metric="received">
                                {{ $currency }}{{ number_format($summary['received'], 2) }}
                            </a>
                        </div>
                        @if ($methodTotals->isEmpty())
                            <p class="text-muted mb-0" style="font-size: 12px;">@lang('reports.receipts_none')</p>
                        @else
                            @foreach ($methodTotals as $m)
                                <a href="#" class="metric-row receipts-metric d-flex justify-content-between align-items-center"
                                    style="--metric-accent: #2f6fed;"
                                    data-metric="{{ $m['metric'] }}">
                                    <span class="metric-label">{{ $m['name'] }}</span>
                                    <span>
                                        <span class="metric-value">{{ $currency }}{{ number_format($m['amount'], 2) }}</span>
                                        <span class="metric-count">{{ __('reports.receipts_counted', ['count' => $m['receipts']]) }}</span>
                                    </span>
                                </a>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>

            <div class="row mt-10">
                <div class="col-lg-12">
                    <div class="white-box">
                        <div class="main-title">
                            <h3 class="mb-15">
                                @lang('reports.receipts_all_breakdown')
                                <span class="badge badge-info">{{ __('reports.receipts_counted', ['count' => $rows->count()]) }}</span>
                            </h3>
                        </div>

                        @if ($rows->count() == 0)
                            <p class="text-center text-muted mb-0">@lang('reports.receipts_none')</p>
                        @else
                            <x-table>
                                <div class="breakdown-scroll">
                                <table id="table_id" class="table data-table Crm_table_active3 no-footer dtr-inline collapsed"
                                    cellspacing="0" width="100%">
                                    <thead>
                                        <tr>
                                            <th>@lang('common.sl')</th>
                                            <th>@lang('common.date')</th>
                                            <th>@lang('academics.student')</th>
                                            <th>@lang('student.admission_no')</th>
                                            <th>@lang('reports.receipts_applied_to')</th>
                                            <th>@lang('fees.payment_method')</th>
                                            <th>@lang('accounts.amount') ({{ $currency }})</th>
                                            <th>@lang('reports.fees_metric_fine') ({{ $currency }})</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($rows as $key => $row)
                                            <tr>
                                                <td>{{ $key + 1 }}</td>
                                                <td>{{ $row['date'] ? $row['date']->format('M d, Y') : '-' }}</td>
                                                <td>{{ optional($row['student'])->full_name ?: '-' }}</td>
                                                <td>{{ optional($row['student'])->admission_no ?: '-' }}</td>
                                                <td>
                                                    @if ($row['unapplied'])
                                                        <span class="badge badge-danger">@lang('reports.receipts_metric_unapplied')</span>
                                                    @else
                                                        {{ $row['invoice']->invoice_id ?: '#' . $row['invoice']->id }}
                                                    @endif
                                                </td>
                                                <td>{{ $row['method'] }}</td>
                                                <td class="amount-cell">{{ number_format($row['amount'], 2) }}</td>
                                                <td class="amount-cell">{{ number_format($row['fine'], 2) }}</td>
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

    <div class="modal fade" id="receiptsMetricModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered student-details" role="document">
            <div class="modal-content"></div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        $(document).on('click', '.receipts-metric', function (e) {
            e.preventDefault();

            $('.receipts-metric').removeClass('is-selected');
            $(this).addClass('is-selected');

            /* The criteria are read back off the form rather than stamped onto every
               figure, so a list can never be filtered differently from the figure
               that was clicked - there is only one source for both. */
            var criteria = $('#receiptsReportForm').serialize();

            $('#receiptsMetricModal .modal-content').html('');
            $('#receiptsMetricModal').modal('show');

            $.get("{{ route('cash-receipts-report-metric') }}", criteria + '&metric=' + $(this).data('metric'))
                .done(function (html) {
                    $('#receiptsMetricModal .modal-content').html(html);
                })
                .fail(function () {
                    toastr.error(window.jsLang('Operation failed'));
                    $('#receiptsMetricModal').modal('hide');
                });
        });

        $(document).on('hidden.bs.modal', '#receiptsMetricModal', function () {
            $('.receipts-metric').removeClass('is-selected');
        });
    </script>
@endpush
