@extends('backEnd.master')
    @section('title')
        @lang('academics.misc_fee_assign')
    @endsection
@section('mainContent')
<section class="sms-breadcrumb mb-20">
    <div class="container-fluid">
        <div class="row justify-content-between">
            <h1>@lang('academics.misc_fee_assign')</h1>
            <div class="bc-pages">
                <a href="{{route('dashboard')}}">@lang('common.dashboard')</a>
                <a href="#">@lang('academics.academics')</a>
                <a href="#">@lang('academics.misc_fee_assign')</a>
            </div>
        </div>
    </div>
</section>

<section class="admin-visitor-area up_st_admin_visitor">
    <div class="container-fluid p-0">

        <div class="row">
            <div class="col-lg-12">
                <div class="main-title">
                    <h3 class="mb-20">@lang('academics.select_criteria')</h3>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-12">
                <div class="white-box">
                    {{ Form::open(['class' => 'form-horizontal', 'route' => 'misc-fee-assign', 'method' => 'GET']) }}
                        <div class="row">
                            <div class="col-lg-4 mb-3 mb-lg-0">
                                <select class="primary_select form-control" name="course_id" required>
                                    <option value="">@lang('academics.program') *</option>
                                    @foreach($courses as $c)
                                    <option value="{{$c->id}}" {{@$course && $course->id == $c->id ? 'selected' : ''}}>{{$c->course_name}}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-3 mb-3 mb-lg-0">
                                <select class="primary_select form-control" name="class_id" required>
                                    <option value="">@lang('academics.year_level') *</option>
                                    @foreach($classes as $cl)
                                    <option value="{{$cl->id}}" {{@$class && $class->id == $cl->id ? 'selected' : ''}}>{{$cl->class_name}}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-3 mb-3 mb-lg-0">
                                <select class="primary_select form-control" name="semester_id" required>
                                    <option value="">@lang('academics.semester') *</option>
                                    @foreach($semesters as $sem)
                                    <option value="{{$sem->id}}" {{@$semester && $semester->id == $sem->id ? 'selected' : ''}}>{{$sem->semester_name}}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-2 mb-3 mb-lg-0">
                                <button type="submit" class="primary-btn small fix-gr-bg w-100">
                                    <span class="ti-search pr-2"></span>
                                    @lang('common.search')
                                </button>
                            </div>
                        </div>
                    {{ Form::close() }}
                </div>
            </div>
        </div>

        @if(isset($miscFees))
        <div class="row mt-40 justify-content-between align-items-center">
            <div class="col-auto">
                <div class="main-title">
                    <h3 class="mb-0">{{$course->course_name}} &mdash; {{$class->class_name}} &mdash; {{$semester->semester_name}}</h3>
                </div>
            </div>
            @if(userPermission('misc-fee-assign'))
            <div class="col-auto">
                <a href="#" data-toggle="modal" data-target="#addMiscFeeModal" class="primary-btn fix-gr-bg" title="@lang('academics.add_misc_fee')" style="width:40px;height:40px;padding:0;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;">
                    <span class="ti-plus"></span>
                </a>
            </div>
            @endif
        </div>

        <div class="row mt-20">
            <div class="col-lg-12">
                <div class="white-box">
                    <div class="row">
                        <div class="col-lg-12">
                            <x-table>
                                <table id="table_id" class="table Crm_table_active3" cellspacing="0" width="100%">
                                    <thead>
                                        <tr>
                                            <th>@lang('common.sl')</th>
                                            <th>@lang('common.name')</th>
                                            <th>@lang('fees.amount')</th>
                                            <th>@lang('common.action')</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $i=0; @endphp
                                        @forelse($miscFees as $fee)
                                        <tr>
                                            <td>{{++$i}}</td>
                                            <td>{{$fee->name}}</td>
                                            <td>{{currency_format($fee->amount) ?: number_format($fee->amount, 2)}}</td>
                                            <td>
                                                @php
                                                    $routeList = [
                                                        userPermission('misc-fee-assign') ?
                                                        '<a class="dropdown-item" data-toggle="modal" data-target="#editMiscFeeModal'.$fee->id.'" href="#">'.__('common.edit').'</a>':null,
                                                        userPermission('misc-fee-assign') ?
                                                        '<a class="dropdown-item" data-toggle="modal" data-target="#deleteMiscFeeModal'.$fee->id.'" href="#">'.__('common.delete').'</a>' : null,
                                                    ]
                                                @endphp
                                                <x-drop-down-action-component :routeList="$routeList" />
                                            </td>
                                        </tr>

                                        {{-- Edit modal --}}
                                        <div class="modal fade admin-query" id="editMiscFeeModal{{$fee->id}}">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    {{ Form::open(['class' => 'form-horizontal', 'route' => 'misc-fee-assign-update', 'method' => 'POST']) }}
                                                    <div class="modal-header">
                                                        <h4 class="modal-title">@lang('academics.edit_misc_fee')</h4>
                                                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="id" value="{{$fee->id}}">
                                                        <div class="primary_input mb-15">
                                                            <label class="primary_input_label">@lang('common.name') <span class="text-danger">*</span></label>
                                                            <input class="primary_input_field form-control" type="text" name="name" value="{{$fee->name}}" required>
                                                        </div>
                                                        <div class="primary_input">
                                                            <label class="primary_input_label">@lang('fees.amount') <span class="text-danger">*</span></label>
                                                            <input class="primary_input_field form-control" type="number" step="0.01" min="0" name="amount" value="{{$fee->amount}}" required>
                                                        </div>
                                                    </div>
                                                    <div class="mt-20 mb-20 text-center">
                                                        <button class="primary-btn fix-gr-bg submit">
                                                            <span class="ti-check"></span>
                                                            @lang('common.update')
                                                        </button>
                                                    </div>
                                                    {{ Form::close() }}
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Delete modal --}}
                                        <div class="modal fade admin-query" id="deleteMiscFeeModal{{$fee->id}}">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h4 class="modal-title">@lang('academics.delete_misc_fee')</h4>
                                                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="text-center">
                                                            <h4>@lang('common.are_you_sure_to_delete')</h4>
                                                        </div>
                                                        <div class="mt-40 d-flex justify-content-between">
                                                            <button type="button" class="primary-btn tr-bg" data-dismiss="modal">@lang('common.cancel')</button>
                                                            <a href="{{route('misc-fee-assign-delete', [$fee->id])}}" class="text-light">
                                                            <button class="primary-btn fix-gr-bg" type="submit">@lang('common.delete')</button>
                                                             </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">@lang('academics.no_misc_fee_set')</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </x-table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Add modal --}}
        <div class="modal fade admin-query" id="addMiscFeeModal">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    {{ Form::open(['class' => 'form-horizontal', 'route' => 'misc-fee-assign-store', 'method' => 'POST']) }}
                    <div class="modal-header">
                        <h4 class="modal-title">@lang('academics.add_misc_fee')</h4>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="course_id" value="{{$course->id}}">
                        <input type="hidden" name="class_id" value="{{$class->id}}">
                        <input type="hidden" name="semester_id" value="{{$semester->id}}">
                        <div class="primary_input mb-15">
                            <label class="primary_input_label">@lang('common.name') <span class="text-danger">*</span></label>
                            <input class="primary_input_field form-control" type="text" name="name" placeholder="e.g. Library Fee" required>
                        </div>
                        <div class="primary_input">
                            <label class="primary_input_label">@lang('fees.amount') <span class="text-danger">*</span></label>
                            <input class="primary_input_field form-control" type="number" step="0.01" min="0" name="amount" required>
                        </div>
                    </div>
                    <div class="mt-20 mb-20 text-center">
                        <button class="primary-btn fix-gr-bg submit">
                            <span class="ti-check"></span>
                            @lang('common.save')
                        </button>
                    </div>
                    {{ Form::close() }}
                </div>
            </div>
        </div>
        @endif

    </div>
</section>
@endsection
@include('backEnd.partials.data_table_js')
