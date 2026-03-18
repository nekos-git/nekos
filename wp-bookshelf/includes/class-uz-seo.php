<?php
/**
 * UZ Bookshelf - SEO
 *
 * Handles OGP meta tags, JSON-LD structured data, Twitter Cards,
 * and XML Sitemap integration for bookshelf pages.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UZ_Bookshelf_SEO {

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
        add_action( 'wp_head', array( $this, 'output_meta_tags' ), 1 );
        add_filter( 'wp_sitemaps_add_provider', array( $this, 'maybe_add_sitemap_provider' ), 10, 2 );
        add_filter( 'document_title_parts', array( $this, 'filter_title' ) );
    }

    // =========================================================================
    // Meta Tag Output
    // =========================================================================

    /**
     * Output OGP, Twitter Card, and JSON-LD meta tags on bookshelf pages.
     */
    public function output_meta_tags() {
        if ( ! $this->is_bookshelf_page() ) {
            return;
        }

        $book_id    = isset( $_GET['uz_book'] ) ? sanitize_text_field( wp_unslash( $_GET['uz_book'] ) ) : '';
        $theme_slug = isset( $_GET['uz_theme'] ) ? sanitize_text_field( wp_unslash( $_GET['uz_theme'] ) ) : '';

        if ( ! empty( $book_id ) ) {
            $this->output_book_meta( $book_id );
        } elseif ( ! empty( $theme_slug ) ) {
            $this->output_theme_meta( $theme_slug );
        } else {
            $this->output_bookshelf_meta();
        }
    }

    /**
     * Output OGP, Twitter Card, and JSON-LD for a single book.
     *
     * @param string $book_id The item_id of the book.
     */
    private function output_book_meta( $book_id ) {
        $item = $this->get_item_by_item_id( $book_id );
        if ( ! $item ) {
            $this->output_bookshelf_meta();
            return;
        }

        $title       = $item['title'];
        $author      = $item['author'];
        $og_title    = ! empty( $author ) ? $title . ' - ' . $author : $title;
        $description = ! empty( $item['comment'] ) ? wp_strip_all_tags( $item['comment'] ) : $og_title;
        $description = mb_substr( $description, 0, 200 );
        $cover_url   = $item['cover_url'];
        $page_url    = $this->get_current_page_url( array( 'uz_book' => $book_id ) );

        // OGP tags.
        $this->output_og_tag( 'og:type', 'book' );
        $this->output_og_tag( 'og:title', $og_title );
        $this->output_og_tag( 'og:description', $description );
        $this->output_og_tag( 'og:url', $page_url );
        if ( ! empty( $cover_url ) ) {
            $this->output_og_tag( 'og:image', $cover_url );
        }

        // Twitter Card tags.
        $this->output_twitter_tag( 'twitter:card', ! empty( $cover_url ) ? 'summary_large_image' : 'summary' );
        $this->output_twitter_tag( 'twitter:title', $og_title );
        $this->output_twitter_tag( 'twitter:description', $description );
        if ( ! empty( $cover_url ) ) {
            $this->output_twitter_tag( 'twitter:image', $cover_url );
        }

        // JSON-LD Book schema.
        $schema = array(
            '@context' => 'https://schema.org',
            '@type'    => 'Book',
            'name'     => $item['full_title'] ? $item['full_title'] : $title,
            'url'      => $page_url,
        );
        if ( ! empty( $author ) ) {
            $schema['author'] = array(
                '@type' => 'Person',
                'name'  => $item['full_author'] ? $item['full_author'] : $author,
            );
        }
        if ( ! empty( $cover_url ) ) {
            $schema['image'] = $cover_url;
        }
        if ( ! empty( $item['comment'] ) ) {
            $schema['description'] = wp_strip_all_tags( $item['comment'] );
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

        $this->output_json_ld( $schema );
    }

    /**
     * Output OGP, Twitter Card, and JSON-LD for a theme page.
     *
     * @param string $theme_slug The theme taxonomy slug.
     */
    private function output_theme_meta( $theme_slug ) {
        $term = get_term_by( 'slug', $theme_slug, 'critique_theme' );
        if ( ! $term ) {
            $this->output_bookshelf_meta();
            return;
        }

        $title       = $term->name;
        $description = ! empty( $term->description ) ? mb_substr( wp_strip_all_tags( $term->description ), 0, 200 ) : $title;
        $page_url    = $this->get_current_page_url( array( 'uz_theme' => $theme_slug ) );

        // OGP tags.
        $this->output_og_tag( 'og:type', 'website' );
        $this->output_og_tag( 'og:title', $title );
        $this->output_og_tag( 'og:description', $description );
        $this->output_og_tag( 'og:url', $page_url );

        // Twitter Card tags.
        $this->output_twitter_tag( 'twitter:card', 'summary' );
        $this->output_twitter_tag( 'twitter:title', $title );
        $this->output_twitter_tag( 'twitter:description', $description );

        // JSON-LD CollectionPage schema.
        $schema = array(
            '@context'    => 'https://schema.org',
            '@type'       => 'CollectionPage',
            'name'        => $title,
            'description' => wp_strip_all_tags( $term->description ),
            'url'         => $page_url,
        );

        $this->output_json_ld( $schema );
    }

    /**
     * Output default OGP, Twitter Card, and JSON-LD for the main bookshelf.
     */
    private function output_bookshelf_meta() {
        $title       = __( 'UZ Bookshelf', 'uz-bookshelf' );
        $description = __( 'Curated book collection with reviews and recommendations.', 'uz-bookshelf' );
        $page_url    = $this->get_current_page_url();

        // OGP tags.
        $this->output_og_tag( 'og:type', 'website' );
        $this->output_og_tag( 'og:title', $title );
        $this->output_og_tag( 'og:description', $description );
        $this->output_og_tag( 'og:url', $page_url );

        // Twitter Card tags.
        $this->output_twitter_tag( 'twitter:card', 'summary' );
        $this->output_twitter_tag( 'twitter:title', $title );
        $this->output_twitter_tag( 'twitter:description', $description );

        // JSON-LD ItemList schema.
        $shelves    = $this->db->get_shelves();
        $list_items = array();
        $position   = 1;

        foreach ( $shelves as $shelf ) {
            $items = $this->db->get_items( $shelf['id'] );
            foreach ( $items as $item ) {
                $item_schema = array(
                    '@type'    => 'ListItem',
                    'position' => $position,
                    'item'     => array(
                        '@type' => 'Book',
                        'name'  => $item['title'],
                    ),
                );
                if ( ! empty( $item['author'] ) ) {
                    $item_schema['item']['author'] = array(
                        '@type' => 'Person',
                        'name'  => $item['author'],
                    );
                }
                if ( ! empty( $item['cover_url'] ) ) {
                    $item_schema['item']['image'] = $item['cover_url'];
                }
                $list_items[] = $item_schema;
                $position++;

                // Limit to a reasonable number for structured data.
                if ( $position > 50 ) {
                    break 2;
                }
            }
        }

        if ( ! empty( $list_items ) ) {
            $schema = array(
                '@context'        => 'https://schema.org',
                '@type'           => 'ItemList',
                'name'            => $title,
                'description'     => $description,
                'url'             => $page_url,
                'numberOfItems'   => count( $list_items ),
                'itemListElement' => $list_items,
            );
            $this->output_json_ld( $schema );
        }
    }

    // =========================================================================
    // Title Filter
    // =========================================================================

    /**
     * Filter the document title for bookshelf pages with query params.
     *
     * @param array $title_parts The document title parts.
     * @return array Modified title parts.
     */
    public function filter_title( $title_parts ) {
        if ( ! $this->is_bookshelf_page() ) {
            return $title_parts;
        }

        $book_id    = isset( $_GET['uz_book'] ) ? sanitize_text_field( wp_unslash( $_GET['uz_book'] ) ) : '';
        $theme_slug = isset( $_GET['uz_theme'] ) ? sanitize_text_field( wp_unslash( $_GET['uz_theme'] ) ) : '';

        if ( ! empty( $book_id ) ) {
            $item = $this->get_item_by_item_id( $book_id );
            if ( $item ) {
                $title = $item['title'];
                if ( ! empty( $item['author'] ) ) {
                    $title .= ' - ' . $item['author'];
                }
                $title_parts['title'] = $title;
            }
        } elseif ( ! empty( $theme_slug ) ) {
            $term = get_term_by( 'slug', $theme_slug, 'critique_theme' );
            if ( $term ) {
                $title_parts['title'] = $term->name;
            }
        }

        return $title_parts;
    }

    // =========================================================================
    // Sitemap Provider
    // =========================================================================

    /**
     * Conditionally add our custom sitemap provider.
     *
     * @param WP_Sitemaps_Provider $provider The provider instance.
     * @param string               $name     The provider name.
     * @return WP_Sitemaps_Provider|false The provider or false to remove it.
     */
    public function maybe_add_sitemap_provider( $provider, $name ) {
        // Register our provider alongside others during init.
        static $registered = false;
        if ( ! $registered ) {
            $registered = true;
            $sitemaps = wp_get_sitemap_providers();
            if ( ! isset( $sitemaps['uz-bookshelf'] ) ) {
                wp_register_sitemap_provider(
                    'uz-bookshelf',
                    new UZ_Bookshelf_Sitemap_Provider( $this->db )
                );
            }
        }

        return $provider;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Check if the current page contains the [uz_bookshelf] shortcode.
     *
     * @return bool True if this is a bookshelf page.
     */
    private function is_bookshelf_page() {
        if ( ! is_singular() ) {
            return false;
        }

        $post = get_post();
        if ( ! $post ) {
            return false;
        }

        return has_shortcode( $post->post_content, 'uz_bookshelf' );
    }

    /**
     * Look up an item by its item_id field.
     *
     * @param string $item_id The item_id to search for.
     * @return array|null Item row or null.
     */
    private function get_item_by_item_id( $item_id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->db->items_table()} WHERE source = 'uz' AND item_id = %s LIMIT 1",
                $item_id
            ),
            ARRAY_A
        );
    }

    /**
     * Get the current page URL, optionally with additional query params.
     *
     * @param array $params Additional query parameters.
     * @return string The URL.
     */
    private function get_current_page_url( $params = array() ) {
        $url = get_permalink();
        if ( ! empty( $params ) ) {
            $url = add_query_arg( $params, $url );
        }
        return $url;
    }

    /**
     * Find the page that contains the [uz_bookshelf] shortcode.
     *
     * @return string|false The permalink of the bookshelf page or false.
     */
    public function get_bookshelf_page_url() {
        $pages = get_posts( array(
            'post_type'   => array( 'page', 'post' ),
            'post_status' => 'publish',
            's'           => '[uz_bookshelf',
            'numberposts' => 1,
        ) );

        if ( empty( $pages ) ) {
            return false;
        }

        foreach ( $pages as $page ) {
            if ( has_shortcode( $page->post_content, 'uz_bookshelf' ) ) {
                return get_permalink( $page->ID );
            }
        }

        return false;
    }

    /**
     * Output a single OGP meta tag.
     *
     * @param string $property The OGP property name.
     * @param string $content  The content value.
     */
    private function output_og_tag( $property, $content ) {
        echo '<meta property="' . esc_attr( $property ) . '" content="' . esc_attr( $content ) . '" />' . "\n";
    }

    /**
     * Output a single Twitter Card meta tag.
     *
     * @param string $name    The meta name.
     * @param string $content The content value.
     */
    private function output_twitter_tag( $name, $content ) {
        echo '<meta name="' . esc_attr( $name ) . '" content="' . esc_attr( $content ) . '" />' . "\n";
    }

    /**
     * Output a JSON-LD script block.
     *
     * @param array $data The structured data array.
     */
    private function output_json_ld( $data ) {
        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
        echo "\n</script>\n";
    }
}

