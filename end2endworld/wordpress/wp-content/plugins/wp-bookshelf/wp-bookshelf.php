<?php
/**
 * Plugin Name: UZ Bookshelf
 * Plugin URI: https://github.com/nekos-git/wp-bookshelf
 * Description: 3D bookshelf display with affiliate links. Embed beautiful wooden bookshelves on any page with [uz_bookshelf] shortcode. Supports UZ Selection and Rakuten Books.
 * Version: 1.1.0
 * Author: UZ Media
 * Author URI: https://uz-media.com
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
define( 'UZ_BOOKSHELF_VERSION', '1.1.0' );
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

    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once UZ_BOOKSHELF_PATH . 'includes/class-uz-db.php';
        require_once UZ_BOOKSHELF_PATH . 'includes/class-uz-api.php';
        require_once UZ_BOOKSHELF_PATH . 'includes/class-uz-admin.php';
        require_once UZ_BOOKSHELF_PATH . 'includes/class-uz-shortcode.php';
        require_once UZ_BOOKSHELF_PATH . 'includes/class-uz-importer.php';
    }

    /**
     * Initialize components
     */
    private function init_components() {
        $this->db        = new UZ_Bookshelf_DB();
        $this->api       = new UZ_Bookshelf_API( $this->db );
        $this->admin     = new UZ_Bookshelf_Admin( $this->db, UZ_BOOKSHELF_URL );
        $this->shortcode = new UZ_Bookshelf_Shortcode( UZ_BOOKSHELF_URL, UZ_BOOKSHELF_PATH );
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks() {
        // Activation / Deactivation
        register_activation_hook( UZ_BOOKSHELF_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( UZ_BOOKSHELF_FILE, array( $this, 'deactivate' ) );

        // Initialize plugin
        add_action( 'init', array( $this, 'init' ) );

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
            add_action( 'save_post_uz_article', array( $this->admin, 'save_article_metabox' ) );
        }

        // Shortcode (frontend)
        $this->shortcode->register();

        // Check for DB upgrades
        add_action( 'plugins_loaded', array( $this->db, 'maybe_upgrade' ) );
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Register post type first so rewrite rules are available
        $this->register_article_post_type();
        $this->register_article_taxonomy();

        $this->db->create_tables();

        // Auto-load sample data on first activation if tables are empty
        if ( $this->db->is_empty() ) {
            $this->db->load_sample_data( true );
        }

        // Auto-import articles from MT export if no uz_article posts exist
        $article_count = wp_count_posts( 'uz_article' );
        if ( empty( $article_count->publish ) || $article_count->publish == 0 ) {
            $this->import_articles_on_activate();
        }

        flush_rewrite_rules();
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
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Plugin init
     */
    public function init() {
        $this->register_article_post_type();
        $this->register_article_taxonomy();
    }

    /**
     * Register uz_article custom post type
     */
    private function register_article_post_type() {
        $labels = array(
            'name'               => 'UZ Articles',
            'singular_name'      => 'UZ Article',
            'add_new'            => '新規追加',
            'add_new_item'       => '新しい記事を追加',
            'edit_item'          => '記事を編集',
            'new_item'           => '新しい記事',
            'view_item'          => '記事を表示',
            'search_items'       => '記事を検索',
            'not_found'          => '記事が見つかりません',
            'not_found_in_trash' => 'ゴミ箱に記事がありません',
            'all_items'          => 'すべての記事',
            'menu_name'          => 'UZ Articles',
        );

        register_post_type( 'uz_article', array(
            'labels'              => $labels,
            'public'              => true,
            'has_archive'         => true,
            'show_in_rest'        => true,
            'supports'            => array( 'title', 'editor', 'author', 'custom-fields' ),
            'rewrite'             => array( 'slug' => 'entry', 'with_front' => false ),
            'menu_icon'           => 'dashicons-media-text',
            'show_in_menu'        => false,
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
        ) );
    }

    /**
     * Register uz_category custom taxonomy
     */
    private function register_article_taxonomy() {
        $labels = array(
            'name'          => 'UZ Categories',
            'singular_name' => 'UZ Category',
            'search_items'  => 'カテゴリを検索',
            'all_items'     => 'すべてのカテゴリ',
            'edit_item'     => 'カテゴリを編集',
            'add_new_item'  => '新しいカテゴリを追加',
            'new_item_name' => '新しいカテゴリ名',
            'menu_name'     => 'UZ Categories',
        );

        register_taxonomy( 'uz_category', 'uz_article', array(
            'labels'            => $labels,
            'hierarchical'      => true,
            'public'            => true,
            'show_in_rest'      => true,
            'rewrite'           => array( 'slug' => 'uz-category' ),
            'show_admin_column' => true,
        ) );
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
