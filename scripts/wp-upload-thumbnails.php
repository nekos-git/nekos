<?php
/**
 * WP-CLI script: Upload cover images as featured images for uz_article posts.
 *
 * For each article, finds the first shelf item with a cover image,
 * downloads/copies the cover, and sets it as the post's featured image.
 *
 * Usage: wp eval-file scripts/wp-upload-thumbnails.php --allow-root
 */

if ( ! defined( 'ABSPATH' ) ) {
    echo "Run with: wp eval-file scripts/wp-upload-thumbnails.php --allow-root\n";
    exit(1);
}

// Load shelf data to find article -> cover mappings
$shelf_data_file = __DIR__ . '/../docs/uz-shelf-data.json';
if ( ! file_exists( $shelf_data_file ) ) {
    WP_CLI::error( "Shelf data not found: $shelf_data_file" );
}

$data = json_decode( file_get_contents( $shelf_data_file ), true );
if ( ! $data || empty( $data['shelves'] ) ) {
    WP_CLI::error( 'Invalid shelf data JSON' );
}

// Build article_id -> first cover_url mapping
$article_covers = array();
foreach ( $data['shelves'] as $shelf ) {
    foreach ( $shelf['items'] as $item ) {
        $aid = $item['articleId'] ?? '';
        $cover = $item['coverUrl'] ?? '';
        if ( $aid && $cover && ! isset( $article_covers[ $aid ] ) ) {
            $article_covers[ $aid ] = $cover;
        }
    }
}

WP_CLI::log( sprintf( 'Found %d article->cover mappings from shelf data', count( $article_covers ) ) );

// Get all uz_article posts
$posts = get_posts( array(
    'post_type'   => 'post',
    'post_status' => 'publish',
    'numberposts' => -1,
    'meta_key'    => '_uz_article',
    'meta_value'  => '1',
) );

$uploaded = 0;
$skipped  = 0;
$failed   = 0;

$covers_dir = __DIR__ . '/../docs/';

foreach ( $posts as $post ) {
    $slug = $post->post_name;

    // Skip if already has thumbnail
    if ( has_post_thumbnail( $post->ID ) ) {
        WP_CLI::log( "SKIP (already has thumbnail): $slug" );
        $skipped++;
        continue;
    }

    if ( ! isset( $article_covers[ $slug ] ) ) {
        WP_CLI::log( "SKIP (no cover found): $slug" );
        $skipped++;
        continue;
    }

    $cover_path = $article_covers[ $slug ];

    // Cover path is relative to docs/ (e.g., "covers/abc123.jpg")
    $local_file = $covers_dir . $cover_path;

    if ( ! file_exists( $local_file ) ) {
        // Try to download from Amazon
        $cover_map_file = $covers_dir . 'cover-map.json';
        $downloaded = false;

        if ( file_exists( $cover_map_file ) ) {
            $cover_map = json_decode( file_get_contents( $cover_map_file ), true );
            $remote_url = array_search( $cover_path, $cover_map );
            if ( $remote_url ) {
                WP_CLI::log( "Downloading: $remote_url" );
                $tmp = download_url( $remote_url, 30 );
                if ( ! is_wp_error( $tmp ) ) {
                    @mkdir( dirname( $local_file ), 0755, true );
                    rename( $tmp, $local_file );
                    $downloaded = true;
                }
            }
        }

        if ( ! $downloaded ) {
            WP_CLI::warning( "FAIL (cover file missing): $slug -> $cover_path" );
            $failed++;
            continue;
        }
    }

    // Upload to WordPress media library
    $file_array = array(
        'name'     => $slug . '-cover.jpg',
        'tmp_name' => $local_file,
    );

    // Copy to temp so media_handle_sideload doesn't delete our original
    $tmp_copy = wp_tempnam( $file_array['name'] );
    copy( $local_file, $tmp_copy );
    $file_array['tmp_name'] = $tmp_copy;

    $attachment_id = media_handle_sideload( $file_array, $post->ID, $post->post_title . ' カバー画像' );

    if ( is_wp_error( $attachment_id ) ) {
        WP_CLI::warning( "FAIL (upload error): $slug - " . $attachment_id->get_error_message() );
        @unlink( $tmp_copy );
        $failed++;
        continue;
    }

    set_post_thumbnail( $post->ID, $attachment_id );
    WP_CLI::log( "OK: $slug (attachment #$attachment_id)" );
    $uploaded++;
}

WP_CLI::success( sprintf(
    'Done! Uploaded: %d, Skipped: %d, Failed: %d (Total posts: %d)',
    $uploaded, $skipped, $failed, count( $posts )
) );
