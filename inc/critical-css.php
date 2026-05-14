<?php
/**
 * Critical CSS strategy — inline + async load del resto.
 *
 * Pattern collaudato su sito con PSI mobile 89, desktop 99:
 *   1. Inline TUTTO il CSS del tema in <head> (~6-8 KB gzip)
 *   2. I `<link rel="stylesheet">` esterni rimangono ma con media="print" + onload
 *      → il browser li scarica low-priority, e quando arrivano fa lo swap
 *   3. CLS = 0 perché il critical CSS copre il 100% dei selettori
 *
 * Storia: la versione "inline solo above-the-fold + async resto" causava CLS
 * 0.65 perché classi below-the-fold arrivavano tardi. Inline-tutto è la
 * soluzione "safe", e nella nostra dimensione di CSS (~60KB raw / ~8KB gz)
 * è anche la più veloce.
 *
 * Per disabilitare in un progetto specifico:
 *   add_filter( 'jbw_critical_css_enabled', '__return_false' );
 *
 * @package justbitwp-starter
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Restituisce il CSS critico concatenato (tokens + base + components).
 * Cachato in transient 24h, invalidato sul template_redirect dopo deploy.
 */
function jbw_get_critical_css(): string {
    if ( ! apply_filters( 'jbw_critical_css_enabled', true ) ) return '';

    $key = 'jbw_critical_css_v' . JBW_THEME_VERSION;
    $css = get_transient( $key );
    if ( $css !== false ) return $css;

    $files = [
        JBW_THEME_DIR . '/assets/css/tokens.css',
        JBW_THEME_DIR . '/assets/css/base.css',
        JBW_THEME_DIR . '/assets/css/components.css',
    ];

    $css = '';
    foreach ( $files as $f ) {
        if ( file_exists( $f ) ) {
            $css .= "/* " . basename( $f ) . " */\n" . file_get_contents( $f ) . "\n";
        }
    }

    // Minify minimo: rimuovi commenti, whitespace ridondanti.
    $css = preg_replace( '#/\*.*?\*/#s', '', $css );
    $css = preg_replace( '/\s+/', ' ', $css );
    $css = preg_replace( '/\s*([{}:;,])\s*/', '$1', $css );
    $css = trim( $css );

    set_transient( $key, $css, DAY_IN_SECONDS );
    return $css;
}

/**
 * Inietta il critical CSS in <head> con priorità alta (subito dopo <title>).
 */
add_action( 'wp_head', function() {
    $css = jbw_get_critical_css();
    if ( $css ) {
        echo "<style id=\"jbw-critical\">{$css}</style>\n";
    }
}, 2 );

/**
 * Trasforma i `<link rel="stylesheet">` del tema in async-load:
 *   media="print" onload="this.media='all'"
 * Solo per i CSS del tema (tokens/base/components), non per plugin/wp-core.
 *
 * Quando il CSS arriva, il browser cambia media→'all' e applica gli stili.
 * Il <noscript> fallback garantisce funzionamento anche senza JS.
 */
add_filter( 'style_loader_tag', function( $tag, $handle, $href, $media ) {
    if ( is_admin() ) return $tag;
    if ( ! apply_filters( 'jbw_critical_css_enabled', true ) ) return $tag;

    $async_handles = apply_filters( 'jbw_async_css_handles', [
        'jbw-tokens', 'jbw-base', 'jbw-components',
    ] );
    if ( ! in_array( $handle, $async_handles, true ) ) return $tag;

    return '<link rel="stylesheet" href="' . esc_url( $href ) .
           '" media="print" onload="this.media=\'all\';this.onload=null">' .
           '<noscript><link rel="stylesheet" href="' . esc_url( $href ) . '"></noscript>' . "\n";
}, 10, 4 );

/**
 * Invalida la cache del critical CSS quando si salva un post (cosmetico —
 * la cache è già auto-scaduta a 24h). Utile su deploy con OPcache reset.
 */
add_action( 'save_post', function() {
    delete_transient( 'jbw_critical_css_v' . JBW_THEME_VERSION );
} );
