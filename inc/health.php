<?php
/**
 * Health check endpoints — per monitoring esterno (UptimeRobot, BetterStack,
 * Healthchecks.io).
 *
 * Endpoint 1: `/health` (plain text, leggero, public)
 *   → "ok\n" se DB raggiungibile, status 200
 *   → "fail: <reason>\n" altrimenti, status 503
 *
 * Endpoint 2: `/wp-json/jbw/v1/health` (JSON, dettagliato, public)
 *   → { status, db, redis, php_version, wp_version, timestamp, response_ms }
 *
 * Entrambi bypassano page cache (No-Cache headers).
 *
 * @package justbitwp-starter
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', function() {
    add_rewrite_rule( '^health/?$', 'index.php?jbw_health=1', 'top' );
} );

add_filter( 'query_vars', function( $vars ) {
    $vars[] = 'jbw_health';
    return $vars;
} );

/**
 * Disabilita trailing-slash redirect su /health.
 * Monitoring tools (UptimeRobot, BetterStack) NON seguono redirect di
 * default — vedono 301 e dichiarano il sito down. Serve risposta diretta.
 */
add_filter( 'redirect_canonical', function( $redirect_url, $requested_url ) {
    if ( get_query_var( 'jbw_health' ) ) return false;
    return $redirect_url;
}, 10, 2 );

add_action( 'template_redirect', function() {
    if ( get_query_var( 'jbw_health' ) ) {
        $start = microtime( true );
        $checks = jbw_run_health_checks();
        $ok = $checks['db'] === 'ok';
        status_header( $ok ? 200 : 503 );
        nocache_headers();
        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'Cache-Control: no-store, max-age=0' );
        echo $ok ? "ok\n" : "fail: db unreachable\n";
        exit;
    }
} );

add_action( 'rest_api_init', function() {
    register_rest_route( 'jbw/v1', '/health', [
        'methods'             => 'GET',
        'permission_callback' => '__return_true',
        'callback'            => function() {
            $start  = microtime( true );
            $checks = jbw_run_health_checks();
            $checks['response_ms'] = (int) ( ( microtime( true ) - $start ) * 1000 );
            $checks['timestamp']   = gmdate( 'c' );
            $checks['php_version'] = PHP_VERSION;
            $checks['wp_version']  = get_bloginfo( 'version' );
            $ok = $checks['db'] === 'ok';
            return new WP_REST_Response( $checks, $ok ? 200 : 503, [
                'Cache-Control' => 'no-store, max-age=0',
            ] );
        },
    ] );
} );

function jbw_run_health_checks(): array {
    global $wpdb;
    $out = [ 'status' => 'ok', 'db' => 'unknown', 'redis' => 'n/a' ];

    // DB
    $res = $wpdb->get_var( 'SELECT 1' );
    $out['db'] = ( $res === '1' ) ? 'ok' : 'fail';

    // Redis (se WP_REDIS_HOST è definito e l'extension è presente)
    if ( defined( 'WP_REDIS_HOST' ) && class_exists( 'Redis' ) ) {
        try {
            $r = new Redis();
            $r->connect( WP_REDIS_HOST, defined( 'WP_REDIS_PORT' ) ? WP_REDIS_PORT : 6379, 1 );
            $out['redis'] = $r->ping() ? 'ok' : 'fail';
            $r->close();
        } catch ( \Throwable $e ) {
            $out['redis'] = 'fail';
        }
    }

    if ( $out['db'] !== 'ok' || $out['redis'] === 'fail' ) $out['status'] = 'degraded';

    return $out;
}
