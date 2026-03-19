/**
 * UZ Bookshelf - Admin Shelf Items Drag & Drop
 *
 * Provides jQuery UI Sortable-based drag-and-drop reordering
 * for both list (table) and grid views.
 */
(function($) {
    'use strict';

    var saving = false;

    function initSortable() {
        // List view: sortable tbody rows
        $('tbody.uz-sortable').sortable({
            handle: '.uz-drag-handle',
            items: 'tr[data-item-id]',
            axis: 'y',
            cursor: 'grabbing',
            placeholder: 'uz-sortable-placeholder-row',
            opacity: 0.7,
            start: function(e, ui) {
                // Set placeholder height to match dragged row
                ui.placeholder.height(ui.item.height());
                // Set column widths on helper
                ui.item.children('td').each(function(i) {
                    ui.helper.children('td').eq(i).width($(this).width());
                });
            },
            stop: function() {
                saveOrder($(this));
            }
        });

        // Grid view: sortable div container
        $('div.uz-sortable').sortable({
            handle: '.uz-grid-drag-handle',
            items: '.uz-grid-item',
            cursor: 'grabbing',
            placeholder: 'uz-sortable-placeholder-grid',
            tolerance: 'pointer',
            opacity: 0.7,
            start: function(e, ui) {
                ui.placeholder.width(ui.item.outerWidth());
                ui.placeholder.height(ui.item.outerHeight());
            },
            stop: function() {
                saveOrder($(this));
            }
        });
    }

    function saveOrder($container) {
        if (saving) return;
        saving = true;

        var isGrid = $container.is('div');
        var selector = isGrid ? '.uz-grid-item' : 'tr[data-item-id]';
        var items = $container.find(selector);
        var order = [];

        items.each(function(index) {
            var id = $(this).data('item-id');
            var sortOrder = index + 1;
            order.push({ id: id, sort_order: sortOrder });

            // Update displayed sort order
            $(this).attr('data-sort', sortOrder);
            if (isGrid) {
                $(this).find('.uz-grid-order').text('#' + sortOrder);
            } else {
                $(this).find('.uz-sort-cell').text(sortOrder);
            }
        });

        // Show saving indicator
        $container.closest('.uz-shelf-group').find('.uz-shelf-group-title').addClass('uz-saving');

        $.ajax({
            url: uzItemsDnd.ajaxUrl,
            method: 'POST',
            data: {
                action: 'uz_bulk_reorder',
                _wpnonce: uzItemsDnd.nonce,
                order: order
            },
            success: function(res) {
                var $title = $container.closest('.uz-shelf-group').find('.uz-shelf-group-title');
                $title.removeClass('uz-saving');
                if (res.success) {
                    $title.addClass('uz-saved');
                    setTimeout(function() { $title.removeClass('uz-saved'); }, 1500);
                } else {
                    $title.addClass('uz-save-error');
                    setTimeout(function() { $title.removeClass('uz-save-error'); }, 2000);
                }
            },
            error: function() {
                var $title = $container.closest('.uz-shelf-group').find('.uz-shelf-group-title');
                $title.removeClass('uz-saving').addClass('uz-save-error');
                setTimeout(function() { $title.removeClass('uz-save-error'); }, 2000);
            },
            complete: function() {
                saving = false;
            }
        });
    }

    // Select-all checkboxes
    function initCheckboxes() {
        $('#uz-items-select-all').on('change', function() {
            $('.uz-items-cb').prop('checked', this.checked);
        });

        // Per-shelf select-all
        $('.uz-items-select-all-shelf').on('change', function() {
            $(this).closest('table').find('.uz-items-cb').prop('checked', this.checked);
        });
    }

    $(document).ready(function() {
        initSortable();
        initCheckboxes();
    });

})(jQuery);
