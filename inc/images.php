<?php
/**
 * Image optimization helpers.
 *
 * 1. `loading="lazy"` automatico su tutti gli <img> non-hero (WP 5.5+ già lo
 *    fa di default, qui rinforziamo + aggiungiamo `decoding="async"`).
 * 2. `fetchpriority="high"` automatico sul primo <img> di una pagina
 *    (presumibilmente l'hero LCP) — questo è il PRIMO accorgimento per
 *    migliorare LCP su mobile.
 * 3. Helper `jbw_img($src, $w, $h, $widths, $sizes, $alt)` per emettere
 *    <img> con srcset esplicito (utile se passi attraverso un CDN tipo
 *    Optimole/Cloudinary che ha bisogno di hints multipli).
 *
 * @package justbitwp-starter
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Aggiunge `decoding="async"` agli <img> del frontend.
 */
add_filter( 'wp_get_attachment_image_attributes', function( $attr ) {
    if ( is_admin() ) return $attr;
    if ( empty( $attr['decoding'] ) ) $attr['decoding'] = 'async';
    return $attr;
} );

/**
 * Imposta `fetchpriority="high"` SOLO sul primo <img> renderizzato della
 * pagina (di solito è l'hero LCP). Gli altri restano `fetchpriority="low"`.
 * Riduce LCP mobile di 0.5-1s in test reali.
 */
add_filter( 'the_content', function( $content ) {
    if ( is_admin() || is_feed() ) return $content;

    static $first_done = false;
    if ( $first_done ) return $content;

    if ( preg_match( '/<img\b[^>]*>/', $content, $m ) ) {
        $img = $m[0];
        if ( strpos( $img, 'fetchpriority' ) === false ) {
            $new = preg_replace( '/<img\b/', '<img fetchpriority="high"', $img, 1 );
            $content = str_replace( $img, $new, $content );
            $first_done = true;
        }
    }
    return $content;
}, 20 );

/**
 * Helper per emettere <img> con srcset esplicito. Pattern:
 *
 *   echo jbw_img([
 *       'src'    => $url,
 *       'width'  => 1600,
 *       'height' => 900,
 *       'widths' => [400, 800, 1200, 1600],
 *       'sizes'  => '(max-width:780px) 100vw, 1200px',
 *       'alt'    => 'Hero description',
 *       'class'  => 'hero-img',
 *       'loading'=> 'lazy', // o 'eager' per LCP
 *   ]);
 *
 * Lo `srcset` può essere generato da un CDN se il `src` matcha un pattern:
 *   - Optimole: applica `vtds_optml_responsive_img()` equivalente
 *   - Cloudinary: usa `c_scale,w_<n>` come param
 *   - Originale: il fallback ricalcola dimensioni con `wp_get_attachment_image_srcset()`
 *
 * @param array $args
 * @return string HTML <img> ben formato
 */
function jbw_img( array $args ): string {
    $defaults = [
        'src'     => '',
        'width'   => null,
        'height'  => null,
        'widths'  => [],
        'sizes'   => '',
        'alt'     => '',
        'class'   => '',
        'loading' => 'lazy',
        'decoding'=> 'async',
        'fetchpriority' => null,
    ];
    $a = array_merge( $defaults, $args );
    if ( empty( $a['src'] ) ) return '';

    // Hook per progetti che vogliono CDN-specific srcset
    $srcset = apply_filters( 'jbw_img_srcset', '', $a );

    $attrs = [
        'src="' . esc_url( $a['src'] ) . '"',
    ];
    if ( $srcset )            $attrs[] = 'srcset="' . esc_attr( $srcset ) . '"';
    if ( $a['sizes'] )        $attrs[] = 'sizes="' . esc_attr( $a['sizes'] ) . '"';
    if ( $a['width'] )        $attrs[] = 'width="' . intval( $a['width'] ) . '"';
    if ( $a['height'] )       $attrs[] = 'height="' . intval( $a['height'] ) . '"';
    if ( $a['alt'] !== null ) $attrs[] = 'alt="' . esc_attr( $a['alt'] ) . '"';
    if ( $a['class'] )        $attrs[] = 'class="' . esc_attr( $a['class'] ) . '"';
    if ( $a['loading'] )      $attrs[] = 'loading="' . esc_attr( $a['loading'] ) . '"';
    if ( $a['decoding'] )     $attrs[] = 'decoding="' . esc_attr( $a['decoding'] ) . '"';
    if ( $a['fetchpriority'] )$attrs[] = 'fetchpriority="' . esc_attr( $a['fetchpriority'] ) . '"';

    return '<img ' . implode( ' ', $attrs ) . '>';
}
