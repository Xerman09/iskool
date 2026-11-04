@extends('backEnd.master')
@section('title')
@lang('academics.pending_enrollees')
@endsection

@section('mainContent')

<section class="sms-breadcrumb mb-20">
    <div class="container-fluid">
        <div class="row justify-content-between">
            <h1>@lang('academics.pending_enrollees')</h1>
            <div class="bc-pages">
                <a href="{{route('dashboard')}}">@lang('common.dashboard')</a>
                <a href="#">@lang('academics.academics')</a>
                <a href="#">@lang('academics.pending_enrollees')</a>
            </div>
        </div>
    </div>
</section>

<section class="admin-visitor-area up_st_admin_visitor">
    <div class="container-fluid p-0">

        {{-- Basic education has no programs, so the filter that narrows by one is
             skipped there, as is the Program column below. --}}
        @php $showProgram = !isBasicEd(); @endphp

        <div class="row">
            <div class="col-lg-12">
                <div class="white-box">
                    {{-- Title, filter and table in the one card, the way the Fees Invoice
                         list is laid out. The filter used to float above the card in a
                         row of its own, so the page read as two unrelated blocks. --}}
                    <div class="main-title mb-10">
                        <h3 class="mb-5">
                            @lang('academics.pending_enrollees')
                            <span class="badge badge-warning">{{ __('academics.pending_enrollees_count', ['count' => $rows->count()]) }}</span>
                        </h3>
                        <p class="text-muted mb-0">@lang('academics.pending_enrollees_hint')</p>
                    </div>

                    {{-- One toolbar line: the criteria on the left, and DataTables' own
                         search and export buttons moved into the right of it after the
                         table is built. Left to itself the search sat on a line of its
                         own below this, which pushed the table down and left the
                         criteria stranded above a gap. --}}
                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-10" id="pendingToolbar">
                        <div id="pendingCriteria" class="mr-15">
                            @if($showProgram)
                            {{ Form::open(['class' => 'form-horizontal mb-0', 'route' => 'pending-enrollees', 'method' => 'GET']) }}
                                <select class="primary_select form-control" name="course_id" onchange="this.form.submit()"
                                    style="min-width: 260px;">
                                    <option data-display="@lang('fees.all')" value="">@lang('academics.program') &mdash; @lang('fees.all')</option>
                                    @foreach($courses as $c)
                                    <option value="{{$c->id}}" {{ (string) request('course_id') === (string) $c->id ? 'selected' : '' }}>{{$c->course_name}}</option>
                                    @endforeach
                                </select>
                            {{ Form::close() }}
                            @endif
                        </div>
                        <div id="pendingToolbarRight" class="d-flex align-items-center"></div>
                    </div>

                    {{-- Not this screen's work - there is nothing to bill until they
                         register - but nothing else lists them either, so the count is
                         said out loud instead of leaving them unaccounted for. --}}
                    @if($awaitingRegistrationCount > 0)
                    <div class="row">
                        <div class="col-lg-12">
                            <p class="text-danger mb-15">
                                {{ __('academics.awaiting_registration_note', ['count' => $awaitingRegistrationCount]) }}
                            </p>
                        </div>
                    </div>
                    @endif

                    @if($rows->count() == 0)
                    <p class="text-center text-muted">@lang('academics.no_pending_enrollees')</p>
                    @else
                    <div class="row">
                        <div class="col-lg-12">
                            <x-table>
                                {{-- Not Crm_table_active3 and not #table_id: both are picked up and
                                     initialised elsewhere (developer.js and the data_table_js partial)
                                     by configurations that leave the Responsive extension off, which
                                     is why ten columns simply ran off the side of the card. This one
                                     is started below with responsive on, so the columns that do not
                                     fit fold into the row's own panel behind the + in the first
                                     column - the same as the Fees Invoice list. --}}
                                <table id="pendingEnrolleesTable" class="table no-footer dtr-inline" cellspacing="0" width="100%">
                                    <thead>
                                        <tr>
                                            <th>@lang('common.sl')</th>
                                            <th>@lang('academics.student')</th>
                                            <th>@lang('student.admission_no')</th>
                                            @if($showProgram)
                                            <th>@lang('academics.program')</th>
                                            @endif
                                            <th>@lang('academics.year_level')</th>
                                            <th>{{ termLabel() }}</th>
                                            <th>@lang('academics.subjects')</th>
                                            <th>@lang('accounts.amount')</th>
                                            <th>@lang('academics.down_payment')</th>
                                            <th>@lang('student.action')</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($rows as $key => $row)
                                        @php $student = $row['student']; @endphp
                                        <tr>
                                            <td>{{ $key + 1 }}</td>
                                            <td>{{ $student->full_name }}</td>
                                            <td>{{ $student->admission_no }}</td>
                                            @if($showProgram)
                                            <td>{{ optional($student->course)->course_name ?: '-' }}</td>
                                            @endif
                                            <td>{{ optional($row['record']->class)->class_name ?: '-' }}</td>
                                            <td>{{ $row['semester'] ?: '-' }}</td>
                                            <td>{{ $row['subjectCount'] }}</td>
                                            <td>{{ $row['totalAmount'] === null ? '-' : (currency_format($row['totalAmount']) ?: number_format($row['totalAmount'], 2)) }}</td>
                                            <td>{{ $row['downPayment'] === null ? '-' : (currency_format($row['downPayment']) ?: number_format($row['downPayment'], 2)) }}</td>
                                            <td>
                                                {{-- The same Select dropdown the Fees Invoice list uses, so an
                                                     action column behaves the same wherever it is met. More than
                                                     one thing can be done with a row waiting to be billed, and a
                                                     single button could only ever offer the first of them. --}}
                                                <x-drop-down>
                                                    <a class="dropdown-item" href="#"
                                                        onclick="generateInvoice({{ $student->id }}); return false;">
                                                        @lang('academics.generate_invoice')
                                                    </a>
                                                    @if(userPermission('admin-subject-registration'))
                                                        <a class="dropdown-item"
                                                            href="{{ route('admin-subject-registration', ['student_id' => $student->id]) }}">
                                                            @lang('academics.enroll_student_in_subjects')
                                                        </a>
                                                    @endif
                                                    @if(userPermission('assign-program'))
                                                        <a class="dropdown-item" target="_blank"
                                                            href="{{ route('assign-program-balance-summary', ['student' => $student->id, 'state' => 'view']) }}">
                                                            @lang('academics.view_balance_summary')
                                                        </a>
                                                    @endif
                                                </x-drop-down>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </x-table>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

    </div>
