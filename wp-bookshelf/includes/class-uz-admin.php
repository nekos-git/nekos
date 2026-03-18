<?php
/**
 * UZ Bookshelf - Admin Pages
 *
 * WordPress admin menu pages for managing shelves, items, articles, rakuten books.
 * Uses WordPress native UI patterns (WP_List_Table style).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UZ_Bookshelf_Admin {

    /** @var UZ_Bookshelf_DB */
    private $db;

    /** @var string Plugin base URL for assets */
    private $plugin_url;

    public function __construct( UZ_Bookshelf_DB $db, $plugin_url ) {
        $this->db         = $db;
        $this->plugin_url = $plugin_url;
    }

    /**
     * Register admin menu pages
     */
    public function register_menus() {
        add_menu_page(
            __( 'UZ Bookshelf', 'uz-bookshelf' ),
            __( 'UZ Bookshelf', 'uz-bookshelf' ),
            'manage_options',
            'uz-bookshelf',
            array( $this, 'page_dashboard' ),
            'dashicons-book-alt',
            30
        );

        add_submenu_page(
            'uz-bookshelf',
            __( 'Dashboard', 'uz-bookshelf' ),
            __( 'Dashboard', 'uz-bookshelf' ),
            'manage_options',
            'uz-bookshelf',
            array( $this, 'page_dashboard' )
        );

        add_submenu_page(
            'uz-bookshelf',
            __( 'Shelves', 'uz-bookshelf' ),
            __( 'Shelves', 'uz-bookshelf' ),
            'manage_options',
            'uz-bookshelf-shelves',
            array( $this, 'page_shelves' )
        );

        add_submenu_page(
            'uz-bookshelf',
            __( 'Shelf Items', 'uz-bookshelf' ),
            __( 'Shelf Items', 'uz-bookshelf' ),
            'manage_options',
            'uz-bookshelf-items',
            array( $this, 'page_items' )
        );

        add_submenu_page(
            'uz-bookshelf',
            __( 'Articles', 'uz-bookshelf' ),
            __( 'Articles', 'uz-bookshelf' ),
            'manage_options',
            'edit.php'
        );

        add_submenu_page(
            'uz-bookshelf',
            __( 'Rakuten Books', 'uz-bookshelf' ),
            __( 'Rakuten Books', 'uz-bookshelf' ),
            'manage_options',
            'uz-bookshelf-rakuten',
            array( $this, 'page_rakuten' )
        );

        add_submenu_page(
            'uz-bookshelf',
            __( 'Import / Export', 'uz-bookshelf' ),
            __( 'Import / Export', 'uz-bookshelf' ),
            'manage_options',
            'uz-bookshelf-import',
            array( $this, 'page_import' )
        );

        add_submenu_page(
            'uz-bookshelf',
            __( 'Settings', 'uz-bookshelf' ),
            __( 'Settings', 'uz-bookshelf' ),
            'manage_options',
            'uz-bookshelf-settings',
            array( $this, 'page_settings' )
        );
    }

    /**
     * Enqueue admin styles and scripts
     */
    public function enqueue_admin_assets( $hook ) {
        if ( strpos( $hook, 'uz-bookshelf' ) === false ) {
            return;
        }
        wp_enqueue_style(
            'uz-bookshelf-admin',
            $this->plugin_url . 'assets/css/admin.css',
            array(),
            UZ_BOOKSHELF_VERSION
        );

        // Media uploader for item edit pages
        if ( strpos( $hook, 'uz-bookshelf-items' ) !== false ) {
            wp_enqueue_media();
            wp_enqueue_script(
                'uz-bookshelf-admin-media',
                $this->plugin_url . 'assets/js/admin-media.js',
                array( 'jquery' ),
                UZ_BOOKSHELF_VERSION,
                true
            );
        }
    }

    // =========================================================================
    // Dashboard
    // =========================================================================

    public function page_dashboard() {
        $stats       = $this->db->get_stats();
        $shelf_stats = $this->db->get_shelf_stats();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'UZ Bookshelf Dashboard', 'uz-bookshelf' ); ?></h1>

            <div class="uz-admin-cards">
                <div class="uz-admin-card">
                    <h3><?php echo esc_html( $stats['shelves'] ); ?></h3>
                    <p><?php esc_html_e( 'Shelves', 'uz-bookshelf' ); ?></p>
                </div>
                <div class="uz-admin-card">
                    <h3><?php echo esc_html( $stats['items'] ); ?></h3>
                    <p><?php esc_html_e( 'Shelf Items', 'uz-bookshelf' ); ?></p>
                </div>
                <div class="uz-admin-card">
                    <h3><?php echo esc_html( $stats['articles'] ); ?></h3>
                    <p><?php esc_html_e( 'Articles', 'uz-bookshelf' ); ?></p>
                </div>
                <div class="uz-admin-card">
                    <h3><?php echo esc_html( $stats['rakuten_books'] ); ?></h3>
                    <p><?php esc_html_e( 'Rakuten Books', 'uz-bookshelf' ); ?></p>
                </div>
            </div>

            <?php if ( ! empty( $shelf_stats ) ) : ?>
            <h2><?php esc_html_e( 'Shelf Breakdown', 'uz-bookshelf' ); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ID', 'uz-bookshelf' ); ?></th>
                        <th><?php esc_html_e( 'Title', 'uz-bookshelf' ); ?></th>
                        <th><?php esc_html_e( 'Items', 'uz-bookshelf' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $shelf_stats as $s ) : ?>
                    <tr>
                        <td><code><?php echo esc_html( $s['id'] ); ?></code></td>
                        <td><?php echo esc_html( $s['title'] ); ?></td>
                        <td><?php echo esc_html( $s['item_count'] ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <h2><?php esc_html_e( 'Shortcode Usage', 'uz-bookshelf' ); ?></h2>
            <p><?php esc_html_e( 'Use the following shortcode to display the bookshelf on any page or post:', 'uz-bookshelf' ); ?></p>
            <code>[uz_bookshelf]</code>
            <p><?php esc_html_e( 'Options:', 'uz-bookshelf' ); ?></p>
            <ul>
                <li><code>[uz_bookshelf mode="uz"]</code> - <?php esc_html_e( 'UZ Selection only', 'uz-bookshelf' ); ?></li>
                <li><code>[uz_bookshelf mode="rakuten"]</code> - <?php esc_html_e( 'Rakuten Books only', 'uz-bookshelf' ); ?></li>
                <li><code>[uz_bookshelf shelf="books"]</code> - <?php esc_html_e( 'Specific shelf only', 'uz-bookshelf' ); ?></li>
            </ul>
        </div>
        <?php
    }

    // =========================================================================
    // Shelves (CRUD + reorder)
    // =========================================================================

    public function page_shelves() {
        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';

        switch ( $action ) {
            case 'add':
            case 'edit':
                $this->page_shelf_form();
                break;
            case 'delete':
                $this->handle_shelf_delete();
                break;
            case 'move_up':
            case 'move_down':
                $this->handle_shelf_move();
                break;
            default:
                $this->page_shelves_list();
        }
    }

    private function page_shelves_list() {
        $shelf_stats = $this->db->get_shelf_stats();
        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e( 'Shelves', 'uz-bookshelf' ); ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=uz-bookshelf-shelves&action=add' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'uz-bookshelf' ); ?></a>
            </h1>

            <?php if ( isset( $_GET['msg'] ) ) : ?>
                <?php if ( $_GET['msg'] === 'deleted' ) : ?>
                    <div class="notice notice-success"><p><?php esc_html_e( 'Shelf deleted.', 'uz-bookshelf' ); ?></p></div>
                <?php elseif ( $_GET['msg'] === 'created' ) : ?>
                    <div class="notice notice-success"><p><?php esc_html_e( 'Shelf created.', 'uz-bookshelf' ); ?></p></div>
                <?php endif; ?>
            <?php endif; ?>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ID', 'uz-bookshelf' ); ?></th>
                        <th><?php esc_html_e( 'Title', 'uz-bookshelf' ); ?></th>
                        <th><?php esc_html_e( 'Icon', 'uz-bookshelf' ); ?></th>
                        <th><?php esc_html_e( 'Items', 'uz-bookshelf' ); ?></th>
                        <th><?php esc_html_e( 'Order', 'uz-bookshelf' ); ?></th>
                        <th width="120"><?php esc_html_e( 'Actions', 'uz-bookshelf' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $shelf_stats ) ) : ?>
                        <tr><td colspan="6"><?php echo wp_kses_post( sprintf( __( 'No shelves found. <a href="%s">Create one</a>.', 'uz-bookshelf' ), esc_url( admin_url( 'admin.php?page=uz-bookshelf-shelves&action=add' ) ) ) ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $shelf_stats as $i => $s ) : ?>
                        <tr>
                            <td><code><?php echo esc_html( $s['id'] ); ?></code></td>
                            <td><strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=uz-bookshelf-shelves&action=edit&id=' . urlencode( $s['id'] ) ) ); ?>"><?php echo esc_html( $s['title'] ); ?></a></strong></td>
                            <td><?php echo esc_html( $s['icon'] ?? '-' ); ?></td>
                            <td>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=uz-bookshelf-items&shelf_id=' . urlencode( $s['id'] ) ) ); ?>">
                                    <?php
                                    /* translators: %d: number of items */
                                    echo esc_html( sprintf( __( '%d items', 'uz-bookshelf' ), $s['item_count'] ) );
                                    ?>
                                </a>
                            </td>
                            <td>
                                <?php if ( $i > 0 ) : ?>
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=uz-bookshelf-shelves&action=move_up&id=' . urlencode( $s['id'] ) ), 'uz_move_shelf_' . $s['id'] ) ); ?>" title="<?php esc_attr_e( 'Move up', 'uz-bookshelf' ); ?>">&uarr;</a>
                                <?php endif; ?>
                                <?php if ( $i < count( $shelf_stats ) - 1 ) : ?>
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=uz-bookshelf-shelves&action=move_down&id=' . urlencode( $s['id'] ) ), 'uz_move_shelf_' . $s['id'] ) ); ?>" title="<?php esc_attr_e( 'Move down', 'uz-bookshelf' ); ?>">&darr;</a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=uz-bookshelf-shelves&action=edit&id=' . urlencode( $s['id'] ) ) ); ?>"><?php esc_html_e( 'Edit', 'uz-bookshelf' ); ?></a> |
                                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=uz-bookshelf-shelves&action=delete&id=' . urlencode( $s['id'] ) ), 'uz_delete_shelf_' . $s['id'] ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this shelf and all its items?', 'uz-bookshelf' ) ); ?>');" style="color:#b32d2e;"><?php esc_html_e( 'Delete', 'uz-bookshelf' ); ?></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function page_shelf_form() {
        $id    = isset( $_GET['id'] ) ? sanitize_text_field( $_GET['id'] ) : '';
        $shelf = $id ? $this->db->get_shelf( $id ) : null;

        if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['uz_shelf_nonce'] ) ) {
            if ( wp_verify_nonce( $_POST['uz_shelf_nonce'], 'uz_save_shelf' ) ) {
                $shelf_id = $id ?: sanitize_title( $_POST['shelf_id'] ?? '' );
                if ( ! $shelf_id ) {
                    echo '<div class="notice notice-error"><p>' . esc_html__( 'Shelf ID is required.', 'uz-bookshelf' ) . '</p></div>';
                } else {
                    $max_order = 0;
                    if ( ! $id ) {
                        $all_shelves = $this->db->get_shelves();
                        foreach ( $all_shelves as $s ) {
                            if ( (int) $s['sort_order'] > $max_order ) $max_order = (int) $s['sort_order'];
                        }
                        $max_order++;
                    }
                    $data = array(
                        'title'      => sanitize_text_field( $_POST['title'] ?? '' ),
                        'icon'       => sanitize_text_field( $_POST['icon'] ?? '' ),
                        'sort_order' => $id ? absint( $_POST['sort_order'] ?? 0 ) : $max_order,
                    );
                    $this->db->upsert_shelf( $shelf_id, $data );

                    if ( ! $id ) {
                        wp_redirect( admin_url( 'admin.php?page=uz-bookshelf-shelves&msg=created' ) );
                        exit;
                    }
                    echo '<div class="notice notice-success"><p>' . esc_html__( 'Shelf updated.', 'uz-bookshelf' ) . '</p></div>';
                    $shelf = $this->db->get_shelf( $shelf_id );
                }
            }
        }

        $icons = array( 'book', 'film', 'music', 'manga', 'tech', 'biz', 'culture', 'star', 'heart', 'bookmark' );
        ?>
        <div class="wrap">
            <h1><?php echo $id ? esc_html__( 'Edit Shelf', 'uz-bookshelf' ) : esc_html__( 'Add New Shelf', 'uz-bookshelf' ); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=uz-bookshelf-shelves' ) ); ?>">&larr; <?php esc_html_e( 'Back to list', 'uz-bookshelf' ); ?></a>

            <form method="post" style="max-width:700px;">
                <?php wp_nonce_field( 'uz_save_shelf', 'uz_shelf_nonce' ); ?>

                <table class="form-table">
                    <tr>
                        <th><label for="shelf_id"><?php esc_html_e( 'Shelf ID (slug)', 'uz-bookshelf' ); ?></label></th>
                        <td>
                            <?php if ( $id ) : ?>
                                <code><?php echo esc_html( $id ); ?></code>
                            <?php else : ?>
                                <input type="text" name="shelf_id" id="shelf_id" class="regular-text" required placeholder="<?php esc_attr_e( 'e.g. my-books', 'uz-bookshelf' ); ?>" />
                                <p class="description"><?php esc_html_e( 'Unique identifier. Cannot be changed later.', 'uz-bookshelf' ); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="title"><?php esc_html_e( 'Title', 'uz-bookshelf' ); ?></label></th>
                        <td><input type="text" name="title" id="title" class="regular-text" value="<?php echo esc_attr( $shelf['title'] ?? '' ); ?>" required /></td>
                    </tr>
                    <tr>
                        <th><label for="icon"><?php esc_html_e( 'Icon', 'uz-bookshelf' ); ?></label></th>
                        <td>
                            <select name="icon" id="icon">
                                <?php foreach ( $icons as $ico ) : ?>
                                    <option value="<?php echo esc_attr( $ico ); ?>" <?php selected( $shelf['icon'] ?? '', $ico ); ?>><?php echo esc_html( $ico ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <?php if ( $id ) : ?>
                    <tr>
                        <th><label for="sort_order"><?php esc_html_e( 'Sort Order', 'uz-bookshelf' ); ?></label></th>
                        <td><input type="number" name="sort_order" id="sort_order" style="width:80px;" value="<?php echo esc_attr( $shelf['sort_order'] ?? 0 ); ?>" /></td>
                    </tr>
                    <?php endif; ?>
                </table>

                <?php submit_button( $id ? __( 'Update Shelf', 'uz-bookshelf' ) : __( 'Create Shelf', 'uz-bookshelf' ) ); ?>
            </form>

            <?php if ( $id ) : ?>
            <hr />
            <h2><?php esc_html_e( 'Items in this shelf', 'uz-bookshelf' ); ?></h2>
            <?php
            $items = $this->db->get_items( $id );
            if ( empty( $items ) ) :
            ?>
                <p><?php echo wp_kses_post( sprintf( __( 'No items yet. <a href="%s">Add one</a>.', 'uz-bookshelf' ), esc_url( admin_url( 'admin.php?page=uz-bookshelf-items&action=add&shelf_id=' . urlencode( $id ) ) ) ) ); ?></p>
            <?php else : ?>
                <p><?php
                    /* translators: %1$d: item count, %2$s: view all URL, %3$s: add new URL */
                    echo wp_kses_post( sprintf(
                        __( '%1$d items. <a href="%2$s">View all</a> | <a href="%3$s">Add new</a>', 'uz-bookshelf' ),
                        count( $items ),
                        esc_url( admin_url( 'admin.php?page=uz-bookshelf-items&shelf_id=' . urlencode( $id ) ) ),
                        esc_url( admin_url( 'admin.php?page=uz-bookshelf-items&action=add&shelf_id=' . urlencode( $id ) ) )
                    ) );
                ?></p>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private function handle_shelf_delete() {
        $id = isset( $_GET['id'] ) ? sanitize_text_field( $_GET['id'] ) : '';
        if ( $id && check_admin_referer( 'uz_delete_shelf_' . $id ) ) {
            $this->db->delete_shelf( $id );
        }
        wp_redirect( admin_url( 'admin.php?page=uz-bookshelf-shelves&msg=deleted' ) );
        exit;
    }

    private function handle_shelf_move() {
        $id        = isset( $_GET['id'] ) ? sanitize_text_field( $_GET['id'] ) : '';
        $direction = ( $_GET['action'] === 'move_up' ) ? 'up' : 'down';
        if ( $id && check_admin_referer( 'uz_move_shelf_' . $id ) ) {
            $this->db->move_shelf( $id, $direction );
        }
        wp_redirect( admin_url( 'admin.php?page=uz-bookshelf-shelves' ) );
        exit;
    }

    // =========================================================================
    // Shelf Items
    // =========================================================================

    public function page_items() {
        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';

        switch ( $action ) {
            case 'add':
            case 'edit':
                $this->page_item_form();
                break;
            case 'delete':
                $this->handle_item_delete();
                break;
            case 'move_up':
            case 'move_down':
                $this->handle_item_move();
                break;
            default:
                $this->page_items_list();
        }
    }

    private function page_items_list() {
        $shelf_filter = isset( $_GET['shelf_id'] ) ? sanitize_text_field( $_GET['shelf_id'] ) : '';
        $shelves      = $this->db->get_shelves();

        if ( $shelf_filter ) {
            $items = $this->db->get_items( $shelf_filter );
        } else {
            $items = $this->db->get_items();
        }

        $add_url = admin_url( 'admin.php?page=uz-bookshelf-items&action=add' );
        if ( $shelf_filter ) {
            $add_url .= '&shelf_id=' . urlencode( $shelf_filter );
        }
        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e( 'Shelf Items', 'uz-bookshelf' ); ?>
                <a href="<?php echo esc_url( $add_url ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'uz-bookshelf' ); ?></a>
            </h1>

            <?php if ( isset( $_GET['msg'] ) && $_GET['msg'] === 'deleted' ) : ?>
                <div class="notice notice-success"><p><?php esc_html_e( 'Item deleted.', 'uz-bookshelf' ); ?></p></div>
            <?php endif; ?>

            <div class="tablenav top">
                <div class="alignleft actions">
                    <form method="get">
                        <input type="hidden" name="page" value="uz-bookshelf-items" />
                        <select name="shelf_id">
                            <option value=""><?php esc_html_e( 'All Shelves', 'uz-bookshelf' ); ?></option>
                            <?php foreach ( $shelves as $s ) : ?>
                                <option value="<?php echo esc_attr( $s['id'] ); ?>" <?php selected( $shelf_filter, $s['id'] ); ?>>
                                    <?php echo esc_html( $s['title'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'uz-bookshelf' ); ?>" />
                    </form>
                </div>
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php
                        /* translators: %d: number of items */
                        echo esc_html( sprintf( __( '%d items', 'uz-bookshelf' ), count( $items ) ) );
                    ?></span>
                </div>
            </div>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th width="50"><?php esc_html_e( 'ID', 'uz-bookshelf' ); ?></th>
                        <th width="60"><?php esc_html_e( 'Cover', 'uz-bookshelf' ); ?></th>
                        <th><?php esc_html_e( 'Title', 'uz-bookshelf' ); ?></th>
                        <th><?php esc_html_e( 'Author', 'uz-bookshelf' ); ?></th>
                        <th><?php esc_html_e( 'Shelf', 'uz-bookshelf' ); ?></th>
                        <th><?php esc_html_e( 'Article', 'uz-bookshelf' ); ?></th>
                        <?php if ( $shelf_filter ) : ?><th width="60"><?php esc_html_e( 'Order', 'uz-bookshelf' ); ?></th><?php endif; ?>
                        <th width="100"><?php esc_html_e( 'Actions', 'uz-bookshelf' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $items ) ) : ?>
                        <tr><td colspan="<?php echo $shelf_filter ? 8 : 7; ?>"><?php esc_html_e( 'No items found.', 'uz-bookshelf' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $items as $idx => $item ) : ?>
                        <tr>
                            <td><?php echo esc_html( $item['id'] ); ?></td>
                            <td>
                                <?php if ( $item['cover_url'] ) : ?>
                                    <img src="<?php echo esc_url( $item['cover_url'] ); ?>" style="width:40px;height:auto;" />
                                <?php else : ?>
                                    <span style="color:#999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=uz-bookshelf-items&action=edit&id=' . $item['id'] ) ); ?>"><?php echo esc_html( $item['title'] ); ?></a></strong>
                                <?php if ( $item['full_title'] && $item['full_title'] !== $item['title'] ) : ?>
                                    <br><small><?php echo esc_html( mb_substr( $item['full_title'], 0, 50 ) ); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( $item['author'] ); ?></td>
                            <td><code><?php echo esc_html( $item['shelf_id'] ); ?></code></td>
                            <td><?php echo esc_html( $item['article_title'] ? mb_substr( $item['article_title'], 0, 20 ) . '...' : '-' ); ?></td>
                            <?php if ( $shelf_filter ) : ?>
                            <td>
                                <?php if ( $idx > 0 ) : ?>
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=uz-bookshelf-items&action=move_up&id=' . $item['id'] . '&shelf_id=' . urlencode( $shelf_filter ) ), 'uz_move_item_' . $item['id'] ) ); ?>">&uarr;</a>
                                <?php endif; ?>
                                <?php if ( $idx < count( $items ) - 1 ) : ?>
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=uz-bookshelf-items&action=move_down&id=' . $item['id'] . '&shelf_id=' . urlencode( $shelf_filter ) ), 'uz_move_item_' . $item['id'] ) ); ?>">&darr;</a>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <td>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=uz-bookshelf-items&action=edit&id=' . $item['id'] ) ); ?>"><?php esc_html_e( 'Edit', 'uz-bookshelf' ); ?></a> |
                                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=uz-bookshelf-items&action=delete&id=' . $item['id'] ), 'uz_delete_item_' . $item['id'] ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this item?', 'uz-bookshelf' ) ); ?>');" style="color:#b32d2e;"><?php esc_html_e( 'Delete', 'uz-bookshelf' ); ?></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function handle_item_move() {
        $id        = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        $shelf_id  = isset( $_GET['shelf_id'] ) ? sanitize_text_field( $_GET['shelf_id'] ) : '';
        $direction = ( $_GET['action'] === 'move_up' ) ? 'up' : 'down';
        if ( $id && check_admin_referer( 'uz_move_item_' . $id ) ) {
            $this->db->move_item( $id, $direction );
        }
        wp_redirect( admin_url( 'admin.php?page=uz-bookshelf-items' . ( $shelf_id ? '&shelf_id=' . urlencode( $shelf_id ) : '' ) ) );
        exit;
    }

    private function page_item_form() {
        $id   = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        $item = $id ? $this->db->get_item( $id ) : null;

        // Preset shelf_id and article from URL params
        $preset_shelf   = isset( $_GET['shelf_id'] ) ? sanitize_text_field( $_GET['shelf_id'] ) : '';
        $preset_article = isset( $_GET['article_id'] ) ? sanitize_text_field( $_GET['article_id'] ) : '';

        // Handle form submission
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['uz_item_nonce'] ) ) {
            if ( wp_verify_nonce( $_POST['uz_item_nonce'], 'uz_save_item' ) ) {
                $tags_raw = isset( $_POST['tags'] ) ? sanitize_text_field( $_POST['tags'] ) : '';
                $tags     = array_filter( array_map( 'trim', explode( ',', $tags_raw ) ) );

                // Auto-fill article_title from selected article
                $sel_article_id = sanitize_text_field( $_POST['article_id'] ?? '' );
                $sel_article_title = sanitize_text_field( $_POST['article_title'] ?? '' );
                if ( $sel_article_id && ! $sel_article_title ) {
                    $article_obj = $this->db->get_article( $sel_article_id );
                    if ( $article_obj ) {
                        $sel_article_title = $article_obj['title'];
                    }
                }

                $data = array(
                    'item_id'       => sanitize_text_field( $_POST['item_id'] ?? '' ),
                    'shelf_id'      => sanitize_text_field( $_POST['shelf_id'] ?? '' ),
                    'title'         => sanitize_text_field( $_POST['title'] ?? '' ),
                    'full_title'    => sanitize_text_field( $_POST['full_title'] ?? '' ),
                    'author'        => sanitize_text_field( $_POST['author'] ?? '' ),
                    'full_author'   => sanitize_text_field( $_POST['full_author'] ?? '' ),
                    'cover_url'     => esc_url_raw( $_POST['cover_url'] ?? '' ),
                    'amazon_url'    => esc_url_raw( $_POST['amazon_url'] ?? '' ),
                    'rakuten_url'   => esc_url_raw( $_POST['rakuten_url'] ?? '' ),
                    'affiliate_url' => esc_url_raw( $_POST['affiliate_url'] ?? '' ),
                    'article_id'    => $sel_article_id,
                    'article_title' => $sel_article_title,
                    'comment'       => sanitize_textarea_field( $_POST['comment'] ?? '' ),
                    'tags'          => wp_json_encode( $tags ),
                    'type'          => sanitize_text_field( $_POST['type'] ?? 'product' ),
                    'format'        => sanitize_text_field( $_POST['format'] ?? 'standard' ),
                    'width'         => absint( $_POST['width'] ?? 128 ),
                    'height'        => absint( $_POST['height'] ?? 182 ),
                    'sort_order'    => absint( $_POST['sort_order'] ?? 0 ),
                );

                if ( $id ) {
                    $this->db->update_item( $id, $data );
                    echo '<div class="notice notice-success"><p>' . esc_html__( 'Item updated.', 'uz-bookshelf' ) . '</p></div>';
                    $item = $this->db->get_item( $id );
                } else {
                    $new_id = $this->db->insert_item( $data );
                    wp_redirect( admin_url( 'admin.php?page=uz-bookshelf-items&action=edit&id=' . $new_id . '&msg=created' ) );
                    exit;
                }
            }
        }

        if ( isset( $_GET['msg'] ) && $_GET['msg'] === 'created' ) {
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Item created.', 'uz-bookshelf' ) . '</p></div>';
        }

        $shelves   = $this->db->get_shelves();
        $articles  = $this->db->get_articles();
        $tags_list = '';
        if ( $item && $item['tags'] ) {
            $decoded = json_decode( $item['tags'], true );
            if ( is_array( $decoded ) ) {
                $tags_list = implode( ', ', $decoded );
            }
        }

        $current_shelf   = $item['shelf_id'] ?? $preset_shelf;
        $current_article = $item['article_id'] ?? $preset_article;
        $cover_url_val   = $item['cover_url'] ?? '';
        ?>
        <div class="wrap">
            <h1><?php echo $id ? esc_html__( 'Edit Item', 'uz-bookshelf' ) : esc_html__( 'Add New Item', 'uz-bookshelf' ); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=uz-bookshelf-items' ) ); ?>">&larr; <?php esc_html_e( 'Back to list', 'uz-bookshelf' ); ?></a>

            <form method="post" style="max-width:700px;">
                <?php wp_nonce_field( 'uz_save_item', 'uz_item_nonce' ); ?>

                <table class="form-table">
                    <tr>
                        <th><label for="item_id"><?php esc_html_e( 'Item ID', 'uz-bookshelf' ); ?></label></th>
                        <td><input type="text" name="item_id" id="item_id" class="regular-text" value="<?php echo esc_attr( $item['item_id'] ?? '' ); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="shelf_id"><?php esc_html_e( 'Shelf', 'uz-bookshelf' ); ?></label></th>
                        <td>
                            <select name="shelf_id" id="shelf_id">
                                <?php foreach ( $shelves as $s ) : ?>
                                    <option value="<?php echo esc_attr( $s['id'] ); ?>" <?php selected( $current_shelf, $s['id'] ); ?>>
                                        <?php echo esc_html( $s['title'] ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="title"><?php esc_html_e( 'Title (short)', 'uz-bookshelf' ); ?></label></th>
                        <td><input type="text" name="title" id="title" class="regular-text" value="<?php echo esc_attr( $item['title'] ?? '' ); ?>" required /></td>
                    </tr>
                    <tr>
                        <th><label for="full_title"><?php esc_html_e( 'Full Title', 'uz-bookshelf' ); ?></label></th>
                        <td><input type="text" name="full_title" id="full_title" class="large-text" value="<?php echo esc_attr( $item['full_title'] ?? '' ); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="author"><?php esc_html_e( 'Author', 'uz-bookshelf' ); ?></label></th>
                        <td><input type="text" name="author" id="author" class="regular-text" value="<?php echo esc_attr( $item['author'] ?? '' ); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="full_author"><?php esc_html_e( 'Full Author', 'uz-bookshelf' ); ?></label></th>
                        <td><input type="text" name="full_author" id="full_author" class="regular-text" value="<?php echo esc_attr( $item['full_author'] ?? '' ); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="cover_url"><?php esc_html_e( 'Cover Image', 'uz-bookshelf' ); ?></label></th>
                        <td>
                            <div style="margin-bottom:8px;">
                                <img id="uz-cover-preview" src="<?php echo esc_url( $cover_url_val ); ?>" style="max-width:120px;max-height:170px;border:1px solid #ccc;<?php echo $cover_url_val ? '' : 'display:none;'; ?>" />
                            </div>
                            <input type="url" name="cover_url" id="cover_url" class="large-text" value="<?php echo esc_attr( $cover_url_val ); ?>" placeholder="https://..." />
                            <div style="margin-top:6px;">
                                <button type="button" id="uz-select-cover" class="button"><?php esc_html_e( 'Media Library', 'uz-bookshelf' ); ?></button>
                                <button type="button" id="uz-fetch-cover" class="button"><?php esc_html_e( 'Amazon URL &rarr; Cover', 'uz-bookshelf' ); ?></button>
                                <button type="button" id="uz-clear-cover" class="button" style="color:#b32d2e;"><?php esc_html_e( 'Clear', 'uz-bookshelf' ); ?></button>
                            </div>
                            <p class="description"><?php esc_html_e( 'Select from Media Library, auto-fetch from Amazon URL, or paste a URL directly.', 'uz-bookshelf' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="amazon_url"><?php esc_html_e( 'Amazon URL', 'uz-bookshelf' ); ?></label></th>
                        <td><input type="url" name="amazon_url" id="amazon_url" class="large-text" value="<?php echo esc_attr( $item['amazon_url'] ?? '' ); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="rakuten_url"><?php esc_html_e( 'Rakuten URL', 'uz-bookshelf' ); ?></label></th>
                        <td><input type="url" name="rakuten_url" id="rakuten_url" class="large-text" value="<?php echo esc_attr( $item['rakuten_url'] ?? '' ); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="affiliate_url"><?php esc_html_e( 'Affiliate URL', 'uz-bookshelf' ); ?></label></th>
                        <td><input type="url" name="affiliate_url" id="affiliate_url" class="large-text" value="<?php echo esc_attr( $item['affiliate_url'] ?? '' ); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="article_id"><?php esc_html_e( 'Article', 'uz-bookshelf' ); ?></label></th>
                        <td>
                            <select name="article_id" id="article_id">
                                <option value=""><?php esc_html_e( '-- None --', 'uz-bookshelf' ); ?></option>
                                <?php foreach ( $articles as $a ) : ?>
                                    <option value="<?php echo esc_attr( $a['id'] ); ?>" <?php selected( $current_article, $a['id'] ); ?>>
                                        <?php echo esc_html( mb_substr( $a['title'], 0, 60 ) ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="article_title" id="article_title" value="<?php echo esc_attr( $item['article_title'] ?? '' ); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="comment"><?php esc_html_e( 'UZ Comment', 'uz-bookshelf' ); ?></label></th>
                        <td><textarea name="comment" id="comment" rows="3" class="large-text"><?php echo esc_textarea( $item['comment'] ?? '' ); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="tags"><?php esc_html_e( 'Tags (comma-separated)', 'uz-bookshelf' ); ?></label></th>
                        <td><input type="text" name="tags" id="tags" class="large-text" value="<?php echo esc_attr( $tags_list ); ?>" placeholder="<?php esc_attr_e( 'e.g. SF, philosophy, tech', 'uz-bookshelf' ); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="type"><?php esc_html_e( 'Type', 'uz-bookshelf' ); ?></label></th>
                        <td>
                            <select name="type" id="type">
                                <option value="product" <?php selected( $item['type'] ?? '', 'product' ); ?>><?php esc_html_e( 'Product', 'uz-bookshelf' ); ?></option>
                                <option value="featured" <?php selected( $item['type'] ?? '', 'featured' ); ?>><?php esc_html_e( 'Featured', 'uz-bookshelf' ); ?></option>
                                <option value="spine" <?php selected( $item['type'] ?? '', 'spine' ); ?>><?php esc_html_e( 'Spine', 'uz-bookshelf' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="format"><?php esc_html_e( 'Format', 'uz-bookshelf' ); ?></label></th>
                        <td>
                            <select name="format" id="format">
                                <?php
                                $formats = array( 'standard', 'bunko', 'comic', 'shinsho', 'tankobon', 'hardcover', 'poster', 'disc' );
                                foreach ( $formats as $fmt ) :
                                ?>
                                    <option value="<?php echo esc_attr( $fmt ); ?>" <?php selected( $item['format'] ?? 'standard', $fmt ); ?>><?php echo esc_html( $fmt ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Dimensions', 'uz-bookshelf' ); ?></th>
                        <td>
                            <?php esc_html_e( 'W:', 'uz-bookshelf' ); ?> <input type="number" name="width" style="width:80px;" value="<?php echo esc_attr( $item['width'] ?? 128 ); ?>" />
                            <?php esc_html_e( 'H:', 'uz-bookshelf' ); ?> <input type="number" name="height" style="width:80px;" value="<?php echo esc_attr( $item['height'] ?? 182 ); ?>" />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="sort_order"><?php esc_html_e( 'Sort Order', 'uz-bookshelf' ); ?></label></th>
                        <td><input type="number" name="sort_order" id="sort_order" style="width:80px;" value="<?php echo esc_attr( $item['sort_order'] ?? 0 ); ?>" /></td>
                    </tr>
                </table>

                <?php submit_button( $id ? 'Update Item' : 'Add Item' ); ?>
            </form>
        </div>
        <?php
    }

    private function handle_item_delete() {
        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        if ( $id && check_admin_referer( 'uz_delete_item_' . $id ) ) {
            $this->db->delete_item( $id );
        }
        wp_redirect( admin_url( 'admin.php?page=uz-bookshelf-items&msg=deleted' ) );
        exit;
    }

    // =========================================================================
    // Articles
    // =========================================================================

    // =========================================================================
    // uz_article Metaboxes
    // =========================================================================

    public function register_article_metaboxes() {
        add_meta_box(
            'uz_article_shelf',
            '棚 (Shelf)',
            array( $this, 'render_shelf_metabox' ),
            'post',
            'side',
            'high'
        );

        add_meta_box(
            'uz_article_related_items',
            '関連アイテム (Related Books)',
            array( $this, 'render_related_items_metabox' ),
            'post',
            'normal',
            'default'
        );
    }

    public function render_shelf_metabox( $post ) {
        $current_shelf = get_post_meta( $post->ID, '_uz_shelf', true );
        $shelves = $this->db->get_shelves();
        wp_nonce_field( 'uz_article_shelf_save', 'uz_article_shelf_nonce' );
        ?>
        <select name="_uz_shelf" style="width:100%;">
            <option value="">なし</option>
            <?php foreach ( $shelves as $s ) : ?>
                <option value="<?php echo esc_attr( $s['id'] ); ?>" <?php selected( $current_shelf, $s['id'] ); ?>>
                    <?php echo esc_html( $s['title'] ); ?> (<?php echo esc_html( $s['id'] ); ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public function render_related_items_metabox( $post ) {
        $related_items = $this->db->get_items_by_article( $post->post_name );
        if ( empty( $related_items ) ) {
            echo '<p>この記事に紐付けられたアイテムはありません。</p>';
        } else {
            ?>
            <table class="widefat striped">
                <thead><tr><th width="50">Cover</th><th><?php esc_html_e( 'Title', 'uz-bookshelf' ); ?></th><th><?php esc_html_e( 'Author', 'uz-bookshelf' ); ?></th><th><?php esc_html_e( 'Shelf', 'uz-bookshelf' ); ?></th><th><?php esc_html_e( 'Actions', 'uz-bookshelf' ); ?></th></tr></thead>
                <tbody>
                    <?php foreach ( $related_items as $ri ) : ?>
                    <tr>
                        <td>
                            <?php if ( $ri['cover_url'] ) : ?>
                                <img src="<?php echo esc_url( $ri['cover_url'] ); ?>" style="width:35px;height:auto;" />
                            <?php else : ?>
                                <span style="color:#999;">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $ri['title'] ); ?></td>
                        <td><?php echo esc_html( $ri['author'] ); ?></td>
                        <td><code><?php echo esc_html( $ri['shelf_id'] ); ?></code></td>
                        <td><a href="<?php echo esc_url( admin_url( 'admin.php?page=uz-bookshelf-items&action=edit&id=' . $ri['id'] ) ); ?>">Edit</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
        }
        echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=uz-bookshelf-items&action=add&article_id=' . urlencode( $post->post_name ) ) ) . '" class="button">+ Add Item to This Article</a></p>';
    }

    public function save_article_metabox( $post_id ) {
        if ( ! isset( $_POST['uz_article_shelf_nonce'] ) || ! wp_verify_nonce( $_POST['uz_article_shelf_nonce'], 'uz_article_shelf_save' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( get_post_type( $post_id ) !== 'post' ) {
            return;
        }
        if ( isset( $_POST['_uz_shelf'] ) ) {
            $shelf = sanitize_text_field( $_POST['_uz_shelf'] );
            update_post_meta( $post_id, '_uz_shelf', $shelf );
            if ( ! empty( $shelf ) ) {
                update_post_meta( $post_id, '_uz_article', '1' );
            }
        }
    }

    // =========================================================================
    // Articles (legacy page — redirects to CPT)
    // =========================================================================

    public function page_articles() {
        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';

        if ( $action === 'edit' || $action === 'add' ) {
            $this->page_article_form();
            return;
        }

        if ( $action === 'delete' ) {
            $this->handle_article_delete();
            return;
        }

        $articles = $this->db->get_articles();
        ?>
        <div class="wrap">
            <h1>
                Articles
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=uz-bookshelf-articles&action=add' ) ); ?>" class="page-title-action">Add New</a>
            </h1>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ID', 'uz-bookshelf' ); ?></th>
                        <th><?php esc_html_e( 'Title', 'uz-bookshelf' ); ?></th>
                        <th><?php esc_html_e( 'Date', 'uz-bookshelf' ); ?></th>
                        <th><?php esc_html_e( 'Shelf', 'uz-bookshelf' ); ?></th>
                        <th><?php esc_html_e( 'Categories', 'uz-bookshelf' ); ?></th>
                        <th><?php esc_html_e( 'Products', 'uz-bookshelf' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'uz-bookshelf' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $articles ) ) : ?>
                        <tr><td colspan="7">No articles found.</td></tr>
                    <?php else : ?>
                        <?php foreach ( $articles as $a ) : ?>
                        <tr>
                            <td><code><?php echo esc_html( $a['id'] ); ?></code></td>
                            <td>
                                <strong><?php echo esc_html( $a['title'] ); ?></strong>
                                <?php if ( $a['url'] ) : ?>
                                    <br><a href="<?php echo esc_url( $a['url'] ); ?>" target="_blank" rel="noopener">View &rarr;</a>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( $a['date'] ); ?></td>
                            <td><code><?php echo esc_html( $a['shelf'] ); ?></code></td>
                            <td>
                                <?php
                                $cats = json_decode( $a['categories'], true );
                                echo is_array( $cats ) ? esc_html( implode( ', ', $cats ) ) : '-';
                                ?>
                            </td>
                            <td><?php echo esc_html( $a['product_count'] ); ?></td>
                            <td>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=uz-bookshelf-articles&action=edit&id=' . urlencode( $a['id'] ) ) ); ?>">Edit</a> |
                                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=uz-bookshelf-articles&action=delete&id=' . urlencode( $a['id'] ) ), 'uz_delete_article_' . $a['id'] ) ); ?>" onclick="return confirm('Delete?');" style="color:#b32d2e;">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function page_article_form() {
        $id      = isset( $_GET['id'] ) ? sanitize_text_field( $_GET['id'] ) : '';
        $article = $id ? $this->db->get_article( $id ) : null;

        if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['uz_article_nonce'] ) ) {
            if ( wp_verify_nonce( $_POST['uz_article_nonce'], 'uz_save_article' ) ) {
                $cats_raw = isset( $_POST['categories'] ) ? sanitize_text_field( $_POST['categories'] ) : '';
                $cats     = array_filter( array_map( 'trim', explode( ',', $cats_raw ) ) );

                $article_id = sanitize_text_field( $_POST['article_id'] ?? $id );
                $data = array(
                    'title'         => sanitize_text_field( $_POST['title'] ?? '' ),
                    'date'          => sanitize_text_field( $_POST['date'] ?? '' ),
                    'categories'    => wp_json_encode( $cats ),
                    'shelf'         => sanitize_text_field( $_POST['shelf'] ?? '' ),
                    'product_count' => absint( $_POST['product_count'] ?? 0 ),
                    'url'           => esc_url_raw( $_POST['url'] ?? '' ),
                );

                $this->db->upsert_article( $article_id, $data );
                echo '<div class="notice notice-success"><p>Article saved.</p></div>';
                $article = $this->db->get_article( $article_id );
            }
        }

        $cats_str = '';
        if ( $article && $article['categories'] ) {
            $decoded = json_decode( $article['categories'], true );
            if ( is_array( $decoded ) ) {
                $cats_str = implode( ', ', $decoded );
            }
        }

        $shelves = $this->db->get_shelves();
        ?>
        <div class="wrap">
            <h1><?php echo $id ? 'Edit Article' : 'Add New Article'; ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=uz-bookshelf-articles' ) ); ?>">&larr; Back to list</a>

            <form method="post" style="max-width:700px;">
                <?php wp_nonce_field( 'uz_save_article', 'uz_article_nonce' ); ?>

                <table class="form-table">
                    <tr>
                        <th><label for="article_id"><?php esc_html_e( 'Article ID', 'uz-bookshelf' ); ?></label></th>
                        <td><input type="text" name="article_id" id="article_id" class="regular-text" value="<?php echo esc_attr( $article['id'] ?? '' ); ?>" <?php echo $id ? 'readonly' : ''; ?> required /></td>
                    </tr>
                    <tr>
                        <th><label for="title"><?php esc_html_e( 'Title', 'uz-bookshelf' ); ?></label></th>
                        <td><input type="text" name="title" id="title" class="large-text" value="<?php echo esc_attr( $article['title'] ?? '' ); ?>" required /></td>
                    </tr>
                    <tr>
                        <th><label for="date"><?php esc_html_e( 'Date', 'uz-bookshelf' ); ?></label></th>
                        <td><input type="text" name="date" id="date" class="regular-text" value="<?php echo esc_attr( $article['date'] ?? '' ); ?>" placeholder="MM/DD/YYYY" /></td>
                    </tr>
                    <tr>
                        <th><label for="shelf"><?php esc_html_e( 'Shelf', 'uz-bookshelf' ); ?></label></th>
                        <td>
                            <select name="shelf" id="shelf">
                                <option value="">None</option>
                                <?php foreach ( $shelves as $s ) : ?>
                                    <option value="<?php echo esc_attr( $s['id'] ); ?>" <?php selected( $article['shelf'] ?? '', $s['id'] ); ?>>
                                        <?php echo esc_html( $s['title'] ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="categories"><?php esc_html_e( 'Categories (comma-separated)', 'uz-bookshelf' ); ?></label></th>
                        <td><input type="text" name="categories" id="categories" class="large-text" value="<?php echo esc_attr( $cats_str ); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="product_count"><?php esc_html_e( 'Product Count', 'uz-bookshelf' ); ?></label></th>
                        <td><input type="number" name="product_count" id="product_count" style="width:80px;" value="<?php echo esc_attr( $article['product_count'] ?? 0 ); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="url"><?php esc_html_e( 'URL', 'uz-bookshelf' ); ?></label></th>
                        <td><input type="url" name="url" id="url" class="large-text" value="<?php echo esc_attr( $article['url'] ?? '' ); ?>" /></td>
                    </tr>
                </table>

                <?php submit_button( $id ? 'Update Article' : 'Add Article' ); ?>
            </form>

            <?php if ( $id && $article ) : ?>
            <hr />
            <h2><?php esc_html_e( 'Related Items', 'uz-bookshelf' ); ?></h2>
            <?php
            $related_items = $this->db->get_items_by_article( $id );
            if ( empty( $related_items ) ) :
            ?>
                <p>No items linked to this article yet.</p>
            <?php else : ?>
                <table class="widefat striped" style="max-width:700px;">
                    <thead><tr><th width="50">Cover</th><th><?php esc_html_e( 'Title', 'uz-bookshelf' ); ?></th><th><?php esc_html_e( 'Author', 'uz-bookshelf' ); ?></th><th><?php esc_html_e( 'Shelf', 'uz-bookshelf' ); ?></th><th><?php esc_html_e( 'Actions', 'uz-bookshelf' ); ?></th></tr></thead>
                    <tbody>
                        <?php foreach ( $related_items as $ri ) : ?>
                        <tr>
                            <td>
                                <?php if ( $ri['cover_url'] ) : ?>
                                    <img src="<?php echo esc_url( $ri['cover_url'] ); ?>" style="width:35px;height:auto;" />
                                <?php else : ?>
                                    <span style="color:#999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( $ri['title'] ); ?></td>
                            <td><?php echo esc_html( $ri['author'] ); ?></td>
                            <td><code><?php echo esc_html( $ri['shelf_id'] ); ?></code></td>
                            <td><a href="<?php echo esc_url( admin_url( 'admin.php?page=uz-bookshelf-items&action=edit&id=' . $ri['id'] ) ); ?>">Edit</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=uz-bookshelf-items&action=add&article_id=' . urlencode( $id ) ) ); ?>" class="button">
                    + Add Item to This Article
                </a>
            </p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function handle_article_delete() {
        $id = isset( $_GET['id'] ) ? sanitize_text_field( $_GET['id'] ) : '';
        if ( $id && check_admin_referer( 'uz_delete_article_' . $id ) ) {
            $this->db->delete_article( $id );
        }
        wp_redirect( admin_url( 'admin.php?page=uz-bookshelf-articles' ) );
        exit;
    }

    // =========================================================================
    // Rakuten Books
    // =========================================================================

    public function page_rakuten() {
        $books = $this->db->get_rakuten_books();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Rakuten Books', 'uz-bookshelf' ); ?></h1>
            <p><?php echo esc_html( sprintf( __( '%d books in database.', 'uz-bookshelf' ), count( $books ) ) ); ?></p>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ID', 'uz-bookshelf' ); ?></th>
                        <th><?php esc_html_e( 'Cover', 'uz-bookshelf' ); ?></th>
                        <th><?php esc_html_e( 'Title', 'uz-bookshelf' ); ?></th>
                        <th><?php esc_html_e( 'Author', 'uz-bookshelf' ); ?></th>
                        <th><?php esc_html_e( 'Genre', 'uz-bookshelf' ); ?></th>
                        <th><?php esc_html_e( 'Price', 'uz-bookshelf' ); ?></th>
                        <th><?php esc_html_e( 'Rating', 'uz-bookshelf' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $books ) ) : ?>
                        <tr><td colspan="7"><?php esc_html_e( 'No rakuten books found.', 'uz-bookshelf' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( array_slice( $books, 0, 100 ) as $b ) : ?>
                        <tr>
                            <td><?php echo esc_html( $b['id'] ); ?></td>
                            <td>
                                <?php if ( $b['small_image_url'] ) : ?>
                                    <img src="<?php echo esc_url( $b['small_image_url'] ); ?>" style="width:30px;height:auto;" />
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( mb_substr( $b['title'], 0, 40 ) ); ?></td>
                            <td><?php echo esc_html( $b['author'] ); ?></td>
                            <td><code><?php echo esc_html( $b['genre_id'] ); ?></code></td>
                            <td>&yen;<?php echo number_format( $b['item_price'] ); ?></td>
                            <td><?php echo esc_html( $b['review_average'] ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if ( count( $books ) > 100 ) : ?>
                <p><em><?php echo esc_html( sprintf( __( 'Showing first 100 of %d books.', 'uz-bookshelf' ), count( $books ) ) ); ?></em></p>
            <?php endif; ?>
        </div>
        <?php
    }

    // =========================================================================
    // Import / Export
    // =========================================================================

    public function page_import() {
        $message = '';

        if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['uz_import_nonce'] ) ) {
            if ( wp_verify_nonce( $_POST['uz_import_nonce'], 'uz_import_data' ) ) {
                $import_type = sanitize_text_field( $_POST['import_type'] ?? '' );

                // Load bundled sample data
                if ( $import_type === 'load_sample' ) {
                    $result = $this->db->load_sample_data( true );
                    if ( is_wp_error( $result ) ) {
                        $message = '<div class="notice notice-error"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
                    } else {
                        $message = sprintf(
                            '<div class="notice notice-success"><p>' . esc_html( sprintf( __( 'サンプルデータを読み込みました: %d棚, %dアイテム, %d記事, %d楽天ブックス', 'uz-bookshelf' ), $result['shelves'], $result['items'], $result['articles'], $result['rakuten_books'] ) ) . '</p></div>',
                            $result['shelves'], $result['items'], $result['articles'], $result['rakuten_books']
                        );
                    }
                }

                // Start Fresh — clear all data
                if ( $import_type === 'start_fresh' ) {
                    $this->db->clear_all_data();
                    $message = '<div class="notice notice-success"><p>' . esc_html__( '全データをクリアしました。1から作成できます。', 'uz-bookshelf' ) . '</p></div>';
                }

                if ( $import_type === 'json_upload' && ! empty( $_FILES['json_file']['tmp_name'] ) ) {
                    $json_content = file_get_contents( $_FILES['json_file']['tmp_name'] );
                    $data = json_decode( $json_content, true );

                    if ( $data ) {
                        $result = $this->db->import_from_json( $data );
                        if ( is_wp_error( $result ) ) {
                            $message = '<div class="notice notice-error"><p>' . esc_html( sprintf( __( 'Import failed: %s', 'uz-bookshelf' ), $result->get_error_message() ) ) . '</p></div>';
                        } else {
                            $message = sprintf(
                                '<div class="notice notice-success"><p>' . esc_html( sprintf( __( 'Import complete: %d shelves, %d items, %d articles.', 'uz-bookshelf' ), $result['shelves'], $result['items'], $result['articles'] ) ) . '</p></div>',
                                $result['shelves'], $result['items'], $result['articles']
                            );
                        }
                    } else {
                        $message = '<div class="notice notice-error"><p>' . esc_html__( 'Invalid JSON file.', 'uz-bookshelf' ) . '</p></div>';
                    }
                }

                // Import MT export file as uz_article posts
                if ( $import_type === 'mt_import' && ! empty( $_FILES['mt_file']['tmp_name'] ) ) {
                    $export_content = file_get_contents( $_FILES['mt_file']['tmp_name'] );
                    if ( $export_content ) {
                        // Load shelf/category maps from bundled JSON
                        $json_data = null;
                        $shelf_file = UZ_BOOKSHELF_PATH . 'data/uz-shelf-data.json';
                        if ( file_exists( $shelf_file ) ) {
                            $json_data = json_decode( file_get_contents( $shelf_file ), true );
                        }
                        $result = UZ_Bookshelf_Importer::run_import( $export_content, $json_data );
                        $message = sprintf(
                            '<div class="notice notice-success"><p>' . esc_html( sprintf( __( '記事インポート完了: %d件作成, %d件更新, %dスキップ', 'uz-bookshelf' ), $result['created'], $result['updated'], $result['skipped'] ) ) . '</p></div>',
                            $result['created'], $result['updated'], $result['skipped']
                        );
                        if ( ! empty( $result['errors'] ) ) {
                            $message .= '<div class="notice notice-warning"><p>' . esc_html( sprintf( __( 'エラー: %s', 'uz-bookshelf' ), implode( ', ', $result['errors'] ) ) ) . '</p></div>';
                        }
                    } else {
                        $message = '<div class="notice notice-error"><p>' . esc_html__( 'ファイルの読み込みに失敗しました。', 'uz-bookshelf' ) . '</p></div>';
                    }
                }

                if ( $import_type === 'rakuten_upload' && ! empty( $_FILES['rakuten_file']['tmp_name'] ) ) {
                    $genre_id = sanitize_text_field( $_POST['rakuten_genre_id'] ?? '' );
                    if ( $genre_id ) {
                        $json_content = file_get_contents( $_FILES['rakuten_file']['tmp_name'] );
                        $data = json_decode( $json_content, true );

                        if ( $data && ! empty( $data['Items'] ) ) {
                            $count = 0;
                            foreach ( $data['Items'] as $i => $entry ) {
                                $item = isset( $entry['Item'] ) ? $entry['Item'] : array();
                                $this->db->insert_rakuten_book( array(
                                    'genre_id'         => $genre_id,
                                    'isbn'             => $item['isbn'] ?? '',
                                    'title'            => $item['title'] ?? '',
                                    'author'           => $item['author'] ?? '',
                                    'publisher'        => $item['publisherName'] ?? '',
                                    'item_price'       => $item['itemPrice'] ?? 0,
                                    'item_url'         => $item['itemUrl'] ?? '',
                                    'large_image_url'  => $item['largeImageUrl'] ?? '',
                                    'medium_image_url' => $item['mediumImageUrl'] ?? '',
                                    'small_image_url'  => $item['smallImageUrl'] ?? '',
                                    'item_caption'     => $item['itemCaption'] ?? '',
                                    'books_genre_id'   => $item['booksGenreId'] ?? '',
                                    'sales_date'       => $item['salesDate'] ?? '',
                                    'review_average'   => $item['reviewAverage'] ?? '',
                                    'review_count'     => $item['reviewCount'] ?? 0,
                                    'availability'     => $item['availability'] ?? '',
                                    'affiliate_url'    => $item['affiliateUrl'] ?? '',
                                    'sort_order'       => $i,
                                ) );
                                $count++;
                            }
                            $message = sprintf(
                                '<div class="notice notice-success"><p>' . esc_html( sprintf( __( 'Imported %d Rakuten books for genre %s.', 'uz-bookshelf' ), $count, esc_html( $genre_id ) ) ) . '</p></div>',
                                $count, esc_html( $genre_id )
                            );
                        } else {
                            $message = '<div class="notice notice-error"><p>' . esc_html__( 'Invalid Rakuten JSON file.', 'uz-bookshelf' ) . '</p></div>';
                        }
                    }
                }
            }
        }

        $stats = $this->db->get_stats();
        $has_data = ( $stats['items'] > 0 || $stats['rakuten_books'] > 0 );

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Import / Export', 'uz-bookshelf' ); ?></h1>
            <?php echo $message; ?>

            <!-- ===== Quick Actions ===== -->
            <div style="display:flex;gap:20px;margin:20px 0;flex-wrap:wrap;">

                <div style="flex:1;min-width:300px;background:#f0f0f1;border-left:4px solid #2271b1;padding:20px;">
                    <h2 style="margin-top:0;"><?php esc_html_e( '📚 サンプルデータを読み込む', 'uz-bookshelf' ); ?></h2>
                    <p><?php esc_html_e( '全書籍（6棚・212アイテム）、全記事（48件）、楽天ブックス（90冊）を一括読み込み。', 'uz-bookshelf' ); ?><br>
                    <?php esc_html_e( 'カバー画像・アフィリエイトリンクもすべて含まれます。', 'uz-bookshelf' ); ?></p>
                    <form method="post">
                        <?php wp_nonce_field( 'uz_import_data', 'uz_import_nonce' ); ?>
                        <input type="hidden" name="import_type" value="load_sample" />
                        <?php submit_button( 'サンプルデータを読み込む', 'primary', 'submit', false ); ?>
                    </form>
                </div>

                <div style="flex:1;min-width:300px;background:#f0f0f1;border-left:4px solid #d63638;padding:20px;">
                    <h2 style="margin-top:0;"><?php esc_html_e( '🆕 1から作る（全データクリア）', 'uz-bookshelf' ); ?></h2>
                    <p><?php esc_html_e( '全データを削除して空の状態から始めます。', 'uz-bookshelf' ); ?><br>
                    <?php esc_html_e( '棚の作成、本の追加、記事の紐付けを管理画面から行えます。', 'uz-bookshelf' ); ?></p>
                    <?php if ( $has_data ) : ?>
                    <form method="post" onsubmit="return confirm('<?php echo esc_js( __( '本当に全データを削除しますか？この操作は取り消せません。', 'uz-bookshelf' ) ); ?>');">
                        <?php wp_nonce_field( 'uz_import_data', 'uz_import_nonce' ); ?>
                        <input type="hidden" name="import_type" value="start_fresh" />
                        <?php submit_button( '全データをクリアして1から作る', 'delete', 'submit', false ); ?>
                    </form>
                    <?php else : ?>
                    <p><strong>現在データは空です。</strong> 上の「Shelf Items」「Articles」メニューから追加を始められます。</p>
                    <?php endif; ?>
                </div>

            </div>

            <!-- ===== Current Status ===== -->
            <div style="background:#fff;border:1px solid #c3c4c7;padding:15px;margin:20px 0;">
                <h3 style="margin-top:0;">現在のデータ状況</h3>
                <table>
                    <tr><td style="padding:2px 15px 2px 0;"><strong>棚数:</strong></td><td><?php echo esc_html( $stats['shelves'] ); ?></td></tr>
                    <tr><td style="padding:2px 15px 2px 0;"><strong>アイテム数:</strong></td><td><?php echo esc_html( $stats['items'] ); ?></td></tr>
                    <tr><td style="padding:2px 15px 2px 0;"><strong>記事数:</strong></td><td><?php echo esc_html( $stats['articles'] ); ?></td></tr>
                    <tr><td style="padding:2px 15px 2px 0;"><strong>楽天ブックス:</strong></td><td><?php echo esc_html( $stats['rakuten_books'] ); ?></td></tr>
                </table>
            </div>

            <hr />

            <h2><?php esc_html_e( 'JSONファイルからインポート', 'uz-bookshelf' ); ?></h2>

            <h3>uz-shelf-data.json</h3>
            <p><code>uz-shelf-data.json</code> をアップロードして棚・アイテム・記事を読み込みます。</p>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'uz_import_data', 'uz_import_nonce' ); ?>
                <input type="hidden" name="import_type" value="json_upload" />
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'JSON File', 'uz-bookshelf' ); ?></th>
                        <td><input type="file" name="json_file" accept=".json" required /></td>
                    </tr>
                </table>
                <?php submit_button( __( 'Import Shelf Data', 'uz-bookshelf' ) ); ?>
            </form>

            <hr />

            <h2><?php esc_html_e( '記事インポート（Movable Type形式）', 'uz-bookshelf' ); ?></h2>
            <p>Movable Typeエクスポートファイル（<code>.txt</code>）をアップロードして、WordPressの投稿として取り込みます。<br>
            アフィリエイトリンク（msmaflink等）もそのまま保持されます。同じスラッグの記事があれば更新（upsert）します。</p>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'uz_import_data', 'uz_import_nonce' ); ?>
                <input type="hidden" name="import_type" value="mt_import" />
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'エクスポートファイル', 'uz-bookshelf' ); ?></th>
                        <td><input type="file" name="mt_file" accept=".txt,.export" required /></td>
                    </tr>
                </table>
                <?php submit_button( __( '記事をインポート', 'uz-bookshelf' ) ); ?>
            </form>

            <hr />

            <h3><?php esc_html_e( '楽天ブックス JSON', 'uz-bookshelf' ); ?></h3>
            <p>楽天ブックスAPI レスポンスファイル（例: <code>001005.json</code>）をアップロード。</p>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'uz_import_data', 'uz_import_nonce' ); ?>
                <input type="hidden" name="import_type" value="rakuten_upload" />
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Genre ID', 'uz-bookshelf' ); ?></th>
                        <td>
                            <select name="rakuten_genre_id">
                                <option value="001005">001005 (IT・テクノロジー)</option>
                                <option value="001006">001006 (ビジネス)</option>
                                <option value="001010">001010 (カルチャー)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'JSON File', 'uz-bookshelf' ); ?></th>
                        <td><input type="file" name="rakuten_file" accept=".json" required /></td>
                    </tr>
                </table>
                <?php submit_button( __( 'Import Rakuten Books', 'uz-bookshelf' ) ); ?>
            </form>

            <hr />

            <h2><?php esc_html_e( 'エクスポート', 'uz-bookshelf' ); ?></h2>
            <?php
            // Handle export download
            if ( isset( $_POST['uz_export_action'] ) && wp_verify_nonce( $_POST['uz_export_nonce'] ?? '', 'uz_export_data' ) ) {
                $export_type = sanitize_text_field( $_POST['uz_export_action'] );
                $data = null;
                $filename = 'uz-bookshelf-export.json';

                if ( $export_type === 'shelves' ) {
                    $data = $this->db->export_shelf_data();
                    $filename = 'uz-shelf-data-' . gmdate( 'Y-m-d' ) . '.json';
                } elseif ( $export_type === 'rakuten' ) {
                    $genre = sanitize_text_field( $_POST['export_genre_id'] ?? '001005' );
                    $data = $this->db->export_rakuten_data( $genre );
                    $filename = 'uz-rakuten-' . $genre . '-' . gmdate( 'Y-m-d' ) . '.json';
                }

                if ( $data ) {
                    header( 'Content-Type: application/json; charset=utf-8' );
                    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
                    echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
                    exit;
                }
            }
            ?>
            <form method="post">
                <?php wp_nonce_field( 'uz_export_data', 'uz_export_nonce' ); ?>
                <table class="form-table">
                    <tr>
                        <th>棚データ (JSON)</th>
                        <td>
                            <button type="submit" name="uz_export_action" value="shelves" class="button">棚 + 記事データをダウンロード</button>
                            <p class="description">全ての棚・アイテム・記事データをJSONファイルとしてエクスポート</p>
                        </td>
                    </tr>
                    <tr>
                        <th>楽天ブックス (JSON)</th>
                        <td>
                            <select name="export_genre_id" style="vertical-align:middle;">
                                <option value="001005">001005 (IT・テクノロジー)</option>
                                <option value="001006">001006 (ビジネス)</option>
                                <option value="001010">001010 (カルチャー)</option>
                            </select>
                            <button type="submit" name="uz_export_action" value="rakuten" class="button" style="margin-left:8px;">ダウンロード</button>
                        </td>
                    </tr>
                </table>
            </form>

            <h3><?php esc_html_e( 'REST API', 'uz-bookshelf' ); ?></h3>
            <ul>
                <li><code>GET /wp-json/uz-bookshelf/v1/shelves</code> — 全棚データ</li>
                <li><code>GET /wp-json/uz-bookshelf/v1/articles</code> — 全記事</li>
                <li><code>GET /wp-json/uz-bookshelf/v1/rakuten/{genre_id}</code> — 楽天ブックス</li>
            </ul>
        </div>
        <?php
    }

    // =========================================================================
    // Settings
    // =========================================================================

    public function page_settings() {
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['uz_settings_nonce'] ) ) {
            if ( wp_verify_nonce( $_POST['uz_settings_nonce'], 'uz_save_settings' ) ) {
                update_option( 'uz_bookshelf_rakuten_app_id', sanitize_text_field( $_POST['rakuten_app_id'] ?? '' ) );
                update_option( 'uz_bookshelf_rakuten_affiliate_id', sanitize_text_field( $_POST['rakuten_affiliate_id'] ?? '' ) );
                update_option( 'uz_bookshelf_amazon_tag', sanitize_text_field( $_POST['amazon_tag'] ?? '' ) );
                update_option( 'uz_bookshelf_shelf_color', sanitize_hex_color( $_POST['shelf_color'] ?? '#5a3d25' ) );
                update_option( 'uz_bookshelf_shelf_rows', absint( $_POST['shelf_rows'] ?? 2 ) );
                update_option( 'uz_bookshelf_load_sample_data', ! empty( $_POST['load_sample_data'] ) ? '1' : '0' );
                echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'uz-bookshelf' ) . '</p></div>';
            }
        }

        $rakuten_app_id       = get_option( 'uz_bookshelf_rakuten_app_id', '' );
        $rakuten_affiliate_id = get_option( 'uz_bookshelf_rakuten_affiliate_id', '' );
        $amazon_tag           = get_option( 'uz_bookshelf_amazon_tag', '' );
        $shelf_color          = get_option( 'uz_bookshelf_shelf_color', '#5a3d25' );
        $shelf_rows           = get_option( 'uz_bookshelf_shelf_rows', 2 );
        $load_sample_data     = get_option( 'uz_bookshelf_load_sample_data', '1' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'UZ Bookshelf Settings', 'uz-bookshelf' ); ?></h1>

            <form method="post" style="max-width:700px;">
                <?php wp_nonce_field( 'uz_save_settings', 'uz_settings_nonce' ); ?>

                <h2><?php esc_html_e( 'Affiliate Settings', 'uz-bookshelf' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="rakuten_app_id"><?php esc_html_e( 'Rakuten Application ID', 'uz-bookshelf' ); ?></label></th>
                        <td>
                            <input type="text" name="rakuten_app_id" id="rakuten_app_id" class="regular-text" value="<?php echo esc_attr( $rakuten_app_id ); ?>" />
                            <p class="description">Rakuten Books API を利用するためのアプリケーションID</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="rakuten_affiliate_id"><?php esc_html_e( 'Rakuten Affiliate ID', 'uz-bookshelf' ); ?></label></th>
                        <td>
                            <input type="text" name="rakuten_affiliate_id" id="rakuten_affiliate_id" class="regular-text" value="<?php echo esc_attr( $rakuten_affiliate_id ); ?>" />
                            <p class="description">楽天リンクに自動付与されるアフィリエイトID</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="amazon_tag"><?php esc_html_e( 'Amazon Associate Tag', 'uz-bookshelf' ); ?></label></th>
                        <td>
                            <input type="text" name="amazon_tag" id="amazon_tag" class="regular-text" value="<?php echo esc_attr( $amazon_tag ); ?>" />
                            <p class="description">AmazonリンクにAssociate Tagを自動付与</p>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'Display Settings', 'uz-bookshelf' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="shelf_color"><?php esc_html_e( 'Shelf Wood Color', 'uz-bookshelf' ); ?></label></th>
                        <td>
                            <input type="color" name="shelf_color" id="shelf_color" value="<?php echo esc_attr( $shelf_color ); ?>" />
                            <span style="margin-left:8px;"><?php echo esc_html( $shelf_color ); ?></span>
                            <p class="description">本棚の木目ベースカラー</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="shelf_rows"><?php esc_html_e( 'Shelf Rows', 'uz-bookshelf' ); ?></label></th>
                        <td>
                            <input type="number" name="shelf_rows" id="shelf_rows" min="1" max="10" value="<?php echo esc_attr( $shelf_rows ); ?>" style="width:80px;" />
                            <p class="description">1棚あたりの段数（デフォルト: 2）</p>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'Data Settings', 'uz-bookshelf' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Sample Data', 'uz-bookshelf' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="load_sample_data" value="1" <?php checked( $load_sample_data, '1' ); ?> />
                                空のDBにサンプルデータを自動ロードする
                            </label>
                            <p class="description">無効にすると、プラグイン有効化時にサンプルデータが挿入されません</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button( __( 'Save Settings', 'uz-bookshelf' ) ); ?>
            </form>
        </div>
        <?php
    }
}
