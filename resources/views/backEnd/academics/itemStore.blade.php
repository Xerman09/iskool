@extends('backEnd.master')
@section('title')
@lang('academics.item_store')
@endsection

@section('mainContent')
<section class="sms-breadcrumb mb-20">
    <div class="container-fluid">
        <div class="row justify-content-between">
            <h1>@lang('academics.item_store')</h1>
            <div class="bc-pages">
                <a href="{{route('dashboard')}}">@lang('common.dashboard')</a>
                <a href="#">@lang('academics.item_store')</a>
            </div>
        </div>
    </div>
</section>

<section class="admin-visitor-area up_st_admin_visitor">
    <div class="container-fluid p-0">
        {{ Form::open(['route' => 'student-item-store-add', 'method' => 'POST', 'id' => 'itemStoreForm']) }}
        <div class="row">
            <div class="col-lg-12">
                <div class="white-box">
                    <x-table>
                        <table class="table Crm_table_active3" cellspacing="0" width="100%">
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
                                <tr>
                                    <td>{{$item->item_name}}</td>
                                    <td>{{ optional($item->category)->category_name }}</td>
                                    <td class="item-price" data-price="{{$item->unit_price}}">{{currency_format($item->unit_price) ?: number_format($item->unit_price, 2)}}</td>
                                    <td>{{$item->total_in_stock}}</td>
                                    <td style="max-width:120px;">
                                        <input type="number" class="form-control item-qty" min="0" max="{{$item->total_in_stock}}" value="0" data-item-id="{{$item->id}}">
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

                    <div class="row mt-30">
                        <div class="col-lg-12 text-center">
                            <button type="submit" class="primary-btn fix-gr-bg submit" id="itemStoreSubmit" disabled>
                                <span class="ti-check"></span>
                                @lang('academics.add_to_invoice')
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        {{ Form::close() }}
    </div>
</section>
@endsection

@push('script')
<script>
$(document).ready(function () {
    function recalcItemStoreTotals() {
        var grandTotal = 0;
        var anySelected = false;

        $('.item-qty').each(function () {
            var $row = $(this).closest('tr');
            var price = parseFloat($row.find('.item-price').data('price')) || 0;
            var qty = parseInt($(this).val(), 10) || 0;
            var lineTotal = price * qty;

            $row.find('.item-line-total').text(lineTotal.toFixed(2));
            grandTotal += lineTotal;

            if (qty > 0) {
                anySelected = true;
            }
        });

        $('#itemStoreGrandTotal').html('<strong>' + grandTotal.toFixed(2) + '</strong>');
        $('#itemStoreSubmit').prop('disabled', !anySelected);
    }

    $('.item-qty').on('input change', recalcItemStoreTotals);
    recalcItemStoreTotals();

    $('#itemStoreForm').on('submit', function () {
        $('.item-qty').each(function () {
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
