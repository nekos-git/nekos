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
     * GET /articles
     */
    public function get_articles( WP_REST_Request $request ) {
        $articles = $this->db->get_articles();
        $result   = array();

        foreach ( $articles as $a ) {
            $cats = json_decode( $a['categories'], true );
            if ( ! is_array( $cats ) ) {
                $cats = array();
            }

            $result[] = array(
                'id'           => $a['id'],
                'title'        => $a['title'],
                'date'         => $a['date'] ?: '',
                'categories'   => $cats,
                'shelf'        => $a['shelf'] ?: '',
                'productCount' => (int) $a['product_count'],
                'url'          => $a['url'] ?: '',
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
