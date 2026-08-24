@extends('backEnd.master')
    @section('title')
        @lang('academics.payment_plan_type')
    @endsection
@section('mainContent')
<section class="sms-breadcrumb mb-20">
    <div class="container-fluid">
        <div class="row justify-content-between">
            <h1>@lang('academics.payment_plan_type')</h1>
            <div class="bc-pages">
                <a href="{{route('dashboard')}}">@lang('common.dashboard')</a>
                <a href="#">@lang('academics.academics')</a>
                <a href="#">@lang('academics.payment_plan_type')</a>
            </div>
        </div>
    </div>
</section>
<section class="admin-visitor-area up_st_admin_visitor">
    <div class="container-fluid p-0">
        @if(isset($paymentPlanType))
          @if(userPermission('payment-plan-type-store'))
        <div class="row">
            <div class="offset-lg-10 col-lg-2 text-right col-md-12 mb-20">
                <a href="{{route('payment-plan-type')}}" class="primary-btn small fix-gr-bg">
                    <span class="ti-plus pr-2"></span>
                    @lang('common.add')
                </a>
            </div>
        </div>
        @endif
        @endif
        <div class="row">

            <div class="col-lg-4 col-xl-3">
                <div class="row">
                    <div class="col-lg-12">
                        @if(isset($paymentPlanType))
                        {{ Form::open(['class' => 'form-horizontal', 'route' => 'payment-plan-type-update', 'method' => 'POST']) }}
                        @else
                        @if(userPermission('payment-plan-type-store'))
                        {{ Form::open(['class' => 'form-horizontal', 'route' => 'payment-plan-type-store', 'method' => 'POST']) }}
                        @endif
                        @endif
                        <div class="white-box">
                            <div class="main-title">
                                <h3 class="mb-15">@if(isset($paymentPlanType))
                                        @lang('academics.edit_payment_plan_type')
                                    @else
                                        @lang('academics.add_payment_plan_type')
                                    @endif
                                </h3>
                            </div>
                            <div class="add-visitor">
                                <input type="hidden" name="id" value="{{isset($paymentPlanType)? $paymentPlanType->id: ''}}">
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="primary_input">
                                            <label class="primary_input_label" for="">@lang('academics.payment_plan_name') <span class="text-danger"> *</span></label>
                                            <input class="primary_input_field form-control{{ @$errors->has('name') ? ' is-invalid' : '' }}"
                                            type="text" name="name" autocomplete="off" placeholder="e.g. Monthly" value="{{isset($paymentPlanType)? $paymentPlanType->name: old('name')}}">
                                            @if ($errors->has('name'))
                                                <span class="text-danger">{{ @$errors->first('name') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-15">
                                    <div class="col-lg-12">
                                        <div class="primary_input">
                                            <label class="primary_input_label" for="">@lang('academics.number_of_installments') <span class="text-danger"> *</span></label>
                                            <input class="primary_input_field form-control{{ @$errors->has('number_of_installments') ? ' is-invalid' : '' }}"
                                            type="number" min="1" name="number_of_installments" autocomplete="off" value="{{isset($paymentPlanType)? $paymentPlanType->number_of_installments: old('number_of_installments')}}">
                                            @if ($errors->has('number_of_installments'))
                                                <span class="text-danger">{{ @$errors->first('number_of_installments') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-40">
                                    <div class="col-lg-12 text-center">
                                       <button class="primary-btn fix-gr-bg submit">
                                            <span class="ti-check"></span>
                                            @if(isset($paymentPlanType))
                                                @lang('academics.update_payment_plan_type')
                                            @else
                                                @lang('academics.save_payment_plan_type')
                                            @endif
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        {{ Form::close() }}
                    </div>
                </div>
            </div>

            <div class="col-lg-8 col-xl-9">
                <div class="white-box">
                    <div class="row">
                        <div class="col-lg-4 no-gutters">
                            <div class="main-title">
                                <h3 class="mb-15">@lang('academics.payment_plan_type_list')</h3>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-12">
                            <x-table>
                                <table id="table_id" class="table Crm_table_active3" cellspacing="0" width="100%">
                                    <thead>
                                        <tr>
                                            <th>@lang('common.sl')</th>
                                            <th>@lang('academics.payment_plan_name')</th>
                                            <th>@lang('academics.number_of_installments')</th>
                                            <th>@lang('common.action')</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $i=0; @endphp
                                        @foreach($paymentPlanTypes as $type)
                                        <tr>
                                            <td>{{++$i}}</td>
                                            <td>{{@$type->name}}</td>
                                            <td>{{@$type->number_of_installments}}</td>
                                            <td>
                                                @php
                                                    $routeList = [
                                                        userPermission('payment-plan-type-edit') ?
                                                        '<a class="dropdown-item" href="'.route('payment-plan-type-edit', [@$type->id]).'">'.__('common.edit').'</a>':null,
                                                        userPermission('payment-plan-type-delete') ?
                                                        '<a class="dropdown-item" data-toggle="modal" data-target="#deletePaymentPlanTypeModal'.$type->id.'" href="#">'.__('common.delete').'</a>' : null,
                                                    ]
                                                @endphp
                                                <x-drop-down-action-component :routeList="$routeList" />
                                            </td>
                                        </tr>
                                        <div class="modal fade admin-query" id="deletePaymentPlanTypeModal{{@$type->id}}">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h4 class="modal-title">@lang('academics.delete_payment_plan_type')</h4>
                                                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="text-center">
                                                            <h4>@lang('common.are_you_sure_to_delete')</h4>
                                                        </div>
                                                        <div class="mt-40 d-flex justify-content-between">
                                                            <button type="button" class="primary-btn tr-bg" data-dismiss="modal">@lang('common.cancel')</button>
                                                            <a href="{{route('payment-plan-type-delete', [@$type->id])}}" class="text-light">
                                                            <button class="primary-btn fix-gr-bg" type="submit">@lang('common.delete')</button>
                                                             </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @endforeach
                                    </tbody>
                                </table>
                            </x-table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
@include('backEnd.partials.data_table_js')
