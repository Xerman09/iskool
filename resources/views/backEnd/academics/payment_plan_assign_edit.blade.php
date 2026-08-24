@extends('backEnd.master')
@section('title')
@lang('academics.edit_payment_plan')
@endsection

@section('mainContent')
<section class="sms-breadcrumb mb-20">
    <div class="container-fluid">
        <div class="row justify-content-between">
            <h1>@lang('academics.edit_payment_plan')</h1>
            <div class="bc-pages">
                <a href="{{route('dashboard')}}">@lang('common.dashboard')</a>
                <a href="{{route('payment-plan-assign')}}">@lang('academics.payment_plan_assign')</a>
                <a href="#">@lang('academics.edit_payment_plan')</a>
            </div>
        </div>
    </div>
</section>
<section class="admin-visitor-area up_st_admin_visitor">
    <div class="container-fluid p-0">
        <div class="row">
            <div class="col-lg-6">
                {{ Form::open(['class' => 'form-horizontal', 'route' => 'payment-plan-assign-update', 'method' => 'POST']) }}
                <div class="white-box">
                    <div class="main-title">
                        <h3 class="mb-15">@lang('academics.edit_payment_plan')</h3>
                    </div>
                    <div class="add-visitor">
                        <input type="hidden" name="id" value="{{$planAssign->id}}">

                        <div class="row">
                            <div class="col-lg-12">
                                <p class="mb-0"><strong>@lang('academics.student'):</strong> {{ optional($planAssign->student)->first_name }} {{ optional($planAssign->student)->last_name }}</p>
                                <p class="text-muted">@lang('academics.payment_plan_edit_hint')</p>
                            </div>
                        </div>

                        <div class="row mt-15">
                            <div class="col-lg-12">
                                <label class="primary_input_label">@lang('academics.payment_plan_type') <span class="text-danger"> *</span></label>
                                <select class="primary_select form-control{{ @$errors->has('payment_plan_type_id') ? ' is-invalid' : '' }}" name="payment_plan_type_id" required>
                                    <option value="">@lang('academics.payment_plan_type') *</option>
                                    @foreach($paymentPlanTypes as $type)
                                    <option value="{{$type->id}}" {{$type->id == $planAssign->payment_plan_type_id ? 'selected' : ''}}>{{$type->name}} ({{$type->number_of_installments}}x)</option>
                                    @endforeach
                                </select>
                                @if ($errors->has('payment_plan_type_id'))
                                <span class="text-danger invalid-select" role="alert">{{ @$errors->first('payment_plan_type_id') }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="row mt-15">
                            <div class="col-lg-12">
                                <div class="primary_input">
                                    <label class="primary_input_label">@lang('academics.first_due_date') <span class="text-danger"> *</span></label>
                                    <input class="primary_input_field form-control{{ @$errors->has('first_due_date') ? ' is-invalid' : '' }}"
                                        type="date" name="first_due_date" value="{{ old('first_due_date', \Carbon\Carbon::parse($firstUnpaidDueDate)->toDateString()) }}" required>
                                    @if ($errors->has('first_due_date'))
                                    <span class="text-danger">{{ @$errors->first('first_due_date') }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="row mt-15">
                            <div class="col-lg-12">
                                <div class="primary_input">
                                    <label class="primary_input_label">@lang('academics.days_between_installments') <span class="text-danger"> *</span></label>
                                    <input class="primary_input_field form-control{{ @$errors->has('days_between_installments') ? ' is-invalid' : '' }}"
                                        type="number" min="1" name="days_between_installments" value="{{ old('days_between_installments', $daysBetween) }}" required>
                                    @if ($errors->has('days_between_installments'))
                                    <span class="text-danger">{{ @$errors->first('days_between_installments') }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="row mt-40">
                            <div class="col-lg-12 text-center">
                                <button type="submit" class="primary-btn fix-gr-bg submit">
                                    <span class="ti-check"></span>
                                    @lang('academics.reschedule_payment_plan')
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                {{ Form::close() }}
            </div>
        </div>
    </div>
</section>
@endsection
