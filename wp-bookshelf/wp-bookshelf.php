<?php
/**
 * Plugin Name: UZ Bookshelf
 * Plugin URI: https://github.com/nekos-git/wp-bookshelf
 * Description: 3D bookshelf display with affiliate links. Embed beautiful wooden bookshelves on any page with [uz_bookshelf] shortcode. Supports UZ Selection and Rakuten Books.
 * Version: 1.4.0
 * Author: UZ Media
 * Author URI: https://end2endworld.org
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: uz-bookshelf
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'UZ_BOOKSHELF_VERSION', '1.4.0' );
define( 'UZ_BOOKSHELF_FILE', __FILE__ );
define( 'UZ_BOOKSHELF_PATH', plugin_dir_path( __FILE__ ) );
define( 'UZ_BOOKSHELF_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class
 */
final class UZ_Bookshelf_Plugin {

    /** @var UZ_Bookshelf_Plugin Singleton instance */
    private static $instance = null;

    /** @var UZ_Bookshelf_DB Database layer */
    public $db;

    /** @var UZ_Bookshelf_API REST API */
    public $api;

    /** @var UZ_Bookshelf_Admin Admin pages */
    public $admin;

    /** @var UZ_Bookshelf_Shortcode Shortcode handler */
    public $shortcode;

    /**
     * Get singleton instance
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_components();
        $this->register_hooks();
    }

    /** @var UZ_Bookshelf_SEO SEO handler */
    public $seo;

    /** @var UZ_Bookshelf_Book_Page Book pages */
    public $book_page;

