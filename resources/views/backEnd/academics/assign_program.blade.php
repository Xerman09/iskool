@extends('backEnd.master')
@section('title')
@lang('academics.assign_program')
@endsection

@section('mainContent')
<section class="sms-breadcrumb mb-20">
    <div class="container-fluid">
        <div class="row justify-content-between">
            <h1>@lang('academics.assign_program')</h1>
            <div class="bc-pages">
                <a href="{{route('dashboard')}}">@lang('common.dashboard')</a>
                <a href="#">@lang('academics.academics')</a>
                <a href="#">@lang('academics.assign_program')</a>
            </div>
        </div>
    </div>
</section>
<section class="admin-visitor-area up_st_admin_visitor">
    <div class="container-fluid p-0">
        <div class="row">
            <div class="col-lg-4 col-xl-3">
                {{ Form::open(['class' => 'form-horizontal', 'route' => 'assign-program-store', 'method' => 'POST']) }}
                <div class="white-box">
                    <div class="main-title">
                        <h3 class="mb-15">@lang('academics.assign_program')</h3>
                    </div>
                    <div class="add-visitor">
                        <div class="row">
                            <div class="col-lg-12">
                                <label class="primary_input_label">@lang('academics.program') <span class="text-danger"> *</span></label>
                                <select class="primary_select form-control{{ @$errors->has('course_id') ? ' is-invalid' : '' }}" name="course_id" required>
                                    <option value="">@lang('academics.program') *</option>
                                    @foreach($courses as $c)
                                    <option value="{{$c->id}}">{{$c->course_name}}</option>
                                    @endforeach
                                </select>
                                @if ($errors->has('course_id'))
                                <span class="text-danger invalid-select" role="alert">{{ @$errors->first('course_id') }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="row mt-15">
                            <div class="col-lg-12">
                                <label class="primary_input_label">@lang('academics.curriculum_version') <span class="text-danger"> *</span></label>
                                <select class="primary_select form-control{{ @$errors->has('curriculum_version_id') ? ' is-invalid' : '' }}" name="curriculum_version_id" required>
                                    <option value="">@lang('academics.curriculum_version') *</option>
                                    @foreach($curriculumVersions as $cv)
                                    <option value="{{$cv->id}}">{{$cv->version_label}}</option>
                                    @endforeach
                                </select>
                                @if ($errors->has('curriculum_version_id'))
                                <span class="text-danger invalid-select" role="alert">{{ @$errors->first('curriculum_version_id') }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="row mt-15">
                            <div class="col-lg-12">
                                <label class="primary_input_label">@lang('academics.select_student') <span class="text-danger"> *</span></label>
                                <select class="primary_select form-control{{ @$errors->has('student_id') ? ' is-invalid' : '' }}" name="student_id" required>
                                    <option value="">@lang('academics.select_student')</option>
                                    @foreach($students as $s)
                                    <option value="{{$s->id}}" {{ (string) @$selectedStudentId === (string) $s->id ? 'selected' : '' }}>{{$s->first_name}} {{$s->last_name}} ({{$s->admission_no}}){{ $s->course ? ' - ' . __('academics.currently') . ': ' . $s->course->course_name : '' }}</option>
                                    @endforeach
                                </select>
                                @if ($errors->has('student_id'))
                                <span class="text-danger invalid-select" role="alert">{{ @$errors->first('student_id') }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="row mt-40">
                            <div class="col-lg-12 text-center">
                                <button type="submit" class="primary-btn fix-gr-bg submit">
                                    <span class="ti-check pr-2"></span>
                                    @lang('academics.save')
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                {{ Form::close() }}
            </div>

            <div class="col-lg-8 col-xl-9">
                <div class="white-box">
                    <div class="row">
                        <div class="col-lg-4 no-gutters">
                            <div class="main-title">
                                <h3 class="mb-15">@lang('academics.assigned_students')</h3>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-12">
                            <x-table>
                                <table id="table_id" class="table Crm_table_active3" cellspacing="0" width="100%">
                                    <thead>
                                        <tr>
                                            <th>@lang('academics.student')</th>
                                            <th>@lang('academics.program')</th>
                                            <th>@lang('academics.curriculum_version')</th>
                                            <th>@lang('academics.academic_year_added')</th>
                                            <th>@lang('academics.year_level')</th>
                                            <th>@lang('academics.remaining_balance')</th>
                                            <th>@lang('student.status')</th>
                                            <th>@lang('academics.program_status')</th>
                                            <th>@lang('academics.alumni_status')</th>
                                            <th>@lang('student.action')</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($assignedStudents as $s)
                                        <tr>
                                            <td valign="top">{{$s->first_name}} {{$s->last_name}} ({{$s->admission_no}})</td>
                                            <td valign="top">{{ optional($s->course)->course_name }}</td>
                                            <td valign="top">{{ optional($s->curriculumVersion)->version_label }}</td>
                                            <td valign="top">{{ optional($s->academicYear)->year }}</td>
                                            <td valign="top">{{ optional($s->class)->class_name }}</td>
                                            <td valign="top">
                                                @if($s->paymentPlan)
                                                    {{ __('academics.on_payment_plan', ['plan' => $s->paymentPlan['name'], 'paid' => $s->paymentPlan['paid'], 'total' => $s->paymentPlan['total']]) }}
                                                @elseif($s->remainingBalance !== null)
                                                    {{ currency_format($s->remainingBalance) ?: number_format($s->remainingBalance, 2) }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td valign="top">
                                                @if($s->enrollment_status == 'enrolled')
                                                    {{ __('academics.enrolled') }}
                                                @elseif($s->hasRegisteredSubjects)
                                                    {{ __('academics.awaiting_invoice') }}
                                                @else
                                                    {{ __('academics.pending_enrollment') }}
                                                @endif
                                            </td>
                                            <td valign="top">
                                                <label class="switch_toggle">
                                                    <input type="checkbox" class="program_status_switch" data-id="{{ $s->id }}"
                                                        {{ $s->program_status == 1 ? 'checked' : '' }}>
                                                    <span class="slider round"></span>
                                                </label>
                                                <span class="d-block">{{ $s->program_status == 1 ? __('academics.program_completed') : __('academics.program_ongoing') }}</span>
                                            </td>
                                            <td valign="top">
                                                <label class="switch_toggle">
                                                    <input type="checkbox" class="alumni_status_switch" data-id="{{ $s->id }}"
                                                        {{ $s->program_status != 1 ? 'disabled' : '' }}
                                                        {{ $s->alumni_active_status == 1 ? 'checked' : '' }}>
                                                    <span class="slider round"></span>
                                                </label>
                                                @if($s->program_status == 1)
                                                <span class="d-block">{{ $s->alumni_active_status == 1 ? __('academics.alumni_active') : __('academics.alumni_inactive') }}</span>
                                                @else
                                                <span class="d-block">-</span>
                                                @endif
                                            </td>
                                            <td valign="top">
                                                @if($s->hasRegisteredSubjects)
                                                @php
                                                    $generateInvoiceForm = '<form action="'.route('assign-program-generate-invoice').'" method="POST" style="margin:0;">'
                                                        . csrf_field()
                                                        . '<input type="hidden" name="student_id" value="'.$s->id.'">'
                                                        . '<button type="submit" class="dropdown-item">'.__('academics.generate_down_payment_invoice').'</button>'
                                                        . '</form>';

                                                    $routeList = [
                                                        $generateInvoiceForm,
                                                        '<a class="dropdown-item" target="_blank" href="'.route('assign-program-balance-summary', ['student' => $s->id, 'state' => 'view']).'">'.__('academics.balance_summary').'</a>',
                                                    ];
                                                @endphp
                                                <x-drop-down-action-component :routeList="$routeList" />
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </x-table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
@include('backEnd.partials.data_table_js')

@section('script')
<script>
    function bindStatusSwitch(selector, url, fieldName) {
        $(document).on('change', selector, function () {
            var checkbox = $(this);
            var studentId = checkbox.data('id');
            var isChecked = checkbox.is(':checked') ? 1 : 0;
            var data = { student_id: studentId };
            data[fieldName] = isChecked;

            $.ajax({
                type: 'POST',
                url: url,
                data: data,
                dataType: 'json',
                success: function (response) {
                    if (response.message) {
                        toastr.success(response.message, 'Success');
                        location.reload();
                    } else {
                        toastr.error(response.error, 'Failed');
                        checkbox.prop('checked', !isChecked);
                    }
                },
                error: function () {
                    toastr.error('Operation Failed', 'Failed');
                    checkbox.prop('checked', !isChecked);
                }
            });
        });
    }

    bindStatusSwitch('.program_status_switch', "{{ route('assign-program-status-update') }}", 'program_status');
    bindStatusSwitch('.alumni_status_switch', "{{ route('assign-program-alumni-status-update') }}", 'alumni_active_status');
</script>
@endsection
