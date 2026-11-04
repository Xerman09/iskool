let csrf_token = $('meta[name="csrf-token"]').attr('content');

/* The extra roles each save is carried to, beyond the one being arranged. Empty
   unless the person has picked some, so the default stays what it always was:
   this layout and no other. */
function applyRoles() {
    return $('#applyRolesGroup .apply_role:checked').map(function () {
        return $(this).val();
    }).get();
}

function initSortable() {
    $('#itemDiv').sortable({
        scroll: true,
        scrollSensitivity: 100,
        cursor: "move", containment: "parent", update: function (event, ui) {
            let ids = $(this).sortable('toArray', {attribute: 'data-id'});
            let data = {
                ids: ids, _token: csrf_token, apply_roles: applyRoles()
            };
            $.post($('#section_sort_url').val(), data, function (response) {
                reloadAfterChange(response)
            }).fail(function (response) {
                if (response.responseJSON.error) {
                    toastr.error(response.responseJSON.error);
                    hidePreloader()
                    return false;
                }
            });

        }
    })
    checkEmptyChild()
    // .disableSelection();
}

function reloadAfterChange(response) {
    /* An endpoint that returns nothing leaves this holding undefined, and the three
       lines below would then empty the panels - the arrangement looks lost, which is
       what "it does not save" turned out to mean. Say so instead of wiping the screen. */
    if (!response || !response.menus) {
        hidePreloader();
        toastr.error(window.jsLang('Operation failed'));
        return;
    }

    toastr.success(window.jsLang('Operation successful'));
    $('#menu_idv').html(response.menus);
    $('#available_menu_div').html(response.available_list);
    $('#live_preview_div').html(response.live_preview);
    $("#previewMenu").metisMenu();
    hidePreloader();
    initSortable();
    initNestable();
}

/* Drag-and-drop for the layout itself.
 *
 * Set up once, and once more after each redraw. It used to be bound inside a
 * mouseover handler on body, so every movement of the pointer re-initialised
 * nestable on every list - thousands of times a minute, each one adding another
 * callback, so a single drag fired the save several times over and the later
 * answers raced the earlier ones. */
function initNestable() {
    var $lists = $('.menu-list');

    if (!$lists.length || typeof $lists.nestable !== 'function') {
        return;
    }

    $lists.nestable({
        maxDepth: 2,
        callback: function (l, e) {
            if ($('#demoMode').val()) {
                toastr.warning('For the demo version, you cannot change this', 'Warning');
                return false;
            }

            saveMenuOrder(l);
        }
    });
}

/* Sends the arrangement. Pulled out of the nestable callback so a failure here can
   be reported: it used to sit inside an empty catch, so anything thrown while
   building the payload left the drag looking accepted and nothing written. */
function saveMenuOrder(l) {
    var order = null;

    try {
        var serialised = l.nestable('serialize');
        if (typeof serialised === 'object' && serialised !== null) {
            order = JSON.stringify(serialised);
        }
    } catch (err) {
        hidePreloader();
        toastr.error(window.jsLang('Operation failed'));
        return;
    }

    var ids = [];
    var unusedIds = [];

    $('.used_menu').find('li').each(function () {
        ids.push($(this).data('id'));
    });
    $('#available_menu_div').find('li').each(function () {
        unusedIds.push($(this).data('id'));
    });

    showPreloader();

    $.post($('#order_change_url').val(), {
        order: order,
        _token: csrf_token,
        ids: ids,
        unused_ids: unusedIds,
        /* Read off the list being dragged, not off `this` - inside the nestable
           callback `this` is not the element, so this always came through empty. */
        un_used: l.data('type') || null,
        menu_status: 1,
        section: l.data('section') || 1,
        apply_roles: applyRoles()
    }).done(function (response) {
        reloadAfterChange(response);
    }).fail(function (xhr) {
        hidePreloader();
        toastr.error((xhr.responseJSON && xhr.responseJSON.msg) || window.jsLang('Operation failed'));
    });
}


$(document).ready(function () {
    initNestable();
});

function checkDemo() {
    let demoMode = $('#demoMode').val();

    if (demoMode) {
        toastr.warning("For the demo version, you cannot change this", "Warning");
        return false;
    } else {
        return true;
    }
}

function hidePreloader() {
    $('.preloader').fadeOut('slow');
}

