/**
 * UZ Bookshelf - Admin Shelf Items Drag & Drop + View Toggle
 *
 * Provides jQuery UI Sortable-based drag-and-drop reordering
 * and spine/grid view switching without page reload.
 */
(function($) {
    'use strict';

    var saving = false;

    function initSortable() {
        // Spine view: sortable spine row
        $('.uz-spine-row.uz-sortable').sortable({
            items: '.uz-spine-book',
            axis: 'x',
            cursor: 'grabbing',
            placeholder: 'uz-sortable-placeholder-spine',
            opacity: 0.7,
            tolerance: 'pointer',
            start: function(e, ui) {
                ui.placeholder.width(ui.item.outerWidth());
                ui.placeholder.height(ui.item.outerHeight());
            },
            stop: function() {
                saveOrder($(this), true);
            }
        });

        // Grid view: sortable div container
        $('div.uz-items-grid.uz-sortable').sortable({
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
                saveOrder($(this), false);
            }
        });
    }

    function saveOrder($container, isSpine) {
        if (saving) return;
        saving = true;

        var selector = isSpine ? '.uz-spine-book' : '.uz-grid-item';
        var items = $container.find(selector);
        var order = [];

        items.each(function(index) {
            var id = $(this).data('item-id');
            var sortOrder = index + 1;
            order.push({ id: id, sort_order: sortOrder });
            $(this).attr('data-sort', sortOrder);

            if (!isSpine) {
                $(this).find('.uz-grid-order').text('#' + sortOrder);
            }
        });

        // Sync sort order to the other view within the same shelf group
        var $group = $container.closest('.uz-shelf-group');
        var otherSelector = isSpine ? '.uz-grid-item' : '.uz-spine-book';
        order.forEach(function(entry) {
            $group.find(otherSelector + '[data-item-id="' + entry.id + '"]').attr('data-sort', entry.sort_order);
            if (isSpine) {
                $group.find('.uz-grid-item[data-item-id="' + entry.id + '"] .uz-grid-order').text('#' + entry.sort_order);
            }
        });

        // Show saving indicator
        $group.find('.uz-shelf-group-title').addClass('uz-saving');

        $.ajax({
            url: uzItemsDnd.ajaxUrl,
            method: 'POST',
            data: {
                action: 'uz_bulk_reorder',
                _wpnonce: uzItemsDnd.nonce,
                order: order
            },
            success: function(res) {
                var $title = $group.find('.uz-shelf-group-title');
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
                var $title = $group.find('.uz-shelf-group-title');
                $title.removeClass('uz-saving').addClass('uz-save-error');
                setTimeout(function() { $title.removeClass('uz-save-error'); }, 2000);
            },
            complete: function() {
                saving = false;
            }
        });
    }

    // View toggle (spine / grid) - no page reload
    function initViewToggle() {
        $('.uz-view-toggle').on('click', function(e) {
            e.preventDefault();
            var view = $(this).data('view');
            var $wrap = $(this).closest('.uz-items-wrap');

            // Update button states
            $('.uz-view-toggle').removeClass('active');
            $(this).addClass('active');
            $wrap.attr('data-view', view);

            if (view === 'grid') {
                $wrap.find('.uz-view-spine').hide();
                $wrap.find('.uz-view-grid').show();
            } else {
                $wrap.find('.uz-view-grid').hide();
                $wrap.find('.uz-view-spine').show();
            }
        });
    }

    // Select-all checkboxes
    function initCheckboxes() {
        $('#uz-items-select-all').on('change', function() {
            // Only check visible checkboxes
            var $wrap = $(this).closest('.uz-items-wrap');
            var view = $wrap.attr('data-view');
            if (view === 'grid') {
                $wrap.find('.uz-view-grid .uz-items-cb').prop('checked', this.checked);
            } else {
                $wrap.find('.uz-view-spine .uz-items-cb').prop('checked', this.checked);
            }
        });
    }

    $(document).ready(function() {
        initSortable();
        initViewToggle();
        initCheckboxes();
    });

})(jQuery);
