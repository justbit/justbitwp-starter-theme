<?php
/**
 * SEO base — meta description, OG tags, robots meta, hreflang multilingua.
 *
 * Yoast SEO (se installato) sovrascrive questi tag. Sono fallback minimi
 * per quando Yoast non è attivo.
 *
 * Cosa fa:
 *   1. <meta name="description"> da excerpt/bloginfo
 *   2. <meta property="og:*"> per Facebook/LinkedIn share
 *   3. <meta name="twitter:card">
 *   4. <meta name="robots"> — noindex su: search, attachment, maintenance,
 *      paginated pages oltre la 2, archivi vuoti
 *   5. <link rel="alternate" hreflang> per multilingua (Polylang/WPML-aware)
 *
 * @package justbitwp-starter
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
    echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . "\">\n";
    echo '<meta property="og:locale" content="' . esc_attr( str_replace( '_', '-', get_locale() ) ) . "\">\n";

    // OG image: featured image se c'è, altrimenti SVG dinamica (vedi inc/og-image.php).
    if ( function_exists( 'jbw_og_image_url' ) ) {
        echo '<meta property="og:image" content="' . esc_url( jbw_og_image_url() ) . "\">\n";
        echo '<meta property="og:image:width" content="1200">' . "\n";
        echo '<meta property="og:image:height" content="630">' . "\n";
    } elseif ( has_post_thumbnail() ) {
        echo '<meta property="og:image" content="' . esc_url( get_the_post_thumbnail_url( null, 'large' ) ) . "\">\n";
    }
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
}, 1 );

/**
 * meta robots: gestione granulare di noindex/nofollow.
 *
 * Quando emettere noindex:
 *   - sito in MAINTENANCE_MODE
 *   - pagine di search (?s=)
 *   - allegati (?attachment_id=)
 *   - pagine paginate >2 (page/3+ ha valore SEO basso)
 *   - archivi vuoti
 *   - admin
 *
 * Yoast-aware: silente se Yoast attivo.
 */
add_action( 'wp_head', function() {
    if ( defined( 'WPSEO_VERSION' ) ) return;
    if ( is_admin() ) return;

    $directives = [];

    if ( jbw_in_maintenance_mode() ) {
        $directives = [ 'noindex', 'nofollow' ];
    } elseif ( is_search() ) {
        $directives = [ 'noindex', 'follow' ];
    } elseif ( is_attachment() ) {
        $directives = [ 'noindex', 'follow' ];
    } elseif ( is_paged() && get_query_var( 'paged' ) > 2 ) {
        $directives = [ 'noindex', 'follow' ];
    } elseif ( ( is_category() || is_tag() || is_tax() ) && ! have_posts() ) {
        $directives = [ 'noindex', 'follow' ];
    }

    $directives = apply_filters( 'jbw_robots_meta', $directives );
    if ( empty( $directives ) ) return;

    echo '<meta name="robots" content="' . esc_attr( implode( ', ', $directives ) ) . '">' . "\n";
}, 2 );

/**
 * hreflang — per siti multilingua (Polylang/WPML).
 *
 * Emette `<link rel="alternate" hreflang="xx" href="...">` per ogni
 * traduzione disponibile della pagina corrente, più l'`hreflang="x-default"`
 * che punta alla lingua di default.
 *
 * Silente se Yoast attivo (Yoast Premium fa lo stesso, più sofisticato).
 */
add_action( 'wp_head', function() {
    if ( defined( 'WPSEO_VERSION' ) ) return;

    $alts = [];

    // Polylang
    if ( function_exists( 'pll_the_languages' ) && function_exists( 'pll_get_post_translations' ) ) {
        $post_id = get_queried_object_id();
        if ( $post_id ) {
            $translations = pll_get_post_translations( $post_id );
            foreach ( $translations as $lang => $tr_id ) {
                $url = get_permalink( $tr_id );
                if ( $url ) $alts[ $lang ] = $url;
            }
        }
    }
    // WPML
    elseif ( function_exists( 'apply_filters' ) ) {
        $langs = apply_filters( 'wpml_active_languages', null );
        if ( is_array( $langs ) ) {
            foreach ( $langs as $code => $info ) {
                if ( ! empty( $info['url'] ) ) $alts[ $code ] = $info['url'];
            }
        }
    }

    if ( empty( $alts ) ) return;

    foreach ( $alts as $lang => $url ) {
        echo '<link rel="alternate" hreflang="' . esc_attr( $lang ) . '" href="' . esc_url( $url ) . '">' . "\n";
    }
    // x-default = prima lingua nella lista (di solito la default)
    $default = reset( $alts );
    echo '<link rel="alternate" hreflang="x-default" href="' . esc_url( $default ) . '">' . "\n";
}, 3 );

/**
 * Helper condiviso (anche in sitemap.php, robots.php).
 */
if ( ! function_exists( 'jbw_in_maintenance_mode' ) ) {
    function jbw_in_maintenance_mode(): bool {
        $env = getenv( 'MAINTENANCE_MODE' );
        if ( $env === '1' || $env === 'true' ) return true;
        return defined( 'MAINTENANCE_MODE' ) && constant( 'MAINTENANCE_MODE' );
    }
}
