@extends('backEnd.master')
    @section('title')
        @lang('academics.class_routine')
    @endsection
@section('mainContent')
<section class="sms-breadcrumb mb-20">
    <div class="container-fluid">
        <div class="row justify-content-between">
            <h1>@lang('academics.class_routine')</h1>
            <div class="bc-pages">
                <a href="{{route('dashboard')}}">@lang('common.dashboard')</a>
                <a href="#">@lang('academics.class_routine')</a>
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
        @elseif($subjects->count() == 0)
        <div class="row">
            <div class="col-lg-12">
                <div class="white-box">
                    <p class="text-center text-muted">@lang('academics.no_curriculum_subjects_found')</p>
                </div>
            </div>
        </div>
        @else
        <div class="row mt-10">
            <div class="col-lg-12">
                <div class="white-box">
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
        @endif

    </div>
</section>
@endsection
