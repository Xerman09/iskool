{{-- The strip that makes four reports read as one page.

     Each tab is a link, not a pane: every view here is a heavy page with its own
     criteria, printing and modals, and loading all four to show one would cost
     every visitor four times what they asked for.

     A tab appears only where the visitor holds its permission, which is what
     makes the per-tab grants in Role & Permission mean anything - someone given
     only Open Invoices sees one tab, not four with three refusals behind them.

     The criteria travel, so switching view keeps the year, date or programme
     already chosen instead of making it be typed again. Only the keys the
     reports share are carried; a metric or a print flag belongs to the view that
     set it. --}}
@php
    $tabs = \App\Http\Controllers\Admin\Report\ReceivablesController::TABS;
    $carry = request()->only(['academic_id', 'semester_id', 'course_id', 'type', 'as_of', 'search']);
@endphp

<div class="row mb-20">
    <div class="col-lg-12">
        <div class="white-box receivables-tabs">
            @foreach ($tabs as $route => $label)
                @continue(!userPermission($route))
                @php $isActive = Route::currentRouteName() === $route; @endphp
                <a href="{{ route($route, $carry) }}"
                    class="receivables-tab {{ $isActive ? 'is-active' : '' }}"
                    @if ($isActive) aria-current="page" @endif>
                    {{ __($label) }}
                </a>
            @endforeach
        </div>
    </div>
</div>

@push('css')
    <style>
        .receivables-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            padding: 8px 10px;
        }

        .receivables-tab {
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            color: #6c757d;
            text-decoration: none;
            /* Transparent rather than absent, so the active bar appearing does not
               shift the label upward. */
            border-bottom: 3px solid transparent;
            transition: color .15s ease, background-color .15s ease, border-color .15s ease;
        }

        .receivables-tab:hover,
        .receivables-tab:focus-visible {
            color: #2b2b2b;
            background-color: rgba(0, 0, 0, .04);
            text-decoration: none;
        }

        .receivables-tab.is-active {
            color: #0f8b8d;
            border-bottom-color: #0f8b8d;
            background-color: rgba(15, 139, 141, .07);
        }

        /* A printed sheet is of one view; the way to the other three means nothing
           on paper. */
        @media print {
            .receivables-tabs { display: none; }
        }
    </style>
@endpush
