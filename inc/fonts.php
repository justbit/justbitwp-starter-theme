<?php
/**
 * Font loading strategy — self-hosted Variable Fonts con `font-display: swap`.
 *
 * Vantaggi:
 *   - Privacy GDPR (nessun IP inviato a Google)
 *   - Performance (no DNS resolution + TLS handshake verso fonts.gstatic.com)
 *   - Resilienza (CDN Google down ≠ tipografia rotta)
 *   - PSI 100 friendly (preload + display swap evitano FOUT/CLS)
 *
 * Convenzione:
 *   assets/fonts/display.woff2  → font display (es. Fraunces, Playfair, ecc.)
 *   assets/fonts/body.woff2     → font body (es. Inter, Manrope, Source Sans)
 *
 * Se i file NON esistono, questo modulo cade silenziosamente e i fontFamily
 * di theme.json usano i fallback di sistema. Per generare i woff2 vedi
 * `scripts/download-fonts.sh` nel template stack.
 *
 * Subset Latin: usa pyftsubset per ridurre del 70% (~350KB → ~100KB) le
 * Variable Fonts. Vedi DEPLOY.md sezione performance.
 *
 * @package justbitwp-starter
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Definisce le font-face self-hosted via @font-face dinamica.
 * Caricato in <head> prima degli style enqueue per arrivare presto al browser.
 */
add_action( 'wp_head', function() {
    $display_woff = JBW_THEME_DIR . '/assets/fonts/display.woff2';
    $body_woff    = JBW_THEME_DIR . '/assets/fonts/body.woff2';

    if ( ! file_exists( $display_woff ) && ! file_exists( $body_woff ) ) {
        return; // nessun font self-hosted disponibile → fallback di sistema
    }

    $display_url = JBW_THEME_URI . '/assets/fonts/display.woff2';
    $body_url    = JBW_THEME_URI . '/assets/fonts/body.woff2';

    // Preload del font display (visibile above-the-fold sui titoli)
    if ( file_exists( $display_woff ) ) {
        echo '<link rel="preload" href="' . esc_url( $display_url ) .
             '" as="font" type="font/woff2" crossorigin>' . "\n";
    }

    // CSS @font-face inline (no extra HTTP request)
    echo "<style id=\"jbw-fonts\">\n";

    if ( file_exists( $display_woff ) ) {
        echo "@font-face{font-family:'Display';font-style:normal;font-weight:300 700;font-display:swap;src:url('" .
             esc_url( $display_url ) . "') format('woff2-variations'),url('" .
             esc_url( $display_url ) . "') format('woff2')}\n";
    }
    if ( file_exists( $body_woff ) ) {
        echo "@font-face{font-family:'Body';font-style:normal;font-weight:300 700;font-display:swap;src:url('" .
             esc_url( $body_url ) . "') format('woff2-variations'),url('" .
             esc_url( $body_url ) . "') format('woff2')}\n";
    }

    echo "</style>\n";
}, 3 );

/**
 * Mappa i nomi 'Display' / 'Body' (definiti sopra) ai fontFamily di theme.json.
 * theme.json può dichiarare `font-family: 'Display', Georgia, serif` e questo
 * modulo si occupa di servire il woff2 quando esiste.
 *
 * Per personalizzare i nomi in un progetto specifico, override-a questo
 * modulo nel tema child o disattivalo con:
 *   add_filter( 'jbw_fonts_enabled', '__return_false' );
 */
add_filter( 'jbw_fonts_enabled', '__return_true' );
