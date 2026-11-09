<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@lang('reports.standard_staff_report') &mdash; {{ $title }}</title>
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
        /* Figures read as a column, not as prose. */
        table.sheet-table .num { text-align: right; font-variant-numeric: tabular-nums; }
        /* So a long list carries its headings onto every sheet rather than only the
           first - a second page of bare figures is unreadable. */
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
    <h3>@lang('reports.standard_staff_report') &mdash; {{ $title }} ({{ $rows->count() }})</h3>

    {{-- A page of figures says little on its own; the criteria that produced it
         travel with it. --}}
    <div class="meta">
        @foreach ($labels as $label)
            <span>{{ $label }}</span>
        @endforeach
        <span><strong>@lang('common.date'):</strong> {{ date('M d, Y h:i A') }}</span>
    </div>

    @if ($rows->count() == 0)
        <p class="empty">@lang('reports.no_staff_found')</p>
    @else
        <table class="sheet-table">
            <thead>
                <tr>
                    <th>@lang('common.sl')</th>
                    <th>@lang('hr.staff')</th>
                    <th>@lang('hr.staff_no')</th>
                    <th>@lang('hr.role')</th>
                    <th>@lang('hr.department')</th>
                    <th class="num">@lang('reports.staff_metric_att_present')</th>
                    <th class="num">@lang('reports.staff_metric_att_late')</th>
                    <th class="num">@lang('reports.staff_metric_att_absent')</th>
                    <th class="num">@lang('reports.col_attendance_rate')</th>
                    <th class="num">@lang('reports.staff_metric_gross')</th>
                    <th class="num">@lang('reports.staff_metric_deduction')</th>
                    <th class="num">@lang('reports.staff_metric_net')</th>
                    <th>@lang('reports.col_payroll')</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $key => $row)
                    <tr>
                        <td>{{ $key + 1 }}</td>
                        <td>{{ $row['staff']->full_name }}</td>
                        <td>{{ $row['staff']->staff_no ?: '-' }}</td>
                        <td>{{ optional($row['staff']->roles)->name ?: '-' }}</td>
                        <td>{{ optional($row['staff']->departments)->name ?: '-' }}</td>
                        <td class="num">{{ $row['att_present'] }}</td>
                        <td class="num">{{ $row['att_late'] }}</td>
                        <td class="num">{{ $row['att_absent'] }}</td>
                        <td class="num">{{ is_null($row['attendance_rate']) ? '-' : $row['attendance_rate'] . '%' }}</td>
                        <td class="num">{{ number_format($row['gross'], 2) }}</td>
                        <td class="num">{{ number_format($row['deduction'] + $row['tax'], 2) }}</td>
                        <td class="num">{{ number_format($row['net'], 2) }}</td>
                        <td>@lang('reports.staff_metric_' . $row['payroll_status'])</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="5" class="num">@lang('dashboard.total')</th>
                    <th class="num">{{ $totals['att_present'] }}</th>
                    <th class="num">{{ $totals['att_late'] }}</th>
                    <th class="num">{{ $totals['att_absent'] }}</th>
                    <th class="num">{{ $totals['attendance_rate'] }}%</th>
                    <th class="num">{{ $currency }}{{ number_format($totals['gross'], 2) }}</th>
                    <th class="num">{{ $currency }}{{ number_format($totals['deduction'] + $totals['tax'], 2) }}</th>
                    <th class="num">{{ $currency }}{{ number_format($totals['net'], 2) }}</th>
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
