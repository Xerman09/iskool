@extends('backEnd.master')
@section('title')
@lang('academics.my_transactions')
@endsection

@section('mainContent')
<section class="sms-breadcrumb mb-20">
    <div class="container-fluid">
        <div class="row justify-content-between">
            <h1>@lang('academics.my_transactions')</h1>
            <div class="bc-pages">
                <a href="{{route('dashboard')}}">@lang('common.dashboard')</a>
                <a href="{{route('staff-item-store')}}">@lang('academics.item_store')</a>
                <a href="#">@lang('academics.my_transactions')</a>
            </div>
        </div>
    </div>
</section>

<section class="admin-visitor-area up_st_admin_visitor">
    <div class="container-fluid p-0">
        <div class="white-box">
            @if($transactions->count() == 0)
            <p class="text-center text-muted">@lang('fees::feesModule.no_transactions_found')</p>
            @else
            <x-table>
                <table class="table Crm_table_active3" cellspacing="0" width="100%">
                    <thead>
                        <tr>
                            <th>@lang('common.sl')</th>
                            <th>@lang('fees::feesModule.fees_invoice')</th>
                            <th>@lang('common.type')</th>
                            <th>@lang('fees::feesModule.payment_method')</th>
                            <th>@lang('fees::feesModule.paid_amount')</th>
                            <th>@lang('fees.fine')</th>
                            <th>@lang('common.date')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $key => $transaction)
                        <tr>
                            <td>{{ $key + 1 }}</td>
                            <td>{{ optional($transaction->feesInvoiceInfo)->invoice_id ?: '-' }}</td>
                            <td>
                                @if(optional($transaction->feesInvoiceInfo)->type === 'store')
                                <span class="badge badge-info">@lang('academics.item_purchase')</span>
                                @else
                                <span class="badge badge-secondary">@lang('fees::feesModule.fees')</span>
                                @endif
                            </td>
                            <td>{{ $transaction->payment_method }}</td>
                            <td>{{ currency_format($transaction->paid_amount) ?: number_format($transaction->paid_amount, 2) }}</td>
                            <td>{{ currency_format($transaction->fine) ?: number_format($transaction->fine, 2) }}</td>
                            <td>{{ $transaction->created_at->format('M d, Y h:i A') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-table>
            @endif
        </div>
    </div>
</section>
@endsection
