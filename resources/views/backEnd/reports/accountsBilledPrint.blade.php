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
    
@endphp
<div class="sheet">
    <h2>{{ generalSetting()->school_name }}</h2>
    <h3>{{ $title }} ({{ $invoices->count() }})</h3>

    {{-- A page of figures says little on its own; the criteria that produced it
         travel with it. --}}
    <div class="meta">
        @foreach ($labels as $label)
            <span>{{ $label }}</span>
        @endforeach
        <span><strong>@lang('common.date'):</strong> {{ date('M d, Y h:i A') }}</span>
    </div>

    <p style="font-size:11px;color:#666;margin-bottom:10px;">@lang('reports.accounts_billed_grain')</p>

    @if ($invoices->count() == 0)
        <p class="empty">@lang('reports.open_none')</p>
    @else
        <table class="sheet-table">
            <thead>
                <tr>
                    <th>@lang('common.sl')</th>
                    <th>@lang('reports.open_invoice_no')</th>
                    <th>@lang('fees.create_date')</th>
                    <th>@lang('academics.student')</th>
                    <th>@lang('reports.col_charge_type')</th>
                    <th class="num">@lang('reports.fees_metric_assessed')</th>
                    <th class="num">@lang('reports.fees_metric_collected')</th>
                    <th class="num">@lang('reports.col_balance')</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoices as $key => $row)
                    <tr>
                        <td>{{ $key + 1 }}</td>
                        <td>{{ $row["invoice"]->invoice_id ?: "#" . $row["invoice"]->id }}</td>
                        <td>{{ $row["invoice"]->create_date ? \Carbon\Carbon::parse($row["invoice"]->create_date)->format("M d, Y") : "-" }}</td>
                        <td>{{ optional($row["student"])->full_name ?: "-" }}</td>
                        <td>@lang("reports.fees_type_" . ($row["invoice"]->type ?: "fees"))</td>
                        <td class="num">{{ number_format($row["billed"], 2) }}</td>
                        <td class="num">{{ number_format($row["waived"], 2) }}</td>
                        <td class="num">{{ number_format($row["paid"], 2) }}</td>
                        <td class="num">{{ number_format($row["unpaid"], 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="5" class="num">@lang('dashboard.total')</th>
                    <th class="num">{{ $currency }}{{ number_format($invoices->sum("billed"), 2) }}</th>
                    <th class="num">{{ $currency }}{{ number_format($invoices->sum("waived"), 2) }}</th>
                    <th class="num">{{ $currency }}{{ number_format($invoices->sum("paid"), 2) }}</th>
                    <th class="num">{{ $currency }}{{ number_format($invoices->sum("unpaid"), 2) }}</th>
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