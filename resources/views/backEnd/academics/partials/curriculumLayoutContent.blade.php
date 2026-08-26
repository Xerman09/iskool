<div class="row mt-40">
    <div class="col-lg-12">
        <div class="main-title">
            <h3 class="mb-10">{{$course->course_name}} &mdash; {{$curriculumVersion->version_label}}</h3>
        </div>
    </div>
</div>

@if($years->count() == 0)
<div class="row">
    <div class="col-lg-12">
        <div class="white-box">
            <p class="text-center text-muted">@lang('academics.no_curriculum_subjects_found')</p>
        </div>
    </div>
</div>
@else

@foreach($years as $year)
<div class="row mt-30">
    <div class="col-lg-12">
        <div class="white-box">
            <h4 class="mb-20">{{@$year['yearLevel']->class_name}}</h4>

            @foreach($year['semesters'] as $sem)
            <div class="mb-20">
                <h5 class="mb-10">{{@$sem['semester']->semester_name}}</h5>
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
                            @foreach($sem['subjects'] as $subject)
                            <tr>
                                <td>{{$subject->subject_code}}</td>
                                <td>{{$subject->subject_name}}</td>
                                <td>{{$subject->units}}</td>
                                <td>{{ucfirst($subject->subject_classification)}}</td>
                                <td>@include('backEnd.academics.partials.subjectScheduleCell', ['subject' => $subject])</td>
                            </tr>
                            @endforeach
                            <tr>
                                <td colspan="3" class="text-right"><strong>@lang('academics.total_units')</strong></td>
                                <td colspan="2"><strong>{{$sem['units']}}</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="white-box" style="background:#f7f7fb;">
                    <h5 class="mb-15">@lang('academics.sem_fees')</h5>
                    <table class="table table-sm mb-0">
                        <tr>
                            <td>@lang('academics.units_price')</td>
                            <td class="text-right">{{currency_format($sem['unitsPrice']) ?: number_format($sem['unitsPrice'], 2)}}</td>
                        </tr>
                        @forelse($sem['miscFees'] as $fee)
                        <tr>
                            <td>{{$fee->name}}</td>
                            <td class="text-right">{{currency_format($fee->amount) ?: number_format($fee->amount, 2)}}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="2" class="text-muted">@lang('academics.no_misc_fee_set')</td>
                        </tr>
                        @endforelse
                        <tr>
                            <td><strong>@lang('academics.sem_total')</strong></td>
                            <td class="text-right"><strong>{{currency_format($sem['semTotal']) ?: number_format($sem['semTotal'], 2)}}</strong></td>
                        </tr>
                    </table>
                </div>
            </div>
            @endforeach

            <div class="row mt-15">
                <div class="col-lg-12 text-right">
                    <h5>@lang('academics.year_total'): {{currency_format($year['yearTotal']) ?: number_format($year['yearTotal'], 2)}}</h5>
                </div>
            </div>
        </div>
    </div>
</div>
@endforeach

<div class="row mt-30">
    <div class="col-lg-12">
        <div class="white-box" style="background:#eef2ff;">
            <h3 class="mb-20">@lang('academics.program_grand_total')</h3>
            <div class="row">
                <div class="col-lg-6">
                    <p class="mb-0 text-muted">@lang('academics.total_units')</p>
                    <h4>{{$grandTotalUnits}}</h4>
                </div>
                <div class="col-lg-6">
                    <p class="mb-0 text-muted">@lang('academics.program_grand_total')</p>
                    <h4>{{currency_format($grandTotal) ?: number_format($grandTotal, 2)}}</h4>
                </div>
            </div>
        </div>
    </div>
</div>

@endif
