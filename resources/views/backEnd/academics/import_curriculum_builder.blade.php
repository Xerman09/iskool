@extends('backEnd.master')

@section('title', 'Import Curriculum Subjects')

@section('mainContent')
<section class="sms-breadcrumb mb-20">
    <div class="container-fluid">
        <div class="row justify-content-between">
            <h1>Import Curriculum Subjects</h1>
            <div class="bc-pages">
                <a href="{{ route('dashboard') }}">@lang('common.dashboard')</a>
                <a href="{{ route('curriculum-builder', ['course_id' => $course->id, 'curriculum_version_id' => $curriculumVersion->id]) }}">@lang('academics.curriculum_builder')</a>
                <a href="#">Import Curriculum Subjects</a>
            </div>
        </div>
    </div>
</section>

<section class="admin-visitor-area up_st_admin_visitor">
    <div class="container-fluid p-0">
        <div class="row">
            <div class="col-lg-6">
                <div class="main-title">
                    <h3>Import Curriculum Subjects</h3>
                </div>
            </div>
            <div class="offset-lg-3 col-lg-3 text-right mb-20">
                <a href="{{ route('curriculum_builder_import_sample') }}">
                    <button class="primary-btn tr-bg text-uppercase bord-rad">
                        Download Sample File
                        <span class="pl ti-download"></span>
                    </button>
                </a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-12">
                {{ Form::open(['class' => 'form-horizontal', 'files' => true, 'route' => 'curriculum_builder_import_store', 'method' => 'POST']) }}
                    <div class="white-box">
                        <p class="mb-10">Program: <strong>{{ $course->course_name }}</strong> &middot; Curriculum Version: <strong>{{ $curriculumVersion->version_label }}</strong></p>
                        <p class="mb-20">Upload an XLSX, XLS, or CSV file (maximum 5 MB). The first row must contain these columns:</p>
                        <div class="alert alert-info">
                            <code>subject_code, class, semester, units, subject_classification, prerequisite_codes</code><br>
                            <code>subject_code</code> must match an existing base subject. <code>class</code> and <code>semester</code> must match existing names. Use <code>major</code> or <code>minor</code> for subject_classification. <code>prerequisite_codes</code> is optional &mdash; separate multiple codes with a comma or semicolon; each must belong to a subject already in this program &amp; curriculum version (existing or elsewhere in this file).
                        </div>

                        <input type="hidden" name="course_id" value="{{ $course->id }}">
                        <input type="hidden" name="curriculum_version_id" value="{{ $curriculumVersion->id }}">

                        <div class="row mt-30">
                            <div class="col-lg-6">
                                <div class="primary_input">
                                    <div class="primary_file_uploader">
                                        <input class="primary_input_field form-control {{ $errors->has('file') ? ' is-invalid' : '' }}" type="text" id="placeholderPhoto" placeholder="Excel or CSV file" readonly>
                                        <button type="button"><label class="primary-btn small fix-gr-bg" for="curriculum_import_file">@lang('common.browse')</label><input type="file" class="d-none" name="file" id="curriculum_import_file" accept=".xlsx,.xls,.csv"></button>
                                    </div>
                                    @if ($errors->has('file'))<span class="text-danger d-block">{{ $errors->first('file') }}</span>@endif
                                </div>
                            </div>
                        </div>
                        <div class="row mt-40"><div class="col-lg-12 text-center"><button class="primary-btn fix-gr-bg"><span class="ti-check"></span> Import Curriculum Subjects</button></div></div>
                    </div>
                {{ Form::close() }}
            </div>
        </div>
    </div>
</section>
@endsection
