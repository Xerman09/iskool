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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $titles[$part] }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #222; }
        .sheet { padding: 18px 24px; }
        .sheet h2 { margin: 0 0 2px; font-size: 20px; }
        .sheet h3 { margin: 0 0 12px; font-size: 15px; font-weight: 600; }
        .sheet .meta { font-size: 12px; color: #555; margin-bottom: 14px; }
        .sheet .meta span { margin-right: 18px; white-space: nowrap; }
        table.sheet-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        table.sheet-table th, table.sheet-table td {
            border: 1px solid #c9c9c9; padding: 6px 8px; text-align: left; vertical-align: top;
        }
        table.sheet-table thead th { background: #f1f1f1; font-weight: 700; }
        table.sheet-table tfoot th { background: #f7f7f7; font-weight: 700; }
        /* Money reads as a column of figures, not as prose. */
        table.sheet-table .num, table.sheet-table .text-right { text-align: right; font-variant-numeric: tabular-nums; }
        /* So a long list carries its headings onto every sheet rather than only the
           first - a second page of bare amounts is unreadable. */
        table.sheet-table thead { display: table-header-group; }
        table.sheet-table tfoot { display: table-footer-group; }
        table.sheet-table tr { page-break-inside: avoid; }
        .empty { text-align: center; color: #777; padding: 24px 0; }
        .note { font-size: 11px; color: #666; margin-top: 10px; }
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .sheet { padding: 0; }
        }
    </style>
</head>
<body>
<div class="sheet">
    <h2>{{ generalSetting()->school_name }}</h2>
    <h3>{{ $titles[$part] }} &mdash; {{ $currency }}{{ number_format($figures[$part], 2) }}</h3>

    {{-- A page of figures says little on its own; whose account it is and over
         what period travel with it. --}}
    <div class="meta">
        <span><strong>{{ $student->full_name }}</strong></span>
        @if ($student->admission_no)
            <span>{{ $student->admission_no }}</span>
        @endif
        @foreach ($labels as $label)
            <span>{{ $label }}</span>
        @endforeach
        <span><strong>@lang('common.date'):</strong> {{ date('M d, Y h:i A') }}</span>
    </div>

    @include('backEnd.reports.partials.studentAccountRows', [
        'tableClass' => 'sheet-table',
        'emptyClass' => 'empty',
    ])
</div>
<script>
    window.print();
</script>
</body>
</html>
