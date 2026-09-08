@extends('backEnd.master')
@section('title')
@lang('academics.item_store')
@endsection

@section('mainContent')
<section class="sms-breadcrumb mb-20">
    <div class="container-fluid">
        <div class="row justify-content-between">
            <h1>@lang('academics.item_store')</h1>
            <div class="bc-pages">
                <a href="{{route('dashboard')}}">@lang('common.dashboard')</a>
                <a href="#">@lang('academics.item_store')</a>
            </div>
        </div>
    </div>
</section>

<section class="admin-visitor-area up_st_admin_visitor item-store-page">
    <div class="container-fluid p-0">
        <div class="row">
            <div class="col-lg-12 text-center">
                <div class="white-box">
                    <a href="#" class="primary-btn fix-gr-bg" data-toggle="modal" data-target="#itemStoreModal">
                        <span class="ti-shopping-cart pr-2"></span>
                        @lang('academics.buy_items')
                    </a>
                </div>
            </div>
        </div>

        @if(isset($pendingOrders) && $pendingOrders->count() > 0)
        <div class="row mt-30">
            <div class="col-lg-12">
                <div class="white-box">
                    <div class="main-title">
                        <h3 class="mb-15">@lang('academics.my_pending_orders')</h3>
                    </div>
                    <p class="text-muted">@lang('academics.pending_order_hint')</p>
                    <x-table>
                        <table class="table Crm_table_active3" cellspacing="0" width="100%">
                            <thead>
                                <tr>
                                    <th>@lang('inventory.item_name')</th>
                                    <th>@lang('inventory.quantity')</th>
                                    <th>@lang('accounts.amount')</th>
                                    <th>@lang('student.status')</th>
                                    <th>@lang('common.action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pendingOrders as $order)
                                <tr>
                                    <td>{{ optional($order->item)->item_name }}</td>
                                    <td>{{ $order->quantity }}</td>
                                    <td>{{ currency_format($order->amount) ?: number_format($order->amount, 2) }}</td>
                                    <td><span class="badge badge-warning">@lang('academics.order_status_pending')</span></td>
                                    <td>
                                        {{ Form::open(['route' => ['student-item-store-cancel', $order->id], 'method' => 'POST']) }}
                                        <button type="submit" class="primary-btn small tr-bg" onclick="return confirm('{{ __('common.are_you_sure_to_cancel') }}')">
                                            @lang('common.cancel')
                                        </button>
                                        {{ Form::close() }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-table>
                </div>
            </div>
        </div>
        @endif
    </div>
</section>

@include('backEnd.academics.partials.itemStoreModal')
@endsection
