<?php
/**
 * Sitemap XML — `/sitemap.xml` per i crawler (Google, Bing, Yandex, ecc.).
 *
 * Strategia "deferisci ai professionisti":
 *   - Se Yoast SEO è attivo: NON facciamo nulla — Yoast genera una sitemap
 *     molto più ricca (con immagini, video, hreflang, last-modified accurato).
 *     Yoast la serve a `/sitemap_index.xml`.
 *   - Se Yoast NON è attivo: serviamo una sitemap minimale a `/sitemap.xml`
 *     con homepage + post pubblicati + page pubbliche.
 *
 * WordPress 5.5+ ha una sitemap nativa a `/wp-sitemap.xml` ma ha 2 problemi:
 *   1. include `attachment` di default (rumore)
 *   2. non rispetta `MAINTENANCE_MODE` (mostra sitemap anche se sito offline)
 * Quindi la disabilitiamo e serviamo la nostra.
 *
 * Comportamento opt-out:
 *   add_filter( 'jbw_sitemap_enabled', '__return_false' );
 *
 * @package justbitwp-starter
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Disabilita la sitemap nativa di WP (la sostituiamo o lascia che Yoast la faccia)
add_filter( 'wp_sitemaps_enabled', '__return_false' );

// Skip se Yoast è attivo — Yoast fa una sitemap migliore
add_action( 'init', function() {
    if ( defined( 'WPSEO_VERSION' ) ) return;
    if ( ! apply_filters( 'jbw_sitemap_enabled', true ) ) return;

    add_rewrite_rule( '^sitemap\.xml$', 'index.php?jbw_sitemap=1', 'top' );
} );

add_filter( 'query_vars', function( $vars ) {
    $vars[] = 'jbw_sitemap';
    return $vars;
} );

add_action( 'template_redirect', function() {
    if ( ! get_query_var( 'jbw_sitemap' ) ) return;
    if ( defined( 'WPSEO_VERSION' ) ) return;

    // Maintenance mode → sitemap vuota (no-leak di URL)
    if ( jbw_in_maintenance_mode() ) {
        status_header( 200 );
        header( 'Content-Type: application/xml; charset=utf-8' );
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/0.9"></urlset>';
        exit;
    }

    status_header( 200 );
    header( 'Content-Type: application/xml; charset=utf-8' );
    header( 'Cache-Control: public, max-age=3600' ); // 1h CDN
    echo jbw_render_sitemap();
    exit;
} );

/**
 * Genera la sitemap XML.
 * Lista: homepage + post + page + CPT pubblici (eccetto attachment).
 * Cachata in transient per 1h.
 */
function jbw_render_sitemap(): string {
    $cache_key = 'jbw_sitemap_v' . JBW_THEME_VERSION;
    $cached = get_transient( $cache_key );
    if ( $cached !== false ) return $cached;

    $home = home_url( '/' );
    $now  = gmdate( 'c' );

    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/0.9" ' .
            'xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

    // 1. Homepage (priority 1.0)
    $xml .= jbw_sitemap_url( $home, $now, 'daily', '1.0' );

    // 2. Tutti i post type pubblici (post + page + CPT custom)
    $post_types = get_post_types( [ 'public' => true, 'exclude_from_search' => false ], 'names' );
    unset( $post_types['attachment'] ); // mai gli allegati in sitemap

    $post_types = apply_filters( 'jbw_sitemap_post_types', array_values( $post_types ) );

    if ( ! empty( $post_types ) ) {
        $posts = get_posts( [
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => 1000, // limite ragionevole — per più, paginare
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'no_found_rows'  => true,
        ] );

        foreach ( $posts as $p ) {
            $url      = get_permalink( $p );
            if ( ! $url || $url === $home ) continue;
            $modified = mysql2date( 'c', $p->post_modified_gmt, false );
            $priority = $p->post_type === 'page' ? '0.7' : '0.6';
            $freq     = $p->post_type === 'post' ? 'weekly' : 'monthly';

            $alternates = jbw_sitemap_hreflang_alternates( $p );
            $xml .= jbw_sitemap_url( $url, $modified, $freq, $priority, $alternates );
        }
    }

    $xml .= '</urlset>';

    set_transient( $cache_key, $xml, HOUR_IN_SECONDS );
    return $xml;
}

function jbw_sitemap_url( string $loc, string $lastmod, string $changefreq, string $priority, array $alternates = [] ): string {
    $xml = "  <url>\n";
    $xml .= '    <loc>' . esc_url( $loc ) . "</loc>\n";
    $xml .= '    <lastmod>' . esc_html( $lastmod ) . "</lastmod>\n";
    $xml .= '    <changefreq>' . esc_html( $changefreq ) . "</changefreq>\n";
    $xml .= '    <priority>' . esc_html( $priority ) . "</priority>\n";
    foreach ( $alternates as $lang => $alt_url ) {
        $xml .= '    <xhtml:link rel="alternate" hreflang="' . esc_attr( $lang ) . '" href="' . esc_url( $alt_url ) . "\"/>\n";
    }
    $xml .= "  </url>\n";
    return $xml;
}

/**
 * Multilingua (Polylang/WPML-aware): ritorna gli URL alternativi per ogni
 * lingua. Se nessun plugin multilingua attivo, ritorna array vuoto.
 */
function jbw_sitemap_hreflang_alternates( WP_Post $post ): array {
    $alts = [];

    // Polylang
    if ( function_exists( 'pll_get_post_translations' ) && function_exists( 'pll_languages_list' ) ) {
        $translations = pll_get_post_translations( $post->ID );
        foreach ( $translations as $lang => $tr_id ) {
            $tr_url = get_permalink( $tr_id );
            if ( $tr_url ) $alts[ $lang ] = $tr_url;
        }
    }
    // WPML
    elseif ( function_exists( 'icl_object_id' ) && function_exists( 'apply_filters' ) ) {
        $langs = apply_filters( 'wpml_active_languages', null );
        if ( is_array( $langs ) ) {
            foreach ( $langs as $lang_code => $info ) {
                $tr_id = apply_filters( 'wpml_object_id', $post->ID, $post->post_type, false, $lang_code );
                if ( $tr_id ) {
                    $tr_url = get_permalink( $tr_id );
                    if ( $tr_url ) $alts[ $lang_code ] = $tr_url;
                }
            }
        }
    }

    return apply_filters( 'jbw_sitemap_hreflang_alternates', $alts, $post );
}

/**
 * Helper: il sito è in maintenance? Legge env MAINTENANCE_MODE (default 0).
 */
function jbw_in_maintenance_mode(): bool {
    $env = getenv( 'MAINTENANCE_MODE' );
    if ( $env === '1' || $env === 'true' ) return true;
    return defined( 'MAINTENANCE_MODE' ) && constant( 'MAINTENANCE_MODE' );
}

/**
 * Invalida la cache sitemap sui save_post / delete_post (cosmetico: la
 * cache scade comunque a 1h).
 */
add_action( 'save_post',   fn() => delete_transient( 'jbw_sitemap_v' . JBW_THEME_VERSION ) );
add_action( 'delete_post', fn() => delete_transient( 'jbw_sitemap_v' . JBW_THEME_VERSION ) );
