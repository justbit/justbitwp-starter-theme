<?php
/**
 * Performance helpers.
 *
 * - Defer dei JS non critici (ignora `jquery-core` per backcompat plugin)
 * - Rimuove emoji script di default (50 KB risparmiati)
 * - Preload del font display se presente
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Remove emoji
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );

// Defer non-critical JS
add_filter( 'script_loader_tag', function( $tag, $handle, $src ) {
    if ( is_admin() ) return $tag;
    $skip = [ 'jquery-core', 'jquery-migrate' ];
    if ( in_array( $handle, $skip, true ) ) return $tag;
    if ( strpos( $tag, 'defer' ) !== false ) return $tag;
    return str_replace( ' src=', ' defer src=', $tag );
}, 10, 3 );

// Preload font display se esiste
add_action( 'wp_head', function() {
    $font = JBW_THEME_DIR . '/assets/fonts/display.woff2';
    if ( file_exists( $font ) ) {
        echo '<link rel="preload" href="' . esc_url( JBW_THEME_URI . '/assets/fonts/display.woff2' ) .
             '" as="font" type="font/woff2" crossorigin>' . "\n";
    }
}, 2 );
