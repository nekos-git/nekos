<?php
// functions.php の冒頭あたりに追加してください

// 投稿本文（the_content）にショートコードを展開
  add_filter('the_content','do_shortcode',11);

// 抜粋を使っているならこちらも
add_filter( 'the_excerpt',  'do_shortcode' );

// ウィジェットに書いたショートコードを展開したいなら
add_filter( 'widget_text',   'do_shortcode' );

// メニュー説明欄で使いたいなら
add_filter( 'nav_menu_description', 'do_shortcode' );


     if( has_excerpt() ){
          the_excerpt();
          echo '<a href="';
          the_permalink();
          echo '">続きを読む</a>';
     } else {
          the_excerpt();
     }


function music_links_shortcode($atts) {
    $atts = shortcode_atts(
        array(
            'apple' => '',
            'spotify' => '',
        ),
        $atts,
        'music_links'
    );

    $output = '<div class="music-links">';

    if (!empty($atts['apple'])) {
        $output .= '<a class="music-button apple" href="' . esc_url($atts['apple']) . '" target="_blank" rel="noopener noreferrer">
            <span class="icon">
                <!-- Apple Musicロゴ -->
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" width="20" height="20" fill="#eeeeee">
                    <path d="M318.7 268c-35.3-22.3-83-25.7-123.2-8.4-20.6 8.6-36.9 21.3-49.7 37.5-22.7 29.6-30 70.4-19.9 111.8 9.9 40.8 32.4 69.7 62.2 86.3 29.8 16.6 63.9 18.2 98.1 5 21.2-8.1 39.5-19.5 54.4-34.1 27.8-27.2 44.4-65.5 44.4-103.3V196.7c0-16.4-10.7-31.3-26.8-35.5zM176 80c0-26.5 21.5-48 48-48s48 21.5 48 48-21.5 48-48 48-48-21.5-48-48z"/>
                </svg>
            </span>
            <span class="label">Listen on Apple Music</span>
        </a>';
    }

    if (!empty($atts['spotify'])) {
        $output .= '<a class="music-button spotify" href="' . esc_url($atts['spotify']) . '" target="_blank" rel="noopener noreferrer">
            <span class="icon">
                <!-- Spotifyロゴ -->
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 496 512" width="20" height="20" fill="#eeeeee">
                    <path d="M248 8C111 8 0 119 0 256s111 248 248 248c137 0 248-111 248-248S385 8 248 8zm121.1 365.7c-5.2 8.5-16.3 11.1-24.7 5.9-66.4-40.8-150.1-50-249.4-27.1-9.8 2.3-19.8-3.7-22-13.7-2.3-9.8 3.7-19.8 13.7-22 111.2-25 205.2-13.2 278.7 31.7 8.6 5.1 11.3 16.1 5.9 24.7zm30.1-72c-6.5 10.5-20.5 13.7-31 7.2-75.9-46.5-191.8-60.1-281.8-32.7-11.2 3.4-23.2-2.8-26.5-14-3.4-11.2 2.8-23.2 14-26.5 105.1-31.9 234.4-17 322.8 37.7 10.6 6.5 13.8 20.5 7.3 31.2zm.2-77.7c-87.1-52.1-229.8-56.8-312.4-30.9-13 4-26.9-3.2-30.9-16.3-4-13 3.2-26.9 16.3-30.9 96.7-30.1 255.9-24.7 356.6 36 12.1 7.2 16 23.1 8.8 35.2-7.1 12.2-23 16-35.1 8.9z"/>
                </svg>
            </span>
            <span class="label">Listen on Spotify</span>
        </a>';
    }

    $output .= '</div>';

    return $output;
}
add_shortcode('music_links', 'music_links_shortcode');

// SEO機能の追加
function theme_setup() {
    // タイトルタグのサポート
    add_theme_support('title-tag');
    
    // アイキャッチ画像のサポート
    add_theme_support('post-thumbnails');
    
    // HTML5サポート
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'script',
        'style'
    ));
    
    // RSSフィードリンク
    add_theme_support('automatic-feed-links');
    
    // メニューのサポート
    register_nav_menus(array(
        'primary' => 'メインメニュー',
        'footer' => 'フッターメニュー'
    ));
}
add_action('after_setup_theme', 'theme_setup');

// メタディスクリプションの最適化
function custom_excerpt_length($length) {
    return 160; // 160文字に設定
}
add_filter('excerpt_length', 'custom_excerpt_length');

// サイトマップ用の改善
function add_sitemap_meta() {
    echo '<meta name="google-site-verification" content="" />'; // Google Search Console用
}
add_action('wp_head', 'add_sitemap_meta');

// パフォーマンス最適化
function remove_unnecessary_features() {
    // 不要なCSSを削除
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('wp-block-library-theme');
    wp_dequeue_style('wc-blocks-style');
    
    // 絵文字を無効化
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
}
add_action('wp_enqueue_scripts', 'remove_unnecessary_features', 100);

