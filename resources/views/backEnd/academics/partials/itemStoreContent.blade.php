{{--
    The actual item-store UI: search/grid, plus three modals (add-to-order, cart,
    pending-orders) layered on top of it. When this is included inside another
    real Bootstrap modal (see itemStoreModal.blade.php, used on the balance
    summary page) these end up nested modal-on-modal, which Bootstrap 4 doesn't
    natively support well (backdrop stacking, z-index, body scroll-lock removed
    early) - the script block below fixes that generically for any nested modal
    on the page, not just these three. Also included as-is on the standalone Item
    Store page (itemStore.blade.php / staffItemStore.blade.php), where there's no
    outer modal and this fix is simply a no-op.
--}}
@push('css')
<style>
    .item-store-embed .item-store-toolbar {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    .item-store-embed .item-store-search-wrap {
        position: relative;
        flex: 1 1 260px;
        min-width: 220px;
    }
    .item-store-embed .item-store-search-wrap .ti-search {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #b3b8c4;
        font-size: 13px;
    }
    .item-store-embed .item-store-search {
        padding-left: 38px;
        border-radius: 30px;
        background: #f7f7fa;
        border: 1px solid #ececf2;
    }
    .item-store-embed .item-store-cart-trigger-wrap {
        position: relative;
        display: inline-block;
    }
    .item-store-embed .item-store-cart-trigger {
        white-space: nowrap;
    }
    .item-store-embed .item-store-cart-count {
        position: absolute;
        top: -8px;
        right: -8px;
        min-width: 20px;
        height: 20px;
        padding: 0 5px;
        border-radius: 999px;
        background: #e6394a;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        line-height: 20px;
        text-align: center;
        box-shadow: 0 0 0 2px #fff;
        z-index: 2;
        pointer-events: none;
    }
    .item-store-embed .item-store-cart-count.is-warning {
        background: #f0ad4e;
    }

    .order-batch-card {
        border: 1px solid #edeef2;
        border-left: 4px solid #f0ad4e;
        border-radius: 12px;
        padding: 18px 20px;
        margin-bottom: 20px;
    }
    .order-batch-card:last-child {
        margin-bottom: 0;
    }
    .order-batch-card .order-batch-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 14px;
    }
    .order-batch-card .order-batch-date {
        font-size: 12px;
        color: #9098a8;
        text-transform: uppercase;
        letter-spacing: .03em;
        font-weight: 600;
    }
    .order-batch-card .order-batch-total {
        font-size: 17px;
        font-weight: 700;
        color: #1f2233;
    }

    /* Child "sheets" (add-to-order, cart, pending orders) - compact white cards
       floating over the store. The theme's modal rules otherwise stretch them to
       the outer store modal's size, hence the !importants on sizing/header. */
    .item-store-sheet .modal-dialog {
        max-width: 420px !important;
        width: calc(100% - 32px) !important;
        margin-left: auto !important;
        margin-right: auto !important;
    }
    #myPendingOrdersModal.item-store-sheet .modal-dialog {
        max-width: 760px !important;
    }
    .item-store-sheet .modal-content {
        border: 0;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 24px 60px rgba(15, 23, 42, .35);
    }
    .item-store-sheet .modal-header {
        background: #fff !important;
        border-bottom: 1px solid #eef0f4 !important;
        padding: 18px 22px !important;
        align-items: center;
    }
    .item-store-sheet .modal-title {
        color: #1f2233 !important;
        font-size: 18px;
        font-weight: 700;
        margin: 0;
    }
    .item-store-sheet .sheet-eyebrow {
        display: block;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #9098a8;
        margin-bottom: 2px;
    }
    .item-store-sheet .modal-header .close {
        width: 32px;
        height: 32px;
        margin: 0 0 0 auto !important;
        padding: 0 !important;
        border-radius: 50%;
        background: #f3f4f8 !important;
        color: #5b6275 !important;
        font-size: 20px;
        line-height: 32px;
        opacity: 1;
        text-shadow: none;
        box-shadow: none !important;
    }
    .item-store-sheet .modal-header .close:hover {
        background: #e6e8f0 !important;
    }
    .item-store-sheet .modal-body {
        padding: 20px 22px !important;
    }
    .item-store-sheet .modal-footer {
        display: grid;
        grid-template-columns: 1fr 1.6fr;
        gap: 10px;
        padding: 14px 22px 20px !important;
        border-top: 0 !important;
    }
    .item-store-sheet .modal-footer > * {
        margin: 0 !important;
        width: 100%;
        justify-content: center;
    }
    .item-store-sheet .modal-footer > :only-child {
        grid-column: 1 / -1;
    }

    /* Outer store fades back while a sheet is open, so it's obvious which layer
       is active even if the theme's stacked backdrop doesn't render. */
    #itemStoreModal .modal-content {
        transition: filter .2s ease, transform .2s ease;
    }
    #itemStoreModal.item-store-dimmed .modal-content {
        filter: brightness(.45) saturate(.8);
        transform: scale(.97);
        pointer-events: none;
    }

    .item-store-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
        gap: 18px;
    }
    .item-store-card {
        display: flex;
        flex-direction: column;
        gap: 12px;
        padding: 18px;
        border: 1px solid #edeef2;
        border-radius: 14px;
        background: #fff;
        transition: box-shadow .18s ease, transform .18s ease, border-color .18s ease;
    }
    .item-store-card:hover {
        box-shadow: 0 10px 24px rgba(20, 20, 43, .07);
        border-color: #e2e3f5;
        transform: translateY(-2px);
    }
    .item-store-card.item-store-row-hidden {
        display: none;
    }
    .item-store-card-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
    }
    .item-store-card-icon {
        flex-shrink: 0;
        width: 42px;
        height: 42px;
        border-radius: 12px;
        background: #eef0fb;
        color: #5b62d6;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }
    .item-store-card-category {
        font-size: 11px;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #9098a8;
        font-weight: 600;
        margin-top: 6px;
    }
    .item-store-card-name {
        font-size: 15px;
        font-weight: 700;
        color: #1f2233;
        line-height: 1.3;
    }
    .item-store-card-meta {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        margin-top: auto;
    }
    .item-store-card-price {
        font-size: 18px;
        font-weight: 700;
        color: #1f2233;
    }
    .item-store-card-stock {
        font-size: 12px;
        color: #8a90a0;
    }
    .item-store-card-stock.is-low {
        color: #b5750b;
        font-weight: 600;
    }
    .item-store-card-action {
        width: 100%;
        border-radius: 10px;
    }
    .item-store-empty {
        grid-column: 1 / -1;
        text-align: center;
        color: #9098a8;
        padding: 40px 0;
    }

    .item-qty-stepper {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .item-qty-btn {
        width: 36px;
        height: 36px;
        line-height: 34px;
        padding: 0;
        text-align: center;
        border: 1px solid #e2e3ee;
        background: #f7f7fa;
        border-radius: 8px;
        font-weight: 700;
        font-size: 16px;
        cursor: pointer;
    }
    .item-qty-btn:hover {
        background: #edeef7;
    }
    #addToOrderModal .item-qty {
        width: 64px;
        text-align: center;
        padding: 6px;
        font-weight: 700;
    }
    #addToOrderModal .item-meta-row {
        display: flex;
        gap: 10px;
        margin-bottom: 18px;
    }
    #addToOrderModal .item-meta-chip {
        flex: 1;
        background: #f7f7fa;
        border-radius: 10px;
        padding: 10px 14px;
    }
    #addToOrderModal .item-meta-chip span {
        display: block;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #9098a8;
        margin-bottom: 2px;
    }
    #addToOrderModal .item-meta-chip strong {
        font-size: 15px;
        color: #1f2233;
    }
    #addToOrderModal .item-subtotal-box {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 18px;
        padding: 12px 14px;
        border-radius: 10px;
        background: #eef0fb;
    }
    #addToOrderModal .item-subtotal-box span {
        color: #5b62d6;
        font-weight: 600;
        font-size: 13px;
    }
    #addToOrderModal .item-subtotal-box strong {
        font-size: 17px;
        color: #1f2233;
    }

    .item-store-cart-list {
        list-style: none;
        margin: 0;
        padding: 0;
        max-height: 260px;
        overflow-y: auto;
    }
    .item-store-cart-list li {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        padding: 10px 0;
        border-bottom: 1px solid #f1f1f5;
    }
    .item-store-cart-list li:last-child {
        border-bottom: none;
    }
    .item-store-cart-name {
        font-weight: 600;
        color: #1f2233;
        font-size: 14px;
    }
    .item-store-cart-qty {
        color: #9098a8;
        font-size: 12px;
    }
    .item-store-cart-list li > span:first-child {
        flex: 1;
        min-width: 0;
    }
    .item-store-cart-stepper {
        display: inline-flex;
        align-items: center;
        border: 1px solid #e6e8f0;
        border-radius: 999px;
        padding: 2px;
        flex-shrink: 0;
    }
    .item-store-cart-stepper .cart-qty-btn {
        width: 26px;
        height: 26px;
        border: 0;
        border-radius: 50%;
        background: #f3f4f8;
        color: #1f2233;
        font-weight: 700;
        line-height: 1;
        cursor: pointer;
        padding: 0;
    }
    .item-store-cart-stepper .cart-qty-btn:hover:not(:disabled) {
        background: #e6e8f0;
    }
    .item-store-cart-stepper .cart-qty-btn:disabled {
        opacity: .35;
        cursor: not-allowed;
    }
    .item-store-cart-stepper .cart-qty-value {
        min-width: 26px;
        text-align: center;
        font-weight: 700;
        font-size: 13px;
        color: #1f2233;
    }

    /* Inside the balance-summary modal the title moves up into the modal header
       (itemStoreModal.blade.php), so drop the duplicate in-body heading there. */
    #itemStoreModal .item-store-embed > .main-title > h3 {
        display: none;
    }
    #itemStoreModal .item-store-embed > .main-title {
        justify-content: flex-end !important;
    }

    .item-store-cart-remove {
        color: #b3b8c4;
        cursor: pointer;
        font-weight: 700;
        font-size: 16px;
        line-height: 1;
        padding: 2px 4px;
    }
    .item-store-cart-remove:hover {
        color: #e6394a;
    }
    .item-store-cart-total {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 14px;
        margin-top: 10px;
        border-top: 1px solid #edeef2;
    }
    .item-store-cart-total strong:last-child {
        font-size: 18px;
    }
    .item-store-cart-empty {
        text-align: center;
        color: #9098a8;
        padding: 30px 0;
    }
    .item-store-cart-empty .ti-shopping-cart {
        font-size: 28px;
        display: block;
        margin-bottom: 10px;
        color: #dcdee8;
    }
