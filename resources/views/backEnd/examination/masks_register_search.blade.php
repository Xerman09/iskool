@extends('backEnd.master')
@section('title')
    @lang('exam.marks_register')
@endsection

@push('css')

<style>
    table thead th{
        white-space: nowrap;
    }
</style>
@endpush
@section('mainContent')
    <section class="sms-breadcrumb mb-20">
        <div class="container-fluid">
            <div class="row justify-content-between">
                <h1>@lang('exam.marks_register') </h1>
                <div class="bc-pages">
                    <a href="{{route('dashboard')}}">@lang('common.dashboard')</a>
                    <a href="#">@lang('exam.examinations')</a>
                    <a href="{{route('marks_register')}}">@lang('exam.marks_register')</a>
                </div>
            </div>
        </div>
    </section>
    <section class="admin-visitor-area">
        <div class="container-fluid p-0">
            <div class="row">
                <div class="col-lg-12">
                    <div class="white-box">
                        <div class="row">
                            <div class="col-lg-8 col-md-6 col-sm-6">
                                <div class="main-title">
                                    <h3 class="mb-15">@lang('common.select_criteria')</h3>
                                </div>
                            </div>
                            @if(userPermission('marks_register_create'))
                                <div class="col-lg-4 text-md-right text-left col-md-6 mb-30-lg col-sm-6 text_sm_right">
                                    <a href="{{route('marks_register_create')}}" class="primary-btn small fix-gr-bg">
                                        <span class="ti-plus pr-2"></span>
                                        @lang('exam.add_marks')
                                    </a>
                                </div>
                            @endif
                        </div>
                        {{ Form::open(['class' => 'form-horizontal', 'files' => true, 'route' => 'marks_register_search', 'method' => 'POST', 'enctype' => 'multipart/form-data', 'id' => 'search_student']) }}
                        <div class="row">
                            <input type="hidden" name="url" id="url" value="{{URL::to('/')}}">

                            <div class="col-lg-3 mt-30-md">
                                <select class="primary_select form-control{{ $errors->has('exam') ? ' is-invalid' : '' }}" name="exam">
                                    <option data-display="@lang('exam.select_exam') *" value="">@lang('exam.select_exam') *</option>
                                    {{-- The exam rows, matching what this screen's search expects and what the
                                         criteria panel before the search offers. Listing exam types here left the
                                         selection blank and posted an id this search cannot resolve. --}}
                                    @foreach(@$exams ?? [] as $exam)
                                        <option value="{{@$exam->id}}" {{isset($exam_id)? ($exam_id == $exam->id? 'selected':''):''}}>{{@$exam->examType->title}}/{{@$exam->class->class_name}}({{@$exam->section->section_name}})</option>
                                    @endforeach
                                </select>
                                @if ($errors->has('exam'))
                                    <span class="text-danger invalid-select" role="alert">
                                    {{ $errors->first('exam') }}
                                </span>
                                @endif
                            </div>
                            <div class="col-lg-3 mt-30-md">
                                <select class="primary_select form-control" id="select_exam_program" name="course_id">
                                    <option data-display="@lang('academics.program')" value="">@lang('academics.program')</option>
                                    @foreach(@$courses ?? [] as $course)
                                        <option value="{{@$course->id}}" {{isset($course_id)? ($course_id == $course->id? 'selected':''):''}}>{{@$course->course_name}}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-lg-3 mt-30-md">
                                <select class="primary_select form-control" id="select_exam_semester" name="semester_id">
                                    <option data-display="@lang('academics.semester')" value="">@lang('academics.semester')</option>
                                    @foreach(@$semesters ?? [] as $semester)
                                        <option value="{{@$semester->id}}" {{isset($semester_id) && $semester_id ? ($semester_id == $semester->id? 'selected':'') : ($semester->is_active? 'selected':'')}}>{{@$semester->semester_name}}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-lg-3 mt-30-md">
                                <select class="primary_select form-control {{ $errors->has('class') ? ' is-invalid' : '' }}" id="class_subject" name="class">
                                    <option data-display="@lang('common.select_class') *" value="">@lang('common.select_class') *</option>
                                    @foreach($classes as $class)
                                        <option value="{{@$class->id}}" {{isset($class_id)? ($class_id == $class->id? 'selected':''):''}}>{{@$class->class_name}}</option>
                                    @endforeach
                                </select>
                                @if ($errors->has('class'))
                                    <span class="text-danger invalid-select" role="alert">
                                    {{ $errors->first('class') }}
                                </span>
                                @endif
                            </div>



                            <div class="col-lg-3 mt-30-md" id="select_class_subject_div">
                                <select class="primary_select form-control{{ $errors->has('subject') ? ' is-invalid' : '' }} select_class_subject" id="select_class_subject" name="subject">
                                    <option data-display="@lang('common.select_subjects') *" value="">@lang('common.select_subjects') *</option>
                                    {{-- Keep the searched subject visible; the rest is loaded by ajax on class change. --}}
                                    @if(isset($subject_id) && $subject_id)
                                        <option value="{{$subject_id}}" selected>{{@App\SmSubject::find($subject_id)->subject_name}}</option>
                                    @endif
                                </select>
                                @if ($errors->has('subject'))
                                    <span class="text-danger invalid-select" role="alert">
                                    {{ $errors->first('subject') }}
                                </span>
                                @endif
                            </div>

                            <div class="col-lg-3 mt-30-md" id="m_select_subject_section_div">
                                <select class="primary_select form-control{{ $errors->has('section') ? ' is-invalid' : '' }} m_select_subject_section" id="m_select_subject_section" name="section">
                                    <option data-display="@lang('common.select_section') " value="">@lang('common.select_section') </option>
                                    {{-- Keep the searched block visible; the rest is loaded by ajax on subject change. --}}
                                    @if(isset($section_id) && $section_id)
                                        <option value="{{$section_id}}" selected>{{@App\SmSection::find($section_id)->section_name}}</option>
                                    @endif
                                </select>
                                @if ($errors->has('section'))
                                    <span class="text-danger invalid-select" role="alert">
                                    {{ $errors->first('section') }}
                                </span>
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

    @if(isset($students))

        <section class="mt-20">
            <div class="container-fluid p-0">

                {{ Form::open(['class' => 'form-horizontal', 'files' => true, 'route' => 'marks_register_store', 'method' => 'POST', 'enctype' => 'multipart/form-data', 'id' => 'marks_register_store']) }}


                <input type="hidden" name="exam_id" value="{{@$exam_id}}">
                <input type="hidden" name="class_id" value="{{@$class_id}}">
                <input type="hidden" name="section_id" value="{{@$section_id}}">
                <input type="hidden" name="subject_id" value="{{@$subject_id}}">

                <div class="row">
                    <div class="col-lg-12">
                        <div class="white-box mt-20">
                            <div class="row">
                                <div class="col-lg-6 col-md-6">
                                    <div class="main-title">
                                        <h3 class="mb-15">@lang('exam.marks_register')</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="table-responsive">
                            <table id="default_table_searching" class="table" cellspacing="0" width="100%" >
                                <thead>
                                <tr>
                                    <th rowspan="2" >@lang('student.admission_no').</th>
                                    <th rowspan="2" >@lang('student.roll_no').</th>
                                    <th rowspan="2" >@lang('common.student')</th>
                                    <th rowspan="2" >@lang('common.class_Sec')</th>
                                    <th colspan="{{@$number_of_exam_parts}}"> {{@$subjectNames->subject_name}}</th>
                                    <th rowspan="2">@lang('exam.is_present')</th>
                                </tr>
                                <tr>
                                    @foreach($marks_entry_form as $part)
                                        <th>{{@$part->exam_title}} ( {{@$part->exam_mark}} ) </th>
                                    @endforeach
                                </tr>
                                </thead>
                                <tbody>
                                @php $colspan = 3; $counter = 0;  @endphp
                                @foreach($students as $student)
                                    <tr>
                                        <td>{{$student->studentDetail->admission_no}}
                                            <input type="hidden" name="student_ids[]" value="{{@$student->id}}">
                                            <input type="hidden" name="student_rolls[{{$student->id}}]" value="{{@$student->roll_no}}">
                                            <input type="hidden" name="student_admissions[{{@$student->id}}]" value="{{@$student->admission_no}}">
                                        </td>
                                        <td>{{@$student->roll_no}}</td>
                                        <td>{{@$student->studentDetail->full_name}}</td>
                                        <td>{{$student->class->class_name.'('.$student->section->section_name .')' }}</td>
                                        @php $entry_form_count=0; @endphp
                                        @foreach($marks_entry_form as $part)
                                            <?php

                                            $search_mark = App\SmMarkStore::get_mark_by_part($student->student->id, $exam_type_id, $class_id, $student->section_id, $subject_id, $part->id, $student->id);
                                            $is_absent = App\SmMarkStore::get_mark_by_part($student->student->id, $exam_type_id, $class_id, $section_id, $subject_id, $part->id, $student->id);

                                            ?>
                                            <td>
                                                <div class="primary_input mt-10">
                                                    <p>{{@$search_mark}}</p>
                                                </div>
                                            </td>
                                        @endforeach
                                        <?php

                                        $is_absent_check = App\SmMarksRegister::is_absent_check($part->exam_term_id, $part->class_id, $part->section_id, $part->subject_id,$student->student_id, $student->id);
                                        ?>
                                        <td>
                                            <div class="primary_input">
                                                @if(@$is_absent_check->attendance_type == 'P')
                                                    <button class="primary-btn small fix-gr-bg" type="button">@lang('exam.present')</button>
                                                @else
                                                    <button class="primary-btn small bg-danger text-white border-0" type="button">@lang('exam.absent')</button>
                                                @endif
                                            </div>

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
        </section>

    @endif

@endsection
