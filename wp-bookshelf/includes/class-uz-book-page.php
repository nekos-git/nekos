<?php
/**
 * UZ Bookshelf - Individual Book Page
 *
 * Handles server-side rendered detail pages for individual books.
 * Registers rewrite rules and intercepts requests to render
 * a full HTML page with book details, purchase links, and related books.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UZ_Bookshelf_Book_Page {

    /** @var UZ_Bookshelf_DB */
    private $db;

    /**
     * Constructor.
     *
     * @param UZ_Bookshelf_DB $db Database layer instance.
     */
    public function __construct( UZ_Bookshelf_DB $db ) {
        $this->db = $db;
    }

    /**
     * Register hooks.
     */
    public function register() {
        add_action( 'init', array( $this, 'add_rewrite_rules' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'handle_request' ) );
    }

    /**
     * Add rewrite rule for book pages.
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            'book/([^/]+)/?$',
            'index.php?uz_book_slug=$matches[1]',
            'top'
        );
    }

    /**
     * Register uz_book_slug as a recognized query variable.
     *
     * @param array $vars Existing query vars.
     * @return array Modified query vars.
     */
    public function add_query_vars( $vars ) {
        $vars[] = 'uz_book_slug';
        return $vars;
    }

    /**
     * Intercept requests with uz_book_slug and render the book page.
     */
    public function handle_request() {
        $slug = get_query_var( 'uz_book_slug', '' );
        if ( empty( $slug ) ) {
            return;
        }

        $slug = sanitize_text_field( $slug );
        $item = $this->get_item_by_slug( $slug );

        if ( ! $item ) {
            global $wp_query;
            $wp_query->set_404();
            status_header( 404 );
            nocache_headers();
            return;
        }

        $this->render_book_page( $item );
        exit;
    }

    /**
     * Look up an item by slug (matches against item_id field).
     *
     * @param string $slug The slug to search for.
     * @return array|null Item row or null.
     */
    public function get_item_by_slug( $slug ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->db->items_table()} WHERE source = 'uz' AND item_id = %s LIMIT 1",
                $slug
            ),
            ARRAY_A
        );
    }

    /**
     * Generate a URL-safe slug from a book title.
     *
     * @param string $title The book title.
     * @return string The sanitized slug.
     */
    public function generate_slug( $title ) {
        return sanitize_title( $title );
    }

    /**
     * Get the URL for a book detail page.
     *
     * @param string $item_id The item_id of the book.
     * @return string The book page URL.
     */
    public function get_book_url( $item_id ) {
        return home_url( 'book/' . urlencode( $item_id ) . '/' );
    }

    /**
     * Get related books (same article_id or same shelf).
     *
     * @param array $item The current book item.
     * @param int   $limit Maximum number of related books.
     * @return array Array of related items.
     */
    private function get_related_books( $item, $limit = 12 ) {
        global $wpdb;

        $related = array();
        $exclude_id = (int) $item['id'];

        // First: books from the same article.
        if ( ! empty( $item['article_id'] ) ) {
            $article_items = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$this->db->items_table()}
                     WHERE source = 'uz' AND article_id = %s AND id != %d
                     ORDER BY sort_order ASC LIMIT %d",
                    $item['article_id'],
                    $exclude_id,
                    $limit
                ),
                ARRAY_A
            );
            foreach ( $article_items as $related_item ) {
                $related[ $related_item['id'] ] = $related_item;
            }
        }

        // Second: books from the same shelf (fill remaining slots).
        if ( count( $related ) < $limit && ! empty( $item['shelf_id'] ) ) {
            $exclude_ids = array_merge( array( $exclude_id ), array_map( 'intval', array_keys( $related ) ) );
            $placeholders = implode( ', ', array_fill( 0, count( $exclude_ids ), '%d' ) );
            $remaining = $limit - count( $related );

            $query_args = array_merge(
                array( $item['shelf_id'] ),
                $exclude_ids,
                array( $remaining )
            );

            $shelf_items = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$this->db->items_table()}
                     WHERE source = 'uz' AND shelf_id = %s AND id NOT IN ($placeholders)
                     ORDER BY sort_order ASC LIMIT %d",
                    ...$query_args
                ),
                ARRAY_A
            );
            foreach ( $shelf_items as $related_item ) {
                $related[ $related_item['id'] ] = $related_item;
            }
        }

        return array_values( $related );
    }

    /**
     * Build purchase URL with affiliate ID if configured.
     *
     * @param string $url    The original purchase URL.
     * @param string $type   The affiliate type ('amazon' or 'rakuten').
     * @return string The URL, possibly with affiliate tag.
     */
    private function get_affiliate_url( $url, $type ) {
        if ( empty( $url ) ) {
            return '';
        }

        if ( 'amazon' === $type ) {
            $tag = get_option( 'uz_bookshelf_amazon_associate_id', '' );
            if ( ! empty( $tag ) && strpos( $url, 'tag=' ) === false ) {
                $url = add_query_arg( 'tag', $tag, $url );
            }
        } elseif ( 'rakuten' === $type ) {
            $affiliate_id = get_option( 'uz_bookshelf_rakuten_affiliate_id', '' );
            if ( ! empty( $affiliate_id ) && strpos( $url, 'affiliateId=' ) === false ) {
                $url = add_query_arg( 'affiliateId', $affiliate_id, $url );
            }
        }

        return $url;
    }

    /**
     * Get the bookshelf page URL.
     *
     * @return string|false The permalink of the bookshelf page or false.
     */
    private function get_bookshelf_page_url() {
        $pages = get_posts( array(
            'post_type'   => array( 'page', 'post' ),
            'post_status' => 'publish',
            's'           => '[uz_bookshelf',
            'numberposts' => 1,
        ) );

        if ( empty( $pages ) ) {
            return home_url( '/' );
        }

        foreach ( $pages as $page ) {
            if ( has_shortcode( $page->post_content, 'uz_bookshelf' ) ) {
                return get_permalink( $page->ID );
            }
        }

        return home_url( '/' );
    }

    /**
     * Render the full book detail page.
     *
     * @param array $item The book item data.
     */
    private function render_book_page( $item ) {
        $title        = $item['title'];
        $full_title   = ! empty( $item['full_title'] ) ? $item['full_title'] : $title;
        $author       = $item['author'];
        $full_author  = ! empty( $item['full_author'] ) ? $item['full_author'] : $author;
        $comment      = $item['comment'];
        $cover_url    = $item['cover_url'];
        $amazon_url   = $this->get_affiliate_url( $item['amazon_url'], 'amazon' );
        $rakuten_url  = $this->get_affiliate_url( $item['rakuten_url'], 'rakuten' );
        $page_url     = $this->get_book_url( $item['item_id'] );
        $bookshelf_url = $this->get_bookshelf_page_url();
        $related      = $this->get_related_books( $item );

        $og_title    = ! empty( $author ) ? $title . ' - ' . $author : $title;
        $description = ! empty( $comment ) ? wp_strip_all_tags( $comment ) : $og_title;
        $description = mb_substr( $description, 0, 200 );

        $tags = json_decode( $item['tags'], true );
        if ( ! is_array( $tags ) ) {
            $tags = array();
        }

        // Build JSON-LD schema.
        $schema = array(
            '@context' => 'https://schema.org',
            '@type'    => 'Book',
            'name'     => $full_title,
            'url'      => $page_url,
        );
        if ( ! empty( $full_author ) ) {
            $schema['author'] = array(
                '@type' => 'Person',
                'name'  => $full_author,
            );
        }
        if ( ! empty( $cover_url ) ) {
            $schema['image'] = $cover_url;
        }
        if ( ! empty( $comment ) ) {
            $schema['description'] = wp_strip_all_tags( $comment );
        }
        if ( ! empty( $item['isbn'] ) ) {
            $schema['isbn'] = $item['isbn'];
        }
        if ( ! empty( $item['publisher'] ) ) {
            $schema['publisher'] = array(
                '@type' => 'Organization',
                'name'  => $item['publisher'],
            );
        }

        // Start output.
        // Set wp_title filter for this request.
        add_filter( 'pre_get_document_title', function () use ( $og_title ) {
            return $og_title . ' | ' . get_bloginfo( 'name' );
        } );

        get_header();
        ?>

        <meta property="og:type" content="book" />
        <meta property="og:title" content="<?php echo esc_attr( $og_title ); ?>" />
        <meta property="og:description" content="<?php echo esc_attr( $description ); ?>" />
        <meta property="og:url" content="<?php echo esc_url( $page_url ); ?>" />
        <?php if ( ! empty( $cover_url ) ) : ?>
        <meta property="og:image" content="<?php echo esc_url( $cover_url ); ?>" />
        <?php endif; ?>

        <meta name="twitter:card" content="<?php echo esc_attr( ! empty( $cover_url ) ? 'summary_large_image' : 'summary' ); ?>" />
        <meta name="twitter:title" content="<?php echo esc_attr( $og_title ); ?>" />
        <meta name="twitter:description" content="<?php echo esc_attr( $description ); ?>" />
        <?php if ( ! empty( $cover_url ) ) : ?>
        <meta name="twitter:image" content="<?php echo esc_url( $cover_url ); ?>" />
        <?php endif; ?>

        <script type="application/ld+json">
        <?php echo wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ); ?>
        </script>

        <style>
            .uz-book-page {
                max-width: 960px;
                margin: 0 auto;
                padding: 40px 20px;
                font-family: "Noto Sans JP", system-ui, -apple-system, "Segoe UI", sans-serif;
                color: #333;
                line-height: 1.7;
            }
            .uz-book-page a {
                color: #5a3d25;
                text-decoration: none;
            }
            .uz-book-page a:hover {
                text-decoration: underline;
            }
            .uz-book-breadcrumb {
                font-size: 13px;
                color: #888;
                margin-bottom: 24px;
            }
            .uz-book-detail {
                display: flex;
                gap: 40px;
                margin-bottom: 48px;
            }
            .uz-book-cover {
                flex-shrink: 0;
                width: 240px;
            }
            .uz-book-cover img {
                width: 100%;
                height: auto;
                border-radius: 4px;
                box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
            }
            .uz-book-info {
                flex: 1;
                min-width: 0;
            }
            .uz-book-info h1 {
                font-size: 24px;
                font-weight: 700;
                margin: 0 0 8px;
                line-height: 1.4;
                color: #1a1a1a;
            }
            .uz-book-author {
                font-size: 16px;
                color: #666;
                margin-bottom: 20px;
            }
            .uz-book-full-title {
                font-size: 14px;
                color: #888;
                margin-bottom: 16px;
            }
            .uz-book-comment {
                font-size: 15px;
                color: #444;
                margin-bottom: 24px;
                padding: 16px 20px;
                background: #f8f6f3;
                border-left: 4px solid #c49a6c;
                border-radius: 0 4px 4px 0;
            }
            .uz-book-tags {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
                margin-bottom: 24px;
            }
            .uz-book-tag {
                display: inline-block;
                padding: 3px 10px;
                font-size: 12px;
                color: #7a5535;
                background: #f0e6d8;
                border-radius: 12px;
            }
            .uz-book-actions {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
                margin-bottom: 24px;
            }
            .uz-book-btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 10px 24px;
                font-size: 14px;
                font-weight: 600;
                border-radius: 6px;
                text-decoration: none !important;
                transition: opacity 0.2s;
            }
            .uz-book-btn:hover {
                opacity: 0.85;
            }
            .uz-book-btn--amazon {
                background: #ff9900;
                color: #111 !important;
            }
            .uz-book-btn--rakuten {
                background: #bf0000;
                color: #fff !important;
            }
            .uz-book-meta-table {
                width: 100%;
                font-size: 14px;
                border-collapse: collapse;
            }
            .uz-book-meta-table th {
                text-align: left;
                padding: 8px 16px 8px 0;
                color: #888;
                font-weight: 500;
                white-space: nowrap;
                width: 120px;
                border-bottom: 1px solid #eee;
            }
            .uz-book-meta-table td {
                padding: 8px 0;
                border-bottom: 1px solid #eee;
            }
            .uz-book-related {
                margin-top: 48px;
            }
            .uz-book-related h2 {
                font-size: 20px;
                font-weight: 700;
                margin: 0 0 20px;
                padding-bottom: 12px;
                border-bottom: 2px solid #c49a6c;
                color: #1a1a1a;
            }
            .uz-book-related-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
                gap: 20px;
            }
            .uz-book-related-item {
                text-align: center;
            }
            .uz-book-related-item img {
                width: 100%;
                max-width: 120px;
                height: auto;
                border-radius: 3px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                transition: transform 0.2s;
            }
            .uz-book-related-item img:hover {
                transform: translateY(-2px);
            }
            .uz-book-related-item-title {
                font-size: 12px;
                margin-top: 8px;
                color: #555;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }
            .uz-book-back {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                margin-top: 40px;
                padding: 10px 20px;
                font-size: 14px;
                color: #5a3d25;
                background: #f0e6d8;
                border-radius: 6px;
                text-decoration: none !important;
            }
            .uz-book-back:hover {
                background: #e6d8c8;
            }
            @media (max-width: 640px) {
                .uz-book-detail {
                    flex-direction: column;
                    gap: 24px;
                }
                .uz-book-cover {
                    width: 160px;
                    margin: 0 auto;
                }
            }
        </style>

        <main class="uz-book-page">
            <nav class="uz-book-breadcrumb">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'uz-bookshelf' ); ?></a>
                &raquo;
                <a href="<?php echo esc_url( $bookshelf_url ); ?>"><?php esc_html_e( 'Bookshelf', 'uz-bookshelf' ); ?></a>
                &raquo;
                <span><?php echo esc_html( $title ); ?></span>
            </nav>

            <article class="uz-book-detail">
                <div class="uz-book-cover">
                    <?php if ( ! empty( $cover_url ) ) : ?>
                        <img src="<?php echo esc_url( $cover_url ); ?>"
                             alt="<?php echo esc_attr( $title ); ?>"
                             loading="eager" />
                    <?php else : ?>
                        <div style="width:100%;aspect-ratio:128/182;background:#ddd;border-radius:4px;display:flex;align-items:center;justify-content:center;color:#999;font-size:14px;">
                            <?php esc_html_e( 'No Cover', 'uz-bookshelf' ); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="uz-book-info">
                    <h1><?php echo esc_html( $title ); ?></h1>

                    <?php if ( ! empty( $full_author ) ) : ?>
                        <p class="uz-book-author"><?php echo esc_html( $full_author ); ?></p>
                    <?php endif; ?>

                    <?php if ( $full_title !== $title ) : ?>
                        <p class="uz-book-full-title"><?php echo esc_html( $full_title ); ?></p>
                    <?php endif; ?>

                    <?php if ( ! empty( $comment ) ) : ?>
                        <div class="uz-book-comment">
                            <?php echo wp_kses_post( wpautop( $comment ) ); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ( ! empty( $tags ) ) : ?>
                        <div class="uz-book-tags">
                            <?php foreach ( $tags as $tag ) : ?>
                                <span class="uz-book-tag"><?php echo esc_html( $tag ); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="uz-book-actions">
                        <?php if ( ! empty( $amazon_url ) ) : ?>
                            <a href="<?php echo esc_url( $amazon_url ); ?>"
                               class="uz-book-btn uz-book-btn--amazon"
                               target="_blank"
                               rel="nofollow noopener noreferrer">
                                <?php esc_html_e( 'Amazon', 'uz-bookshelf' ); ?>
                            </a>
                        <?php endif; ?>

                        <?php if ( ! empty( $rakuten_url ) ) : ?>
                            <a href="<?php echo esc_url( $rakuten_url ); ?>"
                               class="uz-book-btn uz-book-btn--rakuten"
                               target="_blank"
                               rel="nofollow noopener noreferrer">
                                <?php esc_html_e( 'Rakuten', 'uz-bookshelf' ); ?>
                            </a>
                        <?php endif; ?>
                    </div>

                    <table class="uz-book-meta-table">
                        <?php if ( ! empty( $item['isbn'] ) ) : ?>
                            <tr>
                                <th><?php esc_html_e( 'ISBN', 'uz-bookshelf' ); ?></th>
                                <td><?php echo esc_html( $item['isbn'] ); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ( ! empty( $item['publisher'] ) ) : ?>
                            <tr>
                                <th><?php esc_html_e( 'Publisher', 'uz-bookshelf' ); ?></th>
                                <td><?php echo esc_html( $item['publisher'] ); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ( ! empty( $item['shelf_id'] ) ) : ?>
                            <?php $shelf = $this->db->get_shelf( $item['shelf_id'] ); ?>
                            <?php if ( $shelf ) : ?>
                                <tr>
                                    <th><?php esc_html_e( 'Shelf', 'uz-bookshelf' ); ?></th>
                                    <td><?php echo esc_html( $shelf['title'] ); ?></td>
                                </tr>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ( ! empty( $item['article_title'] ) ) : ?>
                            <tr>
                                <th><?php esc_html_e( 'Article', 'uz-bookshelf' ); ?></th>
                                <td>
                                    <?php
                                    $article = ! empty( $item['article_id'] ) ? $this->db->get_article( $item['article_id'] ) : null;
                                    if ( $article && ! empty( $article['url'] ) ) :
                                        ?>
                                        <a href="<?php echo esc_url( $article['url'] ); ?>">
                                            <?php echo esc_html( $item['article_title'] ); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo esc_html( $item['article_title'] ); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php if ( ! empty( $item['item_price'] ) && (int) $item['item_price'] > 0 ) : ?>
                            <tr>
                                <th><?php esc_html_e( 'Price', 'uz-bookshelf' ); ?></th>
                                <td>&yen;<?php echo esc_html( number_format( (int) $item['item_price'] ) ); ?></td>
                            </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </article>

            <?php if ( ! empty( $related ) ) : ?>
                <section class="uz-book-related">
                    <h2><?php esc_html_e( 'Related Books', 'uz-bookshelf' ); ?></h2>
                    <div class="uz-book-related-grid">
                        <?php foreach ( $related as $rel ) : ?>
                            <div class="uz-book-related-item">
                                <?php
                                $rel_url = ! empty( $rel['item_id'] ) ? $this->get_book_url( $rel['item_id'] ) : '#';
                                ?>
                                <a href="<?php echo esc_url( $rel_url ); ?>">
                                    <?php if ( ! empty( $rel['cover_url'] ) ) : ?>
                                        <img src="<?php echo esc_url( $rel['cover_url'] ); ?>"
                                             alt="<?php echo esc_attr( $rel['title'] ); ?>"
                                             loading="lazy" />
                                    <?php else : ?>
                                        <div style="width:100%;aspect-ratio:128/182;background:#ddd;border-radius:3px;display:flex;align-items:center;justify-content:center;color:#999;font-size:11px;">
                                            <?php esc_html_e( 'No Cover', 'uz-bookshelf' ); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="uz-book-related-item-title">
                                        <?php echo esc_html( $rel['title'] ); ?>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <a href="<?php echo esc_url( $bookshelf_url ); ?>" class="uz-book-back">
                &larr; <?php esc_html_e( 'Back to Bookshelf', 'uz-bookshelf' ); ?>
            </a>
        </main>

        <?php
        get_footer();
    }
}
