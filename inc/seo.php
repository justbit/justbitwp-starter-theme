<?php
/**
 * SEO base — meta description, OG tags, Schema.org WebSite.
 *
 * Yoast SEO (se installato) sovrascrive questi tag. Sono fallback minimi
 * per quando Yoast non è attivo.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_head', function() {
    if ( defined( 'WPSEO_VERSION' ) ) return; // Yoast attivo, salta i fallback.

    $title = wp_get_document_title();
    $desc  = get_bloginfo( 'description' );
    if ( is_singular() ) {
        $desc = wp_trim_words( get_the_excerpt() ?: get_post_field( 'post_content', get_the_ID() ), 28 );
    }

    echo "\n<!-- jbw seo fallback -->\n";
    echo '<meta name="description" content="' . esc_attr( $desc ) . "\">\n";
    echo '<meta property="og:title" content="' . esc_attr( $title ) . "\">\n";
    echo '<meta property="og:description" content="' . esc_attr( $desc ) . "\">\n";
    echo '<meta property="og:type" content="' . ( is_singular() ? 'article' : 'website' ) . "\">\n";
    echo '<meta property="og:url" content="' . esc_url( home_url( add_query_arg( null, null ) ) ) . "\">\n";
    if ( has_post_thumbnail() ) {
        echo '<meta property="og:image" content="' . esc_url( get_the_post_thumbnail_url( null, 'large' ) ) . "\">\n";
    }
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
}, 1 );
