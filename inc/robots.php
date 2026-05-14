<?php
/**
 * robots.txt dinamico — controllo indicizzazione lato crawler.
 *
 * Lo legge da:
 *   - env `ROBOTS_DIRECTIVES` (in .env, da .env.example dello stack)
 *   - env `MAINTENANCE_MODE` (se 1, force `Disallow: /`)
 *
 * Pattern tipico:
 *   - Pre-launch:  ROBOTS_DIRECTIVES="Disallow: /"   (no index, neanche staging)
 *   - Go-live:     ROBOTS_DIRECTIVES="Allow: /"      (apri a tutti i crawler)
 *
 * Punta alla sitemap automatica:
 *   - Yoast attivo → /sitemap_index.xml
 *   - Altrimenti   → /sitemap.xml
 *
 * @package justbitwp-starter
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'robots_txt', function( $output, $public ) {
    // Maintenance mode → blocca tutto, qualsiasi cosa dica il sito public flag
    if ( jbw_in_maintenance_mode() ) {
        return "User-agent: *\nDisallow: /\n";
    }

    // Search Engine Optimization off (Settings → Reading) → standard WP behaviour
    if ( $public !== '1' ) return $output;

    $directives = getenv( 'ROBOTS_DIRECTIVES' ) ?: 'Allow: /';
    $directives = apply_filters( 'jbw_robots_directives', $directives );

    $sitemap = defined( 'WPSEO_VERSION' )
        ? home_url( '/sitemap_index.xml' )
        : home_url( '/sitemap.xml' );

    $txt = "User-agent: *\n";
    $txt .= trim( $directives ) . "\n";

    // Disallow paths comuni che NON vogliamo indicizzati
    $disallow = apply_filters( 'jbw_robots_disallow', [
        '/wp-admin/',
        '/wp-login.php',
        '/xmlrpc.php',
        '/?s=',         // search results
        '/feed/',       // RSS
        '/trackback/',
        '/wp-json/wp/v2/users', // anti user enumeration belt+suspenders
    ] );
    foreach ( $disallow as $path ) {
        $txt .= 'Disallow: ' . $path . "\n";
    }

    $txt .= "\nAllow: /wp-admin/admin-ajax.php\n";
    $txt .= "\nSitemap: " . esc_url( $sitemap ) . "\n";

    return $txt;
}, 10, 2 );

if ( ! function_exists( 'jbw_in_maintenance_mode' ) ) {
    function jbw_in_maintenance_mode(): bool {
        $env = getenv( 'MAINTENANCE_MODE' );
        if ( $env === '1' || $env === 'true' ) return true;
        return defined( 'MAINTENANCE_MODE' ) && constant( 'MAINTENANCE_MODE' );
    }
}
