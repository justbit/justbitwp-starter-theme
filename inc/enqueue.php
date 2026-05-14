<?php
/**
 * Enqueue di asset front-end (CSS + JS + fonts).
 *
 * - tokens.css     → CSS custom properties (sincronizzate con theme.json)
 * - base.css       → reset + tipografia + accessibility safety nets
 * - components.css → button, card, tag, breadcrumb
 *
 * Le `*.woff2` self-hosted vivono in assets/fonts/. Se assenti, il
 * theme.json fa cadere il browser su font-family fallback (Georgia, system-ui).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_enqueue_scripts', function() {
    $v = JBW_THEME_VERSION;
    wp_enqueue_style( 'jbw-tokens',     JBW_THEME_URI . '/assets/css/tokens.css',     [], $v );
    wp_enqueue_style( 'jbw-base',       JBW_THEME_URI . '/assets/css/base.css',       [ 'jbw-tokens' ], $v );
    wp_enqueue_style( 'jbw-components', JBW_THEME_URI . '/assets/css/components.css', [ 'jbw-base' ], $v );
} );
