@extends('backEnd.master')
@section('title')
@lang('academics.item_order_approval')
@endsection

@section('mainContent')
@include('backEnd.academics.partials.studentOrdersModalAssets')
<section class="sms-breadcrumb mb-20">
    <div class="container-fluid">
        <div class="row justify-content-between">
            <h1>@lang('academics.item_order_approval')</h1>
            <div class="bc-pages">
                <a href="{{route('dashboard')}}">@lang('common.dashboard')</a>
                <a href="#">@lang('academics.item_order_approval')</a>
            </div>
        </div>
    </div>
</section>

<section class="admin-visitor-area up_st_admin_visitor">
    <div class="container-fluid p-0">
        {{-- Split by who ordered: students vs everyone else (staff - parents
             can't order at all). Both queues are already rendered server-side;
             this toggle just shows one at a time instead of stacking them, so
             reception isn't scrolling past one to reach the other. --}}
        <div class="row mb-20">
            <div class="col-lg-3">
                <select id="itemOrderAudienceFilter" class="primary_select form-control">
                    <option value="#pendingStudentOrdersSection">@lang('academics.student')</option>
                    <option value="#pendingStaffOrdersSection">@lang('academics.employee')</option>
                </select>
            </div>
        </div>

        <div class="row" id="pendingStudentOrdersSection">
            <div class="col-lg-12">
                <div class="white-box">
                    <div class="row">
                        <div class="col-lg-4 no-gutters">
                            <div class="main-title">
                                <h3 class="mb-15">@lang('academics.pending_student_item_orders')</h3>
                            </div>
                        </div>
                    </div>

                    @if($pendingStudentOrders->count() == 0)
                    <p class="text-center text-muted">@lang('academics.no_pending_student_item_orders')</p>
                    @else
                    <div class="row">
                        <div class="col-lg-12">
                            <x-table>
                                <table id="table_id" class="table data-table Crm_table_active3 no-footer dtr-inline collapsed" cellspacing="0" width="100%">
                                    <thead>
                                        <tr>
                                            <th>@lang('common.sl')</th>
                                            <th>@lang('academics.student')</th>
                                            <th>@lang('student.admission_no')</th>
                                            <th>@lang('academics.pending_items_count')</th>
                                            <th>@lang('accounts.amount')</th>
                                            <th>@lang('academics.latest_order')</th>
                                            <th>@lang('student.action')</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($pendingStudentOrders->groupBy('student_id') as $studentId => $studentOrders)
                                        @php
                                            $student = optional($studentOrders->first())->student;
                                            $studentTotal = $studentOrders->sum('amount');
                                            $latestOrder = $studentOrders->sortByDesc('created_at')->first();
                                        @endphp
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ optional($student)->first_name }} {{ optional($student)->last_name }}</td>
                                            <td>{{ optional($student)->admission_no }}</td>
                                            <td><span class="badge badge-warning">{{ $studentOrders->count() }}</span></td>
                                            <td>{{ currency_format($studentTotal) ?: number_format($studentTotal, 2) }}</td>
                                            <td>{{ optional($latestOrder->created_at)->format('M d, Y h:i A') }}</td>
                                            <td>
                                                <button type="button" class="primary-btn small fix-gr-bg" data-toggle="modal" data-target="#studentOrdersModalstudent{{ $studentId }}">
                                                    @lang('academics.view_orders')
                                                </button>
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

        <div class="row" id="pendingStaffOrdersSection" hidden>
            <div class="col-lg-12">
                <div class="white-box">
                    <div class="row">
                        <div class="col-lg-4 no-gutters">
                            <div class="main-title">
                                <h3 class="mb-15">@lang('academics.pending_staff_item_orders')</h3>
                            </div>
                        </div>
                    </div>

                    @if($pendingStaffOrders->count() == 0)
                    <p class="text-center text-muted">@lang('academics.no_pending_staff_item_orders')</p>
                    @else
                    <div class="row">
                        <div class="col-lg-12">
                            <x-table>
                                <table class="table Crm_table_active3" cellspacing="0" width="100%">
                                    <thead>
                                        <tr>
                                            <th>@lang('common.sl')</th>
                                            <th>@lang('academics.employee')</th>
                                            <th>@lang('academics.staff_no')</th>
                                            <th>@lang('academics.pending_items_count')</th>
                                            <th>@lang('accounts.amount')</th>
                                            <th>@lang('academics.latest_order')</th>
                                            <th>@lang('student.action')</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($pendingStaffOrders->groupBy('staff_id') as $staffId => $staffOrders)
                                        @php
                                            $staffMember = optional($staffOrders->first())->staff;
                                            $staffTotal = $staffOrders->sum('amount');
                                            $latestOrder = $staffOrders->sortByDesc('created_at')->first();
                                        @endphp
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ optional($staffMember)->full_name }}</td>
                                            <td>{{ optional($staffMember)->staff_no }}</td>
                                            <td><span class="badge badge-warning">{{ $staffOrders->count() }}</span></td>
                                            <td>{{ currency_format($staffTotal) ?: number_format($staffTotal, 2) }}</td>
                                            <td>{{ optional($latestOrder->created_at)->format('M d, Y h:i A') }}</td>
                                            <td>
                                                <button type="button" class="primary-btn small fix-gr-bg" data-toggle="modal" data-target="#studentOrdersModalstaff{{ $staffId }}">
                                                    @lang('academics.view_orders')
                                                </button>
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

@foreach($pendingStudentOrders->groupBy('student_id') as $studentId => $studentOrders)
@php $student = optional($studentOrders->first())->student; @endphp
@if($student)
@include('backEnd.academics.partials.studentOrdersModal', [
    'ownerId' => 'student' . $studentId,
    'ownerLabel' => trim($student->first_name . ' ' . $student->last_name) . ' (' . $student->admission_no . ')',
    'orders' => $studentOrders,
])
@endif
@endforeach

@foreach($pendingStaffOrders->groupBy('staff_id') as $staffId => $staffOrders)
@php $staffMember = optional($staffOrders->first())->staff; @endphp
@if($staffMember)
@include('backEnd.academics.partials.studentOrdersModal', [
    'ownerId' => 'staff' . $staffId,
    'ownerLabel' => trim($staffMember->full_name) . ($staffMember->staff_no ? ' (' . $staffMember->staff_no . ')' : ''),
    'orders' => $staffOrders,
])
@endif
@endforeach

@include('backEnd.partials.data_table_js')
@push('script')
<script>
$(document).ready(function () {
    var $filter = $('#itemOrderAudienceFilter');

    function showAudience(value) {
        $('#pendingStudentOrdersSection, #pendingStaffOrdersSection').prop('hidden', true);
        $(value).prop('hidden', false);
        $filter.val(value);
    }

    $filter.on('change', function () {
        showAudience($(this).val());
    });

    // A notification for a staff/admin order links here with #pendingStaffOrdersSection
    // so reception lands straight on it - otherwise the page defaults to the Student
    // tab and a staff order sits there looking like it "never showed up".
    if (window.location.hash && $(window.location.hash).length && $filter.find('option[value="' + window.location.hash + '"]').length) {
        showAudience(window.location.hash);
    } else if ($('#pendingStudentOrdersSection tbody tr').length === 0 && $('#pendingStaffOrdersSection tbody tr').length > 0) {
        // Nothing pending on the default Student tab but the Employee tab has orders -
        // show that one instead of the empty default.
        showAudience('#pendingStaffOrdersSection');
    }
});
</script>
@endpush
@endsection
