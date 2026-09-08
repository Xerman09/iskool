@push('css')
<style>
    #itemStoreModal .modal-dialog {
        max-width: 1000px;
    }
    #itemStoreModal .item-store-search {
        margin-bottom: 15px;
    }
    #itemStoreModal .modal-body {
        max-height: 65vh;
        overflow-y: auto;
    }
    #itemStoreModal tr.item-store-row-hidden {
        display: none;
    }
    #itemStoreModal .item-qty-stepper {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    #itemStoreModal .item-qty-btn {
        width: 28px;
        height: 28px;
        line-height: 26px;
        padding: 0;
        text-align: center;
        border: 1px solid #dcdcdc;
        background: #f5f5f5;
        border-radius: 4px;
        font-weight: 600;
        cursor: pointer;
    }
    #itemStoreModal .item-qty-btn:hover {
        background: #ebebeb;
    }
    #itemStoreModal .item-qty {
        width: 50px;
        text-align: center;
        padding: 4px;
    }
    #itemStoreModal .item-store-cart {
        margin-top: 25px;
        padding-top: 15px;
        border-top: 1px solid #eee;
    }
    #itemStoreModal .item-store-cart-list {
        list-style: none;
        margin: 0;
        padding: 0;
    }
    #itemStoreModal .item-store-cart-list li {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px dashed #eee;
    }
    #itemStoreModal .item-store-cart-remove {
        color: #d9534f;
        cursor: pointer;
        font-weight: 600;
        margin-left: 10px;
    }
</style>
@endpush

<div class="modal fade admin-query" id="itemStoreModal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            {{ Form::open(['route' => 'student-item-store-add', 'method' => 'POST', 'id' => 'itemStoreForm']) }}
            <div class="modal-header">
                <h4 class="modal-title">@lang('academics.buy_items')</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <input type="text" class="primary_input_field form-control item-store-search" id="itemStoreSearch" placeholder="@lang('common.search')">

                <x-table>
                    <table id="itemStoreModalTable" class="table Crm_table_active3" cellspacing="0" width="100%">
                        <thead>
                            <tr>
                                <th>@lang('inventory.item_name')</th>
                                <th>@lang('student.category')</th>
                                <th>@lang('academics.unit_price')</th>
                                <th>@lang('academics.in_stock')</th>
                                <th>@lang('inventory.quantity')</th>
                                <th>@lang('accounts.amount')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($items as $item)
                            <tr class="item-store-row" data-search-text="{{ strtolower($item->item_name.' '.optional($item->category)->category_name) }}">
                                <td class="item-name">{{$item->item_name}}</td>
                                <td>{{ optional($item->category)->category_name }}</td>
                                <td class="item-price" data-price="{{$item->unit_price}}">{{currency_format($item->unit_price) ?: number_format($item->unit_price, 2)}}</td>
                                <td>{{$item->total_in_stock}}</td>
                                <td style="max-width:140px;">
                                    <div class="item-qty-stepper">
                                        <button type="button" class="item-qty-btn item-qty-minus" data-item-id="{{$item->id}}">&minus;</button>
                                        <input type="number" class="form-control item-qty" min="0" max="{{$item->total_in_stock}}" value="0" data-item-id="{{$item->id}}">
                                        <button type="button" class="item-qty-btn item-qty-plus" data-item-id="{{$item->id}}">&plus;</button>
                                    </div>
                                </td>
                                <td class="item-line-total">0.00</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center">@lang('common.no_data_found')</td>
                            </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" class="text-right"><strong>@lang('academics.items_total')</strong></td>
                                <td id="itemStoreGrandTotal"><strong>0.00</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </x-table>

                <div class="item-store-cart">
                    <h5 class="mb-15">@lang('academics.your_cart')</h5>
                    <p id="itemStoreCartEmpty" class="text-muted">@lang('academics.cart_empty_hint')</p>
                    <ul id="itemStoreCartList" class="item-store-cart-list" hidden></ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="primary-btn fix-gr-bg submit" id="itemStoreSubmit" disabled>
                    <span class="ti-check pr-2"></span>
                    @lang('academics.submit_order')
                </button>
            </div>
            {{ Form::close() }}
        </div>
    </div>
