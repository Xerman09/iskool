@extends('backEnd.master')
@section('title')
    {{ $title }}
@endsection

@section('mainContent')
    @php
        $currency = generalSetting()->currency_symbol ?: '$';
        $showProgram = !isBasicEd();

        /* The figure this page is about. Taken from the same summary the Standard
           Fees Report draws its cards from, so the headline here and the card
           there are one number. */
        $figure = $totals[$metric] ?? null;
        $isMoney = !in_array($metric, ['paid', 'partial', 'unpaid', 'no_invoice', 'all'], true);

        /* Only the chase list carries urgency, so only it gets the column. On the
           others it would be a column of blanks. */
        $showUrgency = $metric === 'due_now';
    @endphp

    <style>
        .mp-head { display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 14px; }
        .mp-figure { font-size: 28px; font-weight: 700; line-height: 1.1; }
        .mp-sub { font-size: 12px; color: #6b7280; }
        .mp-note { font-size: 12px; color: #6b7280; }
        .mp-crit { font-size: 12px; color: #9aa0a6; }
        .late { color: #b03024; font-weight: 600; }
        .soon { color: #a8641f; }
    </style>

    <section class="sms-breadcrumb mb-20">
        <div class="container-fluid">
            <div class="row justify-content-between">
                <h1>{{ $title }}</h1>
                <div class="bc-pages">
                    <a href="{{ route('dashboard') }}">@lang('common.dashboard')</a>
                    <a href="{{ route('fees-standard-report') }}">@lang('reports.standard_fees_report')</a>
                    <a href="#">{{ $title }}</a>
                </div>
            </div>
        </div>
    </section>

    <section class="admin-visitor-area up_st_admin_visitor">
        <div class="container-fluid p-0">

            <div class="row mb-20">
                <div class="col-lg-12">
                    <div class="white-box">
                        <form method="GET" action="" id="metricForm">
                            <div class="row">
                                <div class="col-lg-3 mb-3 mb-lg-0">
                                    <label class="primary_input_label">@lang('reports.school_year')</label>
                                    <select class="primary_select form-control" name="academic_id">
                                        @foreach ($years as $year)
                                            <option value="{{ $year->id }}"
                                                {{ (int) $criteria['academicId'] === (int) $year->id ? 'selected' : '' }}>
                                                {{ $year->year }}{{ $year->title ? ' - ' . $year->title : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-lg-3 mb-3 mb-lg-0">
                                    <label class="primary_input_label">{{ termLabel() }}</label>
                                    <select class="primary_select form-control" name="semester_id">
                                        <option value="">@lang('reports.whole_year')</option>
                                        <option value="whole_stay" {{ $criteria['scope'] === 'whole_stay' ? 'selected' : '' }}>
                                            @lang('academics.whole_stay')
                                        </option>
                                        @foreach ($semesters as $semester)
                                            <option value="{{ $semester->id }}"
                                                {{ (int) $criteria['semesterId'] === (int) $semester->id ? 'selected' : '' }}>
                                                {{ $semester->semester_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                @if ($showProgram)
                                    <div class="col-lg-3 mb-3 mb-lg-0">
                                        <label class="primary_input_label">@lang('academics.program')</label>
                                        <select class="primary_select form-control" name="course_id">
                                            <option value="">@lang('reports.all_programs')</option>
                                            @foreach ($courses as $course)
                                                <option value="{{ $course->id }}"
                                                    {{ (int) $criteria['courseId'] === (int) $course->id ? 'selected' : '' }}>
                                                    {{ $course->course_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                <div class="col-lg-3 mb-3 mb-lg-0">
                                    <label class="primary_input_label">@lang('academics.year_level')</label>
                                    <select class="primary_select form-control" name="class_id"
                                        onchange="this.form.querySelector('[name=section_name]').value=''; this.form.submit();">
                                        <option value="">@lang('reports.all_year_levels')</option>
                                        @foreach ($classes as $class)
                                            <option value="{{ $class->id }}"
                                                {{ (int) $criteria['classId'] === (int) $class->id ? 'selected' : '' }}>
                                                {{ $class->class_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-lg-3 mb-3 mb-lg-0 mt-lg-3">
                                    <label class="primary_input_label">@lang('academics.section')</label>
                                    {{-- Never disabled - see the note on the same control in
                                         feesReport.blade.php. --}}
                                    <select class="primary_select form-control" name="section_name">
                                        <option value="">@lang('reports.all_sections')</option>
                                        @foreach ($sections as $section)
                                            <option value="{{ $section->section_name }}"
                                                {{ $criteria['sectionName'] === $section->section_name ? 'selected' : '' }}>
                                                {{ $section->section_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-lg-3 mb-3 mb-lg-0 mt-lg-3">
                                    <label class="primary_input_label">@lang('reports.fees_as_of')</label>
                                    <input type="date" class="form-control" name="as_of" value="{{ $criteria['asOf'] }}">
                                </div>

                                <div class="col-lg-3 mb-3 mb-lg-0 mt-lg-3">
                                    <label class="primary_input_label">@lang('common.search')</label>
                                    <input type="text" class="form-control" name="search"
                                        value="{{ request('search') }}" placeholder="@lang('academics.student')">
                                </div>

                                <div class="col-lg-3 mb-3 mb-lg-0 mt-lg-3 d-flex align-items-end">
                                    <button type="submit" class="primary-btn small fix-gr-bg mr-10">
                                        @lang('common.search')
                                    </button>
                                    <a href="{{ url()->current() }}" class="primary-btn small tr-bg">@lang('reports.reset')</a>
                                </div>
                            </div>
                        </form>
                        <p class="mp-crit mt-15 mb-0">{{ implode(' · ', $labels) }}</p>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-12">
                    <div class="white-box">
                        <div class="mp-head mb-15">
                            <div>
                                <h3 class="mb-5">{{ $title }}</h3>
                                <span class="mp-figure">
                                    @if ($figure === null)
                                        {{ number_format($rows->count()) }}
                                    @elseif ($isMoney)
                                        {{ $currency }}{{ number_format($figure, 2) }}
                                    @else
                                        {{ number_format($figure) }}
                                    @endif
                                </span>
                                <span class="mp-sub d-block">
                                    {{ __('reports.students_counted', ['count' => $rows->count()]) }}
                                </span>
                            </div>
                            <div>
                                @if ($rows->count() > 0)
                                    <a href="{{ $statementsUrl }}" target="_blank" class="primary-btn small fix-gr-bg mr-15">
                                        <span class="ti-printer pr-2"></span>@lang('reports.fees_statement_print_all')
                                    </a>
                                    <a href="{{ $printUrl }}" target="_blank" class="primary-btn small fix-gr-bg mr-15">
                                        <span class="ti-printer pr-2"></span>@lang('common.print')
                                    </a>
                                @endif
                                <a href="{{ route('fees-standard-report') }}" class="primary-btn small tr-bg">
                                    @lang('reports.fees_see_whole')
                                </a>
                            </div>
                        </div>

                        @if ($showUrgency)
                            <p class="mp-note mb-15">@lang('reports.fees_due_now_note')</p>
                        @endif

                        {{-- The same table the card's modal shows, so a column added
                             there reaches this page too. --}}
                        @include('backEnd.reports.partials.feesRows')
                    </div>
                </div>
            </div>

        </div>
    </section>
@endsection
