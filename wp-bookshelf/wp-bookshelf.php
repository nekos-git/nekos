<?php
/**
 * Plugin Name: UZ Bookshelf
 * Plugin URI: https://github.com/nekos-git/wp-bookshelf
 * Description: 3D bookshelf display with affiliate links. Embed beautiful wooden bookshelves on any page with [uz_bookshelf] shortcode. Supports UZ Selection and Rakuten Books.
 * Version: 1.0.0
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
define( 'UZ_BOOKSHELF_VERSION', '1.0.3' );
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

        // Admin menus
        if ( is_admin() ) {
            add_action( 'admin_menu', array( $this->admin, 'register_menus' ) );
            add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_admin_assets' ) );
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
        $this->db->create_tables();

        // Auto-load sample data on first activation if tables are empty
        if ( $this->db->is_empty() ) {
            $this->db->load_sample_data( true );
        }

        flush_rewrite_rules();
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
        // Future: load textdomain for i18n
        // load_plugin_textdomain( 'uz-bookshelf', false, dirname( plugin_basename( UZ_BOOKSHELF_FILE ) ) . '/languages' );
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
