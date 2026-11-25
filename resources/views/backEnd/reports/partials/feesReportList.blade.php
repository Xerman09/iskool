{{-- The students behind one figure on a card. Rendered by
     FeesStandardReportController@metricList from the very rows that figure was
     counted from, so the two cannot disagree. --}}
@php
    $currency = generalSetting()->currency_symbol ?: '$';

    /* The period this report is being read at, translated into the account page's
       own terms. The two name periods differently - the report says academic_id
       and semester_id, the account says scope, session_id and semester_id - so the
       link has to carry the translation or the account opens on its own default
       and the reader lands on different figures from the row they clicked. */
    $acctParams = request('semester_id') === 'whole_stay'
        ? ['scope' => 'whole_stay']
        : array_filter([
            'scope' => 'semester',
            'session_id' => request('academic_id'),
            'semester_id' => request('semester_id'),
        ]);
@endphp
<div class="modal-header">
    <h4 class="modal-title">
        {{ $title }}
        <span class="badge badge-info">{{ __('reports.students_counted', ['count' => $rows->count()]) }}</span>
    </h4>
    <div>
        {{-- Carries the criteria this list was built from, so the sheet is of what is
             on screen. Opens as its own page and prints itself - printing the modal in
             place would bring the whole report behind it. Nothing to print when the
             list is empty. --}}
        @if ($rows->count() > 0)
            {{-- Statements for exactly the students in this list, which is what makes
                 the Outstanding figure printable as a stack of letters. --}}
            {{-- fix-gr-bg, not tr-bg: the modal header is dark, and tr-bg's dark text
                 on it is all but invisible. Both buttons print, so both read alike. --}}
            <a href="{{ $statementsUrl }}" target="_blank" class="primary-btn small fix-gr-bg mr-15">
                <span class="ti-printer pr-2"></span>@lang('reports.fees_statement_print_all')
            </a>
            <a href="{{ $printUrl }}" target="_blank" class="primary-btn small fix-gr-bg mr-15">
                <span class="ti-printer pr-2"></span>@lang('common.print')
            </a>
        @endif
        <button type="button" class="close" data-dismiss="modal">&times;</button>
    </div>
</div>
<div class="modal-body">
    @include('backEnd.reports.partials.feesRows')
</div>
