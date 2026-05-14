<?php
/**
 * PWA — Progressive Web App support.
 *
 * Componenti:
 *   1. `manifest.json` servito da `/manifest.json` (rewrite) o `<link rel="manifest">`
 *   2. Service Worker `/sw.js` (offline-first per shell + cache-first per assets)
 *   3. Pagina `/offline` minimale (mostrata quando SW intercetta una nav offline)
 *   4. Meta tag iOS (apple-touch-icon, status bar style)
 *
 * Per disabilitare in un progetto:
 *   add_filter( 'jbw_pwa_enabled', '__return_false' );
 *
 * @package justbitwp-starter
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! apply_filters( 'jbw_pwa_enabled', true ) ) return;

/**
 * Rewrite per servire /sw.js, /manifest.json, /offline a livello root
 * (i SW devono vivere alla root del path che vogliono controllare).
 */
add_action( 'init', function() {
    add_rewrite_rule( '^sw\.js$',       'index.php?jbw_sw=1', 'top' );
    add_rewrite_rule( '^manifest\.json$','index.php?jbw_manifest=1', 'top' );
    add_rewrite_rule( '^offline/?$',    'index.php?jbw_offline=1', 'top' );
} );

add_filter( 'query_vars', function( $vars ) {
    $vars[] = 'jbw_sw';
    $vars[] = 'jbw_manifest';
    $vars[] = 'jbw_offline';
    return $vars;
} );

/**
 * Router custom — intercetta le tre rewrite rules sopra.
 */
add_action( 'template_redirect', function() {
    if ( get_query_var( 'jbw_sw' ) ) {
        header( 'Content-Type: application/javascript; charset=utf-8' );
        header( 'Cache-Control: no-cache, must-revalidate' );
        header( 'Service-Worker-Allowed: /' );
        readfile( JBW_THEME_DIR . '/assets/js/sw.js' );
        exit;
    }
    if ( get_query_var( 'jbw_manifest' ) ) {
        header( 'Content-Type: application/manifest+json; charset=utf-8' );
        echo wp_json_encode( jbw_pwa_manifest_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        exit;
    }
    if ( get_query_var( 'jbw_offline' ) ) {
        status_header( 200 );
        nocache_headers();
        include JBW_THEME_DIR . '/templates/offline.php';
        exit;
    }
} );

/**
 * Manifest data: title, short_name, theme_color, icons.
 * Override-abile via filter `jbw_pwa_manifest` da un progetto specifico.
 */
function jbw_pwa_manifest_data(): array {
    $name = get_bloginfo( 'name' );
    $desc = get_bloginfo( 'description' );

    return apply_filters( 'jbw_pwa_manifest', [
        'name'             => $name,
        'short_name'       => mb_substr( $name, 0, 12 ),
        'description'      => $desc,
        'start_url'        => '/',
        'scope'            => '/',
        'display'          => 'standalone',
        'orientation'      => 'portrait',
        'lang'             => str_replace( '_', '-', get_locale() ),
        'theme_color'      => '#1a3a52',
        'background_color' => '#fdfbf6',
        'icons'            => [
            [ 'src' => JBW_THEME_URI . '/assets/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable' ],
            [ 'src' => JBW_THEME_URI . '/assets/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable' ],
        ],
    ] );
}

/**
 * Inietta in <head>: link al manifest, theme-color, registrazione SW, meta iOS.
 */
add_action( 'wp_head', function() {
    $tc = apply_filters( 'jbw_pwa_theme_color', '#1a3a52' );
    echo '<link rel="manifest" href="/manifest.json">' . "\n";
    echo '<meta name="theme-color" content="' . esc_attr( $tc ) . '">' . "\n";
    echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
    echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n";
    echo '<meta name="apple-mobile-web-app-title" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
    if ( file_exists( JBW_THEME_DIR . '/assets/icons/apple-touch-icon.png' ) ) {
        echo '<link rel="apple-touch-icon" href="' . esc_url( JBW_THEME_URI . '/assets/icons/apple-touch-icon.png' ) . '">' . "\n";
    }
}, 5 );

/**
 * SW registration script — minimo, fine-page, no blocking.
 */
add_action( 'wp_footer', function() {
    ?>
<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function() {
    navigator.serviceWorker.register('/sw.js', { scope: '/' })
      .catch(function(err) { console.warn('SW registration failed:', err); });
  });
}
</script>
    <?php
}, 99 );
