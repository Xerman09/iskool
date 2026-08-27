@extends('backEnd.master')

@section('title', 'Import Subjects')

@section('mainContent')
<section class="sms-breadcrumb mb-20">
    <div class="container-fluid">
        <div class="row justify-content-between">
            <h1>Import Subjects</h1>
            <div class="bc-pages">
                <a href="{{ route('dashboard') }}">@lang('common.dashboard')</a>
                <a href="{{ route('subject') }}">@lang('academics.subject')</a>
                <a href="#">Import Subjects</a>
            </div>
        </div>
    </div>
</section>

<section class="admin-visitor-area up_st_admin_visitor">
    <div class="container-fluid p-0">
        <div class="row">
            <div class="col-lg-6">
                <div class="main-title">
                    <h3>Import Subjects</h3>
                </div>
            </div>
            <div class="offset-lg-3 col-lg-3 text-right mb-20">
                <a href="{{ route('subject_import_sample') }}">
                    <button class="primary-btn tr-bg text-uppercase bord-rad">
                        Download Sample File
                        <span class="pl ti-download"></span>
                    </button>
                </a>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-12">
                {{ Form::open(['class' => 'form-horizontal', 'files' => true, 'route' => 'subject_import_store', 'method' => 'POST']) }}
                    <div class="white-box">
                        <p class="mb-20">Upload an XLSX, XLS, or CSV file (maximum 5 MB). The first row must contain these columns:</p>
                        <div class="alert alert-info">
                            <code>subject_name, subject_code, subject_type{{ @generalSetting()->result_type == 'mark' ? ', pass_mark' : '' }}</code><br>
                            Use <code>T</code> for theory or <code>P</code> for practical. Subject names and codes must be unique for the active academic year.
                        </div>
                        <div class="row mt-30">
                            <div class="col-lg-6">
                                <div class="primary_input">
                                    <div class="primary_file_uploader">
                                        <input class="primary_input_field form-control {{ $errors->has('file') ? ' is-invalid' : '' }}" type="text" id="placeholderPhoto" placeholder="Excel or CSV file" readonly>
                                        <button type="button"><label class="primary-btn small fix-gr-bg" for="subject_import_file">@lang('common.browse')</label><input type="file" class="d-none" name="file" id="subject_import_file" accept=".xlsx,.xls,.csv"></button>
                                    </div>
                                    @if ($errors->has('file'))<span class="text-danger d-block">{{ $errors->first('file') }}</span>@endif
                                </div>
                            </div>
                        </div>
                        <div class="row mt-40"><div class="col-lg-12 text-center"><button class="primary-btn fix-gr-bg"><span class="ti-check"></span> Import Subjects</button></div></div>
                    </div>
                {{ Form::close() }}
            </div>
        </div>
    </div>
</section>
@endsection

@section('script')
<script>
    document.getElementById('subject_import_file').addEventListener('change', function (event) {
        if (event.target.files.length) {
            document.getElementById('placeholderPhoto').placeholder = event.target.files[0].name;
        }
    });
</script>
@endsection
