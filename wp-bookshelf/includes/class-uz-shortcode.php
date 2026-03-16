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

        $version = UZ_BOOKSHELF_VERSION;

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

        // Babel standalone for JSX transpilation
        wp_enqueue_script(
            'babel-standalone',
            'https://unpkg.com/@babel/standalone/babel.min.js',
            array(),
            '7',
            true
        );

        // Bookshelf CSS
        wp_enqueue_style(
            'uz-bookshelf',
            $this->plugin_url . 'assets/css/UzBookshelf.css',
            array(),
            $version
        );

        // Output config as inline script before JSX
        add_action( 'wp_footer', array( $this, 'output_config_script' ), 18 );

        // Bookshelf JSX - loaded as text/babel for Babel transpilation
        add_action( 'wp_footer', array( $this, 'output_jsx_script' ), 20 );
    }

    /**
     * Output the config as an inline script (before Babel scripts)
     */
    public function output_config_script() {
        $config = array(
            'apiBase'    => esc_url_raw( rest_url( 'uz-bookshelf/v1' ) ),
            'nonce'      => wp_create_nonce( 'wp_rest' ),
            'coversBase' => $this->plugin_url . 'assets/covers/',
        );
        echo '<script>var uzBookshelfConfig = ' . wp_json_encode( $config ) . ';</script>' . "\n";
    }

    /**
     * Output the JSX script tag with type="text/babel"
     * This is needed because wp_enqueue_script doesn't support custom script types.
     */
    public function output_jsx_script() {
        $jsx_url = $this->plugin_url . 'assets/js/UzBookshelf.jsx';
        $version = UZ_BOOKSHELF_VERSION;
        echo '<script type="text/babel" data-type="module" src="' . esc_url( $jsx_url ) . '?ver=' . esc_attr( $version ) . '"></script>' . "\n";

        // Initialize: find all containers and mount React
        ?>
        <script type="text/babel" data-type="module">
        (function() {
            const containers = document.querySelectorAll('.uz-bookshelf-container');
            containers.forEach(container => {
                const root = ReactDOM.createRoot(container);
                const props = {};
                if (container.dataset.mode) props.initialMode = container.dataset.mode;
                if (container.dataset.shelf) props.initialShelf = container.dataset.shelf;
                if (container.dataset.article) props.initialArticle = container.dataset.article;
                root.render(<UzBookshelf {...props} />);
            });
        })();
        </script>
        <script>
        // Babel standalone may have already run; re-process any unprocessed text/babel scripts
        if (typeof Babel !== 'undefined' && Babel.transformScriptTags) {
            Babel.transformScriptTags();
        } else {
            // Babel not yet loaded; wait for it
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof Babel !== 'undefined' && Babel.transformScriptTags) {
                    Babel.transformScriptTags();
                }
            });
        }
        </script>
        <?php
    }
}
