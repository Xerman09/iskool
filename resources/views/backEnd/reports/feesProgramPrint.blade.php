<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@lang('reports.fees_revenue_by_program')</title>
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
        table.sheet-table .num { text-align: right; font-variant-numeric: tabular-nums; }
        /* So a long list carries its headings onto every sheet rather than only the
           first - a second page of bare amounts is unreadable. */
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
    $showProgram = !isBasicEd();
@endphp
<div class="sheet">
    <h2>{{ generalSetting()->school_name }}</h2>
    <h3>@lang('reports.fees_revenue_by_program') ({{ $programs->count() }})</h3>

    {{-- A page of figures says little on its own; the criteria that produced it
         travel with it. --}}
    <div class="meta">
        @foreach ($labels as $label)
            <span>{{ $label }}</span>
        @endforeach
        <span><strong>@lang('common.date'):</strong> {{ date('M d, Y h:i A') }}</span>
    </div>

    @if ($programs->count() == 0)
        <p class="empty">@lang('reports.no_students_found')</p>
    @else
        <table class="sheet-table">
            <thead>
                <tr>
                    <th>{{ $showProgram ? __('academics.program') : __('academics.year_level') }}</th>
                    <th class="num">@lang('academics.student')</th>
                    <th class="num">@lang('reports.fees_metric_assessed')</th>
                    <th class="num">@lang('reports.fees_metric_collected')</th>
                    <th class="num">@lang('reports.fees_metric_outstanding')</th>
                    <th class="num">@lang('reports.fees_per_student')</th>
                    <th class="num">@lang('reports.fees_collected_label')</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($programs as $program)
                    @php
                        $rate = $program['billed'] > 0
                            ? round($program['collected'] / $program['billed'] * 100, 1)
                            : 0;
                    @endphp
                    <tr>
                        <td>{{ $program['name'] }}</td>
                        <td class="num">{{ $program['students'] }}</td>
                        <td class="num">{{ number_format($program['billed'], 2) }}</td>
                        <td class="num">{{ number_format($program['collected'], 2) }}</td>
                        <td class="num">{{ number_format($program['outstanding'], 2) }}</td>
                        <td class="num">{{ number_format($program['per_student'], 2) }}</td>
                        <td class="num">{{ $rate }}%</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2" class="num">@lang('reports.fees_tuition')</th>
                    <th class="num">{{ number_format($totals['tuition_assessed'], 2) }}</th>
                    <th class="num">{{ number_format($totals['tuition_paid'], 2) }}</th>
                    <th colspan="3"></th>
                </tr>
                <tr>
                    <th colspan="2" class="num">@lang('reports.fees_item_store')</th>
                    <th class="num">{{ number_format($totals['item_assessed'], 2) }}</th>
                    <th class="num">{{ number_format($totals['item_paid'], 2) }}</th>
                    <th colspan="3"></th>
                </tr>
                <tr>
                    <th colspan="2" class="num">@lang('dashboard.total')</th>
                    <th class="num">{{ $currency }}{{ number_format($totals['assessed'], 2) }}</th>
                    <th class="num">{{ $currency }}{{ number_format($totals['collected'], 2) }}</th>
                    <th class="num">{{ $currency }}{{ number_format($totals['outstanding'], 2) }}</th>
                    <th></th>
                    <th class="num">{{ $totals['collection_rate'] }}%</th>
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
