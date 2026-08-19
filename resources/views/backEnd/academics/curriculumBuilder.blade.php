@extends('backEnd.master')
    @section('title')
        @lang('academics.curriculum_builder')
    @endsection
@section('mainContent')
<section class="sms-breadcrumb mb-20">
    <div class="container-fluid">
        <div class="row justify-content-between">
            <h1>@lang('academics.curriculum_builder')</h1>
            <div class="bc-pages">
                <a href="{{route('dashboard')}}">@lang('common.dashboard')</a>
                <a href="#">@lang('academics.academics')</a>
                <a href="#">@lang('academics.curriculum_builder')</a>
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
                    {{ Form::open(['class' => 'form-horizontal', 'route' => 'curriculum-builder', 'method' => 'GET']) }}
                        <div class="row">
                            <div class="col-lg-3 mb-3 mb-lg-0">
                                <select class="primary_select form-control{{ @$errors->has('course_id') ? ' is-invalid' : '' }}" name="course_id" required>
                                    <option value="">@lang('academics.program') *</option>
                                    @foreach($courses as $course)
                                    <option value="{{$course->id}}" {{@$criteria['course_id'] == $course->id ? 'selected' : ''}}>{{$course->course_name}}</option>
                                    @endforeach
                                </select>
                                @if ($errors->has('course_id'))
                                    <span class="text-danger">{{ @$errors->first('course_id') }}</span>
                                @endif
                            </div>
                            <div class="col-lg-3 mb-3 mb-lg-0">
                                <select class="primary_select form-control{{ @$errors->has('curriculum_version_id') ? ' is-invalid' : '' }}" name="curriculum_version_id" required>
                                    <option value="">@lang('academics.curriculum_version') *</option>
                                    @foreach($curriculumVersions as $curriculumVersion)
                                    <option value="{{$curriculumVersion->id}}" {{@$criteria['curriculum_version_id'] == $curriculumVersion->id ? 'selected' : ''}}>{{$curriculumVersion->version_label}}</option>
                                    @endforeach
                                </select>
                                @if ($errors->has('curriculum_version_id'))
                                    <span class="text-danger">{{ @$errors->first('curriculum_version_id') }}</span>
                                @endif
                            </div>
                            <div class="col-lg-3 mb-3 mb-lg-0">
                                <select class="primary_select form-control{{ @$errors->has('class_id') ? ' is-invalid' : '' }}" name="class_id" required>
                                    <option value="">@lang('academics.year_level') *</option>
                                    @foreach($classes as $class)
                                    <option value="{{$class->id}}" {{@$criteria['class_id'] == $class->id ? 'selected' : ''}}>{{$class->class_name}}</option>
                                    @endforeach
                                </select>
                                @if ($errors->has('class_id'))
                                    <span class="text-danger">{{ @$errors->first('class_id') }}</span>
                                @endif
                            </div>
                            <div class="col-lg-3 mb-3 mb-lg-0">
                                <select class="primary_select form-control{{ @$errors->has('semester_id') ? ' is-invalid' : '' }}" name="semester_id" required>
                                    <option value="">@lang('academics.semester') *</option>
                                    @foreach($semesters as $semester)
                                    <option value="{{$semester->id}}" {{@$criteria['semester_id'] == $semester->id ? 'selected' : ''}}>{{$semester->semester_name}}</option>
                                    @endforeach
                                </select>
                                @if ($errors->has('semester_id'))
                                    <span class="text-danger">{{ @$errors->first('semester_id') }}</span>
                                @endif
                            </div>
                            <div class="col-lg-12 mt-20 text-right">
                                <button type="submit" class="primary-btn small fix-gr-bg">
                                    <span class="ti-search pr-2"></span>
                                    @lang('common.search')
                                </button>
                            </div>
                        </div>
                    {{ Form::close() }}
                </div>
            </div>
        </div>

        @if(isset($subjects))
        <div class="row mt-40 justify-content-between align-items-center">
            <div class="col-auto">
                <div class="main-title">
                    <h3 class="mb-0">@lang('academics.curriculum_subjects')</h3>
                </div>
            </div>
            @if(userPermission('curriculum_builder_store'))
            <div class="col-auto">
                <a href="#" data-toggle="modal" data-target="#addCurriculumSubjectModal" class="primary-btn fix-gr-bg" title="@lang('academics.add_curriculum_subject')" style="width:40px;height:40px;padding:0;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;">
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
                                            <th>@lang('academics.subject_code')</th>
                                            <th>@lang('academics.subject_name')</th>
                                            <th>@lang('academics.units')</th>
                                            <th>@lang('common.type')</th>
                                            <th>@lang('academics.prerequisite_subject')</th>
                                            <th>@lang('common.action')</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $i=0; @endphp
                                        @foreach($subjects as $curriculumSubject)
                                        @php
                                            $rowSelectedPrerequisiteIds = $curriculumSubject->prerequisites->pluck('prerequisite_subject_id')->toArray();
                                        @endphp
                                        <tr>
                                            <td>{{++$i}}</td>
                                            <td>{{@$curriculumSubject->subject_code}}</td>
                                            <td>{{@$curriculumSubject->subject_name}}</td>
                                            <td>{{@$curriculumSubject->units}}</td>
                                            <td>{{ucfirst(@$curriculumSubject->subject_classification)}}</td>
                                            <td>{{$curriculumSubject->prerequisites->pluck('prerequisiteSubject.subject_name')->filter()->implode(', ') ?: '-'}}</td>
                                            <td>
                                                @php
                                                    $routeList = [
                                                        userPermission('curriculum_builder_edit') ?
                                                        '<a class="dropdown-item" data-toggle="modal" data-target="#editCurriculumSubjectModal'.$curriculumSubject->id.'" href="#">'.__('common.edit').'</a>':null,
                                                        userPermission('curriculum_builder_delete') ?
                                                        '<a class="dropdown-item" data-toggle="modal" data-target="#deleteCurriculumSubjectModal'.$curriculumSubject->id.'" href="#">'.__('common.delete').'</a>' : null,
                                                    ]
                                                @endphp
                                                <x-drop-down-action-component :routeList="$routeList" />
                                            </td>
                                        </tr>

                                        {{-- Edit modal --}}
                                        <div class="modal fade admin-query" id="editCurriculumSubjectModal{{$curriculumSubject->id}}">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    {{ Form::open(['class' => 'form-horizontal', 'route' => 'curriculum_builder_update', 'method' => 'POST']) }}
                                                    <div class="modal-header">
                                                        <h4 class="modal-title">@lang('academics.edit_curriculum_subject')</h4>
                                                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="id" value="{{$curriculumSubject->id}}">
                                                        <input type="hidden" name="course_id" value="{{$criteria['course_id']}}">
                                                        <input type="hidden" name="curriculum_version_id" value="{{$criteria['curriculum_version_id']}}">
                                                        <input type="hidden" name="class_id" value="{{$criteria['class_id']}}">
                                                        <input type="hidden" name="semester_id" value="{{$criteria['semester_id']}}">
                                                        @include('backEnd.academics.partials.curriculumSubjectFields', [
                                                            'baseSubjects' => $baseSubjects,
                                                            'prerequisiteOptions' => $prerequisiteOptions->where('id', '!=', $curriculumSubject->id),
                                                            'selectedSourceId' => $curriculumSubject->source_subject_id,
                                                            'selectedUnits' => $curriculumSubject->units,
                                                            'selectedClassification' => $curriculumSubject->subject_classification,
                                                            'selectedPrerequisiteIds' => $rowSelectedPrerequisiteIds,
                                                            'uid' => $curriculumSubject->id,
                                                        ])
                                                    </div>
                                                    <div class="mt-20 mb-20 text-center">
                                                        <button class="primary-btn fix-gr-bg submit">
                                                            <span class="ti-check"></span>
                                                            @lang('academics.update_curriculum_subject')
                                                        </button>
                                                    </div>
                                                    {{ Form::close() }}
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Delete modal --}}
                                        <div class="modal fade admin-query" id="deleteCurriculumSubjectModal{{@$curriculumSubject->id}}">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h4 class="modal-title">@lang('academics.delete_curriculum_subject')</h4>
                                                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="text-center">
                                                            <h4>@lang('common.are_you_sure_to_delete')</h4>
                                                        </div>
                                                        <div class="mt-40 d-flex justify-content-between">
                                                            <button type="button" class="primary-btn tr-bg" data-dismiss="modal">@lang('common.cancel')</button>
                                                            <a href="{{route('curriculum_builder_delete', [@$curriculumSubject->id])}}" class="text-light">
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

        {{-- Add modal --}}
        <div class="modal fade admin-query" id="addCurriculumSubjectModal">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    {{ Form::open(['class' => 'form-horizontal', 'route' => 'curriculum_builder_store', 'method' => 'POST']) }}
                    <div class="modal-header">
                        <h4 class="modal-title">@lang('academics.add_curriculum_subject')</h4>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="course_id" value="{{$criteria['course_id']}}">
                        <input type="hidden" name="curriculum_version_id" value="{{$criteria['curriculum_version_id']}}">
                        <input type="hidden" name="class_id" value="{{$criteria['class_id']}}">
                        <input type="hidden" name="semester_id" value="{{$criteria['semester_id']}}">
                        @include('backEnd.academics.partials.curriculumSubjectFields', [
                            'baseSubjects' => $baseSubjects,
                            'prerequisiteOptions' => $prerequisiteOptions,
                            'selectedSourceId' => null,
                            'selectedUnits' => null,
                            'selectedClassification' => 'major',
                            'selectedPrerequisiteIds' => [],
                            'uid' => 'new',
                        ])
                    </div>
                    <div class="mt-20 mb-20 text-center">
                        <button class="primary-btn fix-gr-bg submit">
                            <span class="ti-check"></span>
                            @lang('academics.save_curriculum_subject')
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
