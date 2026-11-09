{{-- The staff behind one figure on a card. Rendered by
     StaffStandardReportController@metricList from the very rows that figure was
     counted from, so the two cannot disagree. --}}
@php
    $currency = generalSetting()->currency_symbol ?: '$';
@endphp
<div class="modal-header">
    <h4 class="modal-title">
        {{ $title }}
        <span class="badge badge-info">{{ __('reports.staff_counted', ['count' => $rows->count()]) }}</span>
    </h4>
    <div>
        {{-- Carries the criteria this list was built from, so the sheet is of what is
             on screen. Opens as its own page and prints itself - printing the modal in
             place would bring the whole report behind it. Nothing to print when the
             list is empty. --}}
        @if ($rows->count() > 0)
            <a href="{{ $printUrl }}" target="_blank" class="primary-btn small fix-gr-bg mr-15">
                <span class="ti-printer pr-2"></span>@lang('common.print')
            </a>
        @endif
        <button type="button" class="close" data-dismiss="modal">&times;</button>
    </div>
</div>
<div class="modal-body">
    @if ($rows->count() == 0)
        <p class="text-center text-muted mb-0">@lang('reports.no_staff_found')</p>
    @else
        <div class="table-responsive">
            <table class="table school-table-style shadow-none p-0" cellspacing="0" width="100%">
                <thead>
                    <tr>
                        <th>@lang('common.sl')</th>
                        <th>@lang('hr.staff')</th>
                        <th>@lang('hr.staff_no')</th>
                        <th>@lang('hr.role')</th>
                        <th>@lang('hr.department')</th>
                        <th class="text-right">@lang('reports.staff_metric_att_present')</th>
                        <th class="text-right">@lang('reports.staff_metric_att_late')</th>
                        <th class="text-right">@lang('reports.staff_metric_att_absent')</th>
                        <th class="text-right">@lang('reports.col_attendance_rate')</th>
                        <th class="text-right">@lang('reports.staff_metric_gross')</th>
                        <th class="text-right">@lang('reports.staff_metric_net')</th>
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
                            <td class="text-right">{{ $row['att_present'] }}</td>
                            <td class="text-right">{{ $row['att_late'] }}</td>
                            <td class="text-right">{{ $row['att_absent'] }}</td>
                            <td class="text-right">
                                {{ is_null($row['attendance_rate']) ? '-' : $row['attendance_rate'] . '%' }}
                            </td>
                            <td class="text-right">{{ number_format($row['gross'], 2) }}</td>
                            <td class="text-right">{{ number_format($row['net'], 2) }}</td>
                            <td>
                                @php
                                    $payTone = [
                                        'pay_paid' => 'success',
                                        'pay_generated' => 'warning',
                                        'pay_not_generated' => 'secondary',
                                    ][$row['payroll_status']] ?? 'secondary';
                                @endphp
                                <span class="badge badge-{{ $payTone }}">@lang('reports.staff_metric_' . $row['payroll_status'])</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                {{-- The list carries money and days, so it has to add up - a page of
                     figures with no total asks the reader to do the arithmetic the
                     report was for. Totalled from these rows alone, which is why it
                     matches the figure that was clicked. --}}
                <tfoot>
                    <tr>
                        <th colspan="5" class="text-right">@lang('dashboard.total')</th>
                        <th class="text-right">{{ $totals['att_present'] }}</th>
                        <th class="text-right">{{ $totals['att_late'] }}</th>
                        <th class="text-right">{{ $totals['att_absent'] }}</th>
                        <th class="text-right">{{ $totals['attendance_rate'] }}%</th>
                        <th class="text-right">{{ $currency }}{{ number_format($totals['gross'], 2) }}</th>
                        <th class="text-right">{{ $currency }}{{ number_format($totals['net'], 2) }}</th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
