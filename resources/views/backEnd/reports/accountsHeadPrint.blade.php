<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@lang('reports.standard_accounts_report') &mdash; {{ $title }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #222; }
        .sheet { padding: 18px 24px; max-width: 720px; }
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
        table.sheet-table .num { text-align: right; font-variant-numeric: tabular-nums; }
        /* So a long list carries its headings onto every sheet rather than only the
           first - and a long list is exactly what this sheet is for. */
        table.sheet-table thead { display: table-header-group; }
        table.sheet-table tfoot { display: table-footer-group; }
        table.sheet-table tr { page-break-inside: avoid; }
        .empty { text-align: center; color: #777; padding: 24px 0; }
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .sheet { padding: 0; }
        }
    </style>
</head>
<body>
@php
    $currency = generalSetting()->currency_symbol ?: '$';
    $total = (float) $heads->sum('amount');
@endphp
<div class="sheet">
    <h2>{{ generalSetting()->school_name }}</h2>
    <h3>{{ $title }} ({{ $heads->count() }})</h3>

    {{-- A page of figures says little on its own; the criteria that produced it
         travel with it. --}}
    <div class="meta">
        @foreach ($labels as $label)
            <span>{{ $label }}</span>
        @endforeach
        <span><strong>@lang('common.date'):</strong> {{ date('M d, Y h:i A') }}</span>
    </div>

    @if ($heads->count() == 0)
        <p class="empty">@lang('reports.accounts_no_entries')</p>
    @else
        <table class="sheet-table">
            <thead>
                <tr>
                    <th>@lang('reports.accounts_head')</th>
                    <th class="num">@lang('reports.accounts_entry_type')</th>
                    <th class="num">@lang('accounts.amount')</th>
                    <th class="num">@lang('reports.pl_share_col')</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($heads as $head)
                    <tr>
                        <td>{{ $head['name'] }}</td>
                        <td class="num">{{ $head['entries'] }}</td>
                        <td class="num">{{ number_format($head['amount'], 2) }}</td>
                        <td class="num">{{ $total > 0 ? round($head['amount'] / $total * 100, 1) : 0 }}%</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th class="num">@lang('dashboard.total')</th>
                    <th class="num">{{ $heads->sum('entries') }}</th>
                    <th class="num">{{ $currency }}{{ number_format($total, 2) }}</th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    @endif
</div>
<script>
    window.print();
</script>
</body>
</html>
