@if($subject->scheduleSlots->count() == 0)
    <span class="text-muted">@lang('academics.not_yet_scheduled')</span>
@else
    @foreach($subject->scheduleSlots as $slot)
        <div>
            <strong>{{@$slot->weekend->name}}</strong>
            {{ date('g:i A', strtotime($slot->start_time)) }}&ndash;{{ date('g:i A', strtotime($slot->end_time)) }}
            @if(@$slot->section) ({{$slot->section->section_name}}) @endif
            @if(@$slot->teacherDetail && $slot->teacherDetail->full_name) &mdash; {{$slot->teacherDetail->full_name}} @endif
        </div>
    @endforeach
@endif
