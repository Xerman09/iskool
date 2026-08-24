@extends('backEnd.master')
@section('title')
@lang('academics.payment_plan_assign')
@endsection

@section('mainContent')
<section class="sms-breadcrumb mb-20">
    <div class="container-fluid">
        <div class="row justify-content-between">
            <h1>@lang('academics.payment_plan_assign')</h1>
            <div class="bc-pages">
                <a href="{{route('dashboard')}}">@lang('common.dashboard')</a>
                <a href="#">@lang('academics.academics')</a>
                <a href="#">@lang('academics.payment_plan_assign')</a>
            </div>
        </div>
    </div>
</section>
<section class="admin-visitor-area up_st_admin_visitor">
    <div class="container-fluid p-0">
        <div class="row">
            <div class="col-lg-4 col-xl-3">
                {{ Form::open(['class' => 'form-horizontal', 'route' => 'payment-plan-assign-store', 'method' => 'POST']) }}
                <div class="white-box">
                    <div class="main-title">
                        <h3 class="mb-15">@lang('academics.payment_plan_assign')</h3>
                    </div>
                    <div class="add-visitor">
                        <div class="row">
                            <div class="col-lg-12">
                                <label class="primary_input_label">@lang('academics.select_student') <span class="text-danger"> *</span></label>
                                <select class="primary_select form-control{{ @$errors->has('student_id') ? ' is-invalid' : '' }}" name="student_id" required>
                                    <option value="">@lang('academics.select_student')</option>
                                    @foreach($eligibleStudents as $s)
                                    <option value="{{$s->id}}">{{$s->first_name}} {{$s->last_name}} ({{$s->admission_no}})</option>
                                    @endforeach
                                </select>
                                @if ($errors->has('student_id'))
                                <span class="text-danger invalid-select" role="alert">{{ @$errors->first('student_id') }}</span>
                                @endif
                                <small class="text-muted">@lang('academics.payment_plan_eligible_hint')</small>
                            </div>
                        </div>
                        <div class="row mt-15">
                            <div class="col-lg-12">
                                <label class="primary_input_label">@lang('academics.payment_plan_type') <span class="text-danger"> *</span></label>
                                <select class="primary_select form-control{{ @$errors->has('payment_plan_type_id') ? ' is-invalid' : '' }}" name="payment_plan_type_id" required>
                                    <option value="">@lang('academics.payment_plan_type') *</option>
                                    @foreach($paymentPlanTypes as $type)
                                    <option value="{{$type->id}}">{{$type->name}} ({{$type->number_of_installments}}x)</option>
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
                                        type="date" name="first_due_date" value="{{ old('first_due_date', now()->addDays(14)->toDateString()) }}" required>
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
                                        type="number" min="1" name="days_between_installments" value="{{ old('days_between_installments', 30) }}" required>
                                    <small class="text-muted">@lang('academics.days_between_installments_hint')</small>
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
                                    @lang('academics.save')
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                {{ Form::close() }}
            </div>

            <div class="col-lg-8 col-xl-9">
                <div class="white-box">
                    <div class="row">
                        <div class="col-lg-4 no-gutters">
                            <div class="main-title">
                                <h3 class="mb-15">@lang('academics.assigned_payment_plans')</h3>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-12">
                            <x-table>
                                <table id="table_id" class="table Crm_table_active3" cellspacing="0" width="100%">
                                    <thead>
                                        <tr>
                                            <th>@lang('academics.student')</th>
                                            <th>@lang('academics.payment_plan_type')</th>
                                            <th>@lang('academics.total_amount_due')</th>
                                            <th>@lang('academics.installments_progress')</th>
                                            <th>@lang('student.action')</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($assignedPlans as $assign)
                                        <tr>
                                            <td valign="top">{{ optional($assign->student)->first_name }} {{ optional($assign->student)->last_name }}</td>
                                            <td valign="top">{{ optional($assign->planType)->name }}</td>
                                            <td valign="top">{{ currency_format($assign->total_amount) ?: number_format($assign->total_amount, 2) }}</td>
                                            <td valign="top">{{ $assign->paidCount }} @lang('academics.of') {{ $assign->number_of_installments }} @lang('academics.paid')</td>
                                            <td valign="top">
                                                @php
                                                    $routeList = [
                                                        '<a class="dropdown-item" data-toggle="modal" data-target="#viewInstallmentsModal'.$assign->id.'" href="#">'.__('academics.view').'</a>',
                                                        $assign->invoices->contains(fn ($i) => $i->payment_status != 'paid') ?
                                                        '<a class="dropdown-item" href="'.route('payment-plan-assign-edit', [$assign->id]).'">'.__('academics.edit_payment_plan').'</a>' : null,
                                                    ];
                                                @endphp
                                                <x-drop-down-action-component :routeList="$routeList" />
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </x-table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modals live outside the table/tbody structure — a raw <div> is not
             valid as a direct child of <tbody> and breaks the DataTable render. --}}
        @foreach($assignedPlans as $assign)
        <div class="modal fade admin-query" id="viewInstallmentsModal{{$assign->id}}">
            <div class="modal-dialog modal-dialog-centered large-modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">{{ optional($assign->planType)->name }} &mdash; {{ optional($assign->student)->first_name }} {{ optional($assign->student)->last_name }}</h4>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>@lang('academics.installment_no')</th>
                                        <th>@lang('academics.due_date')</th>
                                        <th>@lang('academics.total_amount_due')</th>
                                        <th>@lang('academics.down_payment_paid')</th>
                                        <th>@lang('academics.remaining_balance')</th>
                                        <th>@lang('student.status')</th>
                                        <th>@lang('student.action')</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($assign->invoices as $invoice)
                                    @php
                                        $installmentAmount = $invoice->invoiceDetails->sum('amount');
                                        $installmentPaid = $invoice->invoiceDetails->sum('paid_amount');
                                        $installmentBalance = max(0, $installmentAmount - $installmentPaid);
                                        $viewRouteList = [
                                            '<a class="dropdown-item" target="_blank" href="'.route('fees.fees-invoice-view', ['id' => $invoice->id, 'state' => 'view']).'">'.__('academics.view').'</a>',
                                        ];
                                    @endphp
                                    <tr>
                                        <td>{{$invoice->installment_no}} @lang('academics.of') {{$assign->number_of_installments}}</td>
                                        <td>{{ \Carbon\Carbon::parse($invoice->due_date)->format('M d, Y') }}</td>
                                        <td>{{ currency_format($installmentAmount) ?: number_format($installmentAmount, 2) }}</td>
                                        <td>{{ currency_format($installmentPaid) ?: number_format($installmentPaid, 2) }}</td>
                                        <td>{{ currency_format($installmentBalance) ?: number_format($installmentBalance, 2) }}</td>
                                        <td>
                                            @if($invoice->payment_status == 'paid')
                                                <span class="badge badge-success">@lang('academics.paid_status')</span>
                                            @elseif($invoice->payment_status == 'partial')
                                                <span class="badge badge-warning">@lang('academics.partial_status')</span>
                                            @else
                                                <span class="badge badge-secondary">@lang('academics.unpaid_status')</span>
                                            @endif
                                        </td>
                                        <td>
                                            <x-drop-down-action-component :routeList="$viewRouteList" />
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</section>
@endsection
@include('backEnd.partials.data_table_js')
