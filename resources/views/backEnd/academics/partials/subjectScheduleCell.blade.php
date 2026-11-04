{{-- A routine row per weekday collapses into one line per arrangement - MW rather
     than a Monday line and a Wednesday line carrying the same section, hour and
     teacher. See groupedScheduleSlots().

     Takes either a $subject carrying scheduleSlots, or $slots directly - Enroll in
     Subjects holds them on the block rather than the subject, and both screens
     should read the same.

     $showContext hides the section and the teacher. On a curriculum they belong in
     the cell, because the row is the subject and the cell is the only place they
     appear. On Enroll in Subjects the row is the block and both already have
     columns of their own, so repeating them there says the same thing twice. --}}
@php
    $scheduleSlots = $slots ?? (@$subject->scheduleSlots);
    $withContext = $showContext ?? true;
    $meetings = groupedScheduleSlots($scheduleSlots);
@endphp
@if($meetings->isEmpty())
    <span class="text-muted">@lang('academics.not_yet_scheduled')</span>
@else
    @foreach($meetings as $meeting)
        <div>
            <strong>{{ $meeting['days'] }}</strong>
            @if($meeting['start_time'] && $meeting['end_time'])
                {{ date('g:i A', strtotime($meeting['start_time'])) }}&ndash;{{ date('g:i A', strtotime($meeting['end_time'])) }}
            @endif
            @if($withContext && $meeting['section']) ({{ $meeting['section'] }}) @endif
            @if($withContext && $meeting['teacher']) &mdash; {{ $meeting['teacher'] }} @endif
        </div>
    @endforeach
@endif
