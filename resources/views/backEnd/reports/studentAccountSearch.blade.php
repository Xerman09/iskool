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
        /* The heading keeps to the left of its row on purpose. The theme pins the
           quick search and the export buttons into the strip above the table -
           position: absolute, top: -56px, one centred and one hard right - so
           that strip is theirs, and anything spanning the full width lands under
           them. The same reason the other list pages put their title in a narrow
           column. */
        .sum { font-size: 13px; color: #4b5563; }
        .sum strong { color: #b03024; }
        .hint { font-size: 12px; color: #9aa0a6; display: block; }

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
                        <div class="row">
                            <div class="col-lg-5 no-gutters">
                                <div class="main-title">
                                    <h3 class="mb-5">@lang('reports.account_all_students')</h3>
                                    <span class="hint mb-10">@lang('reports.account_list_hint')</span>
                                    <div class="sum mb-15">
                                        {{ __('reports.students_counted', ['count' => $students->count()]) }}
                                        &nbsp;&middot;&nbsp;
                                        {{ __('reports.account_owing_count', ['count' => $owing->count()]) }}
                                        &nbsp;&middot;&nbsp;
                                        <strong>{{ $currency }}{{ number_format($owedTotal, 2) }}</strong>
                                        @lang('reports.account_owed_total')
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if ($students->isEmpty())
                            <p class="text-center text-muted mb-0">@lang('reports.no_students_found')</p>
                        @else
                            <x-table>
                                {{-- Its own id and no Crm_table_active3: that class is
                                     started by account.js with a config of its own, and
                                     two inits on one table fight each other. --}}
                                <table id="studentAccountTable" class="table no-footer dtr-inline"
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
                            </x-table>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@include('backEnd.partials.data_table_js')

@push('script')
<script>
    $(document).ready(function () {
        if (!$('#studentAccountTable').length) {
            return;
        }

        /* responsive: true is the whole point of this init - it puts the +/- in
           the first column and folds whatever does not fit into the row's own
           panel, instead of letting the table run off the card. The rest matches
           Pending Enrollees so the two lists read the same. */
        $('#studentAccountTable').DataTable({
            bLengthChange: false,
            bDestroy: true,
            responsive: true,
            /* The Action column holds a button, so it is never sorted on and
               never exported - a control is not a value. */
            columnDefs: [{ orderable: false, targets: -1 }],
            language: {
                search: "<i class='ti-search'></i>",
                searchPlaceholder: window.jsLang('quick_search'),
                paginate: {
                    next: "<i class='ti-arrow-right'></i>",
                    previous: "<i class='ti-arrow-left'></i>"
                }
            },
            dom: "Bfrtip",
            buttons: [
                {
                    extend: 'copyHtml5',
                    text: '<i class="fa fa-files-o"></i>',
                    titleAttr: window.jsLang('copy_table'),
                    exportOptions: { columns: ':not(:last-child)' }
                },
                {
                    extend: 'excelHtml5',
                    text: '<i class="fa fa-file-excel-o"></i>',
                    titleAttr: window.jsLang('export_to_excel'),
                    exportOptions: { columns: ':not(:last-child)' }
                },
                {
                    extend: 'print',
                    text: '<i class="fa fa-print"></i>',
                    titleAttr: window.jsLang('print'),
                    exportOptions: { columns: ':not(:last-child)' }
                }
            ]
        });

        /* The search box and the buttons are deliberately left where DataTables
           puts them. The theme pins both against the wrapper - absolute, top:
           -56px, one centred and one hard right - so carrying them into the
           heading carries those offsets along, and they land on the breadcrumb
           above the card. */
    });
</script>
@endpush
