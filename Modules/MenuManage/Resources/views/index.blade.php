@extends('backEnd.master')
@section('title')
    @lang('menumanage::menuManage.manage_position')
@endsection
@push('css')
    <link rel="stylesheet" href="{{ asset('Modules/MenuManage/Resources/assets/css/style.css') }}" />
    <link rel="stylesheet" href="{{ asset('Modules/MenuManage/Resources/assets/css/icon-picker.css') }}" />
@endpush
@section('mainContent')
    <div class="role_permission_wrap">
        <div class="permission_title d-flex flex-wrap justify-content-between mb_10">
            <h4>{{ trans('menumanage::menuManage.menu_manage') }}</h4>
            <div class="">
             
                <a href="{{ route('menumanage.reset-with-section') }}" 
                class="primary-btn radius_30px  fix-gr-bg">{{ __('menumanage::menuManage.Reset to  with Section') }}</a>
            </div>
           
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4 mb_20">
            <div class="white-box available_box  student-details ">
                <div class="add-visitor">
                    <div id="accordion">
                        <div class="card">
                            <div class="card-header pt-0 pb-0" id="headingOne">
                                <h5 class="mb-0 create-title" data-toggle="collapse" data-target="#collapseOne"
                                    aria-expanded="false" aria-controls="collapseOne">
                                    <button class="btn btn-link add_btn_link">
                                        {{ __('menumanage::menuManage.Add Section') }}
                                    </button>
                                </h5>
                            </div>
                            <div id="collapseOne" class="collapse {{ isset($editPermissionSection) ? 'show':'' }}" aria-labelledby="headingOne" data-parent="#accordion">
                                <div class="card-body">
                                   @isset($editPermissionSection)
                                   {!! Form::open(['route'=>'sidebar-manager.section-update', 'method'=>'POST']) !!}
                                    <input type="hidden" name="id" value="{{ $editPermissionSection->id }}">
                                    @else
                                    {!! Form::open(['route'=>'sidebar-manager.section.store', 'method'=>'POST']) !!}
                                    @endif
                                        <div class="row pt-0">
                                        
                                        </div>
                                        <div id="row_element_div">
                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <div class="primary_input mb-25">
                                                        <label class="primary_input_label"
                                                            for="name">{{ __('common.name') }} <span
                                                                class="textdanger">*</span>
                                                        </label>
                                                        <input class="primary_input_field" type="text" name="name" autocomplete="off" value="{{ isset($editPermissionSection) ? $editPermissionSection->name : null }}"
                                                            placeholder="{{ __('common.name') }}">
                                                        @if ($errors->has('name'))
                                                            <span class="text-danger" >{{ @$errors->first('name') }}</span>
                                                        @endif    
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-12 text-center">
                                                <button type="submit"
                                                    class="primary-btn fix-gr-bg">
                                                    <span class="ti-check"></span>
                                                    {{ __('common.save') }} </button>
                                            </div>
                                        </div>
                                    {!! Form::close() !!}
                                </div>
                            </div>
                        </div>

                    </div>
                    <div class="mt_20" id="available_menu_div">
                        @include('menumanage::components.available_list')
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb_20">
            <div class="white-box">
                <input type="hidden" name="data" id="items-data" value="">
                <div class="add-visitor" id="menu_idv">

                    @include('menumanage::components.components')
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="white-box">
            <div class="add-visitor" id="live_preview_div">
                @include('menumanage::components.live_preview')
            </div>
        </div>
    </div>
    </div>





    <input type="hidden" id="order_change_url" value="{{ route('sidebar-manager.menu-update') }}">

    <input type="hidden" id="section_store_url" value="{{ route('sidebar-manager.section.store') }}">
    <input type="hidden" id="section_delete_url" value="{{ route('sidebar-manager.delete-section') }}">
    <input type="hidden" id="menu_delete_url" value="{{ route('sidebar-manager.menu-store') }}">
    <input type="hidden" id="menu_remove_url" value="{{ route('sidebar-manager.menu-remove') }}">

    <input type="hidden" id="section_sort_url" value="{{ route('sidebar-manager.sort-section') }}">

    {{-- One modal for every entry; the pencil icons fill it from their own data attributes,
         so no request is needed just to open it. --}}
    <div class="modal fade" id="renameMenuModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered student-details" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">{{ __('menumanage::menuManage.rename_menu') }}</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                {!! Form::open(['route' => 'sidebar-manager.menu-rename', 'method' => 'POST']) !!}
                <div class="modal-body">
                    <input type="hidden" name="permission_id" id="rename_permission_id">
                    <div class="primary_input mb-15">
                        <label class="primary_input_label" for="rename_custom_name">{{ __('common.name') }}</label>
                        <input class="primary_input_field" type="text" name="custom_name" id="rename_custom_name" autocomplete="off" maxlength="191">
                    </div>
                    <code id="rename_default_hint"></code>
                    <p class="mt-10 mb-0 text-muted">{{ __('menumanage::menuManage.rename_menu_hint') }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="primary-btn tr-bg" data-dismiss="modal">@lang('common.cancel')</button>
                    <button type="submit" class="primary-btn fix-gr-bg"><span class="ti-check"></span> @lang('common.save')</button>
                </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        $(document).on('click', '.rename_menu', function (e) {
            e.preventDefault();
            var $icon = $(this);
            $('#rename_permission_id').val($icon.data('permission-id'));
            $('#rename_custom_name').val($icon.data('custom-name') || '');
            $('#rename_default_hint').text($icon.data('default-name')
                ? @json(__('menumanage::menuManage.rename_menu_default')) + ' ' + $icon.data('default-name')
                : '');
            $('#renameMenuModal').modal('show');
        });
    </script>
@endpush


