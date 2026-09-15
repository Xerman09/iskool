{{--
    Actual Bootstrap modal chrome around the item-store content (see
    itemStoreContent.blade.php for the grid/cart/pending-orders panels). Kept as its
    own file so the "Browse Item Store" button on the balance summary page can keep
    targeting #itemStoreModal via data-toggle="modal".
--}}
<style>
    #itemStoreModal .modal-dialog {
        max-width: 1000px;
    }
    #itemStoreModal .modal-body {
        max-height: 70vh;
        overflow-y: auto;
    }
</style>

<div class="modal fade admin-query" id="itemStoreModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                {{-- Title lives inside itemStoreContent's own heading, shared with the
                     standalone Item Store page - keep this header to just the close button. --}}
                <button type="button" class="close ml-auto" data-dismiss="modal" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                @include('backEnd.academics.partials.itemStoreContent')
            </div>
        </div>
    </div>
</div>
