@extends('backEnd.master')
@section('title')
    @lang('reports.account_title')
@endsection

@section('mainContent')
    @php
        $currency = generalSetting()->currency_symbol ?: '$';
        $money = fn ($v) => $currency . number_format((float) $v, 2);
        /* The sheet prints the period on screen, so the scope travels with the
           link rather than being read off the request - the page has a default,
           and a request that never named one would print a different period. */
        $scopeParams = ['scope' => $criteria['scope']];
        if ($criteria['scope'] === 'semester') {
            $scopeParams['session_id'] = $criteria['academicId'];
            if ($criteria['selectedSemesterId']) {
                $scopeParams['semester_id'] = $criteria['selectedSemesterId'];
            }
        } elseif ($criteria['scope'] === 'year') {
            $scopeParams['year'] = $criteria['selectedYear'];
        }

        $statementUrl = route('fees-standard-report-statement', array_merge(
            ['student' => $student->id],
            $scopeParams
        ));

        $sessions = $criteria['lists']['sessions'];
        $years = $criteria['lists']['years'];
        $semesters = $criteria['lists']['semesters'];
    @endphp

    <style>
        /* Three figures, because the question has three parts: what has been paid,
           what is still owed, and what is coming. Sized so they are read before the
           tables under them. */
        .acct-fig {
            height: 100%;
            padding: 18px 20px;
            border-radius: 6px;
            border-left: 5px solid;
            background: #f7f8f9;
            /* So the "opens" marker can sit in the card's own corner. */
            position: relative;
        }

        .acct-fig .k { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: #6b7280; }
        .acct-fig .v { font-size: 26px; font-weight: 700; line-height: 1.15; margin: 3px 0; }
        .acct-fig .s { font-size: 12px; color: #6b7280; }

        .acct-paid { border-left-color: #15794f; }
        .acct-paid .v { color: #15794f; }
        .acct-owed { border-left-color: #b03024; }
        .acct-owed .v { color: #b03024; }
        .acct-next { border-left-color: #2f6fed; }
        .acct-next .v { color: #2f6fed; }
        .acct-clear { border-left-color: #6c757d; }
        .acct-clear .v { color: #4b5563; }

        /* Who this is, stated once at the top - an account read without a name on
           it is an account somebody applies to the wrong student. */
        .acct-who { font-size: 13px; color: #4b5563; }
        .acct-who strong { font-size: 16px; color: #1a1a1a; }

        table.acct { width: 100%; border-collapse: collapse; }
        table.acct th, table.acct td { padding: 7px 6px; font-size: 13px; border-bottom: 1px solid rgba(0,0,0,.07); }
        table.acct thead th {
            font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px;
            color: #9aa0a6; border-bottom: 1px solid rgba(0,0,0,.12);
        }
        table.acct .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        table.acct tr.grp td { background: #fafafa; font-weight: 700; }
        table.acct tfoot th { border-top: 2px solid #333; border-bottom: 2px solid #333; font-weight: 700; }
        .pl-neg { color: #b03024; }

        .inst-next { background: #eef4ff; }
        .inst-paid { color: #15794f; font-weight: 600; }
        .inst-late { color: #b03024; font-weight: 600; }

        .note { font-size: 12px; color: #6b7280; }

        .cost-paid { color: #15794f; font-weight: 600; }

        /* A figure that opens says so before it is clicked, not after. */
        .acct-fig.acct-open { cursor: pointer; transition: box-shadow .15s, transform .15s; }
        .acct-fig.acct-open:hover { box-shadow: 0 4px 14px rgba(0,0,0,.09); transform: translateY(-1px); }
        .acct-fig.acct-open:focus { outline: 2px solid #2f6fed; outline-offset: 2px; }
        .acct-fig.acct-open::after {
            content: '\e661'; font-family: 'themify'; font-size: 10px; color: #9aa0a6;
            position: absolute; top: 14px; right: 16px;
        }
        .acct-fig.is-selected { box-shadow: 0 4px 14px rgba(0,0,0,.12); }

        .acct-scope { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px; }
        .acct-scope .fld { display: flex; flex-direction: column; min-width: 170px; }
        .acct-scope .fld label { font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .5px; color: #6b7280; margin-bottom: 4px; }
        .acct-scope .fld select { height: 36px; font-size: 13px; }
        .acct-scope .fld button { height: 36px; }

        /* A payment row says it opens, and says which way it is facing. */
        tr.pay-row { cursor: pointer; }
        tr.pay-row:hover td { background: #f6f8fb; }
        .pay-caret { display: inline-block; color: #9aa0a6; font-size: 10px; margin-right: 6px; transition: transform .15s; }
        tr.pay-row[aria-expanded="true"] .pay-caret { transform: rotate(90deg); color: #2f6fed; }
        tr.pay-row[aria-expanded="true"] td { background: #f6f8fb; }

        /* No padding on the carrier cell, so a shut row takes up no height at
           all; the padding belongs to the panel that opens inside it. */
        table.acct tr.pay-detail > td { padding: 0; border-bottom: 0; }
        .pay-body { background: #fafbfc; border-left: 3px solid #2f6fed; padding: 10px 12px; margin-bottom: 6px; }

        table.pay-lines { width: 100%; border-collapse: collapse; }
        table.pay-lines td { padding: 4px 0; font-size: 12px; border-bottom: 1px dashed rgba(0,0,0,.08); }
        table.pay-lines tr:last-child td { border-bottom: 0; }
        table.pay-lines .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        table.pay-lines tr.pay-extra td { color: #6b7280; font-style: italic; }

        .pay-meta { margin: 8px 0 0; font-size: 12px; color: #6b7280; }
        .pay-meta dt { float: left; clear: left; width: 68px; font-weight: 600; }
        .pay-meta dd { margin: 0 0 2px 76px; word-break: break-word; }
    </style>

    <section class="sms-breadcrumb mb-20">
        <div class="container-fluid">
            <div class="row justify-content-between">
                <h1>@lang('reports.account_title')</h1>
                <div class="bc-pages">
                    <a href="{{ route('dashboard') }}">@lang('common.dashboard')</a>
                    <a href="{{ route('fees-standard-report') }}">@lang('reports.standard_fees_report')</a>
                    <a href="#">@lang('reports.account_title')</a>
                </div>
            </div>
        </div>
    </section>

    <section class="admin-visitor-area up_st_admin_visitor">
        <div class="container-fluid p-0">

            <div class="row mb-20">
                <div class="col-lg-12">
                    <div class="white-box d-flex justify-content-between align-items-center flex-wrap">
                        <div class="acct-who">
                            <strong>{{ $student->full_name }}</strong><br>
                            @lang('student.admission_no'): {{ $student->admission_no ?: '-' }}
                            @if (!isBasicEd() && optional($student->course)->course_name)
                                &nbsp;&middot;&nbsp; {{ $student->course->course_name }}
                            @endif
                            <span class="d-block text-muted" style="font-size: 12px;">
                                {{ implode(' · ', $labels) }}
                            </span>
                        </div>
                        <div>
                            {{-- An account someone is looking at is an account they will
                                 be asked to send, so the sheet is one button away. --}}
                            <a href="{{ $statementUrl }}" target="_blank" class="primary-btn small fix-gr-bg mr-15">
                                <span class="ti-printer pr-2"></span>@lang('reports.fees_statement_print')
                            </a>
                            <a href="{{ route('fees-standard-report') }}" class="primary-btn small tr-bg">
                                @lang('reports.account_back')
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- How wide a period the figures below answer for. Placed above them
                 rather than beside the tables, because it governs every number on
                 the page and not only the lists. --}}
            <div class="row mb-20">
                <div class="col-lg-12">
                    <div class="white-box">
                        <form method="GET" action="{{ route('student-account-view', $student->id) }}"
                            class="acct-scope" id="acctScopeForm">
                            <div class="fld">
                                <label for="acctScope">@lang('reports.account_period')</label>
                                <select name="scope" id="acctScope" class="form-control">
                                    <option value="whole_stay" {{ $criteria['scope'] === 'whole_stay' ? 'selected' : '' }}>
                                        @lang('academics.whole_stay')
                                    </option>
                                    <option value="year" {{ $criteria['scope'] === 'year' ? 'selected' : '' }}>
                                        @lang('reports.account_per_year')
                                    </option>
                                    <option value="semester" {{ $criteria['scope'] === 'semester' ? 'selected' : '' }}>
                                        {{ __('reports.account_per_term', ['term' => termLabel()]) }}
                                    </option>
                                </select>
                            </div>

                            {{-- Both pickers are rendered and one is hidden, so changing
                                 the scope needs no round trip to find out what it offers. --}}
                            <div class="fld" id="acctYearFld">
                                <label for="acctYear">@lang('reports.school_year')</label>
                                <select name="year" id="acctYear" class="form-control">
                                    @foreach ($years as $y)
                                        <option value="{{ $y }}" {{ (string) $criteria['selectedYear'] === (string) $y ? 'selected' : '' }}>
                                            {{ $y }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- A term is a school year and a semester together, because
                                 that is how the invoice is stamped. Both pickers show
                                 for this scope; the school year alone would name a span
                                 of two or three semesters. --}}
                            <div class="fld" id="acctSessionFld">
                                <label for="acctSession">@lang('reports.school_year')</label>
                                <select name="session_id" id="acctSession" class="form-control">
                                    @foreach ($sessions as $session)
                                        <option value="{{ $session->id }}" {{ (int) $criteria['academicId'] === (int) $session->id ? 'selected' : '' }}>
                                            {{ $session->year }}{{ $session->title ? ' - ' . $session->title : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="fld" id="acctSemesterFld">
                                <label for="acctSemester">{{ termLabel() }}</label>
                                <select name="semester_id" id="acctSemester" class="form-control">
                                    {{-- Every semester of that year is a real thing to ask
                                         for, so it leads rather than being the fallback
                                         somebody lands on by not choosing. --}}
                                    <option value="">@lang('reports.whole_year')</option>
                                    @foreach ($semesters as $semester)
                                        <option value="{{ $semester->id }}" {{ (int) $criteria['selectedSemesterId'] === (int) $semester->id ? 'selected' : '' }}>
                                            {{ $semester->semester_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="fld">
                                <button type="submit" class="primary-btn small fix-gr-bg">
                                    @lang('reports.account_apply')
                                </button>
                            </div>
                        </form>
                        <p class="note mb-0 mt-10">@lang('reports.account_period_note')</p>
                    </div>
                </div>
            </div>

            <div class="row mb-20">
                <div class="col-lg-4 mb-20 mb-lg-0">
                    {{-- Every figure opens the rows it was counted from, the way the
                         rest of this report works. --}}
                    <div class="acct-fig acct-paid acct-open" data-part="paid" role="button" tabindex="0">
                        <div class="k">@lang('reports.account_paid')</div>
                        <div class="v">{{ $money($s['paid']) }}</div>
                        <div class="s">{{ __('reports.account_paid_sub', ['count' => $s['payments']->count()]) }}</div>
                    </div>
                </div>

                <div class="col-lg-4 mb-20 mb-lg-0">
                    <div class="acct-fig acct-open {{ $s['balance'] > 0 ? 'acct-owed' : 'acct-clear' }}"
                        data-part="unpaid" role="button" tabindex="0">
                        <div class="k">@lang('reports.account_unpaid')</div>
                        <div class="v">{{ $money(abs($s['balance'])) }}</div>
                        <div class="s">
                            @if ($s['balance'] < 0)
                                @lang('reports.fees_statement_credit')
                            @elseif ($s['overdue_days'] > 0)
                                {{ __('reports.account_overdue_sub', ['days' => $s['overdue_days']]) }}
                            @elseif ($s['balance'] > 0)
                                @lang('reports.account_not_late')
                            @else
                                @lang('reports.account_settled')
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="acct-fig acct-next acct-open" data-part="incoming" role="button" tabindex="0">
                        <div class="k">@lang('reports.account_incoming')</div>
                        @if ($next)
                            <div class="v">{{ $money($next['amount']) }}</div>
                            <div class="s">
                                {{ __('reports.account_due_on', ['date' => \Carbon\Carbon::parse($next['due_date'])->format('M d, Y')]) }}
                                @if (!$next['of_plan'])
                                    <span class="d-block">@lang('reports.account_no_plan')</span>
                                @endif
                            </div>
                        @else
                            {{-- Nothing scheduled is an answer, and a blank box is not. --}}
                            <div class="v">&mdash;</div>
                            <div class="s">@lang('reports.account_nothing_due')</div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- What the whole course comes to, folded away. It is not part of the
                 account - nothing here has been billed yet - and putting it beside
                 Paid, Unpaid and Incoming would invite it to be read as a fourth
                 balance. It is the answer to a different question, asked less
                 often, so it waits behind a toggle. --}}
            @if ($cost)
                <div class="row mb-20">
                    <div class="col-lg-12">
                        <div class="white-box">
                            <a class="d-flex justify-content-between align-items-center"
                                data-toggle="collapse" href="#programmeCost" role="button"
                                aria-expanded="false" aria-controls="programmeCost"
                                style="color: inherit; text-decoration: none;">
                                <span>
                                    <strong>@lang('reports.cost_title')</strong>
                                    <span class="d-block text-muted" style="font-size: 12px;">
                                        @lang('reports.cost_hint')
                                    </span>
                                </span>
                                <span class="text-right">
                                    <span style="font-size: 18px; font-weight: 700;">{{ $money($cost['total']) }}</span>
                                    {{-- What has been paid against the course belongs on the
                                         shut row: it is half the answer, and a reader who
                                         never opens the panel would otherwise see only the
                                         bill. --}}
                                    <span class="d-block text-muted" style="font-size: 12px;">
                                        {{ __('reports.cost_units', ['units' => rtrim(rtrim(number_format($cost['units'], 1), '0'), '.')]) }}
                                        &nbsp;&middot;&nbsp;
                                        <span class="cost-paid">{{ $money($cost['paid']) }}</span> @lang('reports.cost_paid_so_far')
                                        &nbsp;&middot;&nbsp; @lang('reports.cost_show')
                                    </span>
                                </span>
                            </a>

                            <div class="collapse mt-15" id="programmeCost">
                                <table class="acct">
                                    <thead>
                                        <tr>
                                            <th>@lang('academics.year_level')</th>
                                            <th>{{ termLabel() }}</th>
                                            <th class="num">@lang('reports.cost_units_col')</th>
                                            <th class="num">@lang('reports.cost_tuition')</th>
                                            <th class="num">@lang('reports.cost_misc')</th>
                                            <th class="num">@lang('dashboard.total')</th>
                                            <th class="num">@lang('reports.account_paid')</th>
                                            <th class="num">@lang('reports.cost_left')</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($cost['levels'] as $level)
                                            @foreach ($level['terms'] as $i => $t)
                                                <tr>
                                                    {{-- The year level named once per block, so the eye
                                                         groups the terms under it. --}}
                                                    <td>{{ $i === 0 ? $level['level'] : '' }}</td>
                                                    <td>{{ $t['term'] }}</td>
                                                    <td class="num">{{ rtrim(rtrim(number_format($t['units'], 1), '0'), '.') }}</td>
                                                    <td class="num">{{ number_format($t['tuition'], 2) }}</td>
                                                    <td class="num">{{ number_format($t['misc'], 2) }}</td>
                                                    <td class="num">{{ number_format($t['total'], 2) }}</td>
                                                    <td class="num {{ $t['paid'] > 0 ? 'cost-paid' : '' }}">
                                                        {{ number_format($t['paid'], 2) }}
                                                        {{-- A term with nothing invoiced says so, because a
                                                             zero on its own reads as a bill that has gone
                                                             unpaid rather than one not yet raised. --}}
                                                        @if ($t['billed'] <= 0)
                                                            <span class="d-block text-muted" style="font-size: 11px; font-weight: 400;">
                                                                @lang('reports.cost_not_billed')
                                                            </span>
                                                        @endif
                                                    </td>
                                                    <td class="num">{{ number_format($t['left'], 2) }}</td>
                                                </tr>
                                            @endforeach
                                            {{-- A subtotal only where there is something to subtotal
                                                 against. On a one-level curriculum it would repeat the
                                                 footer, and saying the same figure twice invites the
                                                 reader to look for the difference. --}}
                                            @if (count($cost['levels']) > 1)
                                                <tr class="grp">
                                                    <td colspan="5">{{ $level['level'] }}</td>
                                                    <td class="num">{{ number_format($level['total'], 2) }}</td>
                                                    <td class="num">{{ number_format($level['paid'], 2) }}</td>
                                                    <td class="num">{{ number_format($level['left'], 2) }}</td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="5">
                                                @lang('reports.cost_whole_course')
                                                @if ($cost['course'])
                                                    <span class="text-muted" style="font-weight: 400;">&mdash; {{ $cost['course'] }}</span>
                                                @endif
                                            </th>
                                            <th class="num">{{ $money($cost['total']) }}</th>
                                            <th class="num">{{ $money($cost['paid']) }}</th>
                                            <th class="num">{{ $money($cost['left']) }}</th>
                                        </tr>
                                    </tfoot>
                                </table>
                                <p class="note mt-15 mb-0">
                                    @lang('reports.cost_note', ['rate' => $money($cost['per_unit'])])
                                    @lang('reports.cost_paid_note')
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if ($s['prior_balance'] !== null && $s['prior_balance'] > 0)
                <div class="row mb-20">
                    <div class="col-lg-12">
                        <div class="alert alert-warning mb-0" style="font-size: 13px;">
                            @lang('reports.fees_statement_prior', ['amount' => $money($s['prior_balance'])])
                        </div>
                    </div>
                </div>
            @endif

            @if ($s['schedules']->isNotEmpty())
                <div class="row">
                    <div class="col-lg-12 mb-20">
                        <div class="white-box">
                            <div class="main-title"><h3 class="mb-15">@lang('reports.fees_statement_schedule')</h3></div>
                            @foreach ($s['schedules'] as $plan)
                                <h5 class="mb-10" style="font-size: 13px;">
                                    {{ optional($plan['assign']->planType)->name ?: __('reports.fees_statement_plan') }}
                                    @if (optional($plan['invoice'])->invoice_id)
                                        <span class="text-muted">&mdash; {{ $plan['invoice']->invoice_id }}</span>
                                    @endif
                                </h5>
                                <table class="acct mb-20">
                                    <thead>
                                        <tr>
                                            <th>@lang('reports.fees_installment_no')</th>
                                            <th>@lang('fees::feesModule.due_date')</th>
                                            <th class="num">@lang('accounts.amount')</th>
                                            <th class="num">@lang('reports.fees_installment_remaining')</th>
                                            <th>@lang('reports.col_status')</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($plan['schedule'] as $i)
                                            @php
                                                $late = $i['status'] !== 'paid'
                                                    && $i['due_date']
                                                    && \Carbon\Carbon::parse($i['due_date'])->startOfDay()->isPast();
                                            @endphp
                                            <tr class="{{ $i['is_next'] ? 'inst-next' : '' }}">
                                                <td>{{ $i['no'] }}</td>
                                                <td>{{ $i['due_date'] ? \Carbon\Carbon::parse($i['due_date'])->format('M d, Y') : '-' }}</td>
                                                <td class="num">{{ number_format($i['amount'], 2) }}</td>
                                                <td class="num">{{ $i['remaining'] > 0 ? number_format($i['remaining'], 2) : '-' }}</td>
                                                <td class="{{ $i['status'] === 'paid' ? 'inst-paid' : ($late ? 'inst-late' : '') }}">
                                                    @lang('reports.fees_installment_' . $i['status'])
                                                    @if ($i['is_next'])
                                                        &nbsp;&larr; @lang('reports.fees_installment_next')
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endforeach
                            <p class="note mb-0">@lang('reports.fees_statement_schedule_note')</p>
                        </div>
                    </div>
                </div>
            @endif

            <div class="row">
                <div class="col-lg-7 mb-20">
                    <div class="white-box h-100">
                        <div class="main-title"><h3 class="mb-15">@lang('reports.fees_statement_charges')</h3></div>
                        @if ($s['breakdown']->isEmpty())
                            <p class="note mb-0">@lang('reports.fees_statement_no_charges')</p>
                        @else
                            <table class="acct">
                                <thead>
                                    <tr>
                                        <th>@lang('academics.description')</th>
                                        <th class="num">@lang('reports.fees_metric_assessed')</th>
                                        <th class="num">@lang('reports.fees_metric_collected')</th>
                                        <th class="num">@lang('reports.col_balance')</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($s['breakdown'] as $g)
                                        <tr class="grp">
                                            <td colspan="4">
                                                {{-- A link here too. The same invoice number plain in one
                                                     table and clickable in the other would read as a fault
                                                     in the page rather than a distinction. --}}
                                                <a href="{{ route('fees.fees-invoice-view', [$g['invoice']->id, 'view']) }}"
                                                    target="_blank">
                                                    {{ $g['invoice']->invoice_id ?: '#' . $g['invoice']->id }}
                                                    <span class="ti-new-window pl-1" style="font-size: 10px; font-weight: 400;"></span>
                                                </a>
                                                @if ($g['invoice']->due_date)
                                                    <span class="text-muted" style="font-weight: 400;">
                                                        &mdash; @lang('fees::feesModule.due_date'):
                                                        {{ \Carbon\Carbon::parse($g['invoice']->due_date)->format('M d, Y') }}
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                        @foreach ($g['lines'] as $line)
                                            <tr>
                                                <td style="padding-left: 16px;">
                                                    {{ $line->sm_item_id ? optional($line->item)->item_name : optional($line->feesType)->name }}
                                                </td>
                                                <td class="num">{{ number_format($line->amount, 2) }}</td>
                                                <td class="num">{{ number_format($line->paid_amount, 2) }}</td>
                                                <td class="num">
                                                    {{ number_format(((float) $line->amount + (float) $line->fine) - ((float) $line->paid_amount + (float) $line->weaver), 2) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th>@lang('dashboard.total')</th>
                                        <th class="num">{{ number_format($s['assessed'], 2) }}</th>
                                        <th class="num">{{ number_format($s['paid'], 2) }}</th>
                                        <th class="num {{ $s['balance'] > 0 ? 'pl-neg' : '' }}">
                                            {{ number_format($s['balance'], 2) }}
                                        </th>
                                    </tr>
                                </tfoot>
                            </table>
                        @endif
                    </div>
                </div>

                <div class="col-lg-5 mb-20">
                    <div class="white-box h-100">
                        <div class="main-title">
                            <h3 class="mb-5">@lang('academics.payment_history')</h3>
                            @if ($s['payments']->isNotEmpty())
                                <p class="note mb-15">@lang('reports.pay_open_hint')</p>
                            @endif
                        </div>
                        @if ($s['payments']->isEmpty())
                            <p class="note mb-0">@lang('academics.no_transactions_yet')</p>
                        @else
                            <table class="acct">
                                <thead>
                                    <tr>
                                        <th>@lang('fees.create_date')</th>
                                        <th>@lang('fees.payment_method')</th>
                                        <th class="num">@lang('accounts.amount')</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($s['payments'] as $payment)
                                        @php
                                            /* The loaded lines, not the paid_amount accessor: that one
                                               re-queries the relation on every read. */
                                            $lines = $payment->transcationDetails;
                                            $payFine = (float) $lines->sum('fine');
                                            $payWaiver = (float) $lines->sum('weaver');
                                            $payCharge = (float) ($payment->service_charge ?: 0);
                                            $payTopUp = (float) ($payment->add_wallet_money ?: 0);
                                            $payId = 'pay' . $payment->id;
                                        @endphp
                                        <tr class="pay-row" data-toggle="collapse" data-target="#{{ $payId }}"
                                            aria-expanded="false" aria-controls="{{ $payId }}">
                                            <td>
                                                <span class="pay-caret">&#9656;</span>
                                                {{ $payment->created_at ? $payment->created_at->format('M d, Y') : '-' }}
                                            </td>
                                            <td>{{ $payment->payment_method ?: '-' }}</td>
                                            <td class="num">{{ number_format($lines->sum('paid_amount'), 2) }}</td>
                                        </tr>
                                        {{-- The breakdown rides in a div inside the cell rather than on the
                                             row itself: a collapsed table row keeps its padding and leaves a
                                             stripe behind, and Bootstrap's display toggle fights table-row. --}}
                                        <tr class="pay-detail">
                                            <td colspan="3">
                                                <div class="collapse" id="{{ $payId }}">
                                                    <div class="pay-body">
                                                        @if ($lines->isEmpty())
                                                            <p class="note mb-0">@lang('reports.pay_no_lines')</p>
                                                        @else
                                                            <table class="pay-lines">
                                                                <tbody>
                                                                    @foreach ($lines as $line)
                                                                        <tr>
                                                                            <td>
                                                                                {{ optional($line->transcationFeesType)->name
                                                                                    ?: ($line->note ?: __('reports.pay_unnamed')) }}
                                                                            </td>
                                                                            <td class="num">{{ number_format((float) $line->paid_amount, 2) }}</td>
                                                                        </tr>
                                                                    @endforeach
                                                                    {{-- Named only when there is one. A row of
                                                                         zeroes reads as a charge that was made. --}}
                                                                    @if ($payFine > 0)
                                                                        <tr class="pay-extra">
                                                                            <td>@lang('fees.fine')</td>
                                                                            <td class="num">{{ number_format($payFine, 2) }}</td>
                                                                        </tr>
                                                                    @endif
                                                                    @if ($payWaiver > 0)
                                                                        <tr class="pay-extra">
                                                                            <td>@lang('fees.waiver')</td>
                                                                            <td class="num">-{{ number_format($payWaiver, 2) }}</td>
                                                                        </tr>
                                                                    @endif
                                                                    @if ($payCharge > 0)
                                                                        <tr class="pay-extra">
                                                                            <td>@lang('reports.pay_service_charge')</td>
                                                                            <td class="num">{{ number_format($payCharge, 2) }}</td>
                                                                        </tr>
                                                                    @endif
                                                                </tbody>
                                                            </table>
                                                        @endif

                                                        {{-- Where the money landed and what it can be traced by,
                                                             so a disputed payment can be looked up at the till. --}}
                                                        <dl class="pay-meta">
                                                            @if (optional($payment->feesInvoiceInfo)->invoice_id)
                                                                <dt>@lang('fees.invoice')</dt>
                                                                <dd>
                                                                    {{-- Opens the invoice itself: a payment is
                                                                         queried by asking what it was against,
                                                                         and the answer is one page away. --}}
                                                                    <a href="{{ route('fees.fees-invoice-view', [$payment->feesInvoiceInfo->id, 'view']) }}"
                                                                        target="_blank">
                                                                        {{ $payment->feesInvoiceInfo->invoice_id }}
                                                                        <span class="ti-new-window pl-1" style="font-size: 10px;"></span>
                                                                    </a>
                                                                </dd>
                                                            @endif
                                                            @if ($payment->invoice_number)
                                                                <dt>@lang('fees.receipt')</dt>
                                                                <dd>{{ $payment->invoice_number }}</dd>
                                                            @endif
                                                            @if ($payment->payment_note)
                                                                <dt>@lang('fees.note')</dt>
                                                                <dd>{{ $payment->payment_note }}</dd>
                                                            @endif
                                                        </dl>

                                                        @if ($payTopUp > 0)
                                                            <p class="note mb-0">
                                                                @lang('reports.pay_wallet_topup', ['amount' => $money($payTopUp)])
                                                            </p>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="2">@lang('dashboard.total')</th>
                                        <th class="num">{{ number_format($s['paid'], 2) }}</th>
                                    </tr>
                                </tfoot>
                            </table>
                        @endif

                        @if ($s['wallet'] > 0)
                            <p class="note mt-15 mb-0">
                                @lang('reports.fees_statement_wallet', ['amount' => $money($s['wallet'])])
                            </p>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </section>

    <div class="modal fade" id="acctDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered student-details" role="document">
            <div class="modal-content"></div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        /* Only the picker the chosen scope uses is shown. The other is left in the
           form but hidden - a disabled field would stop submitting and the page
           would lose which term to return to when the reader switches back. Plain
           JS and no jQuery dependency, so the page works whatever the footer
           loads. */
        (function () {
            var form = document.getElementById('acctScopeForm');
            if (!form) { return; }

            var scope = document.getElementById('acctScope');
            var yearFld = document.getElementById('acctYearFld');
            var sessionFld = document.getElementById('acctSessionFld');
            var semesterFld = document.getElementById('acctSemesterFld');

            function sync() {
                var term = scope.value === 'semester';
                yearFld.style.display = scope.value === 'year' ? '' : 'none';
                sessionFld.style.display = term ? '' : 'none';
                semesterFld.style.display = term ? '' : 'none';
            }

            scope.addEventListener('change', sync);
            sync();
        })();
    </script>

    <script>
        /* The rows behind a figure. The period is read off the same picker the page
           was drawn with, so a list can never be of a different period from the
           figure that was clicked - there is one source for both. */
        $(document).on('click', '.acct-open', function () {
            var part = $(this).data('part');

            $('.acct-open').removeClass('is-selected');
            $(this).addClass('is-selected');

            $('#acctDetailModal .modal-content').html('');
            $('#acctDetailModal').modal('show');

            $.get("{{ route('student-account-detail', $student->id) }}",
                $('#acctScopeForm').serialize() + '&part=' + part)
                .done(function (html) {
                    $('#acctDetailModal .modal-content').html(html);
                })
                .fail(function () {
                    toastr.error(window.jsLang ? window.jsLang('Operation failed') : 'Operation failed');
                    $('#acctDetailModal').modal('hide');
                });
        });

        /* A card is a button, so the keyboard reaches it the way it reaches one. */
        $(document).on('keydown', '.acct-open', function (e) {
            if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
                e.preventDefault();
                $(this).trigger('click');
            }
        });

        /* The mark says which figure the open list belongs to, so it goes when the
           list does - otherwise a closed modal leaves a card looking selected with
           nothing behind it. */
        $(document).on('hidden.bs.modal', '#acctDetailModal', function () {
            $('.acct-open').removeClass('is-selected');
        });
    </script>
@endsection
