@extends('backEnd.master')
@section('title')
@lang('academics.item_order_approval')
@endsection

@section('mainContent')
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
                    <div class="main-title">
                        <h3 class="mb-15">@lang('academics.pending_item_orders')</h3>
                    </div>

                    @if($pendingOrders->count() == 0)
                    <p class="text-center text-muted">@lang('academics.no_pending_item_orders')</p>
                    @else
                    <x-table>
                        <table id="table_id" class="table Crm_table_active3" cellspacing="0" width="100%">
                            <thead>
                                <tr>
                                    <th>@lang('academics.student')</th>
                                    <th>@lang('inventory.item_name')</th>
                                    <th>@lang('inventory.quantity')</th>
                                    <th>@lang('accounts.amount')</th>
                                    <th>@lang('common.date')</th>
                                    <th>@lang('student.action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pendingOrders as $order)
                                <tr>
                                    <td>{{ optional($order->student)->first_name }} {{ optional($order->student)->last_name }} ({{ optional($order->student)->admission_no }})</td>
                                    <td>{{ optional($order->item)->item_name }}</td>
                                    <td>{{ $order->quantity }}</td>
                                    <td>{{ currency_format($order->amount) ?: number_format($order->amount, 2) }}</td>
                                    <td>{{ $order->created_at->format('M d, Y h:i A') }}</td>
                                    <td>
                                        <div class="d-flex">
                                            {{ Form::open(['route' => ['item-order-approval-approve', $order->id], 'method' => 'POST', 'class' => 'mr-10']) }}
                                            <button type="submit" class="primary-btn small fix-gr-bg" onclick="return confirm('{{ __('common.are_you_sure_to_approve') }}')">
                                                @lang('academics.approve')
                                            </button>
                                            {{ Form::close() }}

                                            <a href="#" class="primary-btn small tr-bg ml-10" data-toggle="modal" data-target="#rejectOrderModal{{ $order->id }}">
                                                @lang('academics.reject')
                                            </a>
                                        </div>

                                        <div class="modal fade admin-query" id="rejectOrderModal{{ $order->id }}">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    {{ Form::open(['route' => ['item-order-approval-reject', $order->id], 'method' => 'POST']) }}
                                                    <div class="modal-header">
                                                        <h4 class="modal-title">@lang('academics.reject')</h4>
                                                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="primary_input">
                                                            <label class="primary_input_label">@lang('academics.reject_reason')</label>
                                                            <input class="primary_input_field form-control" type="text" name="reject_reason">
                                                        </div>
                                                        <div class="mt-40 d-flex justify-content-between">
                                                            <button type="button" class="primary-btn tr-bg" data-dismiss="modal">@lang('common.cancel')</button>
                                                            <button type="submit" class="primary-btn fix-gr-bg">@lang('academics.reject')</button>
                                                        </div>
                                                    </div>
                                                    {{ Form::close() }}
                                                </div>
                                            </div>
                                        </div>
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
