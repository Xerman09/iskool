@extends('backEnd.master')
@section('title')
@lang('academics.transaction_ledger')
@endsection

@section('mainContent')
@push('css')
<link rel="stylesheet" href="{{url('Modules\Fees\Resources\assets\css\feesStyle.css')}}"/>
<style>
    .invoice_wrapper{ overflow: auto; }
    .table{ min-width: 600px; }
</style>
@endpush

<section class="sms-breadcrumb mb-20">
    <div class="container-fluid">
        <div class="row justify-content-between">
            <h1>@lang('academics.transaction_ledger')</h1>
            <div class="bc-pages">
                <a href="{{route('dashboard')}}">@lang('common.dashboard')</a>
                <a href="#">@lang('academics.transaction_ledger')</a>
            </div>
        </div>
    </div>
</section>

<section class="admin-visitor-area up_st_admin_visitor">
    <div class="container-fluid p-0">
        <div class="row">
            <div class="col-lg-12">
                <div class="white-box">
                    @include('backEnd.academics.partials.transactionLedgerContent')
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
