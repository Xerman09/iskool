@extends('backEnd.master')
@section('title')
    @lang('academics.tuition_setup')
@endsection

@section('mainContent')
    @php
        $currency = generalSetting()->currency_symbol ?: '$';
        $totalSet = $classes->sum(fn ($c) => (float) ($amounts[$c->id] ?? 0));
        $missing = $classes->filter(fn ($c) => !isset($amounts[$c->id]))->count();
    @endphp

    <style>
        .tuition-hint { font-size: 12px; color: #9aa0a6; }
        .tuition-sum { font-size: 13px; color: #4b5563; }
        .tuition-sum strong { color: #1a1a1a; }

        table.tuition { width: 100%; border-collapse: collapse; }
        table.tuition th, table.tuition td {
            padding: 10px 8px; font-size: 13px; border-bottom: 1px solid rgba(0,0,0,.07);
        }
        table.tuition thead th {
            font-size: 11px; text-transform: uppercase; letter-spacing: .5px;
            color: #6b7280; border-bottom: 2px solid rgba(0,0,0,.12);
        }
        table.tuition tfoot th { border-top: 2px solid #333; font-weight: 700; }
        table.tuition .amount-cell { width: 220px; }
        table.tuition input {
            text-align: right; font-variant-numeric: tabular-nums; max-width: 200px;
        }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .not-set { color: #a8641f; font-size: 11px; }
    </style>

    <section class="sms-breadcrumb mb-20">
        <div class="container-fluid">
            <div class="row justify-content-between">
                <h1>@lang('academics.tuition_setup')</h1>
                <div class="bc-pages">
                    <a href="{{ route('dashboard') }}">@lang('common.dashboard')</a>
                    <a href="#">@lang('academics.academics')</a>
                    <a href="#">@lang('academics.tuition_setup')</a>
                </div>
            </div>
        </div>
    </section>

    <section class="admin-visitor-area up_st_admin_visitor">
        <div class="container-fluid p-0">
            <div class="row">
                <div class="col-lg-5 no-gutters">
                    <div class="main-title">
                        <h3 class="mb-5">@lang('academics.tuition_setup')</h3>
                        <span class="tuition-hint">@lang('academics.tuition_setup_hint')</span>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-12">
                    <div class="white-box">
                        {{ Form::open(['route' => 'class-tuition-store', 'method' => 'POST']) }}

                        <div class="row mb-20">
                            <div class="col-lg-4">
                                <label class="primary_input_label">@lang('reports.school_year')</label>
                                <select name="academic_id" class="form-control"
                                    onchange="window.location = '{{ route('class-tuition') }}?academic_id=' + this.value;">
                                    @foreach ($years as $year)
                                        <option value="{{ $year->id }}" {{ (int) $academicId === (int) $year->id ? 'selected' : '' }}>
                                            {{ $year->year }}{{ $year->title ? ' - ' . $year->title : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                {{-- Kept per year so last year's tuition stays on file after
                                     this year's is raised, and an old invoice can still be
                                     read against the price that was in force. --}}
                                <span class="tuition-hint">@lang('academics.tuition_setup_year_hint')</span>
                            </div>
                        </div>

                        @if ($classes->isEmpty())
                            <p class="text-center text-muted mb-0">@lang('academics.tuition_setup_no_levels')</p>
                        @else
                            <table class="tuition">
                                <thead>
                                    <tr>
                                        <th>@lang('academics.year_level')</th>
                                        <th class="num">@lang('academics.tuition_per_year') ({{ $currency }})</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($classes as $class)
                                        <tr>
                                            <td>
                                                {{ $class->class_name }}
                                                @unless (isset($amounts[$class->id]))
                                                    <span class="d-block not-set">@lang('academics.tuition_not_set')</span>
                                                @endunless
                                            </td>
                                            <td class="amount-cell">
                                                <input type="number" step="0.01" min="0" class="form-control"
                                                    name="amount[{{ $class->id }}]"
                                                    value="{{ isset($amounts[$class->id]) ? number_format((float) $amounts[$class->id], 2, '.', '') : '' }}"
                                                    placeholder="0.00">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th>@lang('academics.tuition_whole_school')</th>
                                        <th class="num">{{ $currency }}{{ number_format($totalSet, 2) }}</th>
                                    </tr>
                                </tfoot>
                            </table>

                            <div class="d-flex justify-content-between align-items-center flex-wrap mt-20">
                                <span class="tuition-sum">
                                    @if ($missing > 0)
                                        {{ __('academics.tuition_missing_count', ['count' => $missing]) }}
                                    @else
                                        @lang('academics.tuition_all_set')
                                    @endif
                                </span>
                                <button type="submit" class="primary-btn fix-gr-bg">
                                    @lang('common.save')
                                </button>
                            </div>
                        @endif

                        {{ Form::close() }}

                        <p class="tuition-hint mt-20 mb-0">@lang('academics.tuition_setup_note')</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