</style>
@endpush

<div class="item-store-embed">
    <div class="main-title d-flex justify-content-between align-items-center flex-wrap">
        <h3 class="mb-15">@lang('academics.buy_items')</h3>
        <div class="d-flex flex-wrap" style="gap:10px;">
            @isset($pendingOrders)
            <span class="item-store-cart-trigger-wrap mb-15">
                <button type="button" class="primary-btn small tr-bg item-store-cart-trigger" data-toggle="modal" data-target="#myPendingOrdersModal">
                    <span class="ti-time pr-2"></span>
                    @lang('academics.my_pending_orders')
                </button>
                @if($pendingOrders->count() > 0)
                <span class="item-store-cart-count is-warning">{{ $pendingOrders->count() }}</span>
                @endif
            </span>
            @endisset
            <span class="item-store-cart-trigger-wrap mb-15">
                <button type="button" class="primary-btn small fix-gr-bg item-store-cart-trigger" data-toggle="modal" data-target="#itemCartModal">
                    <span class="ti-shopping-cart pr-2"></span>
                    @lang('academics.your_cart')
                </button>
                <span class="item-store-cart-count" id="itemCartBadge">0</span>
            </span>
        </div>
    </div>

    <div class="item-store-toolbar">
        <div class="item-store-search-wrap">
            <span class="ti-search"></span>
            <input type="text" class="primary_input_field form-control item-store-search" id="itemStoreSearch" placeholder="@lang('common.search')">
        </div>
    </div>

    <div class="item-store-grid" id="itemStoreGrid">
        @forelse($items as $item)
        <div class="item-store-card item-store-row" data-search-text="{{ strtolower($item->item_name.' '.optional($item->category)->category_name) }}">
            <div class="item-store-card-top">
                <div>
                    <div class="item-store-card-icon"><span class="ti-package"></span></div>
                    <div class="item-store-card-category">{{ optional($item->category)->category_name ?: __('academics.item_store') }}</div>
                </div>
            </div>
            <div class="item-store-card-name">{{$item->item_name}}</div>
            <div class="item-store-card-meta">
                <div class="item-store-card-price">{{currency_format($item->unit_price) ?: number_format($item->unit_price, 2)}}</div>
                <div class="item-store-card-stock {{ $item->total_in_stock <= 3 ? 'is-low' : '' }}">
                    @if($item->total_in_stock <= 3)
                        <span class="ti-alert pr-1"></span>
                    @endif
                    {{ $item->total_in_stock }} @lang('academics.in_stock')
                </div>
            </div>
            <button type="button" class="primary-btn small fix-gr-bg item-store-card-action add-to-order-btn"
                    data-item-id="{{$item->id}}"
                    data-item-name="{{$item->item_name}}"
                    data-item-price="{{$item->unit_price}}"
                    data-item-stock="{{$item->total_in_stock}}">
                <span class="ti-plus pr-2"></span>
                @lang('academics.add_to_order')
            </button>
        </div>
        @empty
        <div class="item-store-empty">@lang('common.no_data_found')</div>
        @endforelse
    </div>
    <div class="item-store-empty" id="itemStoreNoMatch" hidden>@lang('common.no_data_found')</div>
