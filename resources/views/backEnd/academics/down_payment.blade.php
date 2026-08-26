@extends('backEnd.master')
    @section('title')
        @lang('academics.down_payment')
    @endsection
@section('mainContent')
<section class="sms-breadcrumb mb-20">
    <div class="container-fluid">
        <div class="row justify-content-between">
            <h1>@lang('academics.down_payment')</h1>
            <div class="bc-pages">
                <a href="{{route('dashboard')}}">@lang('common.dashboard')</a>
                <a href="#">@lang('academics.down_payment')</a>
            </div>
        </div>
    </div>
</section>

<section class="admin-visitor-area up_st_admin_visitor">
    <div class="container-fluid p-0">
        <div class="row">
            <div class="col-lg-6">
                <div class="white-box">
                    {{ Form::open(['class' => 'form-horizontal', 'route' => 'down-payment-update', 'method' => 'POST']) }}
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="primary_input">
                                    <label class="primary_input_label" for="down_payment_amount">@lang('academics.down_payment_amount')</label>
                                    <input class="primary_input_field form-control{{ $errors->has('down_payment_amount') ? ' is-invalid' : '' }}"
                                        type="text" oninput="numberCheck(this)" name="down_payment_amount" autocomplete="off"
                                        value="{{isset($editData) ? @$editData->down_payment_amount : old('down_payment_amount')}}" id="down_payment_amount">
                                    <small class="text-muted">@lang('academics.down_payment_amount_hint')</small>
                                    @if ($errors->has('down_payment_amount'))
                                    <span class="text-danger">
                                        {{ $errors->first('down_payment_amount') }}
                                    </span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-lg-12 mt-20">
                                <button type="submit" class="primary-btn small fix-gr-bg">
                                    <span class="ti-save pr-2"></span>
                                    @lang('academics.save')
                                </button>
                            </div>
                        </div>
                    {{ Form::close() }}
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
