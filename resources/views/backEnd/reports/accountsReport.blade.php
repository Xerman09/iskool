@extends('backEnd.master')
@section('title')
    @lang('reports.standard_accounts_report')
@endsection

@section('mainContent')
    @php
        $currency = generalSetting()->currency_symbol ?: '$';

        /* The two fixed cards. The by-head cards below them are built from the data
           instead, because the heads are whatever this school has made. */
        $cards = [
            /* netRow draws the subtraction out as its own line under the three it is
               made of. The badge carries the same figure, but a badge is a label -
               set below income, expense and payroll it reads as the sum of them,
               which is what it is. */
            ['title' => __('reports.accounts_cashflow_summary'), 'accent' => '#1a9d6a',
             'total' => 'net', 'rows' => ['income', 'expense', 'payroll'], 'netRow' => 'net',
             'hint' => __('reports.accounts_cashflow_hint')],
            ['title' => __('reports.accounts_entries_summary'), 'accent' => '#e08b1e', 'count' => true,
             'total' => 'all', 'rows' => ['income_entries', 'expense_entries', 'no_head', 'no_account'],
             'hint' => __('reports.accounts_entries_hint')],
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

        /* How many entries a figure is made of - quieter than the money, because it
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

        /* The sum of the rows above it, so it is ruled off from them and set in the
           heavier weight a total earns. */
        .metric-row-net {
            margin-top: 6px;
            padding-top: 11px;
            border-top: 1px solid rgba(0, 0, 0, .14);
        }

        .metric-row-net .metric-label {
            color: #2b2b2b;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: .4px;
        }

        .metric-row-net .metric-value { font-size: 15px; }

        /* "Others" is a total, not an answer - it gathers several heads, so there is
           no one list behind it to open. It reads as a row without behaving as one. */
        .metric-row-static {
            padding: 9px 10px;
            margin: 0 -10px;
            cursor: default;
        }

        .metric-row-static .metric-label {
            color: #9aa0a6;
            font-style: italic;
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

        .amount-in {
            color: #15794f;
            font-weight: 600;
        }

        .amount-out {
            color: #b03024;
            font-weight: 600;
        }
    </style>

    <section class="sms-breadcrumb mb-20">
        <div class="container-fluid">
            <div class="row justify-content-between">
                <h1>@lang('reports.standard_accounts_report')</h1>
                <div class="bc-pages">
                    <a href="{{ route('dashboard') }}">@lang('common.dashboard')</a>
                    <a href="#">@lang('reports.report')</a>
                    <a href="#">@lang('reports.standard_accounts_report')</a>
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
                            &nbsp;|&nbsp; @lang('reports.accounts_metric_income'):
                            <strong class="amount-in">{{ $currency }}{{ number_format($summary['income'], 2) }}</strong>
                            &nbsp;|&nbsp; @lang('reports.accounts_out'):
                            <strong class="amount-out">{{ $currency }}{{ number_format($summary['out'], 2) }}</strong>
                            &nbsp;|&nbsp; @lang('reports.accounts_metric_net'):
                            <strong class="{{ $summary['net'] < 0 ? 'amount-out' : 'amount-in' }}">
                                {{ $currency }}{{ number_format($summary['net'], 2) }}
                            </strong>
                        </div>
                        <a href="#" class="primary-btn small fix-gr-bg" onclick="window.print(); return false;">
                            <span class="ti-printer pr-2"></span>@lang('common.print')
                        </a>
                    </div>
                </div>
            </div>

            {{-- One GET form drives the whole page, and the modal reads its values back
                 out - so the list behind a figure is filtered exactly as the figure was. --}}
            {{ Form::open(['class' => 'form-horizontal', 'route' => 'accounts-standard-report', 'method' => 'GET', 'id' => 'accountsReportForm']) }}
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
                                <label class="primary_input_label">@lang('reports.accounts_date_from')</label>
                                <input type="date" name="date_from" value="{{ $criteria['dateFrom'] }}"
                                    class="primary_input_field form-control">
                            </div>
                            <div class="col-lg-2 mb-3 mb-lg-0">
                                <label class="primary_input_label">@lang('reports.accounts_date_to')</label>
                                <input type="date" name="date_to" value="{{ $criteria['dateTo'] }}"
                                    class="primary_input_field form-control">
                            </div>
                            <div class="col-lg-3 mb-3 mb-lg-0">
                                <label class="primary_input_label">@lang('reports.accounts_entry_type')</label>
                                <select class="primary_select form-control" name="kind">
                                    <option value="">@lang('reports.accounts_all_kinds')</option>
                                    @foreach (['income', 'expense', 'payroll'] as $kind)
                                        <option value="{{ $kind }}"
                                            {{ $criteria['kind'] === $kind ? 'selected' : '' }}>
                                            @lang('reports.accounts_metric_' . $kind)
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-3 mb-3 mb-lg-0">
                                <label class="primary_input_label">@lang('accounts.bank_account')</label>
                                <select class="primary_select form-control" name="account_id">
                                    <option value="">@lang('reports.accounts_all_accounts')</option>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}"
                                            {{ (int) $criteria['accountId'] === (int) $account->id ? 'selected' : '' }}>
                                            {{ $account->bank_name }}
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
                                    <a href="{{ route('accounts-standard-report') }}" class="primary-btn small tr-bg">
                                        @lang('reports.reset')
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            {{ Form::close() }}

            <p class="text-muted mt-15 mb-5">@lang('reports.click_to_view_entries')</p>

            <div class="row mt-10">
                @foreach ($cards as $card)
                    <div class="col-lg-3 col-md-6 mb-20">
                        <div class="white-box h-100" style="border-top:3px solid {{ $card['accent'] }};">
                            <div class="d-flex justify-content-between align-items-center metric-head"
                                style="--metric-accent: {{ $card['accent'] }};">
                                <h5 class="mb-0">{{ $card['title'] }}</h5>
                                <a href="#" class="metric-total accounts-metric"
                                    data-metric="{{ $card['total'] }}">
                                    @if (empty($card['count']))
                                        {{ $currency }}{{ number_format($summary[$card['total']], 2) }}
                                    @else
                                        {{ $summary[$card['total']] }}
                                    @endif
                                </a>
                            </div>
                            @if (!empty($card['hint']))
                                <p class="text-muted mb-10" style="font-size: 12px;">{{ $card['hint'] }}</p>
                            @endif
                            @foreach ($card['rows'] as $metric)
                                <a href="#" class="metric-row accounts-metric d-flex justify-content-between align-items-center"
                                    style="--metric-accent: {{ $card['accent'] }};"
                                    data-metric="{{ $metric }}">
                                    <span class="metric-label">@lang('reports.accounts_metric_' . $metric)</span>
                                    <span>
                                        <span class="metric-value">
                                            @if (empty($card['count']))
                                                {{ $currency }}{{ number_format($summary[$metric], 2) }}
                                            @else
                                                {{ $summary[$metric] }}
                                            @endif
                                        </span>
                                        @if (empty($card['count']))
                                            <span class="metric-count">{{ __('reports.accounts_counted', ['count' => $summary['count_' . $metric]]) }}</span>
                                        @endif
                                    </span>
                                </a>
                            @endforeach

                            {{-- The subtraction, drawn as its own line under the three it
                                 is made of. Ruled off above so it reads as their sum
                                 rather than as a fourth figure beside them, and coloured
                                 by sign because a negative net is the whole point of
                                 looking. --}}
                            @if (!empty($card['netRow']))
                                @php $net = $summary[$card['netRow']]; @endphp
                                <a href="#" class="metric-row metric-row-net accounts-metric d-flex justify-content-between align-items-center"
                                    style="--metric-accent: {{ $card['accent'] }};"
                                    data-metric="{{ $card['netRow'] }}">
                                    <span class="metric-label">@lang('reports.accounts_metric_' . $card['netRow'])</span>
                                    <span class="metric-value {{ $net < 0 ? 'amount-out' : ($net > 0 ? 'amount-in' : '') }}">
                                        {{ $currency }}{{ number_format($net, 2) }}
                                    </span>
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach

                {{-- The accrual side of the same period. The cash flow card is money
                     that moved; this is money that was earned, and for a school they
                     are different and the second is larger. Reading only the cash
                     cannot tell a quiet term from a collection problem.

                     These open invoices, not the cash entries the rest of the page
                     lists - a different grain, which the modal heading says rather
                     than the figure being left unopenable while every other opens. --}}
                <div class="col-lg-3 col-md-6 mb-20">
                    <div class="white-box h-100" style="border-top:3px solid #7048a8;">
                        <div class="d-flex justify-content-between align-items-center metric-head"
                            style="--metric-accent: #7048a8;">
                            <h5 class="mb-0">@lang('reports.accounts_billed_summary')</h5>
                            <a href="#" class="metric-total accounts-metric" data-metric="billed">
                                {{ $currency }}{{ number_format($billed['total'], 2) }}
                            </a>
                        </div>
                        <p class="text-muted mb-10" style="font-size: 12px;">@lang('reports.accounts_billed_hint')</p>

                        <a href="#" class="metric-row accounts-metric d-flex justify-content-between align-items-center"
                            style="--metric-accent: #7048a8;" data-metric="billed_paid">
                            <span class="metric-label">@lang('reports.accounts_billed_collected')</span>
                            <span class="metric-value">{{ $currency }}{{ number_format($billed['paid'], 2) }}</span>
                        </a>
                        <a href="#" class="metric-row accounts-metric d-flex justify-content-between align-items-center"
                            style="--metric-accent: #7048a8;" data-metric="billed_unpaid">
                            <span class="metric-label">@lang('reports.accounts_billed_unpaid')</span>
                            <span class="metric-value {{ $billed['unpaid'] > 0 ? 'amount-out' : '' }}">
                                {{ $currency }}{{ number_format($billed['unpaid'], 2) }}
                            </span>
                        </a>

                        {{-- Already netted out of the total above, and shown anyway: a
                             figure that quietly reduces another is a figure nobody can
                             question. It has no place in the cash flow card beside this
                             one - a waiver is not money that moved - so this is the only
                             card it can appear on. --}}
                        @if ($billed['waived'] > 0)
                            <a href="#" class="metric-row accounts-metric d-flex justify-content-between align-items-center"
                                style="--metric-accent: #7048a8;" data-metric="billed_waived">
                                <span class="metric-label">@lang('reports.accounts_billed_waived')</span>
                                <span class="metric-value">&ndash; {{ $currency }}{{ number_format($billed['waived'], 2) }}</span>
                            </a>
                        @endif

                        @if ($billed['total'] > 0)
                            <p class="text-muted mb-0 mt-10" style="font-size: 12px;">
                                @lang('reports.accounts_collection_note', ['rate' => $billed['rate']])
                                @if ($billed['unpaid'] > 0)
                                    <a href="{{ route('open-invoices-report') }}">@lang('reports.accounts_see_open')</a>
                                @endif
                            </p>
                        @endif

                        {{-- The net read as a direction, not only as an amount: "worse
                             than last month" lands before any figure does. --}}
                        @php $delta = round($summary['net'] - $prior['net'], 2); @endphp
                        <p class="mb-0 mt-10 pt-10" style="font-size: 12px; border-top: 1px solid rgba(0,0,0,.08);">
                            @if ($delta > 0)
                                <span class="amount-in">&#9650; {{ $currency }}{{ number_format($delta, 2) }} @lang('reports.accounts_better')</span>
                            @elseif ($delta < 0)
                                <span class="amount-out">&#9660; {{ $currency }}{{ number_format(abs($delta), 2) }} @lang('reports.accounts_worse')</span>
                            @else
                                <span class="text-muted">@lang('reports.accounts_unchanged')</span>
                            @endif
                            <span class="text-muted d-block">@lang('reports.accounts_vs_prior')</span>
                        </p>
                    </div>
                </div>

                {{-- The two by-head cards. Built from the data rather than from a fixed
                     list, because the heads are whatever this school has set up - and
                     each line clicks through to its own head's entries. --}}
                @foreach ([['income', $incomeHeadTotals, '#2f6fed'], ['expense', $expenseHeadTotals, '#c0392b']] as [$kind, $heads, $accent])
                    <div class="col-lg-3 col-md-6 mb-20">
                        <div class="white-box h-100" style="border-top:3px solid {{ $accent }};">
                            <div class="d-flex justify-content-between align-items-center metric-head"
                                style="--metric-accent: {{ $accent }};">
                                <h5 class="mb-0">@lang('reports.accounts_by_head_' . $kind)</h5>
                                {{-- The card's own figure opens every head, not the four it
                                     has room for: a school with thirty would otherwise see
                                     four and a line saying "26 others" it could not reach. --}}
                                <a href="#" class="metric-total accounts-metric" data-metric="heads_{{ $kind }}">
                                    {{ $currency }}{{ number_format($summary[$kind], 2) }}
                                </a>
                            </div>
                            @if ($heads['top']->isEmpty())
                                <p class="text-muted mb-0" style="font-size: 12px;">@lang('reports.accounts_no_entries')</p>
                            @else
                                @foreach ($heads['top'] as $head)
                                    <a href="#" class="metric-row accounts-metric d-flex justify-content-between align-items-center"
                                        style="--metric-accent: {{ $accent }};"
                                        data-metric="{{ $head['metric'] }}">
                                        <span class="metric-label">{{ $head['name'] }}</span>
                                        <span>
                                            <span class="metric-value">{{ $currency }}{{ number_format($head['amount'], 2) }}</span>
                                            <span class="metric-count">{{ __('reports.accounts_counted', ['count' => $head['entries']]) }}</span>
                                        </span>
                                    </a>
                                @endforeach
                                @if ($heads['others'])
                                    <div class="metric-row-static d-flex justify-content-between align-items-center">
                                        <span class="metric-label">{{ $heads['others']['name'] }}</span>
                                        <span>
                                            <span class="metric-value">{{ $currency }}{{ number_format($heads['others']['amount'], 2) }}</span>
                                            <span class="metric-count">{{ __('reports.accounts_counted', ['count' => $heads['others']['entries']]) }}</span>
                                        </span>
                                    </div>
                                @endif
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
                                @lang('reports.accounts_all_breakdown')
                                <span class="badge badge-info">{{ __('reports.accounts_counted', ['count' => $rows->count()]) }}</span>
                            </h3>
                        </div>

                        @if ($rows->count() == 0)
                            <p class="text-center text-muted mb-0">@lang('reports.accounts_no_entries')</p>
                        @else
                            <x-table>
                                <div class="breakdown-scroll">
                                <table id="table_id" class="table data-table Crm_table_active3 no-footer dtr-inline collapsed"
                                    cellspacing="0" width="100%">
                                    <thead>
                                        <tr>
                                            <th>@lang('common.sl')</th>
                                            <th>@lang('common.date')</th>
                                            <th>@lang('reports.accounts_entry_type')</th>
                                            <th>@lang('reports.accounts_head')</th>
                                            <th>@lang('common.name')</th>
                                            <th>@lang('accounts.bank_account')</th>
                                            <th>@lang('fees.payment_method')</th>
                                            <th>@lang('accounts.amount') ({{ $currency }})</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($rows as $key => $row)
                                            <tr>
                                                <td>{{ $key + 1 }}</td>
                                                <td>{{ $row['date'] ? date('M d, Y', strtotime($row['date'])) : '-' }}</td>
                                                <td>
                                                    @php
                                                        $tone = ['income' => 'success', 'expense' => 'danger', 'payroll' => 'warning'][$row['kind']] ?? 'secondary';
                                                    @endphp
                                                    <span class="badge badge-{{ $tone }}">@lang('reports.accounts_metric_' . $row['kind'])</span>
                                                </td>
                                                <td>{{ $row['head_name'] ?: __('reports.accounts_no_head') }}</td>
                                                <td>{{ $row['name'] ?: '-' }}</td>
                                                <td>{{ $row['account_name'] ?: '-' }}</td>
                                                <td>{{ $row['method'] ?: '-' }}</td>
                                                <td class="amount-cell {{ $row['kind'] === 'income' ? 'amount-in' : 'amount-out' }}">
                                                    {{ $row['kind'] === 'income' ? '' : '-' }}{{ number_format($row['amount'], 2) }}
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

    <div class="modal fade" id="accountsMetricModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered student-details" role="document">
            <div class="modal-content"></div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        $(document).on('click', '.accounts-metric', function (e) {
            e.preventDefault();

            /* Only one figure is being answered at a time, so the mark moves rather
               than accumulating. */
            $('.accounts-metric').removeClass('is-selected');
            $(this).addClass('is-selected');

            /* The criteria are read back off the form rather than stamped onto every
               figure, so a list can never be filtered differently from the count that
               was clicked - there is only one source for both. */
            var criteria = $('#accountsReportForm').serialize();

            $('#accountsMetricModal .modal-content').html('');
            $('#accountsMetricModal').modal('show');

            $.get("{{ route('accounts-standard-report-metric') }}", criteria + '&metric=' + $(this).data('metric'))
                .done(function (html) {
                    $('#accountsMetricModal .modal-content').html(html);
                })
                .fail(function () {
                    toastr.error(window.jsLang('Operation failed'));
                    $('#accountsMetricModal').modal('hide');
                });
        });

        /* The mark says which figure the open list belongs to, so it goes when the
           list does - otherwise a closed modal leaves a row looking selected with
           nothing behind it. */
        $(document).on('hidden.bs.modal', '#accountsMetricModal', function () {
            $('.accounts-metric').removeClass('is-selected');
        });
    </script>
@endpush
