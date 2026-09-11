@extends('backEnd.master')
@section('title')
@lang('academics.item_order_approval')
@endsection

@section('mainContent')
@push('css')
<style>
    .reject-reason-row td {
        background: #fbfbfd;
        border-top: none !important;
    }
    .reject-reason-row .primary_input_field,
    .bulk-reject-row .primary_input_field {
        margin-bottom: 0;
    }
    .modal-bulk-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
        background: #f7f7fa;
        border-radius: 10px;
        padding: 12px 16px;
        margin-top: 15px;
    }
    .modal-bulk-bar .bulk-count {
        font-weight: 600;
    }
    .bulk-reject-row {
        background: #fbfbfd;
        border-radius: 10px;
        padding: 12px 16px;
        margin-top: 10px;
    }
    .student-orders-modal .modal-dialog {
        max-width: 850px;
        width: calc(100% - 30px);
    }
    .student-orders-modal .modal-body {
        overflow-x: auto;
    }
    /* The shared table component (x-table) pads thead th with 45px on the left
       only (no matching right padding) - harmless on a full-width page table,
       but inside this narrower modal it reads as a lopsided left margin. */
    .student-orders-modal .QA_table th,
    .student-orders-modal .QA_table td {
        padding-left: 16px !important;
        padding-right: 16px !important;
    }
</style>
@endpush
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
        <div class="row">
            <div class="col-lg-12">
                <div class="white-box">
                    <div class="row">
                        <div class="col-lg-4 no-gutters">
                            <div class="main-title">
                                <h3 class="mb-15">@lang('academics.pending_item_orders')</h3>
                            </div>
                        </div>
                    </div>

                    @if($pendingOrders->count() == 0)
                    <p class="text-center text-muted">@lang('academics.no_pending_item_orders')</p>
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
                                        @foreach($pendingOrders->groupBy('student_id') as $studentId => $studentOrders)
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
                                                <button type="button" class="primary-btn small fix-gr-bg" data-toggle="modal" data-target="#studentOrdersModal{{ $studentId }}">
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