// =============================================================================
// Sitemap Provider
// =============================================================================

/**
 * Custom sitemap provider for UZ Bookshelf items and critique themes.
 */
class UZ_Bookshelf_Sitemap_Provider extends WP_Sitemaps_Provider {

    /** @var UZ_Bookshelf_DB */
    private $db;

    /**
     * Constructor.
     *
     * @param UZ_Bookshelf_DB $db Database layer instance.
     */
    public function __construct( UZ_Bookshelf_DB $db ) {
        $this->db           = $db;
        $this->name         = 'uz-bookshelf';
        $this->object_type  = 'uz-bookshelf';
    }

    /**
     * Get the list of sitemap URLs for this provider.
     *
     * @param int    $page_num       Page number for pagination.
     * @param string $object_subtype Optional subtype filter.
     * @return array Array of sitemap entries.
     */
    public function get_url_list( $page_num, $object_subtype = '' ) {
        $base_url = $this->get_bookshelf_page_url();
        if ( ! $base_url ) {
            return array();
        }

        $urls = array();

        if ( '' === $object_subtype || 'book' === $object_subtype ) {
            $items = $this->db->get_items( null, 'sort_order ASC' );
            foreach ( $items as $item ) {
                if ( empty( $item['item_id'] ) ) {
                    continue;
                }
                $url_entry = array(
                    'loc' => add_query_arg( 'uz_book', $item['item_id'], $base_url ),
                );
                if ( ! empty( $item['updated_at'] ) ) {
                    $url_entry['lastmod'] = gmdate( 'Y-m-d\TH:i:s+00:00', strtotime( $item['updated_at'] ) );
                }
                $urls[] = $url_entry;
            }
        }

        if ( '' === $object_subtype || 'theme' === $object_subtype ) {
            if ( taxonomy_exists( 'critique_theme' ) ) {
                $terms = get_terms( array(
                    'taxonomy'   => 'critique_theme',
                    'hide_empty' => false,
                ) );
                if ( ! is_wp_error( $terms ) ) {
                    foreach ( $terms as $term ) {
                        $urls[] = array(
                            'loc' => get_term_link( $term ),
                        );
                    }
                }
            }
        }

        return $urls;
    }

    /**
     * Get the max number of sitemap pages.
     *
     * @param string $object_subtype Optional subtype.
     * @return int Number of pages (always 1 for this provider).
     */
    public function get_max_num_pages( $object_subtype = '' ) {
        return 1;
    }

    /**
     * Get the list of object subtypes for this provider.
     *
     * @return array Array of subtypes.
     */
    public function get_object_subtypes() {
        return array(
            'book'  => (object) array(
                'name'  => 'book',
                'label' => 'Books',
            ),
            'theme' => (object) array(
                'name'  => 'theme',
                'label' => 'Themes',
            ),
        );
    }

    /**
     * Find the page that contains the [uz_bookshelf] shortcode.
     *
     * @return string|false The permalink or false.
     */
    private function get_bookshelf_page_url() {
        $pages = get_posts( array(
            'post_type'   => array( 'page', 'post' ),
            'post_status' => 'publish',
            's'           => '[uz_bookshelf',
            'numberposts' => 1,
        ) );

        if ( empty( $pages ) ) {
            return false;
        }

        foreach ( $pages as $page ) {
            if ( has_shortcode( $page->post_content, 'uz_bookshelf' ) ) {
                return get_permalink( $page->ID );
            }
        }

        return false;
    }
}
