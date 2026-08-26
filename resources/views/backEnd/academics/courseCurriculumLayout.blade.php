@extends('backEnd.master')
    @section('title')
        @lang('academics.curriculum_layout')
    @endsection
@section('mainContent')
<section class="sms-breadcrumb mb-20">
    <div class="container-fluid">
        <div class="row justify-content-between">
            <h1>@lang('academics.curriculum_layout')</h1>
            <div class="bc-pages">
                <a href="{{route('dashboard')}}">@lang('common.dashboard')</a>
                <a href="#">@lang('academics.academics')</a>
                <a href="#">@lang('academics.curriculum_layout')</a>
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
                    {{ Form::open(['class' => 'form-horizontal', 'route' => 'curriculum-layout', 'method' => 'GET']) }}
                        <div class="row">
                            <div class="col-lg-5 mb-3 mb-lg-0">
                                <select class="primary_select form-control" name="course_id" required>
                                    <option value="">@lang('academics.program') *</option>
                                    @foreach($courses as $c)
                                    <option value="{{$c->id}}" {{@$course && $course->id == $c->id ? 'selected' : ''}}>{{$c->course_name}}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-5 mb-3 mb-lg-0">
                                <select class="primary_select form-control" name="curriculum_version_id" required>
                                    <option value="">@lang('academics.curriculum_version') *</option>
                                    @foreach($curriculumVersions as $cv)
                                    <option value="{{$cv->id}}" {{@$curriculumVersion && $curriculumVersion->id == $cv->id ? 'selected' : ''}}>{{$cv->version_label}}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-2 mb-3 mb-lg-0">
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

        @if($course && $curriculumVersion)
        <div class="row mt-20">
            <div class="col-lg-12 text-right">
                <a href="{{$printUrl}}" target="_blank" class="primary-btn small fix-gr-bg">
                    <span class="ti-printer pr-2"></span>
                    @lang('academics.print')
                </a>
            </div>
        </div>

        @include('backEnd.academics.partials.curriculumLayoutContent')
        @else
        <div class="row mt-20">
            <div class="col-lg-12">
                <p class="text-center text-muted">@lang('academics.select_program_and_version')</p>
            </div>
        </div>
        @endif

    </div>
</section>
@endsection
