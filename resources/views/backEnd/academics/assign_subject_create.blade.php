@extends('backEnd.master')

@section('title')
@lang('academics.assign_subject_create')
@endsection

@section('mainContent')
<section class="sms-breadcrumb mb-20">
    <div class="container-fluid">
        <div class="row justify-content-between">
            <h1>@lang('academics.assign_subject_create')</h1>
            <div class="bc-pages">
                <a href="{{route('dashboard')}}">@lang('common.dashboard')</a>
                <a href="#">@lang('academics.academics')</a>
                <a href="{{route('assign_subject')}}">@lang('academics.assign_subject')</a>
                <a href="{{route('assign_subject_create')}}">@lang('academics.assign_subject_create')</a>
            </div>
        </div>
    </div>
</section>
<section class="admin-visitor-area">
    <div class="container-fluid p-0">
        <div class="row">
            <div class="col-lg-4 col-md-6">
                <div class="main-title">
                    <h3 class="mb-30">@lang('common.select_criteria')</h3>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-12">

                <div class="white-box">
                    {{ Form::open(['class' => 'form-horizontal', 'files' => true, 'route' => 'assign_subject_search', 'method' => 'POST', 'enctype' => 'multipart/form-data', 'id' => 'search_student']) }}
                        <div class="row">
                            <input type="hidden" name="url" id="url" value="{{URL::to('/')}}">

                            {{-- Priority criteria: Program + Year Level --}}
                            <div class="col-lg-6 mb-3 mb-lg-0">
                                <select class="primary_select form-control{{ @$errors->has('course_id') ? ' is-invalid' : '' }}" name="course_id" required>
                                    <option value="">@lang('academics.program') *</option>
                                    @foreach($courses as $course)
                                    <option value="{{$course->id}}" {{@$course_id == $course->id ? 'selected' : ''}}>{{$course->course_name}}</option>
                                    @endforeach
                                </select>
                                @if ($errors->has('course_id'))
                                <span class="text-danger invalid-select" role="alert">{{ @$errors->first('course_id') }}</span>
                                @endif
                            </div>
                            <div class="col-lg-6 mb-3 mb-lg-0">
                                <select class="primary_select form-control {{ @$errors->has('class') ? ' is-invalid' : '' }}" id="select_class" name="class" required>
                                    <option data-display="@lang('common.select_class') *" value="">@lang('academics.year_level') *</option>
                                    @foreach($classes as $class)
                                    <option value="{{@$class->id}}" {{isset($class_id)? ($class_id == $class->id? 'selected':''):''}}>{{@$class->class_name}}</option>
                                    @endforeach
                                </select>
                                @if ($errors->has('class'))
                                <span class="text-danger invalid-select" role="alert">{{ @$errors->first('class') }}</span>
                                @endif
                            </div>

                            {{-- Secondary criteria: Curriculum Version + Semester + Block --}}
                            <div class="col-lg-4 mt-15">
                                <select class="primary_select form-control{{ @$errors->has('curriculum_version_id') ? ' is-invalid' : '' }}" name="curriculum_version_id" required>
                                    <option value="">@lang('academics.curriculum_version') *</option>
                                    @foreach($curriculumVersions as $curriculumVersion)
                                    <option value="{{$curriculumVersion->id}}" {{@$curriculum_version_id == $curriculumVersion->id ? 'selected' : ''}}>{{$curriculumVersion->version_label}}</option>
                                    @endforeach
                                </select>
                                @if ($errors->has('curriculum_version_id'))
                                <span class="text-danger invalid-select" role="alert">{{ @$errors->first('curriculum_version_id') }}</span>
                                @endif
                            </div>
                            <div class="col-lg-4 mt-15">
                                <select class="primary_select form-control{{ @$errors->has('semester_id') ? ' is-invalid' : '' }}" name="semester_id" required>
                                    <option value="">@lang('academics.semester') *</option>
                                    @foreach($semesters as $semester)
                                    <option value="{{$semester->id}}" {{@$semester_id == $semester->id ? 'selected' : ''}}>{{$semester->semester_name}}</option>
                                    @endforeach
                                </select>
                                @if ($errors->has('semester_id'))
                                <span class="text-danger invalid-select" role="alert">{{ @$errors->first('semester_id') }}</span>
                                @endif
                            </div>
                            <div class="col-lg-4 mt-15" id="select_section_div">
                                <select class="primary_select form-control {{ @$errors->has('section') ? ' is-invalid' : '' }}" id="select_section" name="section" required>
                                    <option data-display="@lang('common.select_section') *" value="">@lang('academics.block') *</option>
                                </select>
                                <div class="pull-right loader loader_style" id="select_section_loader">
                                    <img class="loader_img_style" src="{{asset('public/backEnd/img/demo_wait.gif')}}" alt="loader">
                                </div>
                                @if ($errors->has('section'))
                                <span class="text-danger invalid-select" role="alert">{{ @$errors->first('section') }}</span>
                                @endif
                            </div>
                            <div class="col-lg-12 mt-10">
                                <small class="text-muted">@lang('academics.assign_subject_curriculum_hint')</small>
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
    </div>