</div>

{{-- Add-to-order modal: asks for quantity for one item at a time before it joins the cart --}}
<div class="modal fade admin-query item-store-sheet" id="addToOrderModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <span class="sheet-eyebrow">@lang('academics.add_to_order')</span>
                    <h4 class="modal-title" id="addToOrderItemName"></h4>
                </div>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="item-meta-row">
                    <div class="item-meta-chip">
                        <span>@lang('academics.unit_price')</span>
                        <strong id="addToOrderPrice"></strong>
                    </div>
                    <div class="item-meta-chip">
                        <span>@lang('academics.in_stock')</span>
                        <strong id="addToOrderStock"></strong>
                    </div>
                </div>
                <label class="primary_input_label">@lang('inventory.quantity')</label>
                <div class="item-qty-stepper">
                    <button type="button" class="item-qty-btn" id="addToOrderMinus">&minus;</button>
                    <input type="number" class="form-control item-qty" id="addToOrderQty" min="1" value="1">
                    <button type="button" class="item-qty-btn" id="addToOrderPlus">&plus;</button>
                </div>
                <div class="item-subtotal-box">
                    <span>@lang('accounts.amount')</span>
                    <strong id="addToOrderSubtotal"></strong>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="primary-btn tr-bg" data-dismiss="modal">@lang('common.cancel')</button>
                <button type="button" class="primary-btn fix-gr-bg" id="addToOrderConfirm">
                    <span class="ti-shopping-cart pr-2"></span>
                    @lang('academics.add_to_order')
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Cart modal: review everything added so far, then actually submit the order --}}
<div class="modal fade admin-query item-store-sheet" id="itemCartModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            {{ Form::open(['route' => $itemStoreSubmitRoute ?? 'student-item-store-add', 'method' => 'POST', 'id' => 'itemStoreForm']) }}
            <div class="modal-header">
                <h4 class="modal-title">@lang('academics.your_cart')</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="itemCartEmpty" class="item-store-cart-empty">
                    <span class="ti-shopping-cart"></span>
                    @lang('academics.cart_empty_hint')
                </div>
                <ul id="itemCartList" class="item-store-cart-list" hidden></ul>
                <div class="item-store-cart-total" id="itemCartTotalRow" hidden>
                    <strong>@lang('academics.items_total')</strong>
                    <strong id="itemCartTotal">0.00</strong>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="primary-btn fix-gr-bg submit" id="itemStoreSubmit" disabled style="width:100%;">
                    <span class="ti-check pr-2"></span>
                    @lang('academics.submit_order')
                </button>
            </div>
            {{ Form::close() }}
        </div>
    </div>
