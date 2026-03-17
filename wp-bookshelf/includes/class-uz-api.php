<?php
/**
 * UZ Bookshelf - REST API
 *
 * Registers WP REST API endpoints for the bookshelf data.
 * Read endpoints are public; write endpoints require manage_options.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UZ_Bookshelf_API {

    /** @var string API namespace */
    const NAMESPACE = 'uz-bookshelf/v1';

    /** @var UZ_Bookshelf_DB */
    private $db;

    public function __construct( UZ_Bookshelf_DB $db ) {
        $this->db = $db;
    }

    /**
     * Register all REST routes
     */
    public function register_routes() {
        // GET /shelves - Full shelf data (matches uz-shelf-data.json format)
        register_rest_route( self::NAMESPACE, '/shelves', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_shelves' ),
            'permission_callback' => '__return_true',
        ) );

        // GET /shelves/{id}/items - Items for a specific shelf
        register_rest_route( self::NAMESPACE, '/shelves/(?P<id>[a-zA-Z0-9_-]+)/items', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_shelf_items' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'id' => array(
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );

        // GET /articles - All articles
        register_rest_route( self::NAMESPACE, '/articles', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_articles' ),
            'permission_callback' => '__return_true',
        ) );

        // GET /rakuten/{genre_id} - Rakuten books by genre
        register_rest_route( self::NAMESPACE, '/rakuten/(?P<genre_id>[0-9]+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_rakuten_books' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'genre_id' => array(
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );

        // GET /rakuten-search?q=... - Search Rakuten Books API
        register_rest_route( self::NAMESPACE, '/rakuten-search', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'rakuten_search' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'q' => array(
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );

        // GET /search?q=... - Cross-shelf search
        register_rest_route( self::NAMESPACE, '/search', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'search' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'q' => array(
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );

        // GET /themes - All critique themes with article counts
        register_rest_route( self::NAMESPACE, '/themes', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_themes' ),
            'permission_callback' => '__return_true',
        ) );

        // GET /themes/{slug} - Single theme with full article list
        register_rest_route( self::NAMESPACE, '/themes/(?P<slug>[a-z0-9-]+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_theme' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'slug' => array(
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_title',
                ),
            ),
        ) );

        // POST /seed-themes - Re-seed critique themes (admin)
        register_rest_route( self::NAMESPACE, '/seed-themes', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'seed_themes' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );

        // GET /stats - Dashboard statistics
        register_rest_route( self::NAMESPACE, '/stats', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_stats' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );

        // POST /import - Import JSON data
        register_rest_route( self::NAMESPACE, '/import', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'import_data' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );

        // POST /batch-update-authors - Batch update authors by title
        register_rest_route( self::NAMESPACE, '/batch-update-authors', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'batch_update_authors' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );

        // POST /import-articles - Import Movable Type export data
        register_rest_route( self::NAMESPACE, '/import-articles', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'import_articles' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );

        // POST /upload-thumbnails - Upload cover images to articles (admin)
        register_rest_route( self::NAMESPACE, '/upload-thumbnails', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'upload_thumbnails' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );

        // --- CRUD for shelf items (admin) ---
        register_rest_route( self::NAMESPACE, '/items', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'create_item' ),
            'permission_callback' => array( $this, 'check_admin_permission' ),
        ) );

        register_rest_route( self::NAMESPACE, '/items/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_item' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_item' ),
                'permission_callback' => array( $this, 'check_admin_permission' ),
            ),
        ) );
    }

    /**
     * Permission check for admin endpoints
     */
    public function check_admin_permission() {
        return current_user_can( 'manage_options' );
    }

    // =========================================================================
    // Public Endpoints
    // =========================================================================

    /**
     * GET /shelves - Returns full shelf data in uz-shelf-data.json format
     */
    public function get_shelves( WP_REST_Request $request ) {
        $data = $this->db->export_shelf_data();
        return rest_ensure_response( $data );
    }

    /**
     * GET /shelves/{id}/items
     */
    public function get_shelf_items( WP_REST_Request $request ) {
        $shelf_id = $request->get_param( 'id' );
        $shelf    = $this->db->get_shelf( $shelf_id );

        if ( ! $shelf ) {
            return new WP_Error(
                'shelf_not_found',
                'Shelf not found',
                array( 'status' => 404 )
            );
        }

        $items = $this->db->get_items( $shelf_id );
        $result = array();

        foreach ( $items as $item ) {
            $tags = json_decode( $item['tags'], true );
            if ( ! is_array( $tags ) ) {
                $tags = array();
            }

            $result[] = array(
                'id'           => $item['item_id'],
                'title'        => $item['title'],
                'fullTitle'    => $item['full_title'] ?: '',
                'author'       => $item['author'] ?: '',
                'coverUrl'     => $item['cover_url'] ?: '',
                'amazonUrl'    => $item['amazon_url'] ?: '',
                'rakutenUrl'   => $item['rakuten_url'] ?: '',
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

        return rest_ensure_response( $result );
    }

    /**
     * GET /articles - now returns uz_article custom post data with WP permalinks
     */
    public function get_articles( WP_REST_Request $request ) {
        $articles = $this->db->get_articles();
        $result   = array();

        foreach ( $articles as $a ) {
            $cats = json_decode( $a['categories'], true );
            if ( ! is_array( $cats ) ) {
                $cats = array();
            }

            // Include critique themes if available
            $themes = array();
            if ( ! empty( $a['post_id'] ) ) {
                $theme_terms = wp_get_object_terms( $a['post_id'], 'critique_theme', array( 'fields' => 'slugs' ) );
                if ( is_array( $theme_terms ) ) {
                    $themes = $theme_terms;
                }
            }

            $result[] = array(
                'id'           => $a['id'],
                'title'        => $a['title'],
                'date'         => $a['date'] ?: '',
                'categories'   => $cats,
                'themes'       => $themes,
                'shelf'        => $a['shelf'] ?: '',
                'productCount' => (int) $a['product_count'],
                'url'          => $a['url'] ?: '',
                'thumbnailUrl' => $a['thumbnail_url'] ?: '',
            );
        }

        return rest_ensure_response( $result );
    }

    /**
     * GET /rakuten/{genre_id}
     */
    public function get_rakuten_books( WP_REST_Request $request ) {
        $genre_id = $request->get_param( 'genre_id' );
        $data     = $this->db->export_rakuten_data( $genre_id );
        return rest_ensure_response( $data );
    }

    /**
     * GET /rakuten-search?q=... - Search Rakuten Books API by keyword
     */
    public function rakuten_search( WP_REST_Request $request ) {
        $query  = $request->get_param( 'q' );

        if ( empty( $query ) || mb_strlen( $query ) < 2 ) {
            return new WP_Error(
                'query_too_short',
                '検索キーワードは2文字以上入力してください',
                array( 'status' => 400 )
            );
        }

        $app_id = get_option( 'uz_bookshelf_rakuten_app_id', '' );

        // If API key is set, search via Rakuten API
        if ( ! empty( $app_id ) ) {
            $cache_key = 'uz_rksearch_' . md5( $query );
            $cached    = get_transient( $cache_key );
            if ( false !== $cached ) {
                return rest_ensure_response( $cached );
            }

            $api_url = add_query_arg( array(
                'format'        => 'json',
                'applicationId' => $app_id,
                'keyword'       => $query,
                'hits'          => 20,
                'sort'          => 'reviewCount',
                'outOfStockFlag' => 0,
            ), 'https://app.rakuten.co.jp/services/api/BooksTotal/Search/20170404' );

            $response = wp_remote_get( $api_url, array( 'timeout' => 10 ) );

            if ( ! is_wp_error( $response ) ) {
                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( ! empty( $body ) && ! isset( $body['error'] ) ) {
                    $result = array( 'Items' => array() );
                    foreach ( $body['Items'] ?? array() as $entry ) {
                        $item = $entry['Item'] ?? $entry;
                        $result['Items'][] = array(
                            'Item' => array(
                                'isbn'           => $item['isbn'] ?? '',
                                'title'          => $item['title'] ?? '',
                                'author'         => $item['author'] ?? '',
                                'publisherName'  => $item['publisherName'] ?? '',
                                'itemPrice'      => $item['itemPrice'] ?? 0,
                                'itemUrl'        => $item['itemUrl'] ?? '',
                                'largeImageUrl'  => $item['largeImageUrl'] ?? '',
                                'mediumImageUrl' => $item['mediumImageUrl'] ?? '',
                                'smallImageUrl'  => $item['smallImageUrl'] ?? '',
                                'itemCaption'    => $item['itemCaption'] ?? '',
                                'salesDate'      => $item['salesDate'] ?? '',
                                'reviewAverage'  => $item['reviewAverage'] ?? '',
                                'reviewCount'    => $item['reviewCount'] ?? 0,
                                'booksGenreId'   => $item['booksGenreId'] ?? '',
                            ),
                        );
                    }
                    set_transient( $cache_key, $result, 30 * MINUTE_IN_SECONDS );
                    return rest_ensure_response( $result );
                }
            }
        }

        // Fallback: search local DB
        $books  = $this->db->search_rakuten_books( $query );
        $result = array( 'Items' => array() );
        foreach ( $books as $book ) {
            $result['Items'][] = array(
                'Item' => array(
                    'isbn'           => $book['isbn'] ?: '',
                    'title'          => $book['title'] ?: '',
                    'author'         => $book['author'] ?: '',
                    'publisherName'  => $book['publisher'] ?: '',
                    'itemPrice'      => (int) $book['item_price'],
                    'itemUrl'        => $book['item_url'] ?: '',
                    'largeImageUrl'  => $book['large_image_url'] ?: '',
                    'mediumImageUrl' => $book['medium_image_url'] ?: '',
                    'smallImageUrl'  => $book['small_image_url'] ?: '',
                    'itemCaption'    => $book['item_caption'] ?: '',
                    'salesDate'      => $book['sales_date'] ?: '',
                    'reviewAverage'  => $book['review_average'] ?: '',
                    'reviewCount'    => (int) $book['review_count'],
                    'booksGenreId'   => $book['books_genre_id'] ?: '',
                ),
            );
        }
        return rest_ensure_response( $result );
    }

    /**
     * GET /search?q=...
     */
    public function search( WP_REST_Request $request ) {
        $query = $request->get_param( 'q' );
        if ( empty( $query ) || strlen( $query ) < 2 ) {
            return new WP_Error(
                'query_too_short',
                'Search query must be at least 2 characters',
                array( 'status' => 400 )
            );
        }

        $items  = $this->db->search_items( $query );
        $result = array();

        foreach ( $items as $item ) {
            $tags = json_decode( $item['tags'], true );
            if ( ! is_array( $tags ) ) {
                $tags = array();
            }

            $result[] = array(
                'id'           => $item['item_id'],
                'title'        => $item['title'],
                'fullTitle'    => $item['full_title'] ?: '',
                'author'       => $item['author'] ?: '',
                'coverUrl'     => $item['cover_url'] ?: '',
                'amazonUrl'    => $item['amazon_url'] ?: '',
                'rakutenUrl'   => $item['rakuten_url'] ?: '',
                'articleId'    => $item['article_id'] ?: '',
                'articleTitle' => $item['article_title'] ?: '',
                'comment'      => $item['comment'] ?: '',
                'tags'         => $tags,
                'shelfId'      => $item['shelf_id'],
                'type'         => $item['type'] ?: 'product',
                'format'       => $item['format'] ?: 'standard',
            );
        }

        return rest_ensure_response( $result );
    }

    /**
     * POST /batch-update-authors - Update authors for items matching by full_title
     */
    public function batch_update_authors( WP_REST_Request $request ) {
        $updates = $request->get_json_params();
        if ( ! is_array( $updates ) ) {
            return new WP_Error( 'invalid_data', 'Expected array of {title, author}', array( 'status' => 400 ) );
        }

        global $wpdb;
        $table   = $wpdb->prefix . 'uz_items';
        $updated = 0;

        foreach ( $updates as $entry ) {
            $title  = sanitize_text_field( $entry['title'] ?? '' );
            $author = sanitize_text_field( $entry['author'] ?? '' );
            if ( ! $title || ! $author ) continue;

            $rows = $wpdb->query( $wpdb->prepare(
                "UPDATE {$table} SET author = %s, full_author = %s WHERE source = 'uz' AND (full_title = %s OR title = %s) AND (author = '' OR author IS NULL)",
                $author, $author, $title, $title
            ) );
            $updated += (int) $rows;
        }

        return rest_ensure_response( array( 'success' => true, 'updated' => $updated ) );
    }

    // =========================================================================
    // Critique Themes
    // =========================================================================

    /**
     * GET /themes - All critique themes with article counts and descriptions
     */
    public function get_themes( WP_REST_Request $request ) {
        $terms = get_terms( array(
            'taxonomy'   => 'critique_theme',
            'hide_empty' => false,
            'orderby'    => 'name',
        ) );

        if ( is_wp_error( $terms ) ) {
            return rest_ensure_response( array() );
        }

        $result = array();
        foreach ( $terms as $term ) {
            $result[] = array(
                'slug'         => $term->slug,
                'name'         => $term->name,
                'description'  => $term->description,
                'articleCount' => (int) $term->count,
            );
        }

        return rest_ensure_response( $result );
    }

    /**
     * GET /themes/{slug} - Single theme with full article list
     */
    public function get_theme( WP_REST_Request $request ) {
        $slug = $request->get_param( 'slug' );
        $term = get_term_by( 'slug', $slug, 'critique_theme' );

        if ( ! $term ) {
            return new WP_Error(
                'theme_not_found',
                'Theme not found',
                array( 'status' => 404 )
            );
        }

        $posts = get_posts( array(
            'post_type'   => 'post',
            'post_status' => 'publish',
            'numberposts' => -1,
            'tax_query'   => array(
                array(
                    'taxonomy' => 'critique_theme',
                    'field'    => 'term_id',
                    'terms'    => $term->term_id,
                ),
            ),
            'meta_key'    => '_uz_article',
            'meta_value'  => '1',
        ) );

        $articles = array();
        foreach ( $posts as $post ) {
            $themes = wp_get_object_terms( $post->ID, 'critique_theme', array( 'fields' => 'slugs' ) );
            $articles[] = array(
                'id'     => $post->post_name,
                'title'  => $post->post_title,
                'date'   => $post->post_date ? gmdate( 'Y-m-d', strtotime( $post->post_date ) ) : '',
                'url'    => get_permalink( $post->ID ),
                'themes' => is_array( $themes ) ? $themes : array(),
            );
        }

        return rest_ensure_response( array(
            'slug'         => $term->slug,
            'name'         => $term->name,
            'description'  => $term->description,
            'articleCount' => count( $articles ),
            'articles'     => $articles,
        ) );
    }

    /**
     * POST /seed-themes - Re-seed critique themes from bundled data (deletes existing first)
     */
    public function seed_themes( WP_REST_Request $request ) {
        // Remove existing terms
        $existing = get_terms( array(
            'taxonomy'   => 'critique_theme',
            'hide_empty' => false,
            'fields'     => 'ids',
        ) );
        if ( ! is_wp_error( $existing ) ) {
            foreach ( $existing as $term_id ) {
                wp_delete_term( $term_id, 'critique_theme' );
            }
        }

        // Re-seed
        uz_bookshelf()->seed_critique_themes();

        $terms = get_terms( array(
            'taxonomy'   => 'critique_theme',
            'hide_empty' => false,
        ) );

        $count = is_wp_error( $terms ) ? 0 : count( $terms );

        return rest_ensure_response( array(
            'success' => true,
            'seeded'  => $count,
        ) );
    }

    // =========================================================================
    // Admin Endpoints
    // =========================================================================

    /**
     * GET /stats
     */
    public function get_stats( WP_REST_Request $request ) {
        return rest_ensure_response( $this->db->get_stats() );
    }

    /**
     * POST /upload-thumbnails - Upload cover images as featured images for articles.
     * Accepts JSON: { "articles": [ { "slug": "...", "image_url": "https://..." }, ... ] }
     * Downloads each image and sets it as the article's featured image.
     */
    public function upload_thumbnails( WP_REST_Request $request ) {
        $body     = $request->get_json_params();
        $articles = $body['articles'] ?? array();

        if ( empty( $articles ) ) {
            return new WP_Error( 'no_data', 'No articles provided', array( 'status' => 400 ) );
        }

        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $results = array();
        foreach ( $articles as $entry ) {
            $slug      = sanitize_title( $entry['slug'] ?? '' );
            $image_url = esc_url_raw( $entry['image_url'] ?? '' );
            $image_b64 = $entry['image_base64'] ?? '';

            if ( ! $slug ) {
                $results[] = array( 'slug' => $slug, 'status' => 'skip', 'reason' => 'no slug' );
                continue;
            }

            $posts = get_posts( array(
                'post_type'   => 'post',
                'name'        => $slug,
                'post_status' => 'publish',
                'numberposts' => 1,
                'meta_key'    => '_uz_article',
                'meta_value'  => '1',
            ) );

            if ( empty( $posts ) ) {
                $results[] = array( 'slug' => $slug, 'status' => 'skip', 'reason' => 'post not found' );
                continue;
            }

            $post = $posts[0];

            if ( has_post_thumbnail( $post->ID ) ) {
                $results[] = array( 'slug' => $slug, 'status' => 'skip', 'reason' => 'already has thumbnail' );
                continue;
            }

            // Handle base64 image data
            if ( ! empty( $image_b64 ) ) {
                $decoded = base64_decode( $image_b64 );
                if ( ! $decoded ) {
                    $results[] = array( 'slug' => $slug, 'status' => 'error', 'reason' => 'invalid base64' );
                    continue;
                }
                $tmp = wp_tempnam( $slug . '-cover.jpg' );
                file_put_contents( $tmp, $decoded );
            } elseif ( ! empty( $image_url ) ) {
                $tmp = download_url( $image_url, 30 );
                if ( is_wp_error( $tmp ) ) {
                    $results[] = array( 'slug' => $slug, 'status' => 'error', 'reason' => $tmp->get_error_message() );
                    continue;
                }
            } else {
                $results[] = array( 'slug' => $slug, 'status' => 'skip', 'reason' => 'no image source' );
                continue;
            }

            $file_array = array(
                'name'     => $slug . '-cover.jpg',
                'tmp_name' => $tmp,
            );

            $att_id = media_handle_sideload( $file_array, $post->ID, $post->post_title . ' カバー画像' );

            if ( is_wp_error( $att_id ) ) {
                $results[] = array( 'slug' => $slug, 'status' => 'error', 'reason' => $att_id->get_error_message() );
                @unlink( $tmp );
                continue;
            }

            set_post_thumbnail( $post->ID, $att_id );
            $results[] = array( 'slug' => $slug, 'status' => 'ok', 'attachment_id' => $att_id );
        }

        return rest_ensure_response( array(
            'uploaded' => count( array_filter( $results, function( $r ) { return $r['status'] === 'ok'; } ) ),
            'total'    => count( $articles ),
            'results'  => $results,
        ) );
    }

    /**
     * POST /import - Import JSON data (uz-shelf-data.json format)
     */
    public function import_data( WP_REST_Request $request ) {
        $body = $request->get_json_params();
        if ( empty( $body ) ) {
            return new WP_Error(
                'empty_body',
                'Request body must contain valid JSON',
                array( 'status' => 400 )
            );
        }

        $result = $this->db->import_from_json( $body );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( array(
            'success' => true,
            'counts'  => $result,
        ) );
    }

    /**
     * POST /import-articles - Import Movable Type export data as uz_article posts
     */
    public function import_articles( WP_REST_Request $request ) {
        $params = $request->get_json_params();

        if ( empty( $params['export_content'] ) ) {
            return new WP_Error(
                'missing_content',
                'export_content is required',
                array( 'status' => 400 )
            );
        }

        $json_data = null;
        if ( ! empty( $params['json_data'] ) ) {
            $json_data = $params['json_data'];
        } else {
            // Try to load from bundled data file
            $shelf_file = UZ_BOOKSHELF_PATH . 'data/uz-shelf-data.json';
            if ( file_exists( $shelf_file ) ) {
                $json_data = json_decode( file_get_contents( $shelf_file ), true );
            }
        }

        $result = UZ_Bookshelf_Importer::run_import( $params['export_content'], $json_data );

        return rest_ensure_response( array(
            'success' => true,
            'results' => $result,
        ) );
    }

    /**
     * POST /items - Create new shelf item
     */
    public function create_item( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $data   = $this->sanitize_item_data( $params );

        if ( empty( $data['title'] ) || empty( $data['shelf_id'] ) ) {
            return new WP_Error(
                'missing_fields',
                'title and shelf_id are required',
                array( 'status' => 400 )
            );
        }

        $id = $this->db->insert_item( $data );
        return rest_ensure_response( array(
            'success' => true,
            'id'      => $id,
        ) );
    }

    /**
     * PUT/PATCH /items/{id} - Update shelf item
     */
    public function update_item( WP_REST_Request $request ) {
        $id   = (int) $request->get_param( 'id' );
        $item = $this->db->get_item( $id );

        if ( ! $item ) {
            return new WP_Error(
                'item_not_found',
                'Item not found',
                array( 'status' => 404 )
            );
        }

        $params = $request->get_json_params();
        $data   = $this->sanitize_item_data( $params );

        $this->db->update_item( $id, $data );
        return rest_ensure_response( array( 'success' => true ) );
    }

    /**
     * DELETE /items/{id}
     */
    public function delete_item( WP_REST_Request $request ) {
        $id   = (int) $request->get_param( 'id' );
        $item = $this->db->get_item( $id );

        if ( ! $item ) {
            return new WP_Error(
                'item_not_found',
                'Item not found',
                array( 'status' => 404 )
            );
        }

        $this->db->delete_item( $id );
        return rest_ensure_response( array( 'success' => true ) );
    }

    /**
     * Sanitize item data from request
     */
    private function sanitize_item_data( $params ) {
        $data = array();
        $text_fields = array(
            'item_id', 'shelf_id', 'title', 'full_title', 'author', 'full_author',
            'cover_url', 'amazon_url', 'rakuten_url', 'affiliate_url',
            'article_id', 'article_title', 'comment', 'type', 'format',
        );

        foreach ( $text_fields as $field ) {
            if ( isset( $params[ $field ] ) ) {
                $data[ $field ] = sanitize_text_field( $params[ $field ] );
            }
        }

        // Tags: expect array, store as JSON
        if ( isset( $params['tags'] ) ) {
            $tags = is_array( $params['tags'] ) ? $params['tags'] : array();
            $data['tags'] = wp_json_encode( array_map( 'sanitize_text_field', $tags ) );
        }

        // Numeric fields
        if ( isset( $params['width'] ) ) {
            $data['width'] = absint( $params['width'] );
        }
        if ( isset( $params['height'] ) ) {
            $data['height'] = absint( $params['height'] );
        }
        if ( isset( $params['sort_order'] ) ) {
            $data['sort_order'] = absint( $params['sort_order'] );
        }

        return $data;
    }
}
