<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@lang('reports.standard_student_report') &mdash; {{ $title }}</title>
    <link rel="stylesheet" href="{{ url('Modules\Fees\Resources\assets\css\feesStyle.css') }}"/>
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
        /* So a long list carries its headings onto every sheet rather than only the
           first - a second page of bare names is unreadable. */
        table.sheet-table thead { display: table-header-group; }
        table.sheet-table tr { page-break-inside: avoid; }
        .empty { text-align: center; color: #777; padding: 24px 0; }
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .sheet { padding: 0; }
        }
    </style>
</head>
<body>
<div class="sheet">
    <h2>{{ generalSetting()->school_name }}</h2>
    <h3>@lang('reports.standard_student_report') &mdash; {{ $title }} ({{ $rows->count() }})</h3>

    {{-- A page of names says little on its own; the criteria that produced it travel
         with it. --}}
    <div class="meta">
        <span><strong>@lang('reports.school_year'):</strong> {{ $labels['year'] }}</span>
        <span><strong>{{ termLabel() }}:</strong> {{ $labels['semester'] }}</span>
        @if (!isBasicEd())
            <span><strong>@lang('academics.program'):</strong> {{ $labels['course'] }}</span>
        @endif
        @if ($labels['search'] !== '')
            <span><strong>@lang('common.search'):</strong> {{ $labels['search'] }}</span>
        @endif
        <span><strong>@lang('common.date'):</strong> {{ date('M d, Y h:i A') }}</span>
    </div>

    @if ($rows->count() == 0)
        <p class="empty">@lang('reports.no_students_found')</p>
    @else
        <table class="sheet-table">
            <thead>
                <tr>
                    <th>@lang('common.sl')</th>
                    <th>@lang('academics.student')</th>
                    <th>@lang('student.admission_no')</th>
                    @if (!isBasicEd())
                        <th>@lang('academics.program')</th>
                    @endif
                    <th>@lang('academics.year_level')</th>
                    <th>@lang('reports.col_enrollment')</th>
                    <th>@lang('reports.col_method')</th>
                    <th>@lang('reports.col_status')</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $key => $row)
                    <tr>
                        <td>{{ $key + 1 }}</td>
                        <td>{{ $row['student']->full_name }}</td>
                        <td>{{ $row['student']->admission_no }}</td>
                        @if (!isBasicEd())
                            <td>{{ optional($row['student']->course)->course_name ?: '-' }}</td>
                        @endif
                        <td>{{ optional($row['record']->class)->class_name ?: '-' }}</td>
                        <td>@lang('reports.student_metric_' . ($row['enrolled'] ? 'enrolled' : 'not_enrolled'))</td>
                        <td>@lang('reports.student_metric_' . $row['method'])</td>
                        <td>@lang('reports.student_metric_' . $row['status'])</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
<script>
    window.print();
</script>
</body>
</html>