function showPreloader() {
    $('.preloader').fadeIn('slow');
}





$(document).ready(function () {
    $("#previewMenu").metisMenu();

    initSortable();


    $(document).on('click', '.remove_menu', function () {
        /* The row is no longer taken out of the page before the server has agreed.
           It used to be removed on the spot, so a refusal - a menu that still holds
           items - left the entry gone from the screen and still there in the
           sidebar. The panels are redrawn from the answer instead. */
        let id = $(this).closest('.dd-item').data('id');
        let data = {
            id: id,
            _token: csrf_token
        }
        showPreloader()
        $.post($('#menu_remove_url').val(), data).done(function (response) {
            reloadAfterChange(response)
        }).fail(function (xhr) {
            hidePreloader();
            toastr.error((xhr.responseJSON && xhr.responseJSON.msg) || window.jsLang('Operation failed'));
        });

    });


    $(document).on('click', '.toggle_up_down', function (event) {
        if ($(this).hasClass('ti-angle-up')) {
            $(this).removeClass('ti-angle-up');
            $(this).addClass('ti-angle-down');
            $(this).closest('.closed_section').find('.card').addClass('d-none');

        } else if ($(this).hasClass('ti-angle-down')) {
            $(this).removeClass('ti-angle-down');
            $(this).addClass('ti-angle-up');

            $(this).closest('.closed_section').find('.card').removeClass('d-none');
        }
    });
    // addSectionBtn
    $(document).on('click', '#addSectionBtn', function (event) {
        event.preventDefault();
        showPreloader();
        let formElement = $('#addSectionForm').serializeArray()
        let formData = new FormData();
        formElement.forEach(element => {
            formData.append(element.name, element.value);
        });

        formData.append('_token', csrf_token);

        $.ajax({
            url: $('#section_store_url').val(),
            type: "POST",
            cache: false,
            contentType: false,
            processData: false,
            data: formData,
            success: function (response) {
                hidePreloader();
                $('.section_name').val('');
                reloadAfterChange(response)
            },
            error: function (response) {
                hidePreloader()

                $.each(response.responseJSON.errors, function (k, v) {
                    toastr.error(v);
                });
            }
        });

    });

    $(document).on('click', '#addMenuBtn', function (event) {
        event.preventDefault();
        showPreloader();
        var formElement = $('#addMenuForm').serializeArray()
        var formData = new FormData();
        formElement.forEach(element => {
            formData.append(element.name, element.value);
        });


        formData.append('_token', csrf_token);

        $.ajax({
            url: $('#menu_delete_url').val(),
            type: "POST",
            cache: false,
            contentType: false,
            processData: false,
            data: formData,
            success: function (response) {
                reloadAfterChange(response)
                $('.menu_name').val('');
                $('.route_name').val('');
            },
            error: function (response) {
                hidePreloader();

                $.each(response.responseJSON.errors, function (k, v) {
                    toastr.error(v);
                });

            }
        });

    });

    $(document).on('click', '.delete_section', function () {
        let id = $(this).data('id');
        let data = {
            id: id, _token: csrf_token
        }
        showPreloader();
        $.post($('#section_delete_url').val(), data, function (response) {
            reloadAfterChange(response)
        });
    });

    /* Deletes a self-made menu from the Available list for good. Unlike .remove_menu,
       which only makes an entry unused, there is nothing to undo this - hence the
       confirmation. The server refuses anything it did not create itself and says why,
       so a refusal is shown rather than swallowed. */
    $(document).on('click', '.delete_menu', function () {
        if (!confirm($('#menu_delete_permanent_confirm').val())) {
            return false;
        }

        let id = $(this).data('id');
        let data = {
            id: id, _token: csrf_token
        }
        showPreloader();
        $.post($('#menu_delete_permanent_url').val(), data, function (response) {
            reloadAfterChange(response)
        }).fail(function (xhr) {
            hidePreloader();
            toastr.error((xhr.responseJSON && xhr.responseJSON.msg) || window.jsLang('Operation failed'));
        });
    });


})


function checkEmptyChild() {
    $('.dd-list').each(function (i, obj) {
        if ($(this).children('.dd-item').length > 0) {

        } else {
            $(this).closest('.dd-list').remove();
        }
    });
}