</div>

@isset($pendingOrders)
{{-- My Pending Orders modal: everything already submitted and awaiting reception's review --}}
<div class="modal fade admin-query item-store-sheet" id="myPendingOrdersModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">@lang('academics.my_pending_orders')</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                @if($pendingOrders->count() == 0)
                <div class="item-store-cart-empty">
                    <span class="ti-time"></span>
                    @lang('academics.no_pending_orders')
                </div>
                @else
                <p class="text-muted">@lang('academics.pending_order_hint')</p>
                @foreach($pendingOrders->groupBy('order_batch') as $batchOrders)
                @php $batchTotal = $batchOrders->sum('amount'); @endphp
                <div class="order-batch-card">
                    <div class="order-batch-head">
                        <span class="order-batch-date">
                            @lang('academics.order_submitted_on') {{ optional($batchOrders->first()->created_at)->format('M d, Y \a\t h:i A') }}
                        </span>
                        <span class="order-batch-total">{{ currency_format($batchTotal) ?: number_format($batchTotal, 2) }}</span>
                    </div>
                    <x-table>
                        <table class="table Crm_table_active3" cellspacing="0" width="100%">
                            <thead>
                                <tr>
                                    <th>@lang('inventory.item_name')</th>
                                    <th>@lang('inventory.quantity')</th>
                                    <th>@lang('accounts.amount')</th>
                                    <th>@lang('student.status')</th>
                                    <th>@lang('common.action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($batchOrders as $order)
                                <tr>
                                    <td>{{ optional($order->item)->item_name }}</td>
                                    <td>{{ $order->quantity }}</td>
                                    <td>{{ currency_format($order->amount) ?: number_format($order->amount, 2) }}</td>
                                    <td><span class="badge badge-warning">@lang('academics.order_status_pending')</span></td>
                                    <td>
                                        {{ Form::open(['route' => [$itemStoreCancelRoute ?? 'student-item-store-cancel', $order->id], 'method' => 'POST']) }}
                                        <button type="submit" class="primary-btn small tr-bg" onclick="return confirm('{{ __('common.are_you_sure_to_cancel') }}')">
                                            @lang('common.cancel')
                                        </button>
                                        {{ Form::close() }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-table>
                </div>
                @endforeach
                @endif
            </div>
        </div>
    </div>
</div>
@endisset

@push('script')
<script>
$(document).ready(function () {
    var cart = {};

    // When this content sits inside #itemStoreModal, these child modals are DOM
    // descendants of the outer modal's transformed/scrolling body, so their
    // position:fixed is trapped in that box and they render overlapping it
    // instead of over the page. Moving them to <body> lets them stack properly;
    // on the standalone Item Store page they're already effectively top-level.
    $('#addToOrderModal, #itemCartModal, #myPendingOrdersModal').appendTo(document.body);

    // Push the outer store back visually while a sheet is on top of it.
    $(document).on('show.bs.modal', '.item-store-sheet', function () {
        $('#itemStoreModal').addClass('item-store-dimmed');
    });
    $(document).on('hidden.bs.modal', '.item-store-sheet', function () {
        if (!$('.item-store-sheet:visible').length) {
            $('#itemStoreModal').removeClass('item-store-dimmed');
        }
    });

    // Bootstrap 4 doesn't natively support opening a modal from inside another
    // open modal (e.g. this cart modal opened while the outer #itemStoreModal is
    // already showing on the balance summary page): the new backdrop lands behind
    // the parent's, and closing the child strips modal-open off <body> even
    // though the parent is still open, breaking its scroll lock. Both fixed here,
    // generically, for any modal on the page - a no-op when there's no nesting.
    $(document).on('show.bs.modal', '.modal', function () {
        var zIndex = 1050 + (10 * $('.modal:visible').length);
        $(this).css('z-index', zIndex);
        setTimeout(function () {
            $('.modal-backdrop').not('.modal-stack-fixed').css('z-index', zIndex - 1).addClass('modal-stack-fixed');
        }, 0);
    });
    $(document).on('hidden.bs.modal', '.modal', function () {
        if ($('.modal:visible').length) {
            $(document.body).addClass('modal-open');
        }
    });

    function renderCart() {
        var $list = $('#itemCartList');
        $list.empty();
        var total = 0;
        var count = 0;

        $.each(cart, function (itemId, entry) {
            var lineTotal = entry.price * entry.qty;
            total += lineTotal;
            count += 1;

            $('<li>')
                .append(
                    $('<span>')
                        .append($('<span>').addClass('item-store-cart-name d-block').text(entry.name))
                        .append($('<span>').addClass('item-store-cart-qty').text(entry.price.toFixed(2) + ' each'))
                )
                .append(
                    $('<span>').addClass('item-store-cart-stepper')
                        .append($('<button type="button">').addClass('cart-qty-btn').attr('data-item-id', itemId).attr('data-step', -1).attr('aria-label', 'Decrease').html('&minus;'))
                        .append($('<span>').addClass('cart-qty-value').text(entry.qty))
                        .append(
                            $('<button type="button">').addClass('cart-qty-btn').attr('data-item-id', itemId).attr('data-step', 1).attr('aria-label', 'Increase').html('&plus;')
                                .prop('disabled', entry.max > 0 && entry.qty >= entry.max)
                        )
                )
                .append(
                    $('<span>').css('display', 'flex').css('align-items', 'center').css('gap', '10px')
                        .append($('<strong>').text(lineTotal.toFixed(2)))
                        .append(
                            $('<span>')
                                .addClass('item-store-cart-remove')
                                .attr('data-item-id', itemId)
                                .attr('title', '{{ __('common.delete') }}')
                                .text('×')
                        )
                )
                .appendTo($list);
        });

        $('#itemCartBadge').text(count);
        $('#itemCartTotal').text(total.toFixed(2));
        $('#itemStoreSubmit').prop('disabled', count === 0);

        if (count === 0) {
            $list.attr('hidden', true);
            $('#itemCartEmpty').show();
            $('#itemCartTotalRow').attr('hidden', true);
        } else {
            $list.removeAttr('hidden');
            $('#itemCartEmpty').hide();
            $('#itemCartTotalRow').removeAttr('hidden');
        }

        // Keep the actual submitted fields in sync with the cart state.
        $('#itemStoreForm input[name="item_id[]"], #itemStoreForm input[name="quantity[]"]').remove();
        $.each(cart, function (itemId, entry) {
            $('<input>').attr({ type: 'hidden', name: 'item_id[]', value: itemId }).appendTo('#itemStoreForm');
            $('<input>').attr({ type: 'hidden', name: 'quantity[]', value: entry.qty }).appendTo('#itemStoreForm');
        });
    }

    function addToOrderSubtotal() {
        var price = parseFloat($('#addToOrderModal').data('item-price')) || 0;
        var qty = parseInt($('#addToOrderQty').val(), 10) || 0;
        $('#addToOrderSubtotal').text((price * qty).toFixed(2));
    }

    function setAddToOrderQty(newQty) {
        var max = parseInt($('#addToOrderQty').attr('max'), 10);
        newQty = Math.max(1, newQty);
        if (!isNaN(max)) {
            newQty = Math.min(newQty, max);
        }
        $('#addToOrderQty').val(newQty);
        addToOrderSubtotal();
    }

    $(document).on('click', '.add-to-order-btn', function () {
        var $btn = $(this);
        var itemId = String($btn.data('item-id'));
        var stock = parseInt($btn.data('item-stock'), 10) || 0;
        var existingQty = cart[itemId] ? cart[itemId].qty : 1;

        $('#addToOrderModal')
            .data('item-id', itemId)
            .data('item-name', $btn.data('item-name'))
            .data('item-price', $btn.data('item-price'));

        $('#addToOrderItemName').text($btn.data('item-name'));
        $('#addToOrderPrice').text(parseFloat($btn.data('item-price')).toFixed(2));
        $('#addToOrderStock').text(stock);
        $('#addToOrderQty').attr('max', stock).val(Math.min(existingQty, stock || existingQty));
        addToOrderSubtotal();

        $('#addToOrderModal').modal('show');
    });

    $('#addToOrderQty').on('input change', addToOrderSubtotal);
    $('#addToOrderPlus').on('click', function () {
        setAddToOrderQty((parseInt($('#addToOrderQty').val(), 10) || 0) + 1);
    });
    $('#addToOrderMinus').on('click', function () {
        setAddToOrderQty((parseInt($('#addToOrderQty').val(), 10) || 0) - 1);
    });

    $('#addToOrderConfirm').on('click', function () {
        var modal = $('#addToOrderModal');
        var itemId = modal.data('item-id');
        var max = parseInt($('#addToOrderQty').attr('max'), 10);
        var qty = parseInt($('#addToOrderQty').val(), 10) || 0;
        if (!isNaN(max)) {
            qty = Math.min(qty, max);
        }
        if (qty < 1) {
            return;
        }

        cart[itemId] = {
            name: modal.data('item-name'),
            price: parseFloat(modal.data('item-price')) || 0,
            qty: qty,
            max: isNaN(max) ? 0 : max,
        };

        renderCart();
        modal.modal('hide');
    });

    // Cart line stepper: stepping below 1 drops the item, + stops at stock.
    $('#itemCartList').on('click', '.cart-qty-btn', function () {
        var itemId = String($(this).data('item-id'));
        var entry = cart[itemId];
        if (!entry) {
            return;
        }
        var qty = entry.qty + parseInt($(this).data('step'), 10);
        if (qty < 1) {
            delete cart[itemId];
        } else {
            entry.qty = entry.max > 0 ? Math.min(qty, entry.max) : qty;
        }
        renderCart();
    });

    $('#itemCartList').on('click', '.item-store-cart-remove', function () {
        delete cart[String($(this).data('item-id'))];
        renderCart();
    });

    $('#itemStoreSearch').on('input', function () {
        var term = $(this).val().toLowerCase().trim();
        var visible = 0;
        $('#itemStoreGrid .item-store-row').each(function () {
            var matches = !term || $(this).data('search-text').indexOf(term) !== -1;
            $(this).toggleClass('item-store-row-hidden', !matches);
            if (matches) { visible++; }
        });
        $('#itemStoreNoMatch').prop('hidden', visible !== 0);
    });

    renderCart();
});
</script>
@endpush
