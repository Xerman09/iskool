<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@lang('reports.activity_audit_report') &mdash; {{ $title }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #222; }
        .sheet { padding: 18px 24px; }
        .sheet h2 { margin: 0 0 2px; font-size: 20px; }
        .sheet h3 { margin: 0 0 12px; font-size: 15px; font-weight: 600; }
        .sheet .meta { font-size: 12px; color: #555; margin-bottom: 14px; }
        .sheet .meta span { margin-right: 18px; white-space: nowrap; }
        table.sheet-table { width: 100%; border-collapse: collapse; font-size: 11.5px; }
        table.sheet-table th, table.sheet-table td {
            border: 1px solid #c9c9c9; padding: 5px 7px; text-align: left; vertical-align: top;
        }
        table.sheet-table thead th { background: #f1f1f1; font-weight: 700; }
        table.sheet-table tfoot th { background: #f7f7f7; font-weight: 700; }
        /* Money reads as a column of figures, not as prose. */
        table.sheet-table .num { text-align: right; font-variant-numeric: tabular-nums; }
        /* So a long list carries its headings onto every sheet rather than only the
           first - a second page of bare entries is unreadable. */
        table.sheet-table thead { display: table-header-group; }
        table.sheet-table tfoot { display: table-footer-group; }
        table.sheet-table tr { page-break-inside: avoid; }
        .empty { text-align: center; color: #777; padding: 24px 0; }
        .note { font-size: 10.5px; color: #666; margin-top: 10px; }
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
    <h3>@lang('reports.activity_audit_report') &mdash; {{ $title }} ({{ $rows->count() }})</h3>

    <div class="meta">
        @foreach ($labels as $label)
            <span>{{ $label }}</span>
        @endforeach
        <span><strong>@lang('common.date'):</strong> {{ date('M d, Y h:i A') }}</span>
    </div>

    @if ($rows->count() == 0)
        <p class="empty">@lang('reports.activity_none')</p>
    @else
        <table class="sheet-table">
            <thead>
                <tr>
                    <th>@lang('common.date')</th>
                    <th>@lang('reports.activity_kind')</th>
                    <th>@lang('reports.open_invoice_no')</th>
                    <th>@lang('common.name')</th>
                    <th>@lang('reports.accounts_head')</th>
                    <th class="num">@lang('accounts.amount')</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['at'] ? $row['at']->format('M d, Y H:i') : '-' }}</td>
                        <td>
                            @lang('reports.activity_metric_' . $row['kind'])
                            @if (!empty($row['duplicate_of_receipt']))
                                ({{ __('reports.activity_posting') }})
                            @endif
                        </td>
                        <td>{{ $row['reference'] ?: '-' }}</td>
                        <td>{{ $row['who'] ?: '-' }}</td>
                        <td>{{ $row['detail'] ?: '-' }}</td>
                        <td class="num">
                            {{ $row['direction'] === 'out' ? '-' : '' }}{{ number_format($row['amount'], 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="5" class="num">@lang('reports.activity_metric_money_in')</th>
                    <th class="num">{{ $currency }}{{ number_format($totals['money_in'], 2) }}</th>
                </tr>
                <tr>
                    <th colspan="5" class="num">@lang('reports.activity_metric_money_out')</th>
                    <th class="num">{{ $currency }}{{ number_format($totals['money_out'], 2) }}</th>
                </tr>
            </tfoot>
        </table>

        {{-- Said on the sheet, because a printed page outlives the screen that
             explained it. --}}
        <p class="note">@lang('reports.activity_no_actor_note')</p>
    @endif
</div>
<script>
    window.print();
</script>
</body>
</html>
