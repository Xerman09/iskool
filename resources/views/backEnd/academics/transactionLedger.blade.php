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
                    <form method="GET" action="{{ route('student-transaction-ledger') }}" class="d-flex align-items-center flex-wrap mb-20" style="gap:10px;">
                        <div class="btn-group" role="group">
                            <a href="{{ route('student-transaction-ledger', ['scope' => 'semester', 'semester_id' => $selectedSemesterId]) }}"
                               class="primary-btn small {{ $scope === 'semester' ? 'fix-gr-bg' : 'tr-bg' }}">@lang('academics.per_semester')</a>
                            <a href="{{ route('student-transaction-ledger', ['scope' => 'whole_stay']) }}"
                               class="primary-btn small {{ $scope === 'whole_stay' ? 'fix-gr-bg' : 'tr-bg' }}">@lang('academics.whole_stay')</a>
                        </div>

                        @if($scope === 'semester' && $semesters->count() > 0)
                        <input type="hidden" name="scope" value="semester">
                        <select name="semester_id" class="primary_select form-control" style="max-width:220px;" onchange="this.form.submit()">
                            @foreach($semesters as $semester)
                            <option value="{{ $semester->id }}" @selected($selectedSemesterId == $semester->id)>{{ $semester->semester_name }}</option>
                            @endforeach
                        </select>
                        @endif
                    </form>

                    @if($scope === 'semester' && $semesters->count() > 0 && $invoices->isEmpty())
                    <p class="text-muted">@lang('academics.no_transactions_for_semester')</p>
                    @endif

                    @include('backEnd.academics.partials.transactionLedgerContent')
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
