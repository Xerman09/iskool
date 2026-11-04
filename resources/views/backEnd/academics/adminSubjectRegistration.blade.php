@extends('backEnd.master')
    @section('title')
        @lang('academics.enroll_student_in_subjects')
    @endsection
@section('mainContent')
<section class="sms-breadcrumb mb-20">
    <div class="container-fluid">
        <div class="row justify-content-between">
            <h1>@lang('academics.enroll_student_in_subjects')</h1>
            <div class="bc-pages">
                <a href="{{route('dashboard')}}">@lang('common.dashboard')</a>
                <a href="#">@lang('academics.academics')</a>
                <a href="#">@lang('academics.enroll_student_in_subjects')</a>
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

        {{-- Basic education has no programs, so the filter that narrows the picker by
             one is skipped there and the student dropdown takes the whole row. --}}
        @php $showProgram = !isBasicEd(); @endphp
        <div class="row">
            <div class="col-lg-12">
                <div class="white-box">
                    {{ Form::open(['class' => 'form-horizontal', 'route' => 'admin-subject-registration', 'method' => 'GET']) }}
                        <div class="row">
                            @if($showProgram)
                            <div class="col-lg-4 mb-3 mb-lg-0">
                                <label class="primary_input_label">@lang('academics.program')</label>
                                <select class="primary_select form-control" name="course_id" onchange="this.form.submit()">
                                    <option data-display="@lang('fees.all')" value="">@lang('academics.program') &mdash; @lang('fees.all')</option>
                                    @foreach($courses as $c)
                                    <option value="{{$c->id}}" {{ (string) request('course_id') === (string) $c->id ? 'selected' : '' }}>{{$c->course_name}}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                            <div class="{{ $showProgram ? 'col-lg-6' : 'col-lg-10' }} mb-3 mb-lg-0">
                                <label class="primary_input_label">@lang('academics.select_student') <span class="text-danger"> *</span></label>
                                <select class="primary_select form-control" name="student_id" required>
                                    <option value="">@lang('academics.select_student') *</option>
                                    @foreach($students as $s)
                                    <option value="{{$s->id}}" {{ (string) request('student_id') === (string) $s->id ? 'selected' : '' }}>
                                        {{$s->full_name}} ({{$s->admission_no}}){{ $s->course ? ' - ' . $s->course->course_name : '' }}{{ $s->class ? ' - ' . $s->class->class_name : '' }}
                                    </option>
                                    @endforeach
                                </select>
                                @if($students->count() == 0)
                                <span class="text-muted">@lang('academics.no_students_with_program')</span>
                                @endif
                            </div>
                            <div class="col-lg-2 mb-3 mb-lg-0">
                                <label class="primary_input_label">&nbsp;</label>
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

        @if(!$student)
        <div class="row mt-20">
            <div class="col-lg-12">
                <p class="text-center text-muted">@lang('academics.select_student_to_enroll')</p>
            </div>
        </div>
        @elseif(!$activeSemester)
        <div class="row mt-20">
            <div class="col-lg-12">
                <div class="white-box">
                    <p class="text-center text-muted">@lang('academics.no_active_semester')</p>
                </div>
            </div>
        </div>
        @elseif(!$student->class_id)
        <div class="row mt-20">
            <div class="col-lg-12">
                <div class="white-box">
                    <p class="text-center text-muted">@lang('academics.not_enrolled_yet')</p>
                </div>
            </div>
        </div>
        @elseif($subjects->count() == 0)
        <div class="row mt-20">
            <div class="col-lg-12">
                <div class="white-box">
                    <p class="text-center text-muted">@lang('academics.no_curriculum_subjects_found')</p>
                </div>
            </div>
        </div>
        @else

        <div class="row mt-20">
            <div class="col-lg-12">
                <div class="white-box">
                    <h4 class="mb-10">
                        {{$student->full_name}} ({{$student->admission_no}})
                        @if(enrollmentStatus($student) == 'enrolled')
                            <span class="badge badge-success">@lang('academics.enrolled')</span>
                        @elseif($hasSubmitted)
                            <span class="badge badge-warning">@lang('academics.awaiting_invoice')</span>
                        @endif
                    </h4>
                    <p class="mb-0 text-muted">
                        @if(!isBasicEd())
                            @lang('academics.program'): {{ optional($student->course)->course_name }} &nbsp;|&nbsp;
                        @endif
                        @lang('academics.curriculum_version'): {{ optional($student->curriculumVersion)->version_label }} &nbsp;|&nbsp;
                        @lang('academics.year_level'): {{ optional($student->class)->class_name }} &nbsp;|&nbsp;
                        {{ termLabel() }}: {{$activeSemester->semester_name}}
                    </p>
                </div>
            </div>
        </div>

        @if($hasSubmitted)
        <div class="row mb-20 mt-20">
            <div class="col-lg-12">
                <a href="{{route('assign-program-balance-summary', ['student' => $student->id, 'state' => 'view'])}}" class="primary-btn small fix-gr-bg" target="_blank">
                    <span class="ti-eye pr-2"></span>
                    @lang('academics.view_balance_summary')
                </a>
            </div>
        </div>
        @endif

        {{ Form::open(['class' => 'form-horizontal', 'route' => 'admin-subject-registration-store', 'method' => 'POST']) }}
        <input type="hidden" name="student_id" value="{{$student->id}}">

        @foreach($subjects as $subject)
        <div class="row mt-20">
            <div class="col-lg-12">
                <div class="white-box">
                    <h5 class="mb-15">
                        {{$subject->subject_name}} ({{$subject->subject_code}}) &mdash; {{$subject->units}} @lang('academics.units')
                        @if($subject->subject_classification === 'minor')
                        <span class="badge badge-info">@lang('academics.optional_minor')</span>
                        @endif
                    </h5>

                    @if(!empty($subject->unmetPrerequisites))
                    <p class="text-danger mb-15">
                        @lang('academics.prerequisite_not_met'): {{ implode(', ', $subject->unmetPrerequisites) }}
                    </p>
                    @endif

                    @if($subject->blocks->count() == 0)
                    <p class="text-muted mb-0">@lang('academics.no_open_blocks')</p>
                    @else
                    <div class="table-responsive">
                        <table class="table Crm_table_active3">
                            <thead>
                                <tr>
                                    <th></th>
                                    @if(!isBasicEd())
                                    <th>@lang('academics.program')</th>
                                    @endif
                                    <th>@lang('academics.block')</th>
                                    <th>@lang('academics.teacher')</th>
                                    <th>@lang('academics.schedule')</th>
                                    <th>@lang('academics.slots_left')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($subject->blocks as $block)
                                @php
                                    $isFull = ($block->slotsLeft !== null && $block->slotsLeft <= 0 && $subject->chosenAssignSubjectId != $block->id) || !empty($subject->unmetPrerequisites);
                                @endphp
                                <tr>
                                    <td>
                                        <input type="radio"
                                            name="assign_subject_id[{{$subject->id}}]"
                                            value="{{$block->id}}"
                                            class="subject-block-radio"
                                            data-subject-id="{{$subject->id}}"
                                            data-subject-name="{{$subject->subject_name}}"
                                            data-slots="{{ $block->scheduleSlots->map(fn($s) => ['day' => $s->day, 'start' => $s->start_time, 'end' => $s->end_time])->toJson() }}"
                                            {{ $subject->chosenAssignSubjectId == $block->id ? 'checked' : '' }}
                                            {{ $isFull ? 'disabled' : '' }}
                                            {{ $subject->subject_classification === 'minor' ? '' : 'required' }}>
                                    </td>
                                    @if(!isBasicEd())
                                    <td>{{ optional(optional($block->subject)->course)->course_name }}</td>
                                    @endif
                                    <td>{{ optional($block->section)->section_name }}</td>
                                    <td>{{ optional($block->teacher)->full_name }}</td>
                                    <td>
                                        {{-- The same cell the Curriculum Layout uses, so a schedule
                                             reads the same wherever it is met. Section and teacher
                                             are left out: this row is one block, and both already
                                             have a column. --}}
                                        @include('backEnd.academics.partials.subjectScheduleCell', [
                                            'slots' => $block->scheduleSlots,
                                            'showContext' => false,
                                        ])
                                    </td>
                                    <td>{{ $block->slotsLeft === null ? __('academics.unlimited') : $block->slotsLeft }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endforeach

        <div class="row mt-20 mb-40">
            <div class="col-lg-12 text-right">
                <button type="submit" class="primary-btn fix-gr-bg">
                    <span class="ti-check pr-2"></span>
                    @lang('academics.enroll_section')
                </button>
            </div>
        </div>

        {{ Form::close() }}
        @endif

    </div>
</section>
@endsection

@push('script')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var radios = document.querySelectorAll('input.subject-block-radio');

        function overlaps(a, b) {
            return String(a.day) === String(b.day) && a.start < b.end && b.start < a.end;
        }

        radios.forEach(function (radio) {
            radio.addEventListener('change', function () {
                if (!this.checked) return;

                var mySlots = JSON.parse(this.dataset.slots || '[]');
                var mySubjectId = this.dataset.subjectId;
                var conflictWith = null;

                radios.forEach(function (other) {
                    if (conflictWith || other === radio || !other.checked || other.dataset.subjectId === mySubjectId) {
                        return;
                    }
                    var otherSlots = JSON.parse(other.dataset.slots || '[]');
                    mySlots.forEach(function (a) {
                        otherSlots.forEach(function (b) {
                            if (!conflictWith && overlaps(a, b)) {
                                conflictWith = other.dataset.subjectName;
                            }
                        });
                    });
                });

                if (conflictWith) {
                    toastr.error('This schedule conflicts with ' + conflictWith + '. Please choose a different block.', 'Failed');
                    this.checked = false;
                }
            });
        });
    });
</script>
@endpush
