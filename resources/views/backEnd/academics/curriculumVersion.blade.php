@extends('backEnd.master')
    @section('title')
        @lang('academics.curriculum_version')
    @endsection
@section('mainContent')
<section class="sms-breadcrumb mb-20">
    <div class="container-fluid">
        <div class="row justify-content-between">
            <h1>@lang('academics.curriculum_version')</h1>
            <div class="bc-pages">
                <a href="{{route('dashboard')}}">@lang('common.dashboard')</a>
                <a href="#">@lang('academics.academics')</a>
                <a href="#">@lang('academics.curriculum_version')</a>
            </div>
        </div>
    </div>
</section>
<section class="admin-visitor-area up_st_admin_visitor">
    <div class="container-fluid p-0">
        @if(isset($curriculumVersion))
          @if(userPermission('curriculum_version_store'))
        <div class="row">
            <div class="offset-lg-10 col-lg-2 text-right col-md-12 mb-20">
                <a href="{{route('curriculum-version')}}" class="primary-btn small fix-gr-bg">
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
                        @if(isset($curriculumVersion))
                        {{ Form::open(['class' => 'form-horizontal', 'route' => 'curriculum_version_update', 'method' => 'POST']) }}
                        @else
                        @if(userPermission('curriculum_version_store'))
                        {{ Form::open(['class' => 'form-horizontal', 'route' => 'curriculum_version_store', 'method' => 'POST']) }}
                        @endif
                        @endif
                        <div class="white-box">
                            <div class="main-title">
                                <h3 class="mb-15">@if(isset($curriculumVersion))
                                        @lang('academics.edit_curriculum_version')
                                    @else
                                        @lang('academics.add_curriculum_version')
                                    @endif
                                </h3>
                            </div>
                            <div class="add-visitor">
                                <input type="hidden" name="id" value="{{isset($curriculumVersion)? $curriculumVersion->id: ''}}">
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="primary_input">
                                            <label class="primary_input_label" for="">@lang('academics.program') <span class="text-danger"> *</span></label>
                                            <select class="primary_select form-control{{ @$errors->has('course_id') ? ' is-invalid' : '' }}" name="course_id">
                                                <option value="">@lang('common.select')</option>
                                                @foreach($courses as $course)
                                                <option value="{{$course->id}}" {{isset($curriculumVersion) && $curriculumVersion->course_id == $course->id ? 'selected' : ''}}>{{$course->course_name}}</option>
                                                @endforeach
                                            </select>
                                            @if ($errors->has('course_id'))
                                                <span class="text-danger">{{ @$errors->first('course_id') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-15">
                                    <div class="col-lg-12">
                                        <div class="primary_input">
                                            <label class="primary_input_label" for="">@lang('academics.version_label') <span class="text-danger"> *</span></label>
                                            <input class="primary_input_field form-control{{ @$errors->has('version_label') ? ' is-invalid' : '' }}"
                                            type="text" name="version_label" autocomplete="off" value="{{isset($curriculumVersion)? $curriculumVersion->version_label: old('version_label')}}">
                                            @if ($errors->has('version_label'))
                                                <span class="text-danger">{{ @$errors->first('version_label') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-15">
                                    <div class="col-lg-12">
                                        <div class="primary_input">
                                            <label class="primary_input_label" for="">@lang('academics.effective_academic_year')</label>
                                            <select class="primary_select form-control" name="effective_academic_id">
                                                <option value="">@lang('common.select')</option>
                                                @foreach($academicYears as $academicYear)
                                                <option value="{{$academicYear->id}}" {{isset($curriculumVersion) && $curriculumVersion->effective_academic_id == $academicYear->id ? 'selected' : ''}}>{{$academicYear->year}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-15">
                                    <div class="col-lg-12">
                                        <div class="d-flex radio-btn-flex">
                                            <input type="checkbox" name="is_active" id="is_active" class="common-radio" value="1" {{ (!isset($curriculumVersion) || $curriculumVersion->is_active) ? 'checked' : '' }}>
                                            <label for="is_active">@lang('academics.set_as_active_version')</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-40">
                                    <div class="col-lg-12 text-center">
                                       <button class="primary-btn fix-gr-bg submit">
                                            <span class="ti-check"></span>
                                            @if(isset($curriculumVersion))
                                                @lang('academics.update_curriculum_version')
                                            @else
                                                @lang('academics.save_curriculum_version')
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
                                <h3 class="mb-15">@lang('academics.curriculum_version_list')</h3>
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
                                            <th>@lang('academics.program')</th>
                                            <th>@lang('academics.version_label')</th>
                                            <th>@lang('common.status')</th>
                                            <th>@lang('common.action')</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $i=0; @endphp
                                        @foreach($curriculumVersions as $curriculumVersion)
                                        <tr>
                                            <td>{{++$i}}</td>
                                            <td>{{@$curriculumVersion->course->course_name}}</td>
                                            <td>{{@$curriculumVersion->version_label}}</td>
                                            <td>{{$curriculumVersion->is_active ? __('common.active') : __('common.inactive')}}</td>
                                            <td>
                                                @php
                                                    $routeList = [
                                                        (!$curriculumVersion->is_active && userPermission('curriculum_version_edit')) ?
                                                        '<a class="dropdown-item" href="'.route('curriculum_version_activate', [@$curriculumVersion->id]).'">'.__('academics.activate').'</a>':null,
                                                        userPermission('curriculum_version_edit') ?
                                                        '<a class="dropdown-item" href="'.route('curriculum_version_edit', [@$curriculumVersion->id]).'">'.__('common.edit').'</a>':null,
                                                        userPermission('curriculum_version_delete') ?
                                                        '<a class="dropdown-item" data-toggle="modal" data-target="#deleteCurriculumVersionModal'.$curriculumVersion->id.'" href="#">'.__('common.delete').'</a>' : null,
                                                    ]
                                                @endphp
                                                <x-drop-down-action-component :routeList="$routeList" />
                                            </td>
                                        </tr>
                                        <div class="modal fade admin-query" id="deleteCurriculumVersionModal{{@$curriculumVersion->id}}">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h4 class="modal-title">@lang('academics.delete_curriculum_version')</h4>
                                                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="text-center">
                                                            <h4>@lang('common.are_you_sure_to_delete')</h4>
                                                        </div>
                                                        <div class="mt-40 d-flex justify-content-between">
                                                            <button type="button" class="primary-btn tr-bg" data-dismiss="modal">@lang('common.cancel')</button>
                                                            <a href="{{route('curriculum_version_delete', [@$curriculumVersion->id])}}" class="text-light">
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
