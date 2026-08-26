@extends('backEnd.master')

@section('title')
@lang('academics.subject_completion')
@endsection

@section('mainContent')
<section class="sms-breadcrumb mb-20">
    <div class="container-fluid">
        <div class="row justify-content-between">
            <h1>@lang('academics.subject_completion')</h1>
            <div class="bc-pages">
                <a href="{{route('dashboard')}}">@lang('common.dashboard')</a>
                <a href="#">@lang('academics.academics')</a>
                <a href="{{route('subject-completion')}}">@lang('academics.subject_completion')</a>
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
                    {{ Form::open(['class' => 'form-horizontal', 'route' => 'subject-completion-search', 'method' => 'POST', 'id' => 'search_student']) }}
                        <div class="row">
                            <input type="hidden" name="url" id="url" value="{{URL::to('/')}}">

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
                    <h3 class="mb-30">@lang('academics.mark_pass_fail')</h3>
                </div>
            </div>
        </div>

        {{ Form::open(['class' => 'form-horizontal', 'route' => 'subject-completion-store', 'method' => 'POST']) }}
        @if($subjects->count() == 0)
        <div class="row">
            <div class="col-lg-12">
                <div class="white-box">
                    <p class="text-center text-muted">@lang('academics.no_curriculum_subjects_found')</p>
                </div>
            </div>
        </div>
        @else
        @foreach($subjects as $subject)
        <div class="row mt-20">
            <div class="col-lg-12">
                <div class="white-box">
                    <h4 class="mb-15">{{$subject->subject_name}} ({{$subject->subject_code}})</h4>
                    <div class="table-responsive">
                        <table class="table Crm_table_active3">
                            <thead>
                                <tr>
                                    <th>@lang('student.student_name')</th>
                                    <th>@lang('student.admission_no')</th>
                                    <th>@lang('academics.mark_pass_fail')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($subject->enrolledStudents as $assign)
                                <tr>
                                    <td>{{optional($assign->studentDetail)->full_name}}</td>
                                    <td>{{optional($assign->studentDetail)->admission_no}}</td>
                                    <td>
                                        <select class="primary_select form-control" name="is_pass[{{$assign->id}}]">
                                            <option value="" {{is_null($assign->is_pass) ? 'selected' : ''}}>@lang('academics.not_graded')</option>
                                            <option value="1" {{$assign->is_pass === 1 ? 'selected' : ''}}>@lang('academics.passed')</option>
                                            <option value="0" {{$assign->is_pass === 0 ? 'selected' : ''}}>@lang('academics.failed')</option>
                                        </select>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted">@lang('academics.no_students_enrolled_this_subject')</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endforeach

        <div class="row mt-20">
            <div class="col-lg-12 text-center">
                <button type="submit" class="primary-btn fix-gr-bg">
                    <span class="ti-check pr-2"></span>
                    @lang('common.save')
                </button>
            </div>
        </div>
        @endif
        {{ Form::close() }}
    </div>
</section>
@endif
@endsection
