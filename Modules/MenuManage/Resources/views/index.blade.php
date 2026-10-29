@extends('backEnd.master')
@section('title')
    @lang('menumanage::menuManage.manage_position')
@endsection
@push('css')
    <link rel="stylesheet" href="{{ asset('public/backEnd/multiselect/css/jquery.multiselect.css') }}" />
    <link rel="stylesheet" href="{{ asset('public/backEnd/multiselect/css/custom_style.css') }}" />
    <link rel="stylesheet" href="{{ asset('Modules/MenuManage/Resources/assets/css/style.css') }}" />
    <link rel="stylesheet" href="{{ asset('Modules/MenuManage/Resources/assets/css/icon-picker.css') }}" />
@endpush
@section('mainContent')
    <div class="role_permission_wrap">
        <div class="permission_title d-flex flex-wrap justify-content-between mb_10">
            <h4>{{ trans('menumanage::menuManage.menu_manage') }}</h4>
            <div class="d-flex flex-wrap align-items-center">
                {{-- Which role's menu is being arranged. Submits on change; the choice is kept
                     in the session so the drag-and-drop ajax keeps aiming at the same role. --}}
                {!! Form::open(['route' => 'sidebar-manager.select-role', 'method' => 'POST', 'id' => 'managerRoleForm', 'class' => 'd-flex align-items-center mr-20 mb-0']) !!}
                    <label class="mb-0 mr-10 text-nowrap" for="manager_role_id">{{ __('menumanage::menuManage.arranging_menu_for') }}</label>
                    <select class="form-control" name="role_id" id="manager_role_id" onchange="document.getElementById('managerRoleForm').submit();">
                        @foreach (@$manager_roles ?? [] as $managerRole)
                            <option value="{{ $managerRole->id }}" {{ (isset($manager_role_id) && $manager_role_id == $managerRole->id) ? 'selected' : '' }}>
                                {{ $managerRole->name }}</option>
                        @endforeach
                    </select>
                {!! Form::close() !!}

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

                        <div class="card">
                            <div class="card-header pt-0 pb-0" id="headingCreateMenu">
                                <h5 class="mb-0 create-title" data-toggle="collapse" data-target="#collapseCreateMenu"
                                    aria-expanded="false" aria-controls="collapseCreateMenu">
                                    <button class="btn btn-link add_btn_link">
                                        {{ __('menumanage::menuManage.create_menu') }}
                                    </button>
                                </h5>
                            </div>
                            <div id="collapseCreateMenu" class="collapse" aria-labelledby="headingCreateMenu" data-parent="#accordion">
                                <div class="card-body">
                                    {!! Form::open(['route' => 'sidebar-manager.menu-create', 'method' => 'POST']) !!}
                                        <div class="primary_input mb-20">
                                            <label class="primary_input_label" for="create_menu_name">{{ __('common.name') }} <span class="textdanger">*</span></label>
                                            <input class="primary_input_field" type="text" name="name" id="create_menu_name" autocomplete="off" maxlength="191" value="{{ old('name') }}">
                                            @if ($errors->has('name'))
                                                <span class="text-danger d-block">{{ $errors->first('name') }}</span>
                                            @endif
                                        </div>
                                        {{-- The icons this installation already draws its sidebar with, so a
                                             new menu sits among the others instead of standing out. Typing a
                                             class by hand still works for anything not already in use. --}}
                                        <div class="primary_input mb-20">
                                            <label class="primary_input_label" for="create_menu_icon">{{ __('common.Icon') }}</label>
                                            <input class="primary_input_field" type="text" name="icon" id="create_menu_icon"
                                                autocomplete="off" maxlength="100" value="{{ old('icon') }}"
                                                placeholder="flaticon-reading">
                                            @if ($errors->has('icon'))
                                                <span class="text-danger d-block">{{ $errors->first('icon') }}</span>
                                            @endif
                                            <div id="create_menu_icon_choices" class="menu_icon_choices">
                                                @foreach (menuIconChoices() as $iconChoice)
                                                    <button type="button" class="menu_icon_choice"
                                                        data-icon="{{ $iconChoice }}" title="{{ $iconChoice }}">
                                                        <span class="{{ $iconChoice }}"></span>
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>
                                        <div class="primary_input mb-20">
                                            <label class="primary_input_label" for="create_menu_section">{{ __('menumanage::menuManage.add_menu_section') }} <span class="textdanger">*</span></label>
                                            <select class="form-control" name="section_id" id="create_menu_section">
                                                {{-- The unnamed root row is where a student or parent menu has to go,
                                                     so it gets a readable label here instead of being skipped. --}}
                                                @foreach (@$sidebar_menus ?? [] as $addSection)
                                                    <option value="{{ $addSection->permission_id }}">
                                                        {{ menuLabel($addSection->permissionInfo) ?: __('menumanage::menuManage.top_level') }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        {{-- Tick several roles to create the same menu in each of their layouts.
                                             A role whose layout lacks the chosen section gets it in its own
                                             top-level section instead, so it is never left orphaned. --}}
                                        <div class="primary_input mb-20">
                                            <label class="primary_input_label" for="create_menu_roles">{{ __('menumanage::menuManage.create_for_roles') }}</label>
                                            <select multiple class="multypol_check_select active position-relative"
                                                name="role_ids[]" id="create_menu_roles" style="width:100%">
                                                @foreach (@$manager_roles ?? [] as $createRole)
                                                    <option value="{{ $createRole->id }}"
                                                        {{ (isset($manager_role_id) && $manager_role_id == $createRole->id) ? 'selected' : '' }}>
                                                        {{ $createRole->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-lg-12 text-center">
                                            <button type="submit" class="primary-btn fix-gr-bg">
                                                <span class="ti-check"></span> {{ __('common.save') }}</button>
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

@include('backEnd.partials.multi_select_js')

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

@push('css')
<style>
    .menu_icon_choices {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 10px;
        max-height: 150px;
        overflow-y: auto;
        padding: 8px;
        border: 1px solid #e4e8f0;
        border-radius: 4px;
    }
    .menu_icon_choice {
        width: 34px;
        height: 34px;
        border: 1px solid transparent;
        border-radius: 4px;
        background: #f7f8fb;
        cursor: pointer;
        line-height: 1;
        font-size: 15px;
        color: #415094;
    }
    .menu_icon_choice:hover { border-color: #415094; }
    .menu_icon_choice.is_selected { border-color: #415094; background: #e8ecfb; }
</style>
@endpush

@push('script')
<script>
    $(document).on('click', '.menu_icon_choice', function () {
        var icon = $(this).data('icon');
        $('#create_menu_icon').val(icon);
        $('#create_menu_icon_choices .menu_icon_choice').removeClass('is_selected');
        $(this).addClass('is_selected');
    });

    // Typing a class by hand keeps the grid in step, so the two never disagree.
    $(document).on('input', '#create_menu_icon', function () {
        var value = $.trim($(this).val());
        $('#create_menu_icon_choices .menu_icon_choice').each(function () {
            $(this).toggleClass('is_selected', $(this).data('icon') === value);
        });
    });
</script>
@endpush
