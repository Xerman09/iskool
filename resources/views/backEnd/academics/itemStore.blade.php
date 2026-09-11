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
            <div class="col-lg-12">
                @include('backEnd.academics.partials.itemStoreModal')
            </div>
        </div>

        @if(isset($recentResolvedOrders) && $recentResolvedOrders->count() > 0)
        <div class="row mt-30">
            <div class="col-lg-12">
                <div class="white-box">
                    <div class="main-title">
                        <h3 class="mb-15">@lang('academics.recent_order_history')</h3>
                    </div>
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
                                @foreach($recentResolvedOrders as $order)
                                <tr>
                                    <td>{{ optional($order->item)->item_name }}</td>
                                    <td>{{ $order->quantity }}</td>
                                    <td>{{ currency_format($order->amount) ?: number_format($order->amount, 2) }}</td>
                                    <td>
                                        @if($order->status === 'approved')
                                        <span class="badge badge-success">@lang('academics.order_status_approved')</span>
                                        @else
                                        <span class="badge badge-danger" data-tooltip="tooltip" title="{{ $order->reject_reason }}">@lang('academics.order_status_rejected')</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($order->status === 'approved' && $order->invoice)
                                        <a href="{{ route('fees.fees-invoice-view', ['id' => $order->invoice->id, 'state' => 'view']) }}" class="primary-btn small fix-gr-bg">@lang('academics.pay_now')</a>
                                        @endif
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

@push('script')
<script>
    $('[data-tooltip="tooltip"]').tooltip();
</script>
@endpush
@endsection
