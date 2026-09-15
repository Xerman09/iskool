{{--
    Shared CSS/JS for studentOrdersModal.blade.php. Include this once per page no
    matter how many of those modals the page renders - every handler here is
    delegated (bound on $(document)) and reads its target from the clicked
    element/closest .student-orders-modal, so one copy serves any number of
    per-student modals.
--}}
@push('css')
<style>
    .reject-reason-row td {
        background: #fbfbfd;
        border-top: none !important;
    }
    .reject-reason-row .primary_input_field,
    .bulk-reject-row .primary_input_field {
        margin-bottom: 0;
    }
    .modal-bulk-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
        background: #f7f7fa;
        border-radius: 10px;
        padding: 12px 16px;
        margin-top: 15px;
    }
    .modal-bulk-bar .bulk-count {
        font-weight: 600;
    }
    .bulk-reject-row {
        background: #fbfbfd;
        border-radius: 10px;
        padding: 12px 16px;
        margin-top: 10px;
    }
    .student-orders-modal .modal-dialog {
        max-width: 850px;
        width: calc(100% - 30px);
    }
    .student-orders-modal .modal-body {
        overflow-x: auto;
    }
    /* The shared table component (x-table) pads thead th with 45px on the left
       only (no matching right padding) - harmless on a full-width page table,
       but inside this narrower modal it reads as a lopsided left margin. */
    .student-orders-modal .QA_table th,
    .student-orders-modal .QA_table td {
        padding-left: 16px !important;
        padding-right: 16px !important;
    }
</style>
@endpush

@push('script')
<script>
$(document).ready(function () {
    $(document).on('click', '.toggle-reject-reason', function () {
        $($(this).data('target-row')).prop('hidden', false);
    });
    $(document).on('click', '.cancel-reject-reason', function () {
        $($(this).data('target-row')).prop('hidden', true);
    });

    function syncBulkSelection($modal) {
        var studentId = $modal.data('student-id');
        var ids = $modal.find('.order-select-checkbox:checked').map(function () { return $(this).val(); }).get();

        var $approveForm = $('#bulkApproveForm' + studentId);
        var $rejectForm = $('#bulkRejectForm' + studentId);
        $approveForm.find('input[name="order_ids[]"]').remove();
        $rejectForm.find('input[name="order_ids[]"]').remove();
        ids.forEach(function (id) {
            $('<input>').attr({ type: 'hidden', name: 'order_ids[]', value: id }).appendTo($approveForm);
            $('<input>').attr({ type: 'hidden', name: 'order_ids[]', value: id }).appendTo($rejectForm);
        });

        $modal.find('.bulk-count-number').text(ids.length);
        $modal.find('.bulk-order-bar').prop('hidden', ids.length === 0);
    }

    $(document).on('change', '.order-select-checkbox', function () {
        var $modal = $(this).closest('.student-orders-modal');
        var total = $modal.find('.order-select-checkbox').length;
        var checked = $modal.find('.order-select-checkbox:checked').length;
        $modal.find('.select-all-in-modal').prop('checked', total === checked);
        syncBulkSelection($modal);
    });

    $(document).on('change', '.select-all-in-modal', function () {
        var $modal = $(this).closest('.student-orders-modal');
        $modal.find('.order-select-checkbox').prop('checked', $(this).is(':checked'));
        syncBulkSelection($modal);
    });

    // Selection resets each time a popup is reopened, rather than carrying over
    // stale checks from a previous visit to the same student's orders.
    $(document).on('hidden.bs.modal', '.student-orders-modal', function () {
        var $modal = $(this);
        $modal.find('.order-select-checkbox, .select-all-in-modal').prop('checked', false);
        syncBulkSelection($modal);
    });
});
</script>
@endpush