</section>

@if(isset($subjects))
<section class="admin-visitor-area">
    <div class="container-fluid p-0">
        <div class="row mt-40">
            <div class="col-lg-12">
                <div class="main-title">
                    <h3 class="mb-30">@lang('academics.assign_subject_create')</h3>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-12">
                <div class="white-box">
                    @if($subjects->count() == 0)
                        <p class="text-center text-muted">@lang('academics.no_curriculum_subjects_found')</p>
                    @else
                    {{ Form::open(['class' => 'form-horizontal', 'files' => true, 'route' => 'assign-subject-store', 'method' => 'POST', 'enctype' => 'multipart/form-data', 'id' => 'assign_subject']) }}
                        <div class="row">
                            <div class="col-lg-12">
                                <input type="hidden" name="url" id="url" value="{{URL::to('/')}}">
                                <input type="hidden" name="class_id" value="{{$class_id}}">
                                <input type="hidden" name="section_id" value="{{$section_id}}">
                                <input type="hidden" name="update" value="{{$assign_subjects->count() > 0 ? 1 : 0}}">

                                @foreach($subjects as $subject)
                                @php
                                    $existingAssign = $assign_subjects->firstWhere('subject_id', $subject->id);
                                @endphp
                                <div class="row align-items-center mb-20">
                                    <div class="col-lg-4 mb-3 mb-lg-0">
                                        <input type="hidden" name="subjects[]" value="{{$subject->id}}">
                                        <p class="mb-0"><strong>{{$subject->subject_name}}</strong> ({{$subject->subject_code}}) &mdash; {{$subject->units}} @lang('academics.units'), {{ucfirst($subject->subject_classification)}}</p>
                                    </div>
                                    <div class="col-lg-5 mb-3 mb-lg-0">
                                        <select class="primary_select form-control" name="teachers[]" required>
                                            <option data-display="@lang('common.select_teacher')" value="">@lang('common.select_teacher') *</option>
                                            @foreach($teachers as $teacher)
                                            <option value="{{@$teacher->id}}" {{@$existingAssign->teacher_id == @$teacher->id ? 'selected' : ''}}>{{@$teacher->full_name}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-lg-3 mb-3 mb-lg-0">
                                        <input type="number" min="1" class="primary_input form-control" name="max_slots[]" placeholder="@lang('academics.max_slots')" value="{{@$existingAssign->max_slots}}">
                                    </div>
                                </div>
                                @endforeach
                            </div>
                             @if(userPermission('assign-subject-store'))
                            <div class="col-lg-12 mt-20 text-right">
                                <button type="submit" class="primary-btn small fix-gr-bg submit">
                                    <span class="ti-save pr-2"></span>
                                    @lang('academics.save')
                                </button>
                            </div>
                            @endif
                        </div>
                    {{ Form::close() }}
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
@endif

@endsection
