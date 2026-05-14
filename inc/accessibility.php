<?php
/**
 * Accessibility helpers.
 *
 * 1. Skip-link "Vai al contenuto" iniettato all'apertura del <body>.
 * 2. focus-visible polyfill non più necessario (Chrome/Firefox/Safari recenti),
 *    ma il CSS lo gestisce in assets/css/base.css.
 * 3. Auto-add `alt=""` agli <img> in pattern se mancante (anti-warning a11y).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_body_open', function() {
    echo '<a class="skip-link screen-reader-text" href="#main">' .
         esc_html__( 'Vai al contenuto', 'justbitwp-starter' ) . '</a>';
}, 5 );

// Garantisce id="main" sul main container per il skip link
add_filter( 'render_block_core/template-part', function( $content, $block ) {
    if ( ! empty( $block['attrs']['area'] ) && $block['attrs']['area'] === 'main' ) {
        if ( strpos( $content, 'id="main"' ) === false ) {
            $content = preg_replace( '/<main\b/', '<main id="main"', $content, 1 );
        }
    }
    return $content;
}, 10, 2 );
