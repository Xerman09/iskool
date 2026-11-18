{{-- What is behind one figure on a student's account page, rendered by
     FeesStandardReportController@accountDetail from the very statement that
     figure was drawn from, so the two cannot disagree. --}}
@php
    $currency = generalSetting()->currency_symbol ?: '$';

    $titles = [
        'paid' => __('reports.account_detail_paid'),
        'unpaid' => __('reports.account_detail_unpaid'),
        'incoming' => __('reports.account_detail_incoming'),
    ];

    $figures = [
        'paid' => $s['paid'],
        'unpaid' => abs($s['balance']),
        'incoming' => $next ? $next['amount'] : 0,
    ];
@endphp
<div class="modal-header">
    <h4 class="modal-title">
        {{ $titles[$part] }}
        <span class="badge badge-info">{{ $currency }}{{ number_format($figures[$part], 2) }}</span>
    </h4>
    <div>
        {{-- Opens as its own page and prints itself. Printing the modal in place
             would bring the whole account behind it.
             fix-gr-bg, not tr-bg: the modal header is dark, and tr-bg's dark text
             on it is all but invisible. --}}
        <a href="{{ $printUrl }}" target="_blank" class="primary-btn small fix-gr-bg mr-15">
            <span class="ti-printer pr-2"></span>@lang('common.print')
        </a>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
    </div>
</div>
<div class="modal-body">
    <p class="text-muted mb-15" style="font-size: 12px;">
        <strong>{{ $student->full_name }}</strong>
        @if ($student->admission_no)
            &nbsp;&middot;&nbsp; {{ $student->admission_no }}
        @endif
        &nbsp;&middot;&nbsp; {{ implode(' · ', $labels) }}
    </p>

    <div class="table-responsive">
        @include('backEnd.reports.partials.studentAccountRows')
    </div>
</div>
