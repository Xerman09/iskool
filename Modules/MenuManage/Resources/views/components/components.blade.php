<h4>{{ __('common.Menu List') }}</h4>
<div class="">
    @push('css')
        <link href="{{ asset('Modules/MenuManage/Resources/assets/css/jquery.nestable.min.css') }}" rel="stylesheet">
        <link href="{{ asset('Modules/MenuManage/Resources/assets/css/sidebar.css') }}" rel="stylesheet">
    @endpush


    <div class="row">
        <div class="col-xl-12 menu_item_div" id="itemDiv">
            @if (isset($sidebar_menus))
            
                @foreach ($sidebar_menus as $sidebar_menu)
                 
                        <div class="closed_section" data-id="{{ $sidebar_menu->id }}"
                            data-parent_section="{{ $sidebar_menu->permission_id }}">
                            <div id="accordion" class="dd">
                                <div class="section_nav">
                                    <h5>{{ menuLabel($sidebar_menu->permissionInfo) }}</h5>
                                    <div class="setting_icons">
                                        <span class="edit-btn">
                                            <i class="ti-pencil-alt rename_menu" title="{{ __('menumanage::menuManage.rename_menu') }}"
                                               data-permission-id="{{ $sidebar_menu->permission_id }}"
                                               data-custom-name="{{ $sidebar_menu->permissionInfo->custom_name }}"
                                               data-default-name="{{ menuDefaultLabel($sidebar_menu->permissionInfo) }}"></i>
                                        </span>
                                        <i class="ti-close delete_section" data-id="{{ $sidebar_menu->id }}"></i>
                                        <i class="ti-angle-up toggle_up_down"></i>
                                    </div>
                                </div>
                            </div>
                            @if ($sidebar_menu->subModule->count())
                                <div class="card">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-lg-12">
                                                <div id="accordion" class="dd menu-list used_menu" 
                                                    data-section="{{ $sidebar_menu->permission_id }}">
                                                    <ol class="dd-list">
                                                        @foreach ($sidebar_menu->subModule as $menu)
                                                            @if(sidebarPermission($menu->permissionInfo)==true)
                                                            <li class="dd-item" data-id="{{ $menu->id }}" 
                                                                data-section_id="{{ $menu->parent }}"
                                                                data-parent_route="{{$menu->parent}}"
                                                                data-parent="{{ $menu->parent }}"
                                                                >
                                                                <div class="card accordion_card"
                                                                    id="accordion_{{ $menu->id }}">
                                                                    <div class="card-header item_header"
                                                                        id="heading_{{ $menu->id }}">
                                                                        <div class="dd-handle">
                                                                            <div class="float-left">
                                                                                {{ menuLabel($menu->permissionInfo) }} 
                                                                            </div>
                                                                        </div>
                                                                        <div class="float-right btn_div">
                                                                            <div class="edit_icon">
                                                                                <i class="ti-pencil-alt rename_menu" title="{{ __('menumanage::menuManage.rename_menu') }}"
                                                                                   data-permission-id="{{ $menu->permission_id }}"
                                                                                   data-custom-name="{{ $menu->permissionInfo->custom_name }}"
                                                                                   data-default-name="{{ menuDefaultLabel($menu->permissionInfo) }}"></i>
                                                                                <i class="ti-close remove_menu"></i>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                </div>

                                                                <ol class="dd-list">
                                                                    
                                                                    @foreach ($menu->subModule as $submenu)
                                                                        @if(sidebarPermission($submenu->permissionInfo)==true)
                                                                        <li class="dd-item" data-id="{{ $submenu->id }}"
                                                                        
                                                                            >
                                                                            <div class="card accordion_card"
                                                                                id="accordion_{{ $submenu->id }}">
                                                                                <div class="card-header item_header"
                                                                                    id="heading_{{ $submenu->id }}">
                                                                                    <div class="dd-handle">
                                                                                        <div class="float-left">
                                                                                            {{ menuLabel($submenu->permissionInfo) }}
                                                                                            
                                                                                        </div>
                                                                                    </div>
                                                                                <div class="float-right btn_div">
                                                                                    <div class="edit_icon">
                                                                                        <i class="ti-pencil-alt rename_menu" title="{{ __('menumanage::menuManage.rename_menu') }}"
                                                                                           data-permission-id="{{ $submenu->permission_id }}"
                                                                                           data-custom-name="{{ $submenu->permissionInfo->custom_name }}"
                                                                                           data-default-name="{{ menuDefaultLabel($submenu->permissionInfo) }}"></i>
                                                                                        <i class="ti-close remove_menu"></i>
                                                                                    </div>
                                                                                </div>
                                                                                </div>
                                                                            </div>
                                                                        </li>
                                                                        @endif
                                                                    @endforeach
                                                                </ol>
                                                            </li>  
                                                            @endif                                                     
                                                        @endforeach
                                                    </ol>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="card">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-lg-12">
                                                <div id="accordion2" class="dd menu-list used_menu"
                                                    data-section="{{ $sidebar_menu->permission_id }}">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                   
                @endforeach
            @endif
        </div>
    </div>


    @push('scripts')
        <script src="{{ asset('public/backEnd/js/jquery.nestable.min.js') }}"></script>
        <script src="{{ asset('Modules/MenuManage/Resources/assets/js/sidebar.js') }}"></script>
    @endpush


</div>
