<?php
/**
 * UZ Bookshelf - Database Layer
 *
 * Custom tables via dbDelta() for shelves and items (unified).
 * Items are distinguished by `source` column ('uz' or 'rakuten').
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UZ_Bookshelf_DB {

    /** @var string Table prefix (wp_ + uz_) */
    private $prefix;

    /** @var string Plugin DB version */
    const DB_VERSION = '2.0.0';

    /** @var string Option key for DB version tracking */
    const DB_VERSION_OPTION = 'uz_bookshelf_db_version';

    public function __construct() {
        global $wpdb;
        $this->prefix = $wpdb->prefix . 'uz_';
    }

    /**
     * Table name helpers
     */
    public function table( $name ) {
        return $this->prefix . $name;
    }

    public function shelves_table() {
        return $this->table( 'shelves' );
    }

    public function items_table() {
        return $this->table( 'items' );
    }

    /**
     * Create or update tables using dbDelta
     */
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // --- shelves ---
        $sql_shelves = "CREATE TABLE {$this->shelves_table()} (
            id VARCHAR(64) NOT NULL,
            title VARCHAR(255) NOT NULL,
            icon VARCHAR(32) DEFAULT '',
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        // --- unified items (source: 'uz' or 'rakuten') ---
        $sql_items = "CREATE TABLE {$this->items_table()} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source VARCHAR(16) NOT NULL DEFAULT 'uz',
            item_id VARCHAR(128) DEFAULT '',
            shelf_id VARCHAR(64) DEFAULT '',
            title VARCHAR(255) NOT NULL,
            full_title TEXT NOT NULL,
            author VARCHAR(255) DEFAULT '',
            full_author VARCHAR(255) DEFAULT '',
            cover_url TEXT NOT NULL,
            amazon_url TEXT NOT NULL,
            rakuten_url TEXT NOT NULL,
            affiliate_url TEXT NOT NULL,
            article_id VARCHAR(128) DEFAULT '',
            article_title VARCHAR(255) DEFAULT '',
            comment TEXT NOT NULL,
            tags TEXT NOT NULL,
            type VARCHAR(32) DEFAULT 'product',
            format VARCHAR(32) DEFAULT 'standard',
            width INT DEFAULT 128,
            height INT DEFAULT 182,
            genre_id VARCHAR(32) DEFAULT '',
            isbn VARCHAR(32) DEFAULT '',
            publisher VARCHAR(255) DEFAULT '',
            item_price INT DEFAULT 0,
            item_url TEXT NOT NULL,
            large_image_url TEXT NOT NULL,
            medium_image_url TEXT NOT NULL,
            small_image_url TEXT NOT NULL,
            item_caption TEXT NOT NULL,
            books_genre_id VARCHAR(64) DEFAULT '',
            sales_date VARCHAR(64) DEFAULT '',
            review_average VARCHAR(8) DEFAULT '',
            review_count INT DEFAULT 0,
            availability VARCHAR(32) DEFAULT '',
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_source (source),
            KEY idx_shelf_id (shelf_id),
            KEY idx_article_id (article_id),
            KEY idx_genre_id (genre_id)
        ) $charset_collate;";

        dbDelta( $sql_shelves );
        dbDelta( $sql_items );

        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }

    /**
     * Drop all plugin tables (uninstall)
     */
    public function drop_tables() {
        global $wpdb;
        $tables = array(
            $this->items_table(),
            $this->shelves_table(),
        );
        foreach ( $tables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS $table" );
        }
        delete_option( self::DB_VERSION_OPTION );
    }

    /**
     * Check if tables need upgrade
     */
    public function maybe_upgrade() {
        $installed_version = get_option( self::DB_VERSION_OPTION, '0' );
        if ( version_compare( $installed_version, self::DB_VERSION, '<' ) ) {
            $this->create_tables();
            if ( $installed_version !== '0' && version_compare( $installed_version, '2.0.0', '<' ) ) {
                $this->migrate_v2();
            }
        }

        // Auto-load sample data if tables exist but are empty
        // (handles DB volume reset while WP still considers plugin active)
        if ( $this->is_empty() ) {
            $this->load_sample_data( true );
        }
    }

    /**
     * Migrate from v1 (separate shelf_items + rakuten_books) to v2 (unified items table)
     */
    private function migrate_v2() {
        global $wpdb;
        $old_items   = $this->prefix . 'shelf_items';
        $old_rakuten = $this->prefix . 'rakuten_books';
        $new_table   = $this->items_table();

        // Skip if new table already has data
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $new_table" );
        if ( $count > 0 ) {
            return;
        }

        // Migrate UZ items
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$old_items'" ) ) {
            $wpdb->query(
                "INSERT INTO $new_table
                    (source, item_id, shelf_id, title, full_title, author, full_author,
                     cover_url, amazon_url, rakuten_url, affiliate_url, article_id,
                     article_title, comment, tags, type, format, width, height,
                     sort_order, created_at, updated_at)
                 SELECT 'uz', item_id, shelf_id, title, full_title, author, full_author,
                     cover_url, amazon_url, rakuten_url, affiliate_url, article_id,
                     article_title, comment, tags, type, format, width, height,
                     sort_order, created_at, updated_at
                 FROM $old_items"
            );
            $wpdb->query( "DROP TABLE IF EXISTS $old_items" );
        }

        // Migrate Rakuten books
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$old_rakuten'" ) ) {
            $wpdb->query(
                "INSERT INTO $new_table
                    (source, title, author, affiliate_url, genre_id, isbn, publisher,
                     item_price, item_url, large_image_url, medium_image_url,
                     small_image_url, item_caption, books_genre_id, sales_date,
                     review_average, review_count, availability, sort_order,
                     created_at, updated_at,
                     full_title, cover_url, amazon_url, rakuten_url, comment, tags)
                 SELECT 'rakuten', title, author, affiliate_url, genre_id, isbn, publisher,
                     item_price, item_url, large_image_url, medium_image_url,
                     small_image_url, item_caption, books_genre_id, sales_date,
                     review_average, review_count, availability, sort_order,
                     created_at, updated_at,
                     '', '', '', '', '', '[]'
                 FROM $old_rakuten"
            );
            $wpdb->query( "DROP TABLE IF EXISTS $old_rakuten" );
        }

        // Drop old articles table (now using WP posts)
        $old_articles = $this->prefix . 'articles';
        $wpdb->query( "DROP TABLE IF EXISTS $old_articles" );
    }

    // =========================================================================
    // CRUD: Shelves
    // =========================================================================

    public function get_shelves( $order_by = 'sort_order ASC' ) {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$this->shelves_table()} ORDER BY $order_by",
            ARRAY_A
        );
    }

    public function get_shelf( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->shelves_table()} WHERE id = %s", $id ),
            ARRAY_A
        );
    }

    public function upsert_shelf( $id, $data ) {
        global $wpdb;
        $existing = $this->get_shelf( $id );
        if ( $existing ) {
            $wpdb->update(
                $this->shelves_table(),
                $data,
                array( 'id' => $id )
            );
        } else {
            $data['id'] = $id;
            $wpdb->insert( $this->shelves_table(), $data );
        }
    }

    public function delete_shelf( $id ) {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$this->items_table()} WHERE source = 'uz' AND shelf_id = %s",
            $id
        ) );
        $wpdb->delete( $this->shelves_table(), array( 'id' => $id ) );
    }

    // =========================================================================
    // CRUD: Shelf Items
    // =========================================================================

    public function get_items( $shelf_id = null, $order_by = 'sort_order ASC' ) {
        global $wpdb;
        if ( $shelf_id ) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$this->items_table()} WHERE source = 'uz' AND shelf_id = %s ORDER BY $order_by",
                    $shelf_id
                ),
                ARRAY_A
            );
        }
        return $wpdb->get_results(
            "SELECT * FROM {$this->items_table()} WHERE source = 'uz' ORDER BY $order_by",
            ARRAY_A
        );
    }

    public function get_item( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->items_table()} WHERE id = %d", $id ),
            ARRAY_A
        );
    }

    public function get_items_by_article( $article_id ) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->items_table()} WHERE source = 'uz' AND article_id = %s ORDER BY sort_order ASC",
                $article_id
            ),
            ARRAY_A
        );
    }

    public function insert_item( $data ) {
        global $wpdb;
        if ( ! isset( $data['source'] ) ) {
            $data['source'] = 'uz';
        }
        // Ensure TEXT columns have a value (MySQL TEXT cannot have DEFAULT)
        $text_defaults = array(
            'full_title' => '', 'cover_url' => '', 'amazon_url' => '',
            'rakuten_url' => '', 'affiliate_url' => '', 'comment' => '', 'tags' => '[]',
            'item_url' => '', 'large_image_url' => '', 'medium_image_url' => '',
            'small_image_url' => '', 'item_caption' => '',
        );
        foreach ( $text_defaults as $col => $default ) {
            if ( ! isset( $data[ $col ] ) ) {
                $data[ $col ] = $default;
            }
        }
        $wpdb->insert( $this->items_table(), $data );
        return $wpdb->insert_id;
    }

    public function update_item( $id, $data ) {
        global $wpdb;
        $wpdb->update(
            $this->items_table(),
            $data,
            array( 'id' => $id )
        );
    }

    public function delete_item( $id ) {
        global $wpdb;
        $wpdb->delete( $this->items_table(), array( 'id' => $id ) );
    }

    public function count_items( $shelf_id = null ) {
        global $wpdb;
        if ( $shelf_id ) {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->items_table()} WHERE source = 'uz' AND shelf_id = %s",
                    $shelf_id
                )
            );
        }
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->items_table()} WHERE source = 'uz'" );
    }

    public function search_items( $query ) {
        global $wpdb;
        $like = '%' . $wpdb->esc_like( $query ) . '%';
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->items_table()} WHERE source = 'uz' AND (title LIKE %s OR full_title LIKE %s OR author LIKE %s) ORDER BY sort_order ASC",
                $like, $like, $like
            ),
            ARRAY_A
        );
    }

    // =========================================================================
    // CRUD: Articles (now backed by uz_article custom post type)
    // =========================================================================

    /**
     * Get all articles from uz_article posts.
     * Returns data in the same format as the old custom table for backwards compatibility.
     */
    public function get_articles( $order_by = 'date DESC' ) {
        $wp_order    = 'DESC';
        $wp_orderby  = 'date';
        if ( strpos( $order_by, 'ASC' ) !== false ) {
            $wp_order = 'ASC';
        }

        $posts = get_posts( array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'numberposts'    => -1,
            'orderby'        => $wp_orderby,
            'order'          => $wp_order,
            'meta_key'       => '_uz_article',
            'meta_value'     => '1',
        ) );

        $articles = array();
        foreach ( $posts as $post ) {
            $articles[] = $this->post_to_article( $post );
        }

        return $articles;
    }

    /**
     * Get a single article by slug (id = post_name).
     */
    public function get_article( $id ) {
        $posts = get_posts( array(
            'post_type'   => 'post',
            'name'        => $id,
            'post_status' => array( 'publish', 'draft', 'private' ),
            'numberposts' => 1,
            'meta_key'    => '_uz_article',
            'meta_value'  => '1',
        ) );

        if ( empty( $posts ) ) {
            return null;
        }

        return $this->post_to_article( $posts[0] );
    }

    /**
     * Convert WP_Post to article array (backwards-compatible format).
     */
    private function post_to_article( $post ) {
        $shelf = get_post_meta( $post->ID, '_uz_shelf', true );
        $terms = wp_get_object_terms( $post->ID, 'category', array( 'fields' => 'names' ) );
        $cats  = is_array( $terms ) ? $terms : array();

        // Count items linked to this article
        global $wpdb;
        $product_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->items_table()} WHERE source = 'uz' AND article_id = %s",
            $post->post_name
        ) );

        // Get thumbnail URL
        $thumbnail_url = '';
        if ( has_post_thumbnail( $post->ID ) ) {
            $thumb_id  = get_post_thumbnail_id( $post->ID );
            $thumb_arr = wp_get_attachment_image_src( $thumb_id, 'medium' );
            if ( $thumb_arr ) {
                $thumbnail_url = $thumb_arr[0];
            }
        }

        return array(
            'id'            => $post->post_name,
            'title'         => $post->post_title,
            'date'          => $post->post_date ? gmdate( 'm/d/Y H:i:s', strtotime( $post->post_date ) ) : '',
            'categories'    => wp_json_encode( $cats ),
            'shelf'         => $shelf ?: '',
            'product_count' => $product_count,
            'url'           => get_permalink( $post->ID ),
            'thumbnail_url' => $thumbnail_url,
            'post_id'       => $post->ID,
        );
    }

    /**
     * Upsert article - creates/updates uz_article post.
     * Kept for backwards compatibility with JSON import.
     */
    public function upsert_article( $id, $data ) {
        // Check for existing post
        $existing_posts = get_posts( array(
            'post_type'   => 'post',
            'name'        => $id,
            'post_status' => array( 'publish', 'draft', 'private' ),
            'numberposts' => 1,
            'meta_key'    => '_uz_article',
            'meta_value'  => '1',
        ) );

        $post_data = array(
            'post_type'   => 'post',
            'post_name'   => $id,
            'post_title'  => isset( $data['title'] ) ? $data['title'] : '',
            'post_status' => 'publish',
        );

        // Parse date if provided
        if ( ! empty( $data['date'] ) ) {
            $ts = strtotime( $data['date'] );
            if ( $ts ) {
                $post_data['post_date']     = gmdate( 'Y-m-d H:i:s', $ts );
                $post_data['post_date_gmt'] = get_gmt_from_date( $post_data['post_date'] );
            }
        }

        if ( ! empty( $existing_posts ) ) {
            $post_data['ID'] = $existing_posts[0]->ID;
        }

        $post_id = wp_insert_post( $post_data, true );
        if ( is_wp_error( $post_id ) ) {
            return;
        }

        // Mark as bookshelf article
        update_post_meta( $post_id, '_uz_article', '1' );

        // Set shelf meta
        if ( isset( $data['shelf'] ) ) {
            update_post_meta( $post_id, '_uz_shelf', $data['shelf'] );
        }

        // Set categories
        if ( isset( $data['categories'] ) ) {
            $cats = is_string( $data['categories'] ) ? json_decode( $data['categories'], true ) : $data['categories'];
            if ( is_array( $cats ) && ! empty( $cats ) ) {
                $term_ids = array();
                foreach ( $cats as $cat_name ) {
                    $cat_name = trim( $cat_name );
                    if ( empty( $cat_name ) ) continue;
                    $term = term_exists( $cat_name, 'category' );
                    if ( ! $term ) {
                        $term = wp_insert_term( $cat_name, 'category' );
                    }
                    if ( ! is_wp_error( $term ) ) {
                        $term_ids[] = (int) ( is_array( $term ) ? $term['term_id'] : $term );
                    }
                }
                if ( ! empty( $term_ids ) ) {
                    wp_set_object_terms( $post_id, $term_ids, 'category' );
                }
            }
        }
    }

    /**
     * Delete article - trashes the uz_article post.
     */
    public function delete_article( $id ) {
        $posts = get_posts( array(
            'post_type'   => 'post',
            'name'        => $id,
            'post_status' => array( 'publish', 'draft', 'private' ),
            'numberposts' => 1,
            'meta_key'    => '_uz_article',
            'meta_value'  => '1',
        ) );

        if ( ! empty( $posts ) ) {
            wp_trash_post( $posts[0]->ID );
        }
    }

    // =========================================================================
    // CRUD: Rakuten Books
    // =========================================================================

    public function get_rakuten_books( $genre_id = null, $order_by = 'sort_order ASC' ) {
        global $wpdb;
        if ( $genre_id ) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$this->items_table()} WHERE source = 'rakuten' AND genre_id = %s ORDER BY $order_by",
                    $genre_id
                ),
                ARRAY_A
            );
        }
        return $wpdb->get_results(
            "SELECT * FROM {$this->items_table()} WHERE source = 'rakuten' ORDER BY $order_by",
            ARRAY_A
        );
    }

    public function insert_rakuten_book( $data ) {
        $data['source'] = 'rakuten';
        return $this->insert_item( $data );
    }

    public function delete_rakuten_book( $id ) {
        global $wpdb;
        $wpdb->delete( $this->items_table(), array( 'id' => $id ) );
    }

    public function delete_rakuten_by_genre( $genre_id ) {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$this->items_table()} WHERE source = 'rakuten' AND genre_id = %s",
            $genre_id
        ) );
    }

    public function search_rakuten_books( $query ) {
        global $wpdb;
        $like = '%' . $wpdb->esc_like( $query ) . '%';
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->items_table()} WHERE source = 'rakuten' AND (title LIKE %s OR author LIKE %s OR publisher LIKE %s) ORDER BY review_count DESC LIMIT 30",
                $like, $like, $like
            ),
            ARRAY_A
        );
    }

    // =========================================================================
    // Import from SQLite JSON export
    // =========================================================================

    /**
     * Import from uz-shelf-data.json format
     */
    public function import_from_json( $json_data ) {
        $data = is_string( $json_data ) ? json_decode( $json_data, true ) : $json_data;
        if ( ! $data ) {
            return new WP_Error( 'invalid_json', 'Invalid JSON data' );
        }

        $counts = array( 'shelves' => 0, 'items' => 0, 'articles' => 0 );

        // Import shelves and items
        if ( ! empty( $data['shelves'] ) ) {
            foreach ( $data['shelves'] as $i => $shelf ) {
                $this->upsert_shelf( $shelf['id'], array(
                    'title'      => $shelf['title'],
                    'icon'       => isset( $shelf['icon'] ) ? $shelf['icon'] : '',
                    'sort_order' => $i,
                ) );
                $counts['shelves']++;

                if ( ! empty( $shelf['items'] ) ) {
                    foreach ( $shelf['items'] as $j => $item ) {
                        $dims = isset( $item['dimensions'] ) ? $item['dimensions'] : array();
                        $this->insert_item( array(
                            'item_id'       => isset( $item['id'] ) ? $item['id'] : '',
                            'shelf_id'      => $shelf['id'],
                            'title'         => isset( $item['title'] ) ? $item['title'] : '',
                            'full_title'    => isset( $item['fullTitle'] ) ? $item['fullTitle'] : '',
                            'author'        => isset( $item['author'] ) ? $item['author'] : '',
                            'cover_url'     => isset( $item['coverUrl'] ) ? $item['coverUrl'] : '',
                            'amazon_url'    => isset( $item['amazonUrl'] ) ? $item['amazonUrl'] : '',
                            'rakuten_url'   => isset( $item['rakutenUrl'] ) ? $item['rakutenUrl'] : '',
                            'article_id'    => isset( $item['articleId'] ) ? $item['articleId'] : '',
                            'article_title' => isset( $item['articleTitle'] ) ? $item['articleTitle'] : '',
                            'comment'       => isset( $item['comment'] ) ? $item['comment'] : '',
                            'tags'          => isset( $item['tags'] ) ? wp_json_encode( $item['tags'] ) : '[]',
                            'type'          => isset( $item['type'] ) ? $item['type'] : 'product',
                            'format'        => isset( $item['format'] ) ? $item['format'] : 'standard',
                            'width'         => isset( $dims['width'] ) ? (int) $dims['width'] : 128,
                            'height'        => isset( $dims['height'] ) ? (int) $dims['height'] : 182,
                            'sort_order'    => $j,
                        ) );
                        $counts['items']++;
                    }
                }
            }
        }

        // Import articles
        if ( ! empty( $data['articles'] ) ) {
            foreach ( $data['articles'] as $article ) {
                $this->upsert_article( $article['id'], array(
                    'title'         => $article['title'],
                    'date'          => isset( $article['date'] ) ? $article['date'] : '',
                    'categories'    => isset( $article['categories'] ) ? wp_json_encode( $article['categories'] ) : '[]',
                    'shelf'         => isset( $article['shelf'] ) ? $article['shelf'] : '',
                    'product_count' => isset( $article['productCount'] ) ? (int) $article['productCount'] : 0,
                    'url'           => isset( $article['url'] ) ? $article['url'] : '',
                ) );
                $counts['articles']++;
            }
        }

        return $counts;
    }

    /**
     * Export data in uz-shelf-data.json format (for API response)
     */
    public function export_shelf_data() {
        $shelves = $this->get_shelves();
        $result_shelves = array();

        foreach ( $shelves as $shelf ) {
            $items = $this->get_items( $shelf['id'] );
            $result_items = array();

            foreach ( $items as $item ) {
                $tags = json_decode( $item['tags'], true );
                if ( ! is_array( $tags ) ) {
                    $tags = array();
                }

                $result_items[] = array(
                    'id'           => $item['item_id'],
                    'title'        => $item['title'],
                    'fullTitle'    => $item['full_title'] ?: '',
                    'author'       => $item['author'] ?: '',
                    'fullAuthor'   => $item['full_author'] ?: '',
                    'coverUrl'     => $item['cover_url'] ?: '',
                    'amazonUrl'    => $item['amazon_url'] ?: '',
                    'rakutenUrl'   => $item['rakuten_url'] ?: '',
                    'affiliateUrl' => $item['affiliate_url'] ?: '',
                    'articleId'    => $item['article_id'] ?: '',
                    'articleTitle' => $item['article_title'] ?: '',
                    'comment'      => $item['comment'] ?: '',
                    'tags'         => $tags,
                    'type'         => $item['type'] ?: 'product',
                    'format'       => $item['format'] ?: 'standard',
                    'dimensions'   => array(
                        'width'  => (int) $item['width'] ?: 128,
                        'height' => (int) $item['height'] ?: 182,
                    ),
                );
            }

            $result_shelves[] = array(
                'id'    => $shelf['id'],
                'title' => $shelf['title'],
                'icon'  => $shelf['icon'] ?: '',
                'items' => $result_items,
            );
        }

        $articles       = $this->get_articles();
        $result_articles = array();

        foreach ( $articles as $a ) {
            $cats = json_decode( $a['categories'], true );
            if ( ! is_array( $cats ) ) {
                $cats = array();
            }

            $result_articles[] = array(
                'id'           => $a['id'],
                'title'        => $a['title'],
                'date'         => $a['date'] ?: '',
                'categories'   => $cats,
                'shelf'        => $a['shelf'] ?: '',
                'productCount' => (int) $a['product_count'],
                'url'          => $a['url'] ?: '',
                'thumbnailUrl' => $a['thumbnail_url'] ?: '',
            );
        }

        // Also add articleUrl to shelf items for frontend consumption
        foreach ( $result_shelves as &$shelf ) {
            foreach ( $shelf['items'] as &$item ) {
                if ( ! empty( $item['articleId'] ) ) {
                    $art = $this->get_article( $item['articleId'] );
                    $item['articleUrl'] = $art ? $art['url'] : '';
                }
            }
        }
        unset( $shelf, $item );

        return array(
            'shelves'  => $result_shelves,
            'articles' => $result_articles,
        );
    }

    /**
     * Export rakuten books in original API format
     */
    public function export_rakuten_data( $genre_id ) {
        $books = $this->get_rakuten_books( $genre_id );
        $items = array();

        foreach ( $books as $b ) {
            $items[] = array(
                'Item' => array(
                    'isbn'           => $b['isbn'] ?: '',
                    'title'          => $b['title'],
                    'author'         => $b['author'] ?: '',
                    'publisherName'  => $b['publisher'] ?: '',
                    'itemPrice'      => (int) $b['item_price'],
                    'itemUrl'        => $b['item_url'] ?: '',
                    'largeImageUrl'  => $b['large_image_url'] ?: '',
                    'mediumImageUrl' => $b['medium_image_url'] ?: '',
                    'smallImageUrl'  => $b['small_image_url'] ?: '',
                    'itemCaption'    => $b['item_caption'] ?: '',
                    'booksGenreId'   => $b['books_genre_id'] ?: '',
                    'salesDate'      => $b['sales_date'] ?: '',
                    'reviewAverage'  => $b['review_average'] ?: '',
                    'reviewCount'    => (int) $b['review_count'],
                    'availability'   => $b['availability'] ?: '',
                    'affiliateUrl'   => $b['affiliate_url'] ?: '',
                ),
            );
        }

        return array(
            'Items' => $items,
            'count' => count( $items ),
            'hits'  => count( $items ),
        );
    }

    /**
     * Clear all data from all tables (for "Start Fresh" mode)
     */
    public function clear_all_data() {
        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE {$this->items_table()}" );
        $wpdb->query( "TRUNCATE TABLE {$this->shelves_table()}" );
    }

    /**
     * Load bundled sample data from plugin data/ directory
     *
     * @param bool $include_rakuten Whether to also load Rakuten book data
     * @return array|WP_Error Counts of imported items
     */
    public function load_sample_data( $include_rakuten = true ) {
        $data_dir = UZ_BOOKSHELF_PATH . 'data/';

        // Load shelf data
        $shelf_file = $data_dir . 'uz-shelf-data.json';
        if ( ! file_exists( $shelf_file ) ) {
            return new WP_Error( 'missing_data', 'Sample data file not found: uz-shelf-data.json' );
        }

        $json = file_get_contents( $shelf_file );
        $data = json_decode( $json, true );
        if ( ! $data ) {
            return new WP_Error( 'invalid_json', 'Failed to parse uz-shelf-data.json' );
        }

        $result = $this->import_from_json( $data );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $result['rakuten_books'] = 0;

        // Load Rakuten data
        if ( $include_rakuten ) {
            $genres = array( '001005', '001006', '001010' );
            foreach ( $genres as $genre_id ) {
                $rakuten_file = $data_dir . $genre_id . '.json';
                if ( ! file_exists( $rakuten_file ) ) {
                    continue;
                }
                $rjson = file_get_contents( $rakuten_file );
                $rdata = json_decode( $rjson, true );
                if ( ! $rdata || empty( $rdata['Items'] ) ) {
                    continue;
                }
                foreach ( $rdata['Items'] as $i => $entry ) {
                    $item = isset( $entry['Item'] ) ? $entry['Item'] : array();
                    $this->insert_rakuten_book( array(
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
                    $result['rakuten_books']++;
                }
            }
        }

        return $result;
    }

    /**
     * Move shelf sort_order up or down
     */
    public function move_shelf( $id, $direction ) {
        global $wpdb;
        $shelf = $this->get_shelf( $id );
        if ( ! $shelf ) return;

        $current_order = (int) $shelf['sort_order'];
        if ( $direction === 'up' ) {
            $neighbor = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$this->shelves_table()} WHERE sort_order < %d ORDER BY sort_order DESC LIMIT 1",
                    $current_order
                ), ARRAY_A
            );
        } else {
            $neighbor = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$this->shelves_table()} WHERE sort_order > %d ORDER BY sort_order ASC LIMIT 1",
                    $current_order
                ), ARRAY_A
            );
        }
        if ( ! $neighbor ) return;

        $wpdb->update( $this->shelves_table(), array( 'sort_order' => (int) $neighbor['sort_order'] ), array( 'id' => $id ) );
        $wpdb->update( $this->shelves_table(), array( 'sort_order' => $current_order ), array( 'id' => $neighbor['id'] ) );
    }

    /**
     * Move item sort_order up or down within its shelf
     */
    public function move_item( $id, $direction ) {
        global $wpdb;
        $item = $this->get_item( $id );
        if ( ! $item ) return;

        $current_order = (int) $item['sort_order'];
        $shelf_id = $item['shelf_id'];
        if ( $direction === 'up' ) {
            $neighbor = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$this->items_table()} WHERE source = 'uz' AND shelf_id = %s AND sort_order < %d ORDER BY sort_order DESC LIMIT 1",
                    $shelf_id, $current_order
                ), ARRAY_A
            );
        } else {
            $neighbor = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$this->items_table()} WHERE source = 'uz' AND shelf_id = %s AND sort_order > %d ORDER BY sort_order ASC LIMIT 1",
                    $shelf_id, $current_order
                ), ARRAY_A
            );
        }
        if ( ! $neighbor ) return;

        $wpdb->update( $this->items_table(), array( 'sort_order' => (int) $neighbor['sort_order'] ), array( 'id' => $id ) );
        $wpdb->update( $this->items_table(), array( 'sort_order' => $current_order ), array( 'id' => (int) $neighbor['id'] ) );
    }

    /**
     * Check if database has any data
     */
    public function is_empty() {
        global $wpdb;
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->shelves_table()}" );
        return $count === 0;
    }

    /**
     * Get stats for dashboard
     */
    public function get_stats() {
        global $wpdb;
        $article_count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_uz_article' AND pm.meta_value = '1'
             WHERE p.post_type = 'post' AND p.post_status = 'publish'"
        );
        $theme_count = 0;
        if ( taxonomy_exists( 'critique_theme' ) ) {
            $themes = get_terms( array( 'taxonomy' => 'critique_theme', 'hide_empty' => false, 'fields' => 'count' ) );
            $theme_count = is_wp_error( $themes ) ? 0 : (int) $themes;
        }

        return array(
            'shelves'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->shelves_table()}" ),
            'items'         => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->items_table()} WHERE source = 'uz'" ),
            'articles'      => $article_count,
            'themes'        => $theme_count,
            'rakuten_books' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->items_table()} WHERE source = 'rakuten'" ),
        );
    }

    /**
     * Get per-shelf item counts
     */
    public function get_shelf_stats() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT s.id, s.title, COUNT(si.id) as item_count
             FROM {$this->shelves_table()} s
             LEFT JOIN {$this->items_table()} si ON s.id = si.shelf_id AND si.source = 'uz'
             GROUP BY s.id
             ORDER BY s.sort_order",
            ARRAY_A
        );
    }
}
