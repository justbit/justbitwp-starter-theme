<?php
/**
 * Canonical URL — `<link rel="canonical">` per evitare duplicate content.
 *
 * WP nativo emette `rel_canonical` di base ma con limiti:
 *   - paginazione: canonical resta sempre = page 1, anche su /page/2/
 *     → problema: Google deve sapere che /page/2/ è una versione paginata
 *   - search results: canonical = /search/, ma con `noindex` (gestito da seo.php)
 *   - attachment pages: canonical = post genitore (best practice)
 *
 * Questo modulo:
 *   - Silente se Yoast SEO è attivo (Yoast gestisce meglio)
 *   - Forza canonical = paginated URL su /page/N/ (correzione vs WP default)
 *   - Strip query params che non sono "canonical" (?utm_*, ?fbclid, ?gclid)
 *
 * @package justbitwp-starter
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Se Yoast attivo → skip tutto
if ( defined( 'WPSEO_VERSION' ) ) return;

/**
 * Override rel_canonical per gestire paginazione correttamente.
 */
add_filter( 'get_canonical_url', function( $url, $post ) {
    if ( ! is_paged() ) return $url;
    // Su /page/N/ il canonical dev'essere /page/N/, non /
    $paged = get_query_var( 'paged' );
    if ( $paged > 1 ) {
        $url = trailingslashit( $url ) . "page/{$paged}/";
    }
    return $url;
}, 10, 2 );

/**
 * Emette canonical anche su archivi (categoria/tag/CPT archive) e su
 * home — WP nativo non lo fa di default.
 */
add_action( 'wp_head', function() {
    if ( is_singular() ) return; // WP nativo lo emette già su singolari

    $canonical = '';

    if ( is_home() || is_front_page() ) {
        $canonical = home_url( '/' );
    } elseif ( is_category() || is_tag() || is_tax() ) {
        $canonical = get_term_link( get_queried_object() );
    } elseif ( is_post_type_archive() ) {
        $canonical = get_post_type_archive_link( get_query_var( 'post_type' ) );
    } elseif ( is_author() ) {
        $canonical = get_author_posts_url( get_queried_object_id() );
    } elseif ( is_date() ) {
        $canonical = is_year() ? get_year_link( get_query_var( 'year' ) )
                  : ( is_month() ? get_month_link( get_query_var( 'year' ), get_query_var( 'monthnum' ) )
                  : get_day_link( get_query_var( 'year' ), get_query_var( 'monthnum' ), get_query_var( 'day' ) ) );
    } elseif ( is_search() ) {
        // Search results: canonical = URL search "pulito"
        $canonical = home_url( '/?s=' . urlencode( get_search_query() ) );
    }

    if ( ! $canonical || is_wp_error( $canonical ) ) return;

    // Gestione paginazione anche per archivi
    $paged = get_query_var( 'paged' );
    if ( $paged > 1 ) {
        $canonical = trailingslashit( $canonical ) . "page/{$paged}/";
    }

    echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
}, 8 );

/**
 * Strip tracking query params dal canonical (utm_*, fbclid, gclid, ecc.).
 * Quando un visitatore arriva da fb.com/?fbclid=abc, il canonical che
 * emettiamo NON include `fbclid` — così Google sa che è la stessa pagina.
 */
add_filter( 'get_canonical_url', function( $url ) {
    $strip = apply_filters( 'jbw_canonical_strip_params', [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'fbclid', 'gclid', 'dclid', 'msclkid', 'mc_cid', 'mc_eid', 'ref',
    ] );
    return remove_query_arg( $strip, $url );
}, 9 );
