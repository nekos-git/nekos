<?php
/**
 * UZ Bookshelf - Database Layer
 *
 * Custom tables via dbDelta() for shelves, shelf_items, articles, rakuten_books.
 * Designed for self-use with variable-based config for future generalization.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UZ_Bookshelf_DB {

    /** @var string Table prefix (wp_ + uz_) */
    private $prefix;

    /** @var string Plugin DB version */
    const DB_VERSION = '1.0.0';

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
        return $this->table( 'shelf_items' );
    }

    public function articles_table() {
        return $this->table( 'articles' );
    }

    public function rakuten_table() {
        return $this->table( 'rakuten_books' );
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

        // --- shelf_items ---
        $sql_items = "CREATE TABLE {$this->items_table()} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            item_id VARCHAR(128) NOT NULL,
            shelf_id VARCHAR(64) NOT NULL,
            title VARCHAR(255) NOT NULL,
            full_title TEXT DEFAULT '',
            author VARCHAR(255) DEFAULT '',
            full_author VARCHAR(255) DEFAULT '',
            cover_url TEXT DEFAULT '',
            amazon_url TEXT DEFAULT '',
            rakuten_url TEXT DEFAULT '',
            affiliate_url TEXT DEFAULT '',
            article_id VARCHAR(128) DEFAULT '',
            article_title VARCHAR(255) DEFAULT '',
            comment TEXT DEFAULT '',
            tags TEXT DEFAULT '[]',
            type VARCHAR(32) DEFAULT 'product',
            format VARCHAR(32) DEFAULT 'standard',
            width INT DEFAULT 128,
            height INT DEFAULT 182,
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_shelf_id (shelf_id),
            KEY idx_article_id (article_id)
        ) $charset_collate;";

        // --- articles ---
        $sql_articles = "CREATE TABLE {$this->articles_table()} (
            id VARCHAR(128) NOT NULL,
            title VARCHAR(255) NOT NULL,
            date VARCHAR(32) DEFAULT '',
            categories TEXT DEFAULT '[]',
            shelf VARCHAR(64) DEFAULT '',
            product_count INT DEFAULT 0,
            url TEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_shelf (shelf)
        ) $charset_collate;";

        // --- rakuten_books ---
        $sql_rakuten = "CREATE TABLE {$this->rakuten_table()} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            genre_id VARCHAR(32) NOT NULL,
            isbn VARCHAR(32) DEFAULT '',
            title VARCHAR(255) NOT NULL,
            author VARCHAR(255) DEFAULT '',
            publisher VARCHAR(255) DEFAULT '',
            item_price INT DEFAULT 0,
            item_url TEXT DEFAULT '',
            large_image_url TEXT DEFAULT '',
            medium_image_url TEXT DEFAULT '',
            small_image_url TEXT DEFAULT '',
            item_caption TEXT DEFAULT '',
            books_genre_id VARCHAR(64) DEFAULT '',
            sales_date VARCHAR(64) DEFAULT '',
            review_average VARCHAR(8) DEFAULT '',
            review_count INT DEFAULT 0,
            availability VARCHAR(32) DEFAULT '',
            affiliate_url TEXT DEFAULT '',
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_genre_id (genre_id)
        ) $charset_collate;";

        dbDelta( $sql_shelves );
        dbDelta( $sql_items );
        dbDelta( $sql_articles );
        dbDelta( $sql_rakuten );

        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }

    /**
     * Drop all plugin tables (uninstall)
     */
    public function drop_tables() {
        global $wpdb;
        $tables = array(
            $this->items_table(),
            $this->articles_table(),
            $this->rakuten_table(),
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
        }
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
        $wpdb->delete( $this->items_table(), array( 'shelf_id' => $id ) );
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
                    "SELECT * FROM {$this->items_table()} WHERE shelf_id = %s ORDER BY $order_by",
                    $shelf_id
                ),
                ARRAY_A
            );
        }
        return $wpdb->get_results(
            "SELECT * FROM {$this->items_table()} ORDER BY $order_by",
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
                "SELECT * FROM {$this->items_table()} WHERE article_id = %s ORDER BY sort_order ASC",
                $article_id
            ),
            ARRAY_A
        );
    }

    public function insert_item( $data ) {
        global $wpdb;
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
                    "SELECT COUNT(*) FROM {$this->items_table()} WHERE shelf_id = %s",
                    $shelf_id
                )
            );
        }
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->items_table()}" );
    }

    public function search_items( $query ) {
        global $wpdb;
        $like = '%' . $wpdb->esc_like( $query ) . '%';
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->items_table()} WHERE title LIKE %s OR full_title LIKE %s OR author LIKE %s ORDER BY sort_order ASC",
                $like, $like, $like
            ),
            ARRAY_A
        );
    }

    // =========================================================================
    // CRUD: Articles
    // =========================================================================

    public function get_articles( $order_by = 'date DESC' ) {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$this->articles_table()} ORDER BY $order_by",
            ARRAY_A
        );
    }

    public function get_article( $id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->articles_table()} WHERE id = %s", $id ),
            ARRAY_A
        );
    }

    public function upsert_article( $id, $data ) {
        global $wpdb;
        $existing = $this->get_article( $id );
        if ( $existing ) {
            $wpdb->update(
                $this->articles_table(),
                $data,
                array( 'id' => $id )
            );
        } else {
            $data['id'] = $id;
            $wpdb->insert( $this->articles_table(), $data );
        }
    }

    public function delete_article( $id ) {
        global $wpdb;
        $wpdb->delete( $this->articles_table(), array( 'id' => $id ) );
    }

    // =========================================================================
    // CRUD: Rakuten Books
    // =========================================================================

    public function get_rakuten_books( $genre_id = null, $order_by = 'sort_order ASC' ) {
        global $wpdb;
        if ( $genre_id ) {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$this->rakuten_table()} WHERE genre_id = %s ORDER BY $order_by",
                    $genre_id
                ),
                ARRAY_A
            );
        }
        return $wpdb->get_results(
            "SELECT * FROM {$this->rakuten_table()} ORDER BY $order_by",
            ARRAY_A
        );
    }

    public function insert_rakuten_book( $data ) {
        global $wpdb;
        $wpdb->insert( $this->rakuten_table(), $data );
        return $wpdb->insert_id;
    }

    public function delete_rakuten_book( $id ) {
        global $wpdb;
        $wpdb->delete( $this->rakuten_table(), array( 'id' => $id ) );
    }

    public function delete_rakuten_by_genre( $genre_id ) {
        global $wpdb;
        $wpdb->delete( $this->rakuten_table(), array( 'genre_id' => $genre_id ) );
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
            );
        }

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
        $wpdb->query( "TRUNCATE TABLE {$this->articles_table()}" );
        $wpdb->query( "TRUNCATE TABLE {$this->rakuten_table()}" );
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
        return array(
            'shelves'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->shelves_table()}" ),
            'items'         => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->items_table()}" ),
            'articles'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->articles_table()}" ),
            'rakuten_books' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->rakuten_table()}" ),
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
             LEFT JOIN {$this->items_table()} si ON s.id = si.shelf_id
             GROUP BY s.id
             ORDER BY s.sort_order",
            ARRAY_A
        );
    }
}
