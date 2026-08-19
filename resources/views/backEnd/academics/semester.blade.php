@extends('backEnd.master')
    @section('title')
        @lang('academics.semester')
    @endsection
@section('mainContent')
<section class="sms-breadcrumb mb-20">
    <div class="container-fluid">
        <div class="row justify-content-between">
            <h1>@lang('academics.semester')</h1>
            <div class="bc-pages">
                <a href="{{route('dashboard')}}">@lang('common.dashboard')</a>
                <a href="#">@lang('academics.academics')</a>
                <a href="#">@lang('academics.semester')</a>
            </div>
        </div>
    </div>
</section>
<section class="admin-visitor-area up_st_admin_visitor">
    <div class="container-fluid p-0">
        @if(isset($semester))
          @if(userPermission('semester_store'))
        <div class="row">
            <div class="offset-lg-10 col-lg-2 text-right col-md-12 mb-20">
                <a href="{{route('semester')}}" class="primary-btn small fix-gr-bg">
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
                        @if(isset($semester))
                        {{ Form::open(['class' => 'form-horizontal', 'route' => 'semester_update', 'method' => 'POST']) }}
                        @else
                        @if(userPermission('semester_store'))
                        {{ Form::open(['class' => 'form-horizontal', 'route' => 'semester_store', 'method' => 'POST']) }}
                        @endif
                        @endif
                        <div class="white-box">
                            <div class="main-title">
                                <h3 class="mb-15">@if(isset($semester))
                                        @lang('academics.edit_semester')
                                    @else
                                        @lang('academics.add_semester')
                                    @endif
                                </h3>
                            </div>
                            <div class="add-visitor">
                                <input type="hidden" name="id" value="{{isset($semester)? $semester->id: ''}}">
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="primary_input">
                                            <label class="primary_input_label" for="">@lang('academics.semester_name') <span class="text-danger"> *</span></label>
                                            <input class="primary_input_field form-control{{ @$errors->has('semester_name') ? ' is-invalid' : '' }}"
                                            type="text" name="semester_name" autocomplete="off" value="{{isset($semester)? $semester->semester_name: old('semester_name')}}">
                                            @if ($errors->has('semester_name'))
                                                <span class="text-danger">{{ @$errors->first('semester_name') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-15">
                                    <div class="col-lg-12">
                                        <div class="primary_input">
                                            <label class="primary_input_label" for="">@lang('academics.sort_order')</label>
                                            <input class="primary_input_field form-control" type="number" name="sort_order" autocomplete="off" value="{{isset($semester)? $semester->sort_order: old('sort_order')}}">
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-40">
                                    <div class="col-lg-12 text-center">
                                       <button class="primary-btn fix-gr-bg submit">
                                            <span class="ti-check"></span>
                                            @if(isset($semester))
                                                @lang('academics.update_semester')
                                            @else
                                                @lang('academics.save_semester')
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
                                <h3 class="mb-15">@lang('academics.semester_list')</h3>
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
                                            <th>@lang('academics.semester_name')</th>
                                            <th>@lang('academics.sort_order')</th>
                                            <th>@lang('common.action')</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $i=0; @endphp
                                        @foreach($semesters as $semester)
                                        <tr>
                                            <td>{{++$i}}</td>
                                            <td>{{@$semester->semester_name}}</td>
                                            <td>{{@$semester->sort_order}}</td>
                                            <td>
                                                @php
                                                    $routeList = [
                                                        userPermission('semester_edit') ?
                                                        '<a class="dropdown-item" href="'.route('semester_edit', [@$semester->id]).'">'.__('common.edit').'</a>':null,
                                                        userPermission('semester_delete') ?
                                                        '<a class="dropdown-item" data-toggle="modal" data-target="#deleteSemesterModal'.$semester->id.'" href="#">'.__('common.delete').'</a>' : null,
                                                    ]
                                                @endphp
                                                <x-drop-down-action-component :routeList="$routeList" />
                                            </td>
                                        </tr>
                                        <div class="modal fade admin-query" id="deleteSemesterModal{{@$semester->id}}">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h4 class="modal-title">@lang('academics.delete_semester')</h4>
                                                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="text-center">
                                                            <h4>@lang('common.are_you_sure_to_delete')</h4>
                                                        </div>
                                                        <div class="mt-40 d-flex justify-content-between">
                                                            <button type="button" class="primary-btn tr-bg" data-dismiss="modal">@lang('common.cancel')</button>
                                                            <a href="{{route('semester_delete', [@$semester->id])}}" class="text-light">
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
