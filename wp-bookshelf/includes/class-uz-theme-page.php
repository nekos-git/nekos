<?php
/**
 * UZ Bookshelf - Theme Landing Page
 *
 * Handles server-side rendered landing pages for critique_theme terms.
 * Registers rewrite rules and renders a page listing articles and books
 * associated with a given theme.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UZ_Bookshelf_Theme_Page {

    /** @var UZ_Bookshelf_DB */
    private $db;

    public function __construct( UZ_Bookshelf_DB $db ) {
        $this->db = $db;
    }

    public function register() {
        add_action( 'init', array( $this, 'add_rewrite_rules' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'handle_request' ) );
    }

    public function add_rewrite_rules() {
        add_rewrite_rule(
            'theme/([^/]+)/?$',
            'index.php?uz_theme_slug=$matches[1]',
            'top'
        );
    }

    public function add_query_vars( $vars ) {
        $vars[] = 'uz_theme_slug';
        return $vars;
    }

    public function handle_request() {
        $slug = get_query_var( 'uz_theme_slug', '' );
        if ( empty( $slug ) ) {
            return;
        }

        $slug = sanitize_text_field( $slug );
        $term = get_term_by( 'slug', $slug, 'critique_theme' );

        if ( ! $term ) {
            global $wp_query;
            $wp_query->set_404();
            status_header( 404 );
            nocache_headers();
            return;
        }

        $this->render_theme_page( $term );
        exit;
    }

    public function get_theme_url( $slug ) {
        return home_url( 'theme/' . urlencode( $slug ) . '/' );
    }

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
     * Get articles (posts) tagged with this theme term.
     */
    private function get_theme_articles( $term_id ) {
        $posts = get_posts( array(
            'post_type'   => 'post',
            'post_status' => 'publish',
            'numberposts' => 50,
            'tax_query'   => array(
                array(
                    'taxonomy' => 'critique_theme',
                    'field'    => 'term_id',
                    'terms'    => $term_id,
                ),
            ),
        ) );

        $articles = array();
        foreach ( $posts as $post ) {
            $articles[] = array(
                'post' => $post,
            );
        }

        return $articles;
    }

    /**
     * Get books linked to articles in this theme.
     */
    private function get_theme_books( $term_id ) {
        global $wpdb;

        $post_ids = get_posts( array(
            'post_type'   => 'post',
            'post_status' => 'publish',
            'numberposts' => 100,
            'fields'      => 'ids',
            'tax_query'   => array(
                array(
                    'taxonomy' => 'critique_theme',
                    'field'    => 'term_id',
                    'terms'    => $term_id,
                ),
            ),
        ) );

        if ( empty( $post_ids ) ) {
            return array();
        }

        // Get article IDs from the articles table that match these post_ids
        $articles_table = $this->db->table( 'articles' );
        $placeholders = implode( ', ', array_fill( 0, count( $post_ids ), '%d' ) );
        $article_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT article_id FROM {$articles_table} WHERE post_id IN ($placeholders)",
                ...$post_ids
            )
        );

        if ( empty( $article_ids ) ) {
            return array();
        }

        // Get items with these article_ids
        $items_table = $this->db->items_table();
        $art_placeholders = implode( ', ', array_fill( 0, count( $article_ids ), '%s' ) );
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$items_table} WHERE source = 'uz' AND article_id IN ($art_placeholders) ORDER BY sort_order ASC LIMIT 50",
                ...$article_ids
            ),
            ARRAY_A
        );

        return $items ?: array();
    }

    private function render_theme_page( $term ) {
        $title       = $term->name;
        $description = ! empty( $term->description ) ? wp_strip_all_tags( $term->description ) : $title;
        $description = mb_substr( $description, 0, 200 );
        $page_url    = $this->get_theme_url( $term->slug );
        $bookshelf_url = $this->get_bookshelf_page_url();

        $articles = $this->get_theme_articles( $term->term_id );
        $books    = $this->get_theme_books( $term->term_id );

        // JSON-LD
        $schema = array(
            '@context'    => 'https://schema.org',
            '@type'       => 'CollectionPage',
            'name'        => $title,
            'description' => $description,
            'url'         => $page_url,
        );

        add_filter( 'pre_get_document_title', function () use ( $title ) {
            return $title . ' | ' . get_bloginfo( 'name' );
        } );

        get_header();
        ?>

        <meta property="og:type" content="website" />
        <meta property="og:title" content="<?php echo esc_attr( $title ); ?>" />
        <meta property="og:description" content="<?php echo esc_attr( $description ); ?>" />
        <meta property="og:url" content="<?php echo esc_url( $page_url ); ?>" />

        <meta name="twitter:card" content="summary" />
        <meta name="twitter:title" content="<?php echo esc_attr( $title ); ?>" />
        <meta name="twitter:description" content="<?php echo esc_attr( $description ); ?>" />

        <script type="application/ld+json">
        <?php echo wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ); ?>
        </script>

        <style>
            .uz-theme-page {
                max-width: 960px;
                margin: 0 auto;
                padding: 40px 20px;
                font-family: "Noto Sans JP", system-ui, -apple-system, "Segoe UI", sans-serif;
                color: #333;
                line-height: 1.7;
            }
            .uz-theme-page a {
                color: #5a3d25;
                text-decoration: none;
            }
            .uz-theme-page a:hover {
                text-decoration: underline;
            }
            .uz-theme-breadcrumb {
                font-size: 13px;
                color: #888;
                margin-bottom: 24px;
            }
            .uz-theme-header {
                margin-bottom: 32px;
            }
            .uz-theme-header h1 {
                font-size: 28px;
                font-weight: 700;
                margin: 0 0 12px;
                color: #1a1a1a;
            }
            .uz-theme-description {
                font-size: 15px;
                color: #666;
                max-width: 700px;
            }
            .uz-theme-section h2 {
                font-size: 20px;
                font-weight: 700;
                margin: 0 0 20px;
                padding-bottom: 12px;
                border-bottom: 2px solid #c49a6c;
                color: #1a1a1a;
            }
            .uz-theme-articles {
                margin-bottom: 48px;
            }
            .uz-theme-article-item {
                padding: 16px 0;
                border-bottom: 1px solid #eee;
            }
            .uz-theme-article-item:last-child {
                border-bottom: none;
            }
            .uz-theme-article-title {
                font-size: 16px;
                font-weight: 600;
                margin-bottom: 4px;
            }
            .uz-theme-article-excerpt {
                font-size: 14px;
                color: #666;
            }
            .uz-theme-book-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
                gap: 20px;
            }
            .uz-theme-book-item {
                text-align: center;
            }
            .uz-theme-book-item img {
                width: 100%;
                max-width: 120px;
                height: auto;
                border-radius: 3px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                transition: transform 0.2s;
            }
            .uz-theme-book-item img:hover {
                transform: translateY(-2px);
            }
            .uz-theme-book-title {
                font-size: 12px;
                margin-top: 8px;
                color: #555;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }
            .uz-theme-back {
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
            .uz-theme-back:hover {
                background: #e6d8c8;
            }
            @media (max-width: 640px) {
                .uz-theme-book-grid {
                    grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
                    gap: 12px;
                }
            }
        </style>

        <main class="uz-theme-page">
            <nav class="uz-theme-breadcrumb">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'uz-bookshelf' ); ?></a>
                &raquo;
                <a href="<?php echo esc_url( $bookshelf_url ); ?>"><?php esc_html_e( 'Bookshelf', 'uz-bookshelf' ); ?></a>
                &raquo;
                <span><?php echo esc_html( $title ); ?></span>
            </nav>

            <header class="uz-theme-header">
                <h1><?php echo esc_html( $title ); ?></h1>
                <?php if ( ! empty( $term->description ) ) : ?>
                    <p class="uz-theme-description"><?php echo esc_html( wp_strip_all_tags( $term->description ) ); ?></p>
                <?php endif; ?>
            </header>

            <?php if ( ! empty( $articles ) ) : ?>
                <section class="uz-theme-section uz-theme-articles">
                    <h2><?php esc_html_e( 'Articles', 'uz-bookshelf' ); ?></h2>
                    <?php foreach ( $articles as $entry ) :
                        $post = $entry['post'];
                        ?>
                        <div class="uz-theme-article-item">
                            <div class="uz-theme-article-title">
                                <a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>">
                                    <?php echo esc_html( $post->post_title ); ?>
                                </a>
                            </div>
                            <?php if ( ! empty( $post->post_excerpt ) ) : ?>
                                <div class="uz-theme-article-excerpt">
                                    <?php echo esc_html( $post->post_excerpt ); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>

            <?php if ( ! empty( $books ) ) : ?>
                <section class="uz-theme-section">
                    <h2><?php esc_html_e( 'Books', 'uz-bookshelf' ); ?></h2>
                    <div class="uz-theme-book-grid">
                        <?php foreach ( $books as $item ) : ?>
                            <div class="uz-theme-book-item">
                                <?php
                                $book_url = ! empty( $item['item_id'] )
                                    ? uz_bookshelf()->book_page->get_book_url( $item['item_id'] )
                                    : '#';
                                ?>
                                <a href="<?php echo esc_url( $book_url ); ?>">
                                    <?php if ( ! empty( $item['cover_url'] ) ) : ?>
                                        <img src="<?php echo esc_url( $item['cover_url'] ); ?>"
                                             alt="<?php echo esc_attr( $item['title'] ); ?>"
                                             loading="lazy" />
                                    <?php else : ?>
                                        <div style="width:100%;aspect-ratio:128/182;background:#ddd;border-radius:3px;display:flex;align-items:center;justify-content:center;color:#999;font-size:11px;">
                                            <?php esc_html_e( 'No Cover', 'uz-bookshelf' ); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="uz-theme-book-title">
                                        <?php echo esc_html( $item['title'] ); ?>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <a href="<?php echo esc_url( $bookshelf_url ); ?>" class="uz-theme-back">
                &larr; <?php esc_html_e( 'Back to Bookshelf', 'uz-bookshelf' ); ?>
            </a>
        </main>

        <?php
        get_footer();
    }
}