</div>

@push('script')
<script>
$(document).ready(function () {
    function recalcItemStoreTotals() {
        var grandTotal = 0;
        var cartItems = [];

        $('#itemStoreModalTable .item-qty').each(function () {
            var $row = $(this).closest('tr');
            var price = parseFloat($row.find('.item-price').data('price')) || 0;
            var qty = parseInt($(this).val(), 10) || 0;
            var lineTotal = price * qty;

            $row.find('.item-line-total').text(lineTotal.toFixed(2));
            grandTotal += lineTotal;

            if (qty > 0) {
                cartItems.push({
                    itemId: $(this).data('item-id'),
                    name: $row.find('.item-name').text(),
                    qty: qty,
                    subtotal: lineTotal,
                });
            }
        });

        $('#itemStoreGrandTotal').html('<strong>' + grandTotal.toFixed(2) + '</strong>');
        $('#itemStoreSubmit').prop('disabled', cartItems.length === 0);

        var $cartList = $('#itemStoreCartList');
        $cartList.empty();
        if (cartItems.length === 0) {
            $cartList.attr('hidden', true);
            $('#itemStoreCartEmpty').show();
        } else {
            $cartList.removeAttr('hidden');
            $('#itemStoreCartEmpty').hide();
            cartItems.forEach(function (cartItem) {
                $('<li>')
                    .append($('<span>').text(cartItem.name + ' × ' + cartItem.qty))
                    .append(
                        $('<span>')
                            .append($('<strong>').text(cartItem.subtotal.toFixed(2)))
                            .append(
                                $('<span>')
                                    .addClass('item-store-cart-remove')
                                    .attr('data-item-id', cartItem.itemId)
                                    .text('×')
                            )
                    )
                    .appendTo($cartList);
            });
        }
    }

    function setQty($input, newQty) {
        var max = parseInt($input.attr('max'), 10);
        newQty = Math.max(0, newQty);
        if (!isNaN(max)) {
            newQty = Math.min(newQty, max);
        }
        $input.val(newQty);
        recalcItemStoreTotals();
    }

    $('#itemStoreModalTable').on('input change', '.item-qty', recalcItemStoreTotals);

    $('#itemStoreModalTable').on('click', '.item-qty-plus', function () {
        var $input = $('#itemStoreModalTable .item-qty[data-item-id="' + $(this).data('item-id') + '"]');
        setQty($input, (parseInt($input.val(), 10) || 0) + 1);
    });

    $('#itemStoreModalTable').on('click', '.item-qty-minus', function () {
        var $input = $('#itemStoreModalTable .item-qty[data-item-id="' + $(this).data('item-id') + '"]');
        setQty($input, (parseInt($input.val(), 10) || 0) - 1);
    });

    $('#itemStoreCartList').on('click', '.item-store-cart-remove', function () {
        var $input = $('#itemStoreModalTable .item-qty[data-item-id="' + $(this).data('item-id') + '"]');
        setQty($input, 0);
    });

    recalcItemStoreTotals();

    $('#itemStoreSearch').on('input', function () {
        var term = $(this).val().toLowerCase().trim();
        $('#itemStoreModalTable .item-store-row').each(function () {
            var matches = !term || $(this).data('search-text').indexOf(term) !== -1;
            $(this).toggleClass('item-store-row-hidden', !matches);
        });
    });

    $('#itemStoreModal').on('hidden.bs.modal', function () {
        $('#itemStoreSearch').val('').trigger('input');
    });

    $('#itemStoreForm').on('submit', function () {
        $('#itemStoreModalTable .item-qty').each(function () {
            var qty = parseInt($(this).val(), 10) || 0;
            if (qty > 0) {
                $(this).attr('name', 'quantity[]');
                $('<input>').attr({
                    type: 'hidden',
                    name: 'item_id[]',
                    value: $(this).data('item-id'),
                }).insertAfter($(this));
            }
        });
    });
});
</script>
@endpush
