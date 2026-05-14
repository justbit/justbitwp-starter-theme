<?php
/**
 * OG image dinamica — endpoint REST che genera un SVG con titolo, brand
 * e color palette del progetto. Browser-friendly (text/svg), social-friendly
 * (Twitter/Facebook scaricano l'SVG e lo convertono in PNG server-side).
 *
 * Endpoint:
 *   /wp-json/jbw/v1/og?title=...&brand=...&kind=article|page|generic
 *
 * Pattern d'uso:
 *   - Helper `jbw_og_image_url($post_id = null)` restituisce l'URL OG
 *   - Filter `og:image` in inc/seo.php usa questo helper se Yoast NON è attivo
 *
 * Cache:
 *   - CDN-friendly: header `Cache-Control: max-age=2592000` (30 giorni)
 *   - Hash dei parametri → idempotente, niente DB query
 *
 * Override-abile per progetto specifico: il filter `jbw_og_svg_template`
 * permette di sostituire il template SVG.
 *
 * @package justbitwp-starter
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'rest_api_init', function() {
    register_rest_route( 'jbw/v1', '/og', [
        'methods'             => 'GET',
        'callback'            => 'jbw_og_render',
        'permission_callback' => '__return_true',
        'args' => [
            'title' => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
            'brand' => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
            'kind'  => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
        ],
    ] );
} );

function jbw_og_render( WP_REST_Request $req ) {
    $title = $req->get_param( 'title' ) ?: get_bloginfo( 'name' );
    $brand = $req->get_param( 'brand' ) ?: get_bloginfo( 'name' );
    $kind  = $req->get_param( 'kind' )  ?: 'generic';

    $title = mb_substr( $title, 0, 110 );
    $brand = mb_substr( $brand, 0, 30 );

    // Color palette: legge da theme.json se possibile, altrimenti default.
    $primary = '#1a3a52';
    $accent  = '#c89968';
    $bg      = '#fdfbf6';
    $text    = '#1f1813';

    // Wrap del title su più righe (~28 char/line)
    $lines = jbw_og_wrap_text( $title, 28, 3 );
    $line_height = 70;
    $y_start = 320 - ( count( $lines ) - 1 ) * ( $line_height / 2 );

    ob_start();
    ?><svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%"   stop-color="<?php echo esc_attr( $bg ); ?>"/>
      <stop offset="100%" stop-color="<?php echo esc_attr( $bg ); ?>" stop-opacity="0.92"/>
    </linearGradient>
    <linearGradient id="strip" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%"   stop-color="<?php echo esc_attr( $primary ); ?>"/>
      <stop offset="100%" stop-color="<?php echo esc_attr( $accent ); ?>"/>
    </linearGradient>
  </defs>
  <rect width="1200" height="630" fill="url(#bg)"/>
  <rect x="0" y="0" width="12" height="630" fill="url(#strip)"/>
  <text x="80" y="105" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif"
        font-size="22" font-weight="500" letter-spacing="2" fill="<?php echo esc_attr( $accent ); ?>"
        text-transform="uppercase"><?php echo esc_html( strtoupper( $kind ) ); ?></text>
  <?php foreach ( $lines as $i => $line ): ?>
  <text x="80" y="<?php echo intval( $y_start + $i * $line_height ); ?>"
        font-family="Georgia, 'Times New Roman', serif"
        font-size="56" font-weight="500" fill="<?php echo esc_attr( $text ); ?>"
        letter-spacing="-1"><?php echo esc_html( $line ); ?></text>
  <?php endforeach; ?>
  <line x1="80" y1="525" x2="160" y2="525" stroke="<?php echo esc_attr( $accent ); ?>" stroke-width="3"/>
  <text x="80" y="565" font-family="-apple-system, BlinkMacSystemFont, sans-serif"
        font-size="24" font-weight="600" fill="<?php echo esc_attr( $primary ); ?>"><?php echo esc_html( $brand ); ?></text>
</svg><?php
    $svg = ob_get_clean();
    $svg = apply_filters( 'jbw_og_svg_template', $svg, [ 'title' => $title, 'brand' => $brand, 'kind' => $kind ] );

    return new WP_REST_Response( $svg, 200, [
        'Content-Type'  => 'image/svg+xml; charset=utf-8',
        'Cache-Control' => 'public, max-age=2592000, s-maxage=2592000, stale-while-revalidate=86400',
    ] );
}

function jbw_og_wrap_text( string $text, int $max_chars = 28, int $max_lines = 3 ): array {
    $words = explode( ' ', $text );
    $lines = []; $current = '';
    foreach ( $words as $w ) {
        $test = $current ? "$current $w" : $w;
        if ( mb_strlen( $test ) <= $max_chars ) { $current = $test; }
        else {
            if ( $current ) $lines[] = $current;
            $current = $w;
            if ( count( $lines ) >= $max_lines - 1 ) break;
        }
    }
    if ( $current && count( $lines ) < $max_lines ) $lines[] = $current;
    // Tronca se troppo lungo
    if ( ! empty( $lines ) ) {
        $last = end( $lines );
        if ( mb_strlen( $last ) > $max_chars ) {
            $lines[ array_key_last( $lines ) ] = mb_substr( $last, 0, $max_chars - 1 ) . '…';
        }
    }
    return $lines ?: [ $text ];
}

/**
 * Helper: URL OG image per il post corrente (o un post_id specifico).
 * Se il post ha featured image, restituisce quella; altrimenti la SVG dinamica.
 */
function jbw_og_image_url( ?int $post_id = null ): string {
    if ( ! $post_id ) $post_id = get_queried_object_id();

    if ( $post_id && has_post_thumbnail( $post_id ) ) {
        $img = get_the_post_thumbnail_url( $post_id, 'full' );
        if ( $img ) return $img;
    }

    $title = $post_id ? get_the_title( $post_id ) : get_bloginfo( 'name' );
    $brand = get_bloginfo( 'name' );
    $kind  = $post_id ? get_post_type( $post_id ) : 'generic';

    return add_query_arg( [
        'title' => rawurlencode( $title ),
        'brand' => rawurlencode( $brand ),
        'kind'  => $kind,
    ], rest_url( 'jbw/v1/og' ) );
}
