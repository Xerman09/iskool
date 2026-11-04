                                {{-- multi exam div --}}
                              
                                    @if(moduleStatusCheck('University'))
                                    <div class="row  mt-25">
                                        <div class="col-lg-12">
                                            <label class="primary_input_label" for="">@lang('common.select_exam_type') <span class="text-danger"> *</span></label>
                                            @foreach($exams_types as $exams_type)
                                                <div class="primary_input">
                                                    <input type="checkbox" id="exams_types_{{@$exams_type->id}}" class="common-checkbox exam-checkbox" name="exams_types[]" value="{{@$exams_type->id}}" {{isset($selected_exam_type_id)? ($exams_type->id == $selected_exam_type_id? 'checked':''):''}}>
                                                    <label for="exams_types_{{@$exams_type->id}}">{{@$exams_type->title}}</label>
                                                </div>
                                            @endforeach
                                            <div class="primary_input">
                                                <input type="checkbox" id="all_exams" class="common-checkbox" name="all_exams[]" value="0" {{ (is_array(old('class_ids')) and in_array($class->id, old('class_ids'))) ? ' checked' : '' }}>
                                                <label for="all_exams">@lang('exam.all_select')</label>
                                            </div>
                                        </div>
                                        <div class="col-lg-12">
                                            @if($errors->has('exams_types'))
                                                <span class="text-danger validate-textarea-checkbox" role="alert">
                                                    {{ $errors->first('exams_types') }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    @includeIf('university::common.session_faculty_depart_academic_semester_level',
                                    ['required' => 
                                        ['USN', 'UD', 'UA', 'US', 'USL'],
                                        'div'=>'col-lg-12','row'=>1,'hide'=> ['USUB'],'mt'=>'mt-0'
                                    ])

                                    <label class="mt-30">@lang('university::un.select_subject') <span class="text-danger"> *</span></label>
                                    <div class="row" id="universityExamSubejct"></div>
                                        <div class="text-center loader loader_style" id="unSubjectLoader">
                                            <img src="{{asset('public/backEnd/img/demo_wait.gif')}}" alt="loader" height="60px" width="60px">
                                        </div>
                                @else
                                    <div class="row mt-25">
                                        <div class="col-lg-12">
                                            <label class="primary_input_label" for="">@lang('common.select_exam_type') <span class="text-danger"> *</span></label>
                                            @foreach($exams_types as $exams_type)
                                                <div class="primary_input">
                                                    <input type="checkbox" id="exams_types_{{@$exams_type->id}}" class="common-checkbox exam-checkbox" name="exams_types[]" value="{{@$exams_type->id}}" {{isset($selected_exam_type_id)? ($exams_type->id == $selected_exam_type_id? 'checked':''):''}}>
                                                    <label for="exams_types_{{@$exams_type->id}}">{{@$exams_type->title}}</label>
                                                </div>
                                            @endforeach
                                            <div class="primary_input">
                                                <input type="checkbox" id="all_exams" class="common-checkbox" name="all_exams[]" value="0" {{ (is_array(old('class_ids')) and in_array($class->id, old('class_ids'))) ? ' checked' : '' }}>
                                                <label for="all_exams">@lang('exam.all_select')</label>
                                            </div>
                                        </div>
                                        <div class="col-lg-12">
                                            @if($errors->has('exams_types'))
                                                <span class="text-danger validate-textarea-checkbox" role="alert">
                                                    {{ $errors->first('exams_types') }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    {{-- Narrow the subject list before the class loads it: one year level
                                         carries subjects from every program, term and curriculum version
                                         at once. Single exam setup has carried all three; this one was
                                         missing the term, so a year level's two semesters arrived as one
                                         long list of checkboxes. Basic education has no programs, so that
                                         one is skipped there and the other two split the row. --}}
                                    @php
                                        /* Curriculum version is only worth asking once a second one
                                           exists; with one it is a dropdown holding a single answer.
                                           Whatever survives both tests splits the row evenly, so no
                                           gap is left where a hidden field used to sit. */
                                        $showProgram = !isBasicEd();
                                        $showVersion = count(@$curriculumVersions ?? []) > 1;
                                        $criteriaWidth = 'col-lg-' . (int) (12 / (1 + (int) $showProgram + (int) $showVersion));
                                    @endphp
                                    <div class="row mt-25">
                                        @if($showProgram)
                                        <div class="{{ $criteriaWidth }}">
                                            <select class="primary_select form-control" id="exam_course" name="course_id">
                                                <option data-display="@lang('fees.all')" value="">@lang('academics.program') — @lang('fees.all')</option>
                                                @foreach (@$courses ?? [] as $course)
                                                    <option value="{{ $course->id }}">{{ $course->course_name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @endif
                                        @if($showVersion)
                                        <div class="{{ $criteriaWidth }}">
                                            <select class="primary_select form-control" id="exam_curriculum_version" name="curriculum_version_id">
                                                <option data-display="@lang('fees.all')" value="">@lang('academics.curriculum_version') — @lang('fees.all')</option>
                                                @foreach ($curriculumVersions as $cv)
                                                    <option value="{{ $cv->id }}">{{ $cv->version_label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @endif
                                        <div class="{{ $criteriaWidth }}">
                                            <select class="primary_select form-control" id="exam_semester" name="semester_id">
                                                <option data-display="@lang('fees.all')" value="">{{ termLabel() }} — @lang('fees.all')</option>
                                                @foreach (@$semesters ?? [] as $semester)
                                                    <option value="{{ $semester->id }}">{{ $semester->semester_name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row mt-25">
                                        <div class="col-lg-12">
                                            <select class="primary_select form-control {{ $errors->has('class_ids') ? ' is-invalid' : '' }}" id="exam_class" name="class_ids">
                                                <option data-display="@lang('common.select_class') *" value="">@lang('common.select_class') *</option>
                                                @foreach($classes as $class)
                                                <option value="{{@$class->id}}">{{@$class->class_name}}</option>
                                                @endforeach
                                            </select>
                                            @if ($errors->has('class_ids'))
                                                <span class="text-danger invalid-select" role="alert">
                                                    {{ $errors->first('class_ids') }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="row mt-25" id="exam_subejct">
                                    </div>
                                    @endif 


@if(moduleStatusCheck('University'))
<script src="{{ asset('Modules/University/Resources/assets/js/app.js') }}"></script>
@else 
<script src="{{ asset('public/backEnd/js/custom.js') }}"></script>
<script src="{{ asset('public/backEnd/js/developer.js') }}"></script>
@endif 

                               
                                  {{-- multi exam end --}}