<?php
/**
 * UZ Bookshelf - Movable Type Importer
 *
 * Parses Movable Type export format and creates uz_article custom posts.
 * Supports upsert: re-importing updates existing posts by slug (BASENAME).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UZ_Bookshelf_Importer {

    /**
     * Parse Movable Type export file and return array of articles.
     *
     * @param string $content Raw export file content
     * @return array Array of parsed articles
     */
    public static function parse_mt_export( $content ) {
        $articles = array();
        // Split by article separator (8 dashes on its own line)
        $entries = preg_split( '/\n--------\n/', $content );

        foreach ( $entries as $entry ) {
            $entry = trim( $entry );
            if ( empty( $entry ) ) {
                continue;
            }

            $article = self::parse_single_entry( $entry );
            if ( $article && ! empty( $article['basename'] ) ) {
                $articles[] = $article;
            }
        }

        return $articles;
    }

    /**
     * Parse a single MT entry block.
     *
     * @param string $entry Single entry text
     * @return array|null Parsed article data
     */
    private static function parse_single_entry( $entry ) {
        $article = array(
            'author'   => '',
            'title'    => '',
            'basename' => '',
            'status'   => '',
            'date'     => '',
            'body'     => '',
            'category' => '',
        );

        // Split into metadata section and body section by "-----\n"
        $sections = preg_split( '/\n-----\n/', $entry, 2 );
        if ( count( $sections ) < 1 ) {
            return null;
        }

        // Parse metadata lines from first section
        $meta_lines = explode( "\n", $sections[0] );
        foreach ( $meta_lines as $line ) {
            if ( preg_match( '/^(AUTHOR|TITLE|BASENAME|STATUS|DATE|ALLOW COMMENTS|CONVERT BREAKS|CATEGORY):\s*(.*)$/', $line, $m ) ) {
                $key   = strtolower( str_replace( ' ', '_', $m[1] ) );
                $value = trim( $m[2] );
                if ( isset( $article[ $key ] ) ) {
                    $article[ $key ] = $value;
                }
            }
        }

        // Parse body from second section
        if ( isset( $sections[1] ) ) {
            $body_section = $sections[1];
            // Body starts after "BODY:\n"
            if ( preg_match( '/^BODY:\n(.*)/s', $body_section, $bm ) ) {
                $article['body'] = trim( $bm[1] );
            } else {
                $article['body'] = trim( $body_section );
            }
        }

        return $article;
    }

    /**
     * Import parsed articles as uz_article custom posts.
     * Uses upsert: if a post with the same slug exists, it's updated.
     *
     * @param array  $articles     Parsed articles from parse_mt_export()
     * @param array  $shelf_map    Map of basename => shelf_id (from uz-shelf-data.json)
     * @param array  $category_map Map of basename => categories array
     * @return array Import results with counts
     */
    public static function import_articles( $articles, $shelf_map = array(), $category_map = array() ) {
        $results = array(
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors'  => array(),
        );

        // Temporarily allow unfiltered HTML for affiliate link blocks
        $had_filter = false;
        if ( has_filter( 'content_save_pre', 'wp_filter_post_kses' ) ) {
            remove_filter( 'content_save_pre', 'wp_filter_post_kses' );
            $had_filter = true;
        }

        foreach ( $articles as $article ) {
            $result = self::upsert_article( $article, $shelf_map, $category_map );
            if ( is_wp_error( $result ) ) {
                $results['errors'][] = $article['basename'] . ': ' . $result->get_error_message();
                $results['skipped']++;
            } elseif ( $result['action'] === 'created' ) {
                $results['created']++;
            } else {
                $results['updated']++;
            }
        }

        // Restore kses filter
        if ( $had_filter ) {
            add_filter( 'content_save_pre', 'wp_filter_post_kses' );
        }

        return $results;
    }

    /**
     * Upsert a single article as uz_article post.
     *
     * @param array $article     Parsed article data
     * @param array $shelf_map   basename => shelf_id
     * @param array $category_map basename => categories
     * @return array|WP_Error Result with 'action' and 'post_id'
     */
    private static function upsert_article( $article, $shelf_map, $category_map ) {
        $slug = sanitize_title( $article['basename'] );
        if ( empty( $slug ) ) {
            return new WP_Error( 'empty_slug', 'Empty BASENAME' );
        }

        // Parse date: "MM/DD/YYYY HH:MM:SS" -> "YYYY-MM-DD HH:MM:SS"
        $post_date = '';
        if ( ! empty( $article['date'] ) ) {
            $ts = strtotime( $article['date'] );
            if ( $ts ) {
                $post_date = gmdate( 'Y-m-d H:i:s', $ts );
            }
        }

        // Check for existing post by slug
        $existing = get_posts( array(
            'post_type'   => 'uz_article',
            'name'        => $slug,
            'post_status' => array( 'publish', 'draft', 'private' ),
            'numberposts' => 1,
        ) );

        $post_data = array(
            'post_type'    => 'uz_article',
            'post_title'   => $article['title'],
            'post_content' => $article['body'],
            'post_name'    => $slug,
            'post_status'  => 'publish',
        );

        if ( $post_date ) {
            $post_data['post_date']     = $post_date;
            $post_data['post_date_gmt'] = get_gmt_from_date( $post_date );
        }

        $action = 'created';
        if ( ! empty( $existing ) ) {
            $post_data['ID'] = $existing[0]->ID;
            $action = 'updated';
        }

        $post_id = wp_insert_post( $post_data, true );
        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        // Set shelf meta
        $shelf = isset( $shelf_map[ $article['basename'] ] ) ? $shelf_map[ $article['basename'] ] : '';
        if ( $shelf ) {
            update_post_meta( $post_id, '_uz_shelf', $shelf );
        }

        // Set categories via taxonomy
        $cats = isset( $category_map[ $article['basename'] ] ) ? $category_map[ $article['basename'] ] : array();
        if ( ! empty( $article['category'] ) ) {
            $cats[] = $article['category'];
            $cats = array_unique( $cats );
        }
        if ( ! empty( $cats ) ) {
            $term_ids = array();
            foreach ( $cats as $cat_name ) {
                $cat_name = trim( $cat_name );
                if ( empty( $cat_name ) ) continue;
                $term = term_exists( $cat_name, 'uz_category' );
                if ( ! $term ) {
                    $term = wp_insert_term( $cat_name, 'uz_category' );
                }
                if ( ! is_wp_error( $term ) ) {
                    $term_ids[] = (int) ( is_array( $term ) ? $term['term_id'] : $term );
                }
            }
            if ( ! empty( $term_ids ) ) {
                wp_set_object_terms( $post_id, $term_ids, 'uz_category' );
            }
        }

        return array( 'action' => $action, 'post_id' => $post_id );
    }

    /**
     * Build shelf_map and category_map from uz-shelf-data.json articles section.
     *
     * @param array $json_data Decoded uz-shelf-data.json
     * @return array [ 'shelf_map' => [...], 'category_map' => [...] ]
     */
    public static function build_maps_from_json( $json_data ) {
        $shelf_map    = array();
        $category_map = array();

        if ( ! empty( $json_data['articles'] ) ) {
            foreach ( $json_data['articles'] as $article ) {
                $id = $article['id'];
                if ( ! empty( $article['shelf'] ) ) {
                    $shelf_map[ $id ] = $article['shelf'];
                }
                if ( ! empty( $article['categories'] ) && is_array( $article['categories'] ) ) {
                    $category_map[ $id ] = $article['categories'];
                }
            }
        }

        return array(
            'shelf_map'    => $shelf_map,
            'category_map' => $category_map,
        );
    }

    /**
     * Run full import from export file + JSON data.
     *
     * @param string $export_content  Movable Type export file content
     * @param array  $json_data       Decoded uz-shelf-data.json (optional)
     * @return array Import results
     */
    public static function run_import( $export_content, $json_data = null ) {
        $articles = self::parse_mt_export( $export_content );

        $shelf_map    = array();
        $category_map = array();

        if ( $json_data ) {
            $maps         = self::build_maps_from_json( $json_data );
            $shelf_map    = $maps['shelf_map'];
            $category_map = $maps['category_map'];
        }

        return self::import_articles( $articles, $shelf_map, $category_map );
    }
}
