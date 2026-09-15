{{--
    Per-person "view/approve/reject pending item orders" popup - works for either a
    student or a staff member, whichever submitted the orders. Originally only
    built inline on the Item Order Approval queue (one instance per student row);
    pulled out here so any page that already knows who it's looking at - the
    queue's row, or a student's own fees invoice view - can pop this up directly
    instead of sending the user to the standalone queue page just to reach the
    same modal. See studentOrdersModalAssets.blade.php for the CSS/JS this modal
    needs - include that once per page, regardless of how many of these modals the
    page renders.

    Expects: $ownerId (string, unique across the whole page - e.g. "student42" or
    "staff7", since a student and a staff member can share the same numeric id),
    $ownerLabel (display string for the modal header, e.g. "Jane Doe (ADM-042)"),
    $orders (pending SmItemOrder for that person).
--}}
<div class="modal fade admin-query student-orders-modal" id="studentOrdersModal{{ $ownerId }}" data-student-id="{{ $ownerId }}">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">{{ $ownerLabel }}</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <x-table>
                    <table class="table Crm_table_active3" cellspacing="0" width="100%">
                        <thead>
                            <tr>
                                <th><input type="checkbox" class="select-all-in-modal"></th>
                                <th>@lang('common.sl')</th>
                                <th>@lang('inventory.item_name')</th>
                                <th>@lang('inventory.quantity')</th>
                                <th>@lang('accounts.amount')</th>
                                <th>@lang('common.date')</th>
                                <th>@lang('student.action')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($orders as $order)
                            @php
                                $approveForm = '<form action="'.route('item-order-approval-approve', $order->id).'" method="POST" style="margin:0;">'
                                    . csrf_field()
                                    . '<button type="submit" class="dropdown-item" onclick="return confirm(\''.__('common.are_you_sure_to_approve').'\')">'.__('academics.approve').'</button>'
                                    . '</form>';

                                $orderRouteList = [
                                    $approveForm,
                                    '<button type="button" class="dropdown-item text-danger toggle-reject-reason" data-target-row="#rejectReasonRow'.$order->id.'">'.__('academics.reject').'</button>',
                                ];
                            @endphp
                            <tr>
                                <td><input type="checkbox" class="order-select-checkbox" value="{{ $order->id }}"></td>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ optional($order->item)->item_name }}</td>
                                <td>{{ $order->quantity }}</td>
                                <td>{{ currency_format($order->amount) ?: number_format($order->amount, 2) }}</td>
                                <td>{{ $order->created_at->format('M d, Y h:i A') }}</td>
                                <td><x-drop-down-action-component :routeList="$orderRouteList" /></td>
                            </tr>
                            <tr class="reject-reason-row" id="rejectReasonRow{{ $order->id }}" hidden>
                                <td colspan="7">
                                    {{ Form::open(['route' => ['item-order-approval-reject', $order->id], 'method' => 'POST', 'class' => 'd-flex align-items-center flex-wrap', 'style' => 'gap:10px;']) }}
                                    <input class="primary_input_field form-control" type="text" name="reject_reason" placeholder="@lang('academics.reject_reason')" style="max-width:300px;">
                                    <button type="submit" class="primary-btn small tr-bg text-danger">@lang('academics.confirm_reject')</button>
                                    <button type="button" class="primary-btn small tr-bg cancel-reject-reason" data-target-row="#rejectReasonRow{{ $order->id }}">@lang('common.cancel')</button>
                                    {{ Form::close() }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-table>

                <div class="modal-bulk-bar bulk-order-bar" id="bulkBar{{ $ownerId }}" hidden>
                    <span class="bulk-count"><span class="bulk-count-number">0</span> @lang('academics.selected')</span>
                    <div class="d-flex flex-wrap" style="gap:10px;">
                        {{ Form::open(['route' => 'item-order-approval-bulk-approve', 'method' => 'POST', 'id' => 'bulkApproveForm' . $ownerId]) }}
                        <button type="submit" class="primary-btn small fix-gr-bg" onclick="return confirm('{{ __('common.are_you_sure_to_approve') }}')">
                            @lang('academics.approve_selected')
                        </button>
                        {{ Form::close() }}
                        <button type="button" class="primary-btn small tr-bg text-danger toggle-reject-reason" data-target-row="#bulkRejectRow{{ $ownerId }}">
                            @lang('academics.reject_selected')
                        </button>
                    </div>
                </div>

                <div class="bulk-reject-row" id="bulkRejectRow{{ $ownerId }}" hidden>
                    {{ Form::open(['route' => 'item-order-approval-bulk-reject', 'method' => 'POST', 'id' => 'bulkRejectForm' . $ownerId, 'class' => 'd-flex align-items-center flex-wrap', 'style' => 'gap:10px;']) }}
                    <input class="primary_input_field form-control" type="text" name="reject_reason" placeholder="@lang('academics.reject_reason')" style="max-width:300px;">
                    <button type="submit" class="primary-btn small tr-bg text-danger">@lang('academics.confirm_reject')</button>
                    <button type="button" class="primary-btn small tr-bg cancel-reject-reason" data-target-row="#bulkRejectRow{{ $ownerId }}">@lang('common.cancel')</button>
                    {{ Form::close() }}
                </div>
            </div>
        </div>
    </div>
</div>