</section>

{{-- One form for the whole page rather than one per row. Generating is a write, so
     it has to be a POST with a token; a dropdown holds links, so the link fills this
     in and submits it. --}}
{{ Form::open(['route' => 'assign-program-generate-invoice', 'method' => 'POST', 'id' => 'generateInvoiceForm']) }}
    <input type="hidden" name="student_id" id="generateInvoiceStudentId">
{{ Form::close() }}
@endsection

@include('backEnd.partials.data_table_js')

@push('script')
<script>
    function generateInvoice(studentId) {
        $('#generateInvoiceStudentId').val(studentId);
        $('#generateInvoiceForm').submit();
    }

    $(document).ready(function () {
        if (!$('#pendingEnrolleesTable').length) {
            return;
        }

        /* responsive: true is the whole point of this init - it is what puts the
           +/- in the first column and folds whatever does not fit into the row's
           own panel, instead of letting the table run off the card. The rest
           matches the Fees Invoice list so the two read the same. */
        $('#pendingEnrolleesTable').DataTable({
            bLengthChange: false,
            bDestroy: true,
            responsive: true,
            /* The Action column holds a dropdown, so it is never sorted on and
               never exported - a menu is not a value. */
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

        /* The search box and the export buttons are moved out of the wrapper and
           onto the toolbar line, beside the criteria. DataTables puts them in rows
           of their own, which left a band of empty space between the heading and
           the table. */
        var $wrapper = $('#pendingEnrolleesTable_wrapper');
        $('#pendingToolbarRight')
            .append($wrapper.find('.dt-buttons'))
            .append($wrapper.find('.dataTables_filter'));
    });
</script>
@endpush

@push('css')
<style>
    /* They float by default, which is what put each on a line of its own. In the
       toolbar they are flex children and the margins only knock them out of line. */
    #pendingToolbarRight .dt-buttons,
    #pendingToolbarRight .dataTables_filter {
        float: none;
        margin: 0 0 0 12px;
        text-align: left;
    }

    #pendingToolbarRight .dataTables_filter label {
        margin-bottom: 0;
    }
</style>
@endpush
