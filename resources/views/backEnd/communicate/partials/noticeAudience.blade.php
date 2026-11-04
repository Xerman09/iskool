{{-- Who the notice is for, below role.

     Tick as many as apply: a shifting schedule concerns BSIT and BSCS, not one of
     them, and an announcement for second years in sections A and B is two sections
     and one year level. Leave a group untouched and it does not narrow anything -
     which is what a notice meant before these fields existed, and what basic
     education wants every time.

     Checkboxes rather than a multi-select widget, to match the Message To list
     directly above: the same question answered the same way, and nothing that
     opens a panel over the rest of the form.

     Shared by the add and edit screens so the two cannot drift. The caller passes
     what is already chosen. --}}
@php
    $selectedCourses = array_map('intval', (array) ($selectedCourses ?? []));
    $selectedClasses = array_map('intval', (array) ($selectedClasses ?? []));
    $selectedSections = array_map('intval', (array) ($selectedSections ?? []));
    $selectedSemester = $selectedSemester ?? null;
    $uid = $uid ?? 'add';
@endphp

<div class="row mt-25">
    <div class="col-lg-12">
        <label class="primary_input_label mb-0">@lang('communicate.narrow_the_audience')</label>
        <p class="text-muted" style="font-size: 12px;">@lang('communicate.narrow_the_audience_hint')</p>
    </div>
</div>

<div class="row">
    @if (!isBasicEd())
        <div class="col-lg-4 mb-20">
            <label class="primary_input_label" for="">@lang('academics.program')</label>
            @forelse (@$courses ?? [] as $course)
                <div>
                    <input type="checkbox" class="common-checkbox" name="course_ids[]"
                        id="notice_course_{{ $uid }}_{{ $course->id }}" value="{{ $course->id }}"
                        {{ in_array((int) $course->id, $selectedCourses, true) ? 'checked' : '' }}>
                    <label for="notice_course_{{ $uid }}_{{ $course->id }}">{{ $course->course_name }}</label>
                </div>
            @empty
                <p class="text-muted mb-0">&mdash;</p>
            @endforelse
        </div>
    @endif

    <div class="col-lg-4 mb-20">
        <label class="primary_input_label" for="">@lang('academics.year_level')</label>
        @forelse (@$classes ?? [] as $class)
            <div>
                <input type="checkbox" class="common-checkbox" name="class_ids[]"
                    id="notice_class_{{ $uid }}_{{ $class->id }}" value="{{ $class->id }}"
                    {{ in_array((int) $class->id, $selectedClasses, true) ? 'checked' : '' }}>
                <label for="notice_class_{{ $uid }}_{{ $class->id }}">{{ $class->class_name }}</label>
            </div>
        @empty
            <p class="text-muted mb-0">&mdash;</p>
        @endforelse
    </div>

    <div class="col-lg-4 mb-20">
        <label class="primary_input_label" for="">@lang('academics.section')</label>
        @forelse (@$sections ?? [] as $section)
            <div>
                <input type="checkbox" class="common-checkbox" name="section_ids[]"
                    id="notice_section_{{ $uid }}_{{ $section->id }}" value="{{ $section->id }}"
                    {{ in_array((int) $section->id, $selectedSections, true) ? 'checked' : '' }}>
                <label for="notice_section_{{ $uid }}_{{ $section->id }}">{{ $section->section_name }}</label>
            </div>
        @empty
            <p class="text-muted mb-0">&mdash;</p>
        @endforelse
    </div>
</div>

<div class="row">
    <div class="col-lg-4">
        {{-- One term, not several: a notice belongs to the term it is written in,
             and the others have not started. --}}
        <label class="primary_input_label" for="">{{ termLabel() }}</label>
        <select class="primary_select form-control" name="semester_id">
            <option data-display="@lang('fees.all')" value="">{{ termLabel() }} &mdash; @lang('fees.all')</option>
            @foreach (@$semesters ?? [] as $semester)
                <option value="{{ $semester->id }}" {{ (string) $selectedSemester === (string) $semester->id ? 'selected' : '' }}>
                    {{ $semester->semester_name }}</option>
            @endforeach
        </select>
    </div>
</div>
