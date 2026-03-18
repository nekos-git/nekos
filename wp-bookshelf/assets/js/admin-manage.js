/**
 * UZ Bookshelf - Admin Management JS
 *
 * Handles AJAX interactions for the central item management page.
 */
(function($) {
    'use strict';

    // Bulk action visibility toggle
    $(document).on('change', '#bulk_action', function() {
        var val = $(this).val();
        $('#bulk_shelf_id').toggle(val === 'assign_shelf');
        $('#bulk_tags').toggle(val === 'add_tags');
    });

    // Select all checkboxes
    $(document).on('change', '#uz-select-all, #uz-select-all-top', function() {
        $('.uz-item-checkbox').prop('checked', this.checked);
        $('#uz-select-all, #uz-select-all-top').prop('checked', this.checked);
    });

})(jQuery);