    /** @var UZ_Bookshelf_Theme_Page Theme pages */
    public $theme_page;

    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once UZ_BOOKSHELF_PATH . 'includes/class-uz-db.php';
        require_once UZ_BOOKSHELF_PATH . 'includes/class-uz-api.php';
        require_once UZ_BOOKSHELF_PATH . 'includes/class-uz-admin.php';
        require_once UZ_BOOKSHELF_PATH . 'includes/class-uz-shortcode.php';
        require_once UZ_BOOKSHELF_PATH . 'includes/class-uz-importer.php';
        require_once UZ_BOOKSHELF_PATH . 'includes/class-uz-seo.php';
        require_once UZ_BOOKSHELF_PATH . 'includes/class-uz-book-page.php';
        require_once UZ_BOOKSHELF_PATH . 'includes/class-uz-theme-page.php';
    }

    /**
     * Initialize components
     */
    private function init_components() {
        $this->db         = new UZ_Bookshelf_DB();
        $this->api        = new UZ_Bookshelf_API( $this->db );
        $this->admin      = new UZ_Bookshelf_Admin( $this->db, UZ_BOOKSHELF_URL );
        $this->shortcode  = new UZ_Bookshelf_Shortcode( UZ_BOOKSHELF_URL, UZ_BOOKSHELF_PATH );
        $this->seo        = new UZ_Bookshelf_SEO( $this->db );
        $this->book_page  = new UZ_Bookshelf_Book_Page( $this->db );
        $this->theme_page = new UZ_Bookshelf_Theme_Page( $this->db );
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks() {
        // Activation / Deactivation
        register_activation_hook( UZ_BOOKSHELF_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( UZ_BOOKSHELF_FILE, array( $this, 'deactivate' ) );

        // Register custom taxonomy
        add_action( 'init', array( $this, 'register_taxonomies' ) );

        // REST API routes
        add_action( 'rest_api_init', array( $this->api, 'register_routes' ) );

        // Disable page cache on bookshelf pages
        add_action( 'template_redirect', function () {
            if ( is_singular() && has_shortcode( get_post()->post_content ?? '', 'uz_bookshelf' ) ) {
                header( 'Cache-Control: no-cache, must-revalidate, max-age=0' );
                header( 'Pragma: no-cache' );
            }
        } );

        // Admin menus
        if ( is_admin() ) {
            add_action( 'admin_menu', array( $this->admin, 'register_menus' ) );
            add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_admin_assets' ) );
            add_action( 'add_meta_boxes', array( $this->admin, 'register_article_metaboxes' ) );
            add_action( 'save_post', array( $this->admin, 'save_article_metabox' ) );
            add_action( 'wp_ajax_uz_update_sort_order', array( $this->admin, 'ajax_update_sort_order' ) );
            add_action( 'wp_ajax_uz_bulk_reorder', array( $this->admin, 'ajax_bulk_reorder' ) );
        }

        // Shortcode (frontend)
        $this->shortcode->register();

        // SEO: OGP, JSON-LD, sitemap
        $this->seo->register();

        // Individual book pages
        $this->book_page->register();

        // Theme landing pages
        $this->theme_page->register();

        // Check for DB upgrades
        add_action( 'plugins_loaded', array( $this->db, 'maybe_upgrade' ) );

        // WP Cron: Rakuten auto-fetch
        add_action( 'uz_bookshelf_rakuten_cron', array( $this, 'run_rakuten_cron' ) );

        // Register custom cron schedule (weekly)
        add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );
    }

    /**
     * Add custom cron schedules
     */
    public function add_cron_schedules( $schedules ) {
        $schedules['weekly'] = array(
            'interval' => WEEK_IN_SECONDS,
            'display'  => __( '週1回', 'uz-bookshelf' ),
        );
        return $schedules;
    }

    /**
     * WP Cron callback: Fetch from Rakuten for each keyword
     */
    public function run_rakuten_cron() {
        $keywords = get_option( 'uz_bookshelf_cron_keywords', '' );
        if ( empty( $keywords ) ) {
            return;
        }

        $lines = array_filter( array_map( 'trim', explode( "\n", $keywords ) ) );
        $log   = array();
        $log[] = '実行日時: ' . wp_date( 'Y-m-d H:i:s' );

        foreach ( $lines as $keyword ) {
            $result = $this->db->fetch_and_save_rakuten( $keyword );
            if ( is_wp_error( $result ) ) {
                $log[] = "[$keyword] エラー: " . $result->get_error_message();
            } else {
                $log[] = sprintf(
                    '[%s] 取得=%d, 保存=%d, スキップ=%d',
                    $keyword, $result['fetched'], $result['saved'], $result['skipped']
                );
            }
            // Rate limit: 1 sec between API calls
            sleep( 1 );
        }

        update_option( 'uz_bookshelf_cron_last_log', implode( "\n", $log ) );
    }

    /**
     * Register critique_theme taxonomy for cross-genre article discovery
     */
    public function register_taxonomies() {
        register_taxonomy( 'critique_theme', 'post', array(
            'labels' => array(
                'name'              => __( '批評テーマ', 'uz-bookshelf' ),
                'singular_name'     => __( '批評テーマ', 'uz-bookshelf' ),
                'search_items'      => __( '批評テーマを検索', 'uz-bookshelf' ),
                'all_items'         => __( 'すべての批評テーマ', 'uz-bookshelf' ),
                'edit_item'         => __( '批評テーマを編集', 'uz-bookshelf' ),
                'update_item'       => __( '批評テーマを更新', 'uz-bookshelf' ),
                'add_new_item'      => __( '新しい批評テーマを追加', 'uz-bookshelf' ),
                'new_item_name'     => __( '新しい批評テーマ名', 'uz-bookshelf' ),
                'menu_name'         => __( '批評テーマ', 'uz-bookshelf' ),
            ),
            'public'            => true,
            'hierarchical'      => false,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => array( 'slug' => 'theme' ),
        ) );
    }

    /**
     * Plugin activation
     */
    public function activate() {
        $this->db->maybe_upgrade();

        // Register taxonomy before seeding
        $this->register_taxonomies();

        // Generate API token for shared hosting (where Authorization header is stripped)
        if ( ! get_option( 'uz_bookshelf_api_token' ) ) {
            update_option( 'uz_bookshelf_api_token', wp_generate_password( 32, false ) );
        }

        // Auto-import shelf data (items + shelves) from bundled JSON
        $this->import_shelf_data_on_activate();

        // Auto-import articles if no bookshelf posts exist
        $existing = get_posts( array(
            'post_type'   => 'post',
            'meta_key'    => '_uz_article',
            'meta_value'  => '1',
            'numberposts' => 1,
        ) );
        if ( empty( $existing ) ) {
            $this->import_articles_on_activate();
        }

        // Sync article categories from bundled JSON (always runs)
        $this->sync_article_categories();

        // Seed critique themes if none exist
        $this->seed_critique_themes();

        flush_rewrite_rules();
    }

    /**
     * Import shelf data (shelves + items) from bundled JSON on activation
     */
    private function import_shelf_data_on_activate() {
        $json_file = UZ_BOOKSHELF_PATH . 'data/uz-shelf-data.json';
        if ( ! file_exists( $json_file ) ) {
            return;
        }

        $json_data = json_decode( file_get_contents( $json_file ), true );
        if ( ! $json_data || empty( $json_data['shelves'] ) ) {
            return;
        }

        $this->db->import_from_json( $json_data );
    }

    /**
     * Sync article categories from bundled JSON to existing WP posts.
     */
    private function sync_article_categories() {
        $json_file = UZ_BOOKSHELF_PATH . 'data/uz-shelf-data.json';
        if ( ! file_exists( $json_file ) ) {
            return;
        }

        $json_data = json_decode( file_get_contents( $json_file ), true );
        if ( empty( $json_data['articles'] ) ) {
            return;
        }

        foreach ( $json_data['articles'] as $article ) {
            if ( empty( $article['categories'] ) ) {
                continue;
            }

            // Find existing post by slug (article id)
            $posts = get_posts( array(
                'post_type'   => 'post',
                'name'        => $article['id'],
                'post_status' => array( 'publish', 'draft', 'private' ),
                'numberposts' => 1,
                'meta_key'    => '_uz_article',
                'meta_value'  => '1',
            ) );
            if ( empty( $posts ) ) {
                continue;
            }

            $post_id  = $posts[0]->ID;
            $term_ids = array();
            foreach ( $article['categories'] as $cat_name ) {
                $term = term_exists( $cat_name, 'category' );
                if ( ! $term ) {
                    $term = wp_insert_term( $cat_name, 'category' );
                }
                if ( ! is_wp_error( $term ) ) {
                    $term_ids[] = (int) ( is_array( $term ) ? $term['term_id'] : $term );
                }
            }
            if ( $term_ids ) {
                wp_set_object_terms( $post_id, $term_ids, 'category' );
            }

            // Also sync shelf meta
            if ( ! empty( $article['shelf'] ) ) {
                update_post_meta( $post_id, '_uz_shelf', $article['shelf'] );
            }
        }
    }

    /**
     * Import articles from bundled MT export file on activation
     */
    private function import_articles_on_activate() {
        $export_file = UZ_BOOKSHELF_PATH . 'data/uz-media.com.export.txt';
        if ( ! file_exists( $export_file ) ) {
            return;
        }

        $export_content = file_get_contents( $export_file );
        if ( ! $export_content ) {
            return;
        }

        // Load shelf data for metadata mapping
        $json_file = UZ_BOOKSHELF_PATH . 'data/uz-shelf-data.json';
        $json_data = file_exists( $json_file ) ? json_decode( file_get_contents( $json_file ), true ) : null;

        // Remove kses filters for importing HTML with affiliate links
        kses_remove_filters();

        UZ_Bookshelf_Importer::run_import( $export_content, $json_data );

        kses_init_filters();
    }

    /**
     * Seed critique themes from bundled JSON data.
     * Creates taxonomy terms and assigns them to matching articles.
     */
    public function seed_critique_themes() {
        $theme_file = UZ_BOOKSHELF_PATH . 'data/critique-themes.json';
        if ( ! file_exists( $theme_file ) ) {
            return;
        }

        $data = json_decode( file_get_contents( $theme_file ), true );
        if ( empty( $data['themes'] ) ) {
            return;
        }

        // Skip if themes already seeded
        $existing = get_terms( array(
            'taxonomy'   => 'critique_theme',
            'hide_empty' => false,
            'number'     => 1,
        ) );
        if ( ! empty( $existing ) && ! is_wp_error( $existing ) ) {
            return;
        }

        foreach ( $data['themes'] as $theme ) {
            $term = wp_insert_term( $theme['name'], 'critique_theme', array(
                'slug'        => $theme['slug'],
                'description' => $theme['description'],
            ) );

            if ( is_wp_error( $term ) ) {
                continue;
            }

            $term_id = $term['term_id'];

            // Assign to matching articles by slug
            foreach ( $theme['articles'] as $article_slug ) {
                // First try posts with _uz_article meta
                $posts = get_posts( array(
                    'post_type'   => 'post',
                    'name'        => $article_slug,
                    'post_status' => array( 'publish', 'draft', 'private' ),
                    'numberposts' => 1,
                    'meta_key'    => '_uz_article',
                    'meta_value'  => '1',
                ) );
                // Fallback: any post with matching slug
                if ( empty( $posts ) ) {
                    $posts = get_posts( array(
                        'post_type'   => 'post',
                        'name'        => $article_slug,
                        'post_status' => array( 'publish', 'draft', 'private' ),
                        'numberposts' => 1,
                    ) );
                    // Set _uz_article meta for matched post
                    if ( ! empty( $posts ) ) {
                        update_post_meta( $posts[0]->ID, '_uz_article', '1' );
                    }
                }
                if ( ! empty( $posts ) ) {
                    wp_set_object_terms( $posts[0]->ID, $term_id, 'critique_theme', true );
                }
            }
        }
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        wp_clear_scheduled_hook( 'uz_bookshelf_rakuten_cron' );
        flush_rewrite_rules();
    }
}

/**
 * Returns the main plugin instance
 */
function uz_bookshelf() {
    return UZ_Bookshelf_Plugin::instance();
}

// Initialize the plugin
uz_bookshelf();
