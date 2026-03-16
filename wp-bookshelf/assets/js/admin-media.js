/**
 * UZ Bookshelf - Admin Media & Cover Image Helper
 *
 * - WordPress Media Library picker for cover images
 * - Auto-fetch cover from Amazon product URL (ASIN extraction)
 * - Live preview of cover image
 */
(function ($) {
    'use strict';

    $(document).ready(function () {
        var $coverUrl    = $('#cover_url');
        var $preview     = $('#uz-cover-preview');
        var $amazonUrl   = $('#amazon_url');

        // --- Media Library picker ---
        $('#uz-select-cover').on('click', function (e) {
            e.preventDefault();
            var frame = wp.media({
                title: 'Select Cover Image',
                multiple: false,
                library: { type: 'image' }
            });
            frame.on('select', function () {
                var url = frame.state().get('selection').first().toJSON().url;
                $coverUrl.val(url);
                $preview.attr('src', url).show();
            });
            frame.open();
        });

        // --- Amazon URL → Cover image ---
        $('#uz-fetch-cover').on('click', function () {
            var url = $amazonUrl.val();
            if (!url) {
                alert('Amazon URLを先に入力してください。');
                return;
            }
            // Extract ASIN from various Amazon URL formats
            var match = url.match(/\/(?:dp|gp\/product|ASIN)\/([A-Z0-9]{10})/i);
            if (!match) {
                match = url.match(/amazon\.co\.jp.*?\/([A-Z0-9]{10})(?:[/?]|$)/i);
            }
            if (match) {
                var asin = match[1];
                var coverUrl = 'https://m.media-amazon.com/images/P/' + asin + '.09._SL800_.jpg';
                $coverUrl.val(coverUrl);
                $preview.attr('src', coverUrl).show();
            } else {
                alert('Amazon URLからASINを取得できませんでした。\n例: https://www.amazon.co.jp/dp/B0CP31QS6R');
            }
        });

        // --- Clear cover ---
        $('#uz-clear-cover').on('click', function () {
            $coverUrl.val('');
            $preview.hide();
        });

        // --- Live preview on URL change ---
        $coverUrl.on('change input', function () {
            var val = $(this).val();
            if (val) {
                $preview.attr('src', val).show();
            } else {
                $preview.hide();
            }
        });

        // --- Article select auto-fill title ---
        $('#article_id').on('change', function () {
            var $selected = $(this).find('option:selected');
            $('#article_title').val($selected.text().trim());
        });
    });
})(jQuery);
