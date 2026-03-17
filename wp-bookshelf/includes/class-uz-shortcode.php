<?php
/**
 * UZ Bookshelf - Shortcode
 *
 * Registers [uz_bookshelf] shortcode to embed the React 3D bookshelf
 * on any WordPress page or post.
 *
 * Usage:
 *   [uz_bookshelf]                     - Full bookshelf (all modes)
 *   [uz_bookshelf mode="uz"]           - UZ Selection only
 *   [uz_bookshelf mode="rakuten"]      - Rakuten Books only
 *   [uz_bookshelf shelf="books"]       - Specific shelf only
 *   [uz_bookshelf article="article-id"] - Related books for specific article
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UZ_Bookshelf_Shortcode {

    /** @var string Plugin base URL */
    private $plugin_url;

    /** @var string Plugin base path */
    private $plugin_path;

    /** @var bool Track if assets are already enqueued */
    private $enqueued = false;

    public function __construct( $plugin_url, $plugin_path ) {
        $this->plugin_url  = $plugin_url;
        $this->plugin_path = $plugin_path;
    }

    /**
     * Register the shortcode
     */
    public function register() {
        add_shortcode( 'uz_bookshelf', array( $this, 'render' ) );
    }

    /**
     * Render the shortcode
     */
    public function render( $atts ) {
        $atts = shortcode_atts( array(
            'mode'    => '',       // 'uz', 'rakuten', or '' (both)
            'shelf'   => '',       // specific shelf id
            'article' => '',       // specific article id for related books
            'height'  => '',       // optional height override
        ), $atts, 'uz_bookshelf' );

        // Enqueue assets only when shortcode is used
        $this->enqueue_assets();

        // Build container with data attributes
        $container_id = 'uz-bookshelf-' . wp_unique_id();
        $data_attrs   = '';

        if ( $atts['mode'] ) {
            $data_attrs .= ' data-mode="' . esc_attr( $atts['mode'] ) . '"';
        }
        if ( $atts['shelf'] ) {
            $data_attrs .= ' data-shelf="' . esc_attr( $atts['shelf'] ) . '"';
        }
        if ( $atts['article'] ) {
            $data_attrs .= ' data-article="' . esc_attr( $atts['article'] ) . '"';
        }

        $style = '';
        if ( $atts['height'] ) {
            $style = ' style="min-height:' . esc_attr( $atts['height'] ) . '"';
        }

        return '<div id="' . esc_attr( $container_id ) . '" class="uz-bookshelf-container"' . $data_attrs . $style . '></div>';
    }

    /**
     * Enqueue CSS and JS assets
     */
    private function enqueue_assets() {
        if ( $this->enqueued ) {
            return;
        }
        $this->enqueued = true;

        $js_file = UZ_BOOKSHELF_PATH . 'assets/js/UzBookshelf.js';
        $version  = UZ_BOOKSHELF_VERSION . '.' . ( file_exists( $js_file ) ? filemtime( $js_file ) : '' );

        // React 18 — use WP bundled version (WP 6.5+), fallback to CDN for older WP
        if ( wp_script_is( 'react', 'registered' ) ) {
            wp_enqueue_script( 'react' );
            wp_enqueue_script( 'react-dom' );
        } else {
            wp_enqueue_script(
                'react',
                'https://unpkg.com/react@18/umd/react.production.min.js',
                array(),
                '18',
                true
            );
            wp_enqueue_script(
                'react-dom',
                'https://unpkg.com/react-dom@18/umd/react-dom.production.min.js',
                array( 'react' ),
                '18',
                true
            );
        }

        // Bookshelf JS (pre-compiled, no Babel needed)
        wp_enqueue_script(
            'uz-bookshelf-app',
            $this->plugin_url . 'assets/js/UzBookshelf.js',
            array( 'react', 'react-dom' ),
            $version,
            true
        );

        // Bookshelf CSS
        wp_enqueue_style(
            'uz-bookshelf',
            $this->plugin_url . 'assets/css/UzBookshelf.css',
            array(),
            $version
        );

        // Hide theme header/footer and remove layout constraints for fullscreen bookshelf
        wp_add_inline_style( 'uz-bookshelf', '
            body:has(.uz-bookshelf-container) { margin: 0; padding: 0; }
            body:has(.uz-bookshelf-container) > header,
            body:has(.uz-bookshelf-container) > footer,
            body:has(.uz-bookshelf-container) .wp-site-blocks > header,
            body:has(.uz-bookshelf-container) .wp-site-blocks > footer,
            body:has(.uz-bookshelf-container) .site-header,
            body:has(.uz-bookshelf-container) .site-footer { display: none !important; }
            body:has(.uz-bookshelf-container) .wp-site-blocks,
            body:has(.uz-bookshelf-container) .wp-site-blocks > main,
            body:has(.uz-bookshelf-container) .wp-site-blocks > main > .wp-block-group,
            body:has(.uz-bookshelf-container) .entry-content,
            body:has(.uz-bookshelf-container) .wp-block-post-content,
            body:has(.uz-bookshelf-container) .wp-block-group {
                max-width: none !important; padding: 0 !important; margin: 0 !important;
            }
            body:has(.uz-bookshelf-container) .wp-block-post-title { display: none !important; }
            body:has(.uz-bookshelf-container) .is-layout-constrained > :where(:not(.alignleft):not(.alignright):not(.alignfull)) {
                max-width: none !important; margin-left: 0 !important; margin-right: 0 !important;
            }
        ' );

        // Pass config to JS
        wp_localize_script( 'uz-bookshelf-app', 'uzBookshelfConfig', array(
            'apiBase'    => esc_url_raw( rest_url( 'uz-bookshelf/v1' ) ),
            'nonce'      => wp_create_nonce( 'wp_rest' ),
            'coversBase' => $this->plugin_url . 'assets/covers/',
        ) );

        // Mount React app after scripts load
        wp_add_inline_script( 'uz-bookshelf-app', '
            (function() {
                var containers = document.querySelectorAll(".uz-bookshelf-container");
                containers.forEach(function(container) {
                    var root = ReactDOM.createRoot(container);
                    root.render(React.createElement(UzBookshelf));
                });
            })();
        ' );
    }
}
