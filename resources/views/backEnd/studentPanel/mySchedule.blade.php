@extends('backEnd.master')
    @section('title')
        @lang('academics.my_schedule')
    @endsection
@section('mainContent')
<section class="sms-breadcrumb mb-20">
    <div class="container-fluid">
        <div class="row justify-content-between">
            <h1>@lang('academics.my_schedule')</h1>
            <div class="bc-pages">
                <a href="{{route('dashboard')}}">@lang('common.dashboard')</a>
                <a href="#">@lang('academics.my_schedule')</a>
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

        <div class="row mt-10 align-items-center">
            <div class="col-lg-8">
                <div class="main-title">
                    <h4 class="mb-20">@lang('academics.enrolling_for_semester') {{$activeSemester->semester_name}}</h4>
                </div>
            </div>
            <div class="col-lg-4 text-right">
                <button type="button" class="primary-btn small fix-gr-bg" data-toggle="modal" data-target="#enrolledSubjectsModal">
                    <span class="ti-list pr-2"></span>
                    @lang('academics.view_enrolled_subjects')
                </button>
            </div>
        </div>

        @if($times->count() == 0)
        <div class="row">
            <div class="col-lg-12">
                <div class="white-box">
                    <p class="text-center text-muted">@lang('academics.no_open_blocks')</p>
                </div>
            </div>
        </div>
        @else
        <div class="row mt-10">
            <div class="col-lg-12">
                <div class="white-box">
                    <div class="table-responsive">
                        <table class="table school-table-style" cellspacing="0" width="100%">
                            <thead>
                                <tr>
                                    <th>@lang('academics.time')</th>
                                    @foreach($smWeekends as $weekend)
                                    <th>{{$weekend->name}}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($times as $time)
                                <tr>
                                    <td><strong>{{ date('g:i A', strtotime($time)) }}</strong></td>
                                    @foreach($smWeekends as $weekend)
                                    @php $slot = $grid[$time][$weekend->id] ?? null; @endphp
                                    <td>
                                        @if($slot)
                                        <span>{{ date('g:i A', strtotime($slot->start_time)) }}&ndash;{{ date('g:i A', strtotime($slot->end_time)) }}</span><br>
                                        <strong>{{ optional($slot->subject)->subject_name }}</strong> ({{ optional($slot->subject)->subject_code }})<br>
                                        @if(optional($slot->section)->section_name)
                                        <span class="text-muted">{{ $slot->section->section_name }}</span><br>
                                        @endif
                                        @if(optional($slot->classRoom)->room_no)
                                        <span class="text-muted">@lang('common.room'): {{ $slot->classRoom->room_no }}</span><br>
                                        @endif
                                        @if(optional($slot->teacherDetail)->full_name)
                                        <span class="text-muted">{{ $slot->teacherDetail->full_name }}</span>
                                        @endif
                                        @endif
                                    </td>
                                    @endforeach
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif
        @endif

    </div>
</section>

@if($student && $student->course_id && $activeSemester && $subjects->count() > 0)
<div class="modal fade" id="enrolledSubjectsModal">
    <div class="modal-dialog modal-dialog-centered large-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">@lang('academics.view_enrolled_subjects')</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table Crm_table_active3">
                        <thead>
                            <tr>
                                <th>@lang('academics.subject_code')</th>
                                <th>@lang('academics.subject_name')</th>
                                <th>@lang('academics.units')</th>
                                <th>@lang('common.type')</th>
                                <th>@lang('academics.schedule')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($subjects as $subject)
                            <tr>
                                <td>{{$subject->subject_code}}</td>
                                <td>{{$subject->subject_name}}</td>
                                <td>{{$subject->units}}</td>
                                <td>{{ucfirst($subject->subject_classification)}}</td>
                                <td>@include('backEnd.academics.partials.subjectScheduleCell', ['subject' => $subject])</td>
                            </tr>
                            @endforeach
                            <tr>
                                <td colspan="2" class="text-right"><strong>@lang('academics.total_units')</strong></td>
                                <td colspan="3"><strong>{{$totalUnits}}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
@endsection