@foreach($pendingOrders->groupBy('student_id') as $studentId => $studentOrders)
@php $student = optional($studentOrders->first())->student; @endphp
<div class="modal fade admin-query student-orders-modal" id="studentOrdersModal{{ $studentId }}" data-student-id="{{ $studentId }}">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">
                    {{ optional($student)->first_name }} {{ optional($student)->last_name }}
                    ({{ optional($student)->admission_no }})
                </h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <x-table>
                    <table class="table Crm_table_active3" cellspacing="0" width="100%">
                        <thead>
                            <tr>
                                <th><input type="checkbox" class="select-all-in-modal"></th>
                                <th>@lang('common.sl')</th>
                                <th>@lang('inventory.item_name')</th>
                                <th>@lang('inventory.quantity')</th>
                                <th>@lang('accounts.amount')</th>
                                <th>@lang('common.date')</th>
                                <th>@lang('student.action')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($studentOrders as $order)
                            <tr>
                                <td><input type="checkbox" class="order-select-checkbox" value="{{ $order->id }}"></td>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ optional($order->item)->item_name }}</td>
                                <td>{{ $order->quantity }}</td>
                                <td>{{ currency_format($order->amount) ?: number_format($order->amount, 2) }}</td>
                                <td>{{ $order->created_at->format('M d, Y h:i A') }}</td>
                                <td>
                                    <div class="d-flex order-item-actions">
                                        {{ Form::open(['route' => ['item-order-approval-approve', $order->id], 'method' => 'POST', 'class' => 'mr-10']) }}
                                        <button type="submit" class="primary-btn small fix-gr-bg" onclick="return confirm('{{ __('common.are_you_sure_to_approve') }}')">
                                            @lang('academics.approve')
                                        </button>
                                        {{ Form::close() }}

                                        <button type="button" class="primary-btn small tr-bg ml-10 toggle-reject-reason" data-target-row="#rejectReasonRow{{ $order->id }}">
                                            @lang('academics.reject')
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr class="reject-reason-row" id="rejectReasonRow{{ $order->id }}" hidden>
                                <td colspan="7">
                                    {{ Form::open(['route' => ['item-order-approval-reject', $order->id], 'method' => 'POST', 'class' => 'd-flex align-items-center flex-wrap', 'style' => 'gap:10px;']) }}
                                    <input class="primary_input_field form-control" type="text" name="reject_reason" placeholder="@lang('academics.reject_reason')" style="max-width:300px;">
                                    <button type="submit" class="primary-btn small fix-gr-bg">@lang('academics.confirm_reject')</button>
                                    <button type="button" class="primary-btn small tr-bg cancel-reject-reason" data-target-row="#rejectReasonRow{{ $order->id }}">@lang('common.cancel')</button>
                                    {{ Form::close() }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-table>

                <div class="modal-bulk-bar bulk-order-bar" id="bulkBar{{ $studentId }}" hidden>
                    <span class="bulk-count"><span class="bulk-count-number">0</span> @lang('academics.selected')</span>
                    <div class="d-flex flex-wrap" style="gap:10px;">
                        {{ Form::open(['route' => 'item-order-approval-bulk-approve', 'method' => 'POST', 'id' => 'bulkApproveForm' . $studentId]) }}
                        <button type="submit" class="primary-btn small fix-gr-bg" onclick="return confirm('{{ __('common.are_you_sure_to_approve') }}')">
                            @lang('academics.approve_selected')
                        </button>
                        {{ Form::close() }}
                        <button type="button" class="primary-btn small tr-bg toggle-reject-reason" data-target-row="#bulkRejectRow{{ $studentId }}">
                            @lang('academics.reject_selected')
                        </button>
                    </div>
                </div>

                <div class="bulk-reject-row" id="bulkRejectRow{{ $studentId }}" hidden>
                    {{ Form::open(['route' => 'item-order-approval-bulk-reject', 'method' => 'POST', 'id' => 'bulkRejectForm' . $studentId, 'class' => 'd-flex align-items-center flex-wrap', 'style' => 'gap:10px;']) }}
                    <input class="primary_input_field form-control" type="text" name="reject_reason" placeholder="@lang('academics.reject_reason')" style="max-width:300px;">
                    <button type="submit" class="primary-btn small fix-gr-bg">@lang('academics.confirm_reject')</button>
                    <button type="button" class="primary-btn small tr-bg cancel-reject-reason" data-target-row="#bulkRejectRow{{ $studentId }}">@lang('common.cancel')</button>
                    {{ Form::close() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endforeach

@include('backEnd.partials.data_table_js')
@push('script')
<script>
$(document).ready(function () {
    $(document).on('click', '.toggle-reject-reason', function () {
        $($(this).data('target-row')).prop('hidden', false);
    });
    $(document).on('click', '.cancel-reject-reason', function () {
        $($(this).data('target-row')).prop('hidden', true);
    });

    function syncBulkSelection($modal) {
        var studentId = $modal.data('student-id');
        var ids = $modal.find('.order-select-checkbox:checked').map(function () { return $(this).val(); }).get();

        var $approveForm = $('#bulkApproveForm' + studentId);
        var $rejectForm = $('#bulkRejectForm' + studentId);
        $approveForm.find('input[name="order_ids[]"]').remove();
        $rejectForm.find('input[name="order_ids[]"]').remove();
        ids.forEach(function (id) {
            $('<input>').attr({ type: 'hidden', name: 'order_ids[]', value: id }).appendTo($approveForm);
            $('<input>').attr({ type: 'hidden', name: 'order_ids[]', value: id }).appendTo($rejectForm);
        });

        $modal.find('.bulk-count-number').text(ids.length);
        $modal.find('.bulk-order-bar').prop('hidden', ids.length === 0);
    }

    $(document).on('change', '.order-select-checkbox', function () {
        var $modal = $(this).closest('.student-orders-modal');
        var total = $modal.find('.order-select-checkbox').length;
        var checked = $modal.find('.order-select-checkbox:checked').length;
        $modal.find('.select-all-in-modal').prop('checked', total === checked);
        syncBulkSelection($modal);
    });

    $(document).on('change', '.select-all-in-modal', function () {
        var $modal = $(this).closest('.student-orders-modal');
        $modal.find('.order-select-checkbox').prop('checked', $(this).is(':checked'));
        syncBulkSelection($modal);
    });

    // Selection resets each time a popup is reopened, rather than carrying over
    // stale checks from a previous visit to the same student's orders.
    $(document).on('hidden.bs.modal', '.student-orders-modal', function () {
        var $modal = $(this);
        $modal.find('.order-select-checkbox, .select-all-in-modal').prop('checked', false);
        syncBulkSelection($modal);
    });
});
</script>
@endpush
@endsection
