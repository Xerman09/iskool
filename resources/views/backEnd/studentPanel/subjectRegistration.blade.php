@extends('backEnd.master')
    @section('title')
        @lang('academics.enroll_subjects')
    @endsection
@section('mainContent')
<section class="sms-breadcrumb mb-20">
    <div class="container-fluid">
        <div class="row justify-content-between">
            <h1>@lang('academics.enroll_subjects')</h1>
            <div class="bc-pages">
                <a href="{{route('dashboard')}}">@lang('common.dashboard')</a>
                <a href="#">@lang('academics.enroll_subjects')</a>
            </div>
        </div>
    </div>
</section>

<section class="admin-visitor-area up_st_admin_visitor">
    <div class="container-fluid p-0">

        @if(!$student || !$student->course_id)
        <div class="row">
            <div class="col-lg-12">
                <div class="white-box">
                    <p class="text-center text-muted">@lang('academics.not_enrolled_yet')</p>
                </div>
            </div>
        </div>
        @elseif(!$activeSemester)
        <div class="row">
            <div class="col-lg-12">
                <div class="white-box">
                    <p class="text-center text-muted">@lang('academics.no_active_semester')</p>
                </div>
            </div>
        </div>
        @elseif($subjects->count() == 0)
        <div class="row">
            <div class="col-lg-12">
                <div class="white-box">
                    <p class="text-center text-muted">@lang('academics.no_curriculum_subjects_found')</p>
                </div>
            </div>
        </div>
        @else
        <div class="row">
            <div class="col-lg-12">
                <div class="main-title">
                    <h4 class="mb-20">
                        @lang('academics.enrolling_for_semester') {{$activeSemester->semester_name}}
                        @if($student->enrollment_status == 'enrolled')
                            <span class="badge badge-success">@lang('academics.enrolled')</span>
                        @elseif($hasSubmitted)
                            <span class="badge badge-warning">@lang('academics.awaiting_invoice')</span>
                        @endif
                    </h4>
                </div>
            </div>
        </div>

        @if($hasSubmitted)
        <div class="row mb-20">
            <div class="col-lg-12">
                <a href="{{route('student-balance-summary', ['state' => 'view'])}}" class="primary-btn small fix-gr-bg" target="_blank">
                    <span class="ti-eye pr-2"></span>
                    @lang('academics.view_balance_summary')
                </a>
            </div>
        </div>
        @endif

        {{ Form::open(['class' => 'form-horizontal', 'route' => 'student-subject-registration-store', 'method' => 'POST']) }}

        @foreach($subjects as $subject)
        <div class="row mt-20">
            <div class="col-lg-12">
                <div class="white-box">
                    <h5 class="mb-15">{{$subject->subject_name}} ({{$subject->subject_code}}) &mdash; {{$subject->units}} @lang('academics.units')</h5>

                    @if($subject->blocks->count() == 0)
                    <p class="text-muted mb-0">@lang('academics.no_open_blocks')</p>
                    @else
                    <div class="table-responsive">
                        <table class="table Crm_table_active3">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>@lang('academics.block')</th>
                                    <th>@lang('academics.teacher')</th>
                                    <th>@lang('academics.schedule')</th>
                                    <th>@lang('academics.slots_left')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($subject->blocks as $block)
                                @php
                                    $isFull = $block->slotsLeft !== null && $block->slotsLeft <= 0 && $subject->chosenAssignSubjectId != $block->id;
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
                                            required>
                                    </td>
                                    <td>{{ optional($block->section)->section_name }}</td>
                                    <td>{{ optional($block->teacher)->full_name }}</td>
                                    <td>
                                        @forelse($block->scheduleSlots as $slot)
                                        <div>
                                            <strong>{{optional($slot->weekend)->name}}</strong>
                                            {{ date('g:i A', strtotime($slot->start_time)) }}&ndash;{{ date('g:i A', strtotime($slot->end_time)) }}
                                        </div>
                                        @empty
                                        <span class="text-muted">@lang('academics.not_yet_scheduled')</span>
                                        @endforelse
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
