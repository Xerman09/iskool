@extends('backEnd.master')
@section('title')
    @lang('fees::feesModule.transaction_history')
@endsection
@section('mainContent')
<section class="sms-breadcrumb mb-20">
    <div class="container-fluid">
        <div class="row justify-content-between">
            <h1>@lang('fees::feesModule.transaction_history')</h1>
            <div class="bc-pages">
                <a href="{{ route('dashboard') }}">@lang('common.dashboard')</a>
                <a href="{{ route('fees.fees-invoice-list') }}">@lang('fees.fees_invoice')</a>
                <a href="#">@lang('fees::feesModule.transaction_history')</a>
            </div>
        </div>
    </div>
</section>

<section class="admin-visitor-area up_st_admin_visitor">
    <div class="container-fluid p-0">
        <div class="white-box">
            <div class="row mb-15">
                <div class="col-lg-3">
                    <select id="transactionAudienceFilter" class="primary_select form-control">
                        <option value="student">@lang('common.student')</option>
                        <option value="staff">@lang('academics.employee')</option>
                    </select>
                </div>
            </div>

            <x-table>
                <table id="transactionHistoryTable" class="table data-table" cellspacing="0" width="100%">
                    <thead>
                        <tr>
                            <th>@lang('common.sl')</th>
                            <th id="transactionAudienceColumnHeader">@lang('common.student')</th>
                            <th>@lang('fees::feesModule.fees_invoice')</th>
                            <th>@lang('common.type')</th>
                            <th>@lang('fees::feesModule.payment_method')</th>
                            <th>@lang('fees::feesModule.paid_amount')</th>
                            <th>@lang('fees::feesModule.waiver')</th>
                            <th>@lang('fees.fine')</th>
                            <th>@lang('common.date')</th>
                            <th>@lang('common.action')</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </x-table>
        </div>
    </div>
</section>

{{-- View Payment Modal - same popup the Fees Invoice list uses to show a
     single invoice's payment history, reused here to drill into a row. --}}
<div class="modal fade admin-query" id="viewFeesPayment">
    <div class="modal-dialog modal-dialog-centered max_modal">
        <div class="modal-content">
        </div>
    </div>
</div>

@include('backEnd.partials.data_table_js')
@include('backEnd.partials.server_side_datatable')
<script>
    function viewPaymentDetailModal(id) {
        $('#viewFeesPayment').modal('show');
        $.ajax({
            url: "{{ route('fees.fees-view-payment') }}",
            method: "POST",
            data: { invoiceId: id },
            success: function (response) {
                $('#viewFeesPayment .modal-content').html(response);
            },
        });
    }

    var currentTransactionAudience = 'student';

    $(document).ready(function () {
        window.table = $('#transactionHistoryTable').DataTable({
            processing: true,
            serverSide: true,
            "ajax": $.fn.dataTable.pipeline({
                url: "{{ url('fees/transaction-history-datatable') }}",
                data: function (d) {
                    d.audience = currentTransactionAudience;
                },
                pages: "{{ generalSetting()->ss_page_load }}"
            }),
            columns: [
                { data: 'DT_RowIndex', name: 'id' },
                { data: 'owner_name', name: 'owner_name' },
                { data: 'invoice_number', name: 'invoice_number' },
                { data: 'invoice_type', name: 'invoice_type' },
                { data: 'payment_method', name: 'payment_method' },
                { data: 'paid_amount', name: 'paid_amount' },
                { data: 'weaver', name: 'weaver' },
                { data: 'fine', name: 'fine' },
                { data: 'created_date', name: 'created_date' },
                { data: 'action', name: 'action' },
            ],
            bLengthChange: false,
            bDestroy: true,
            language: {
                search: "<i class='ti-search'></i>",
                searchPlaceholder: window.jsLang('quick_search'),
                paginate: {
                    next: "<i class='ti-arrow-right'></i>",
                    previous: "<i class='ti-arrow-left'></i>",
                },
            },
            dom: "Brtip",
            buttons: [
                {
                    extend: "copyHtml5",
                    text: '<i class="fa fa-files-o"></i>',
                    title: $("#logo_title").val(),
                    titleAttr: window.jsLang('copy_table'),
                    exportOptions: {
                        columns: ':visible:not(.not-export-col)'
                    },
                },
                {
                    extend: "excelHtml5",
                    text: '<i class="fa fa-file-excel-o"></i>',
                    titleAttr: window.jsLang('export_to_excel'),
                    title: $("#logo_title").val(),
                    margin: [10, 10, 10, 0],
                    exportOptions: {
                        columns: ':visible:not(.not-export-col)'
                    },
                },
                {
                    extend: "csvHtml5",
                    text: '<i class="fa fa-file-text-o"></i>',
                    titleAttr: window.jsLang('export_to_csv'),
                    exportOptions: {
                        columns: ':visible:not(.not-export-col)'
                    },
                },
                {
                    extend: "pdfHtml5",
                    text: '<i class="fa fa-file-pdf-o"></i>',
                    title: $("#logo_title").val(),
                    titleAttr: window.jsLang('export_to_pdf'),
                    exportOptions: {
                        columns: ':visible:not(.not-export-col)'
                    },
                    orientation: "landscape",
                    pageSize: "A4",
                    margin: [0, 0, 0, 12],
                    alignment: "center",
                    header: true,
                    customize: function(doc) {
                        doc.content[1].margin = [100, 0, 100, 0]; //left, top, right, bottom
                        doc.content.splice(1, 0, {
                            margin: [0, 0, 0, 12],
                            alignment: "center",
                            image: "data:image/png;base64," + $("#logo_img").val(),
                        });
                        doc.defaultStyle = {
                            font: 'DejaVuSans'
                        }
                    },
                },
                {
                    extend: "print",
                    text: '<i class="fa fa-print"></i>',
                    titleAttr: window.jsLang('print'),
                    title: $("#logo_title").val(),
                    exportOptions: {
                        columns: ':visible:not(.not-export-col)'
                    },
                },
                {
                    extend: "colvis",
                    text: '<i class="fa fa-columns"></i>',
                    postfixButtons: ["colvisRestore"],
                },
            ],
            responsive: true,
        });

        $('#transactionAudienceFilter').on('change', function () {
            currentTransactionAudience = $(this).val();

            $('#transactionAudienceColumnHeader').text(
                currentTransactionAudience === 'staff' ? @json(__('academics.employee')) : @json(__('common.student'))
            );

            $('#transactionHistoryTable').DataTable().clearPipeline().draw();
        });
    });
</script>
@endsection
