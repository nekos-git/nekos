<?php
/**
 * Fetch cover images for articles that don't have shelf items.
 * Uses OpenBD (Japanese book API) or Rakuten Books search to find relevant covers.
 *
 * Usage: wp eval-file scripts/wp-fetch-missing-covers.php --allow-root
 */

if ( ! defined( 'ABSPATH' ) ) {
    echo "Run with: wp eval-file scripts/wp-fetch-missing-covers.php --allow-root\n";
    exit(1);
}

// Articles without covers and their search keywords for finding relevant book images
$article_keywords = array(
    'ai-robot-movie'          => array( 'query' => 'ロボット AI 映画', 'isbn' => '9784065272718' ),  // AI・ロボット関連書
    'hosodamamoru'            => array( 'query' => '細田守', 'isbn' => '9784041074510' ),  // 細田守関連
    'ghost-in-the-shell'      => array( 'query' => '攻殻機動隊', 'isbn' => '9784063211344' ),  // 攻殻機動隊
    'zootopia'                => array( 'query' => 'ズートピア', 'isbn' => '' ),
    'eva-jinruihokankeikaku'  => array( 'query' => 'エヴァンゲリオン', 'isbn' => '9784041000748' ),
    'murakami-and-pauro'      => array( 'query' => 'アルケミスト パウロコエーリョ', 'isbn' => '9784042750017' ),  // アルケミスト
    'kiriya-kazuaki'          => array( 'query' => '紀里谷和明 GOEMON', 'isbn' => '' ),
    'mieko-kawakami'          => array( 'query' => '川上未映子 ヘヴン', 'isbn' => '9784062772631' ),  // ヘヴン
    'underrated-masterpiece'  => array( 'query' => '隠れた名作映画', 'isbn' => '' ),
    'pauro-witch'             => array( 'query' => 'パウロコエーリョ 魔女', 'isbn' => '9784042750093' ),  // アルケミスト関連
    'mac-or-win-2025'         => array( 'query' => 'Mac Windows 比較', 'isbn' => '' ),
);

$uploaded = 0;
$failed   = 0;

foreach ( $article_keywords as $slug => $info ) {
    $posts = get_posts( array(
        'post_type'   => 'post',
        'name'        => $slug,
        'post_status' => 'publish',
        'numberposts' => 1,
        'meta_key'    => '_uz_article',
        'meta_value'  => '1',
    ) );

    if ( empty( $posts ) ) {
        WP_CLI::warning( "Post not found: $slug" );
        continue;
    }

    $post = $posts[0];

    if ( has_post_thumbnail( $post->ID ) ) {
        WP_CLI::log( "SKIP (already has thumbnail): $slug" );
        continue;
    }

    $image_url = '';

    // Try OpenBD API first (free Japanese book cover API) if ISBN is provided
    if ( ! empty( $info['isbn'] ) ) {
        $isbn = $info['isbn'];
        $openbd_url = "https://api.openbd.jp/v1/get?isbn=$isbn";
        $response = wp_remote_get( $openbd_url, array( 'timeout' => 15 ) );

        if ( ! is_wp_error( $response ) ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( ! empty( $body[0]['summary']['cover'] ) ) {
                $image_url = $body[0]['summary']['cover'];
                WP_CLI::log( "Found via OpenBD ($isbn): $image_url" );
            }
        }
    }

    // Fallback: Try Rakuten Books Search API
    if ( empty( $image_url ) ) {
        $app_id = get_option( 'uz_bookshelf_rakuten_app_id', '' );
        if ( ! empty( $app_id ) ) {
            $api_url = add_query_arg( array(
                'format'        => 'json',
                'applicationId' => $app_id,
                'keyword'       => $info['query'],
                'hits'          => 1,
                'sort'          => 'reviewCount',
            ), 'https://app.rakuten.co.jp/services/api/BooksTotal/Search/20170404' );

            $response = wp_remote_get( $api_url, array( 'timeout' => 15 ) );
            if ( ! is_wp_error( $response ) ) {
                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( ! empty( $body['Items'][0]['Item']['largeImageUrl'] ) ) {
                    $image_url = $body['Items'][0]['Item']['largeImageUrl'];
                    WP_CLI::log( "Found via Rakuten: $image_url" );
                }
            }
        }
    }

    // Fallback: use Google Books API (no key needed for basic search)
    if ( empty( $image_url ) ) {
        $google_url = 'https://www.googleapis.com/books/v1/volumes?' . http_build_query( array(
            'q'          => $info['query'],
            'langRestrict' => 'ja',
            'maxResults' => 1,
        ) );

        $response = wp_remote_get( $google_url, array( 'timeout' => 15 ) );
        if ( ! is_wp_error( $response ) ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( ! empty( $body['items'][0]['volumeInfo']['imageLinks']['thumbnail'] ) ) {
                $image_url = str_replace( 'http://', 'https://', $body['items'][0]['volumeInfo']['imageLinks']['thumbnail'] );
                WP_CLI::log( "Found via Google Books: $image_url" );
            }
        }
    }

    if ( empty( $image_url ) ) {
        WP_CLI::warning( "No image found for: $slug" );
        $failed++;
        continue;
    }

    // Download and attach
    $tmp = download_url( $image_url, 30 );
    if ( is_wp_error( $tmp ) ) {
        WP_CLI::warning( "Download failed for $slug: " . $tmp->get_error_message() );
        $failed++;
        continue;
    }

    $file_array = array(
        'name'     => $slug . '-cover.jpg',
        'tmp_name' => $tmp,
    );

    $attachment_id = media_handle_sideload( $file_array, $post->ID, $post->post_title . ' カバー画像' );

    if ( is_wp_error( $attachment_id ) ) {
        WP_CLI::warning( "Upload failed for $slug: " . $attachment_id->get_error_message() );
        @unlink( $tmp );
        $failed++;
        continue;
    }

    set_post_thumbnail( $post->ID, $attachment_id );
    WP_CLI::success( "OK: $slug (attachment #$attachment_id)" );
    $uploaded++;

    // Be polite to APIs
    sleep( 1 );
}

WP_CLI::success( sprintf( 'Done! Uploaded: %d, Failed: %d', $uploaded, $failed ) );
