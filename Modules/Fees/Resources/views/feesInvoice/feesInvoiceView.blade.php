 @extends('backEnd.master')
@section('title')
    @lang('fees::feesModule.view_fees_invoice')
@endsection
@section('mainContent')
    @push('css')
        <link rel="stylesheet" href="{{url('Modules\Fees\Resources\assets\css\feesStyle.css')}}"/>
        <style>
        .margin_auto{
            margin-left: auto;
             margin-right: 0
        }
        html[dir="rtl"] .margin_auto{
            margin-left: 0;
             margin-right: auto;
        }
        html[dir="rtl"] .address_text p {
            margin-right: auto;
            margin-left: 0;
        }
        html[dir="rtl"] .total_count {
            margin-right: auto;
            margin-left: 0;
        }

        .invoice_wrapper{
            overflow: auto;
        }

        .table{
            min-width: 600px;
        }

        .order-batch-card {
            border: 1px solid #edeef2;
            border-left: 4px solid #f0ad4e;
            border-radius: 12px;
            padding: 18px 20px;
        }
    </style>
    @endpush
    
    <section class="sms-breadcrumb mb-20">
        <div class="container-fluid">
            <div class="row justify-content-between">
                <h1>@lang('fees::feesModule.view_fees_invoice')</h1>
                <div class="bc-pages">
                    <a href="{{route('dashboard')}}">@lang('common.dashboard')</a>
                    <a href="#">@lang('fees.fees')</a>
                    <a href="">@lang('fees.fees_invoice')</a>
                    <a href="#">@lang('fees::feesModule.view_fees_invoice')</a>
                </div>
            </div>
        </div>
    </section>
    <section class="admin-visitor-area up_st_admin_visitor">
        @php
            $invoiceDueBalance = (float) $invoiceInfo->Tamount - (float) $invoiceInfo->Tpaidamount;
        @endphp
        @if(!empty($isPendingEnrollment) && empty($activePaymentPlan) && $invoiceDueBalance > 0)
        <div class="max_1200 text-right mb-10">
            <p class="text-muted mb-10">@lang('academics.pending_enrollment_choice_hint')</p>
        </div>
        @endif
        <div class="max_1200 text-right">
            @if(empty($activePaymentPlan) && $invoiceDueBalance > 0 && userPermission('fees.add-fees-payment'))
                <a href="{{route('fees.add-fees-payment', $invoiceInfo->id)}}" class="primary-btn small fix-gr-bg">
                    <span class="ti-wallet pr-2"></span>
                    @lang('fees.add_fees')
                </a>
            @endif
            @if(isset($paymentPlanTypes) && empty($activePaymentPlan) && userPermission('payment-plan-assign') && $invoiceDueBalance > 0)
                <a href="#" class="primary-btn small fix-gr-bg" data-toggle="modal" data-target="#assignPaymentPlanModal">
                    <span class="ti-calendar pr-2"></span>
                    @lang('academics.payment_plan_assign')
                </a>
            @endif
            <a href="{{route('fees.fees-invoice-view',['id'=>$invoiceInfo->id,'state'=>'print'])}}" class="primary-btn small fix-gr-bg" target="_blank">
                <span class="ti-printer pr-2"></span>
                @lang('common.print')
            </a>
        </div>

        @if(!empty($pendingItemOrders) && $pendingItemOrders->count() > 0 && userPermission('item-order-approval'))
        <div class="max_1200 mb-30">
            <div class="order-batch-card d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
                <span>
                    <span class="ti-time pr-2"></span>
                    {{ trans_choice('academics.pending_item_orders_notice', $pendingItemOrders->count(), ['count' => $pendingItemOrders->count()]) }}
                </span>
                <a href="{{ route('item-order-approval') }}" class="primary-btn small fix-gr-bg">
                    @lang('academics.review_in_item_order_approval')
                </a>
            </div>
        </div>
        @endif

        <div class="invoice_wrapper">
            <!-- invoice print part here -->
            <div class="invoice_print">
                <div class="container">
                    <div class="invoice_part_iner">
                        <table class="table">
                            <thead>
                            <td>
                                <div class="logo_img">
                                    <img  src="{{asset($generalSetting->letterhead_logo ?: $generalSetting->logo)}}" alt="{{$generalSetting->school_name}}">
                                </div>
                            </td>
                            <td class="virtical_middle address_text">
                                <p>{{$generalSetting->school_name}}</p>
                                <p>{{$generalSetting->phone}}</p>
                                <p>{{$generalSetting->email}}</p>
                                <p>{{$generalSetting->address}}</p>
                            </td>
                            </thead>
                        </table>
                        <!-- middle content  -->
                        <table class="table">
                            <tbody>
                            <tr>
                                <td>
                                    <!-- single table  -->
                                    <table class="mb_30">
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <div class="addressleft_text">
                                                        <p><span>@lang('fees.fees_invoice_issued_to')</p>
                                                        <p><span><strong>@lang('student.student_name') </span> <span class="nowrap">: {{@$invoiceInfo->studentInfo->full_name}}</span> </strong></p>
                                                        <p><span>@lang('student.class_section')</span> <span>: {{@$invoiceInfo->recordDetail->class->class_name}} ({{@$invoiceInfo->recordDetail->section->section_name}})</span> </p>
                                                        <p><span>@lang('student.roll_no')</span> <span>: {{@$invoiceInfo->recordDetail->roll_no}}</span> </p>
                                                        <p><span>@lang('student.admission_no')</span> <span>: {{@$invoiceInfo->studentInfo->admission_no}}</span> </p>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <!--/ single table  -->
                                </td>
                                <td >
                                    <!-- single table  -->
                                    <table class="mb_30 margin_auto">
                                        <tbody>
                                        <tr>
                                            <td>
                                                @php
                                                    $subTotal = $invoiceDetails->sum('sub_total');
                                                    $paidAmount = $invoiceDetails->sum('paid_amount');
                                                    $paymentStatus = $subTotal - $paidAmount;
                                                @endphp
                                                <div class="addressright_text">
                                                    <p><span><strong>@lang('fees.invoice_number')</span> <span>: {{$invoiceInfo->invoice_id}}</span> </strong>
                                                        @if($invoiceInfo->type === 'store')
                                                        <span class="badge badge-info" data-tooltip="tooltip" title="@lang('academics.item_purchase_invoice_hint')">@lang('academics.item_purchase')</span>
                                                        @endif
                                                    </p>
                                                    <p><span>@lang('fees.create_date') </span> <span>: {{dateConvert($invoiceInfo->create_date)}}</span> </p>
                                                    <p><span>@lang('fees.due_date') </span> <span>: {{dateConvert($invoiceInfo->due_date)}}</span> </p>
                                                    <p>
                                                                <span>
                                                                    @lang('fees.payment_status')
                                                                </span>
                                                        <span>:
                                                                    @if ($paymentStatus == 0)
                                                                @lang('fees.paid')
                                                            @else
                                                                @if ($paidAmount > 0)
                                                                    @lang('fees.partial')
                                                                @else
                                                                    @lang('fees.unpaid')
                                                                @endif
                                                            @endif
                                                                </span>
                                                    </p>
                                                </div>
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                    <!--/ single table  -->
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- invoice print part end -->
            <table class="table border_table mb_30 description_table" >
                <thead>
                <tr>
                    <th>@lang('common.sl')</th>
                    <th>@lang('fees.fees_type')</th>
                    <th>@lang('accounts.amount')</th>
                    <th>@lang('fees.waiver')</th>
                    <th>@lang('fees.fine')</th>
                    <th>@lang('fees::feesModule.paid_amount')</th>
                    <th class="text-right">@lang('fees.sub_total')</th>
                </tr>
                </thead>
                <tbody>
                @php
                    $amount = 0;
                    $weaver = 0;
                    $paid_amount = 0;
                    $fine = 0;
                    $service_charge = 0;
                    $grand_total = 0;
                    $balance = 0;

                    // Only group into Items/Tuition/Misc for invoices this enrollment
                    // feature actually produced - any other invoice (exam fees, a
                    // manual one-off charge, etc.) keeps the plain flat list below.
                    $isEnrollmentStyleInvoice = $invoiceDetails->contains(fn ($d) => $d->sm_item_id || optional($d->feesType)->name === 'Tuition');

                    $sections = $isEnrollmentStyleInvoice
                        ? collect([
                            'academics.items_purchased' => $invoiceDetails->filter(fn ($d) => $d->sm_item_id),
                            'academics.tuition' => $invoiceDetails->filter(fn ($d) => !$d->sm_item_id && optional($d->feesType)->name === 'Tuition'),
                            'academics.miscellaneous_fees' => $invoiceDetails->filter(fn ($d) => !$d->sm_item_id && optional($d->feesType)->name !== 'Tuition'),
                        ])->filter(fn ($group) => $group->count() > 0)
                        : collect([null => $invoiceDetails]);

                    $rowNo = 0;
                @endphp
                @foreach ($sections as $sectionLabel => $sectionLines)
                    @if($sectionLabel)
                    <tr class="table-group-header">
                        <td colspan="7"><strong>@lang($sectionLabel)</strong></td>
                    </tr>
                    @endif
                    @php
                        // Beyond a handful of lines (item-heavy enrollment invoices
                        // especially) the itemized table gets unwieldy, so collapse
                        // it behind a summary row instead of always listing every line.
                        $sectionLineCount = $sectionLines->count();
                        $showItemized = $sectionLineCount <= 3;
                        $sectionSubTotal = $sectionLines->sum(fn ($d) => ($d->amount + $d->fine) - ($d->paid_amount + $d->weaver));
                        $sectionKey = 'invoiceItems' . $loop->index;
                    @endphp
                    @unless($showItemized)
                    <tr class="item-summary-row">
                        <td colspan="6">
                            <button type="button" class="primary-btn small tr-bg toggle-invoice-items"
                                    data-target-group="{{ $sectionKey }}"
                                    data-show-text="@lang('fees::feesModule.show_all_items') ({{ $sectionLineCount }})"
                                    data-hide-text="@lang('fees::feesModule.hide_items')">
                                @lang('fees::feesModule.show_all_items') ({{ $sectionLineCount }})
                            </button>
                        </td>
                        <td class="text-right pr-0"><strong>{{currency_format($sectionSubTotal)}}</strong></td>
                    </tr>
                    @endunless
                    @foreach ($sectionLines as $invoiceDetail)
                        @php
                            $rowNo++;
                            $amount += $invoiceDetail->amount;
                            $weaver += $invoiceDetail->weaver;
                            $fine += $invoiceDetail->fine;
                            $service_charge += $invoiceDetail->service_charge;
                            $paid_amount += $invoiceDetail->paid_amount;

                            $totalAmount = ($invoiceDetail->amount + $invoiceDetail->fine) - $invoiceDetail->weaver;
                            $grand_total += $totalAmount ;

                            $total = ($invoiceDetail->amount+ $invoiceDetail->fine) - ($invoiceDetail->paid_amount + $invoiceDetail->weaver) ;
                            $balance += $total;
                        @endphp
                        <tr @if(!$showItemized) class="invoice-item-row" data-item-group="{{ $sectionKey }}" style="display:none;" @endif>
                            <td>{{$rowNo}}</td>
                            <td>
                                {{ $invoiceDetail->sm_item_id ? optional($invoiceDetail->item)->item_name : @$invoiceDetail->feesType->name }}
                                @if($invoiceDetail->note)
                                    <i class="fa fa-info-circle" aria-hidden="true"data-tooltip="tooltip" title="{{$invoiceDetail->note}}" style="courser:help;"></i>
                                @endif
                            </td>
                            <td>{{($invoiceDetail)? $invoiceDetail->amount : 0.00}}</td>
                            <td>{{($invoiceDetail->weaver)? $invoiceDetail->weaver : 0}}</td>
                            <td>{{($invoiceDetail->fine)? $invoiceDetail->fine : 0}}</td>
                            <td>{{($invoiceDetail->paid_amount)? $invoiceDetail->paid_amount : 0}}</td>
                            <td class="text-right pr-0">{{currency_format($total)}}</td>
                        </tr>
                    @endforeach
                @endforeach
                </tbody>
                <tfoot>
                <tr>
                    <td colspan="5"></td>
                    <td colspan="2">
                        <p class="total_count"><span>@lang('fees::feesModule.total_amount')</span> <span>{{currency_format($amount)}}</span></p>
                    </td>
                </tr>
                <tr>
                    <td colspan="5"></td>
                    <td colspan="2">
                        <p class="total_count"><span>@lang('fees::feesModule.total_waiver')</span> <span>{{currency_format($weaver)}}</span></p>
                    </td>
                </tr>
                <tr>
                    <td colspan="5"></td>
                    <td colspan="2">
                        <p class="total_count"><span>@lang('fees::feesModule.total_fine')</span> <span>{{currency_format($fine)}}</span></p>
                    </td>
                </tr>
                <tr>
                    <td colspan="5"></td>
                    <td colspan="2">
                        <p class="total_count"><span>@lang('fees::feesModule.total_service_charge')</span> <span>{{currency_format($service_charge)}}</span></p>
                    </td>
                </tr>
                <tr>
                    <td colspan="5"></td>
                    <td colspan="2">
                        <p class="total_count"><span>@lang('fees::feesModule.total_paid')</span> <span>{{currency_format($paid_amount)}}</span></p>
                    </td>
                </tr>
                <tr>
                    <td colspan="5"></td>
                    <td colspan="2">
                        <p class="total_count"><span>@lang('accounts.grand_total')</span> <span>{{currency_format($grand_total + $service_charge)}}</span></p>
                    </td>
                </tr>
                <tr>
                    <td colspan="5"></td>
                    <td colspan="2">
                        <p class="total_count"><span><strong>@lang('fees::feesModule.due_balance')</span> <span>
                                {{currency_format($balance)}}
                                </strong></span></p>
                    </td>
                </tr>
                </tfoot>
            </table>

            @if(!empty($activePaymentPlan) && $planSchedule)
            <div class="col-lg-12 mb-30">
                <h4 class="mb-15">@lang('academics.payment_schedule') &mdash; {{ optional($activePaymentPlan->planType)->name }}</h4>
                <table class="table border_table mb_30 description_table">
                    <thead>
                        <tr>
                            <th>@lang('academics.installment_no')</th>
                            <th>@lang('academics.due_date')</th>
                            <th class="text-right">@lang('accounts.amount')</th>
                            <th>@lang('student.status')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($planSchedule as $installment)
                        <tr @if($installment['is_next']) style="font-weight:bold;" @endif>
                            <td>
                                {{$installment['installment_no']}} @lang('academics.of') {{$activePaymentPlan->number_of_installments}}
                                @if($installment['is_next'])
                                <span class="badge badge-warning">@lang('academics.next_payment')</span>
                                @endif
                            </td>
                            <td>{{ \Carbon\Carbon::parse($installment['due_date'])->format('M d, Y') }}</td>
                            <td class="text-right">
                                {{currency_format($installment['amount']) ?: number_format($installment['amount'], 2)}}
                                @if($installment['status'] == 'partial')
                                    <br><small class="text-muted">{{ __('academics.installment_remaining_note', ['amount' => currency_format($installment['remaining']) ?: number_format($installment['remaining'], 2)]) }}</small>
                                @endif
                            </td>
                            <td>
                                @if($installment['status'] == 'paid')
                                    <span class="badge badge-success">@lang('academics.paid_status')</span>
                                @elseif($installment['status'] == 'partial')
                                    <span class="badge badge-warning">@lang('academics.partial_status')</span>
                                @else
                                    <span class="badge badge-secondary">@lang('academics.unpaid_status')</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            @if($banks)
            <div class="col-lg-12">
                <table class="table border_table mb_30 description_table" >
                    <thead>
                        <tr>
                            <th>@lang('common.sl')</th>
                            <th>@lang('accounts.bank_name')</th>
                            <th>@lang('accounts.account_name')</th>
                            <th>@lang('accounts.account_number')</th>
                            <th>@lang('accounts.account_type')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($banks as $key=>$bank)
                            <tr>
                                <td>{{$key+1}}</td>
                                <td>{{$bank->bank_name}}</td>
                                <td>{{$bank->account_name}}</td>
                                <td>{{$bank->account_number}}</td>
                                <td>{{$bank->account_type}}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </section>

    @if(isset($paymentPlanTypes) && empty($activePaymentPlan) && userPermission('payment-plan-assign') && $invoiceDueBalance > 0)
    <div class="modal fade admin-query" id="assignPaymentPlanModal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                {{ Form::open(['route' => 'payment-plan-assign-store', 'method' => 'POST']) }}
                <input type="hidden" name="invoice_id" value="{{$invoiceInfo->id}}">
                <div class="modal-header">
                    <h4 class="modal-title">@lang('academics.payment_plan_assign')</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">@lang('academics.remaining_balance'): {{currency_format($invoiceDueBalance) ?: number_format($invoiceDueBalance, 2)}} &mdash; @lang('academics.installment_schedule_hint')</p>
                    <div class="row">
                        <div class="col-lg-12">
                            <label class="primary_input_label">@lang('academics.payment_plan_type') <span class="text-danger"> *</span></label>
                            <select class="primary_select form-control" name="payment_plan_type_id" required>
                                <option value="">@lang('academics.payment_plan_type')</option>
                                @foreach($paymentPlanTypes as $type)
                                <option value="{{$type->id}}">{{$type->name}} ({{$type->number_of_installments}}x)</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row mt-15">
                        <div class="col-lg-12">
                            <label class="primary_input_label">@lang('academics.first_due_date') <span class="text-danger"> *</span></label>
                            <input class="primary_input_field form-control" type="date" name="first_due_date" value="{{ now()->addDays(14)->toDateString() }}" required>
                        </div>
                    </div>
                    <div class="row mt-15">
                        <div class="col-lg-12">
                            <label class="primary_input_label">@lang('academics.days_between_installments') <span class="text-danger"> *</span></label>
                            <input class="primary_input_field form-control" type="number" min="1" name="days_between_installments" value="30" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="primary-btn fix-gr-bg submit">
                        <span class="ti-check"></span>
                        @lang('academics.save')
                    </button>
                </div>
                {{ Form::close() }}
            </div>
        </div>
    </div>
    @endif
@endsection
@push('script')
    <script>
        $('[data-tooltip="tooltip"]').tooltip();

        $(document).on('click', '.toggle-invoice-items', function () {
            var $btn = $(this);
            var $rows = $('tr[data-item-group="' + $btn.data('target-group') + '"]');
            var willShow = $rows.first().is(':hidden');
            $rows.toggle(willShow);
            $btn.text(willShow ? $btn.data('hide-text') : $btn.data('show-text'));
        });
    </script>
@endpush
