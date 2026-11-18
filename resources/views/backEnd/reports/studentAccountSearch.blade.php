@extends('backEnd.master')
@section('title')
    @lang('reports.account_title')
@endsection

@section('mainContent')
    @php
        $currency = generalSetting()->currency_symbol ?: '$';
        $showProgram = !isBasicEd();

        /* Counted here rather than in the controller: the figures are of the rows
           already in hand, and a second pass over the database to say how many
           there are would be a query for something already known. */
        $owing = $students->filter(fn ($s) => (float) ($balances[$s->id] ?? 0) > 0);
        $owedTotal = $owing->sum(fn ($s) => (float) $balances[$s->id]);
    @endphp

    <style>
        .acct-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .acct-head .sum { font-size: 13px; color: #4b5563; }
        .acct-head .sum strong { color: #b03024; }
        .acct-head .hint { font-size: 12px; color: #9aa0a6; }

        /* Ten columns is wider than the card on a laptop and the table is started
           without the Responsive extension, so nothing folds the extra ones away.
           Scrolling sideways keeps every column readable and the card whole. */
        .list-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .list-scroll table td, .list-scroll table th { white-space: nowrap; }

        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .owes { color: #b03024; font-weight: 600; }
        .credit { color: #15794f; font-weight: 600; }
        .settled { color: #9aa0a6; }
    </style>

    <section class="sms-breadcrumb mb-20">
        <div class="container-fluid">
            <div class="row justify-content-between">
                <h1>@lang('reports.account_title')</h1>
                <div class="bc-pages">
                    <a href="{{ route('dashboard') }}">@lang('common.dashboard')</a>
                    <a href="#">@lang('reports.account_title')</a>
                </div>
            </div>
        </div>
    </section>

    <section class="admin-visitor-area up_st_admin_visitor">
        <div class="container-fluid p-0">
            <div class="row">
                <div class="col-lg-12">
                    <div class="white-box">
                        <div class="main-title acct-head mb-15">
                            <div>
                                <h3 class="mb-0">@lang('reports.account_all_students')</h3>
                                <span class="hint">@lang('reports.account_list_hint')</span>
                            </div>
                            <div class="sum">
                                {{ __('reports.students_counted', ['count' => $students->count()]) }}
                                &nbsp;&middot;&nbsp;
                                {{ __('reports.account_owing_count', ['count' => $owing->count()]) }}
                                &nbsp;&middot;&nbsp;
                                <strong>{{ $currency }}{{ number_format($owedTotal, 2) }}</strong>
                                @lang('reports.account_owed_total')
                            </div>
                        </div>

                        @if ($students->isEmpty())
                            <p class="text-center text-muted mb-0">@lang('reports.no_students_found')</p>
                        @else
                            <x-table>
                                <div class="list-scroll">
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
                                            <th>@lang('reports.account_owes_all_years') ({{ $currency }})</th>
                                            <th>@lang('reports.col_status')</th>
                                            <th>@lang('common.action')</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($students as $key => $s)
                                            @php $bal = (float) ($balances[$s->id] ?? 0); @endphp
                                            <tr>
                                                <td>{{ $key + 1 }}</td>
                                                <td>
                                                    <a href="{{ route('student-account-view', $s->id) }}">{{ $s->full_name }}</a>
                                                </td>
                                                <td>{{ $s->admission_no ?: '-' }}</td>
                                                @if ($showProgram)
                                                    <td>{{ optional($s->course)->course_name ?: '-' }}</td>
                                                @endif
                                                <td>{{ optional($s->class)->class_name ?: '-' }}</td>
                                                <td class="num {{ $bal > 0 ? 'owes' : ($bal < 0 ? 'credit' : 'settled') }}">
                                                    {{ number_format($bal, 2) }}
                                                </td>
                                                <td>
                                                    {{-- A student who has left still owes what they owed, and
                                                         is the one somebody is most likely to come looking
                                                         for - so they are listed, and said to be gone. --}}
                                                    @if ((int) $s->active_status === 1)
                                                        <span class="badge badge-success">@lang('reports.staff_metric_active')</span>
                                                    @else
                                                        <span class="badge badge-secondary">@lang('reports.staff_metric_inactive')</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <a href="{{ route('student-account-view', $s->id) }}"
                                                        class="primary-btn small fix-gr-bg">
                                                        @lang('reports.account_view')
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
@endsection
