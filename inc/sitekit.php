<?php
/**
 * Google Site Kit integration — il pezzo SEO più importante.
 *
 * Cosa è Site Kit: plugin ufficiale Google (gratuito) che integra
 * **Search Console, Analytics 4, AdSense, PageSpeed Insights** dentro a
 * wp-admin. È IL modo per:
 *   - Far indicizzare il sito su Google (Search Console verifica + sitemap)
 *   - Vedere le query di ricerca che portano traffico
 *   - Tracciare i visitatori (GA4) — privacy-compliant via Justbitz Consent Mode v2
 *   - Monitorare i Core Web Vitals (CrUX data)
 *
 * Lo stack pre-installa Site Kit via bootstrap.sh ma il **connect** richiede
 * OAuth Google interattivo dal browser (non automatizzabile).
 *
 * Questo modulo:
 *   1. Mostra una admin notice "Site Kit non connesso" finché non lo è
 *   2. Helper `jbw_verify_meta()` per leggere meta verification salvati
 *   3. Link diretto al wizard di Site Kit dalla notice
 *
 * @package justbitwp-starter
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin notice se Site Kit è installato ma NON connesso.
 * Sparisce automaticamente una volta che l'admin completa il connect.
 *
 * Dismissable per utente (transient 7 giorni).
 */
add_action( 'admin_notices', function() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    if ( ! function_exists( 'is_plugin_active' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
    if ( ! is_plugin_active( 'google-site-kit/google-site-kit.php' ) ) return;

    // Site Kit attivo: è connesso?
    $connected = get_option( 'googlesitekit_search-console_settings' );
    if ( $connected && ! empty( $connected['propertyID'] ) ) return; // Search Console connessa → ok

    $dismissed = get_user_meta( get_current_user_id(), 'jbw_sitekit_notice_dismissed', true );
    if ( $dismissed && ( time() - intval( $dismissed ) ) < WEEK_IN_SECONDS ) return;

    $sitekit_url = admin_url( 'admin.php?page=googlesitekit-splash' );
    $dismiss_url = wp_nonce_url( add_query_arg( 'jbw_dismiss', 'sitekit' ), 'jbw_dismiss_sitekit' );
    ?>
    <div class="notice notice-warning is-dismissible" id="jbw-sitekit-notice">
        <p>
            <strong>📈 <?php esc_html_e( 'Google Site Kit non connesso', 'justbitwp-starter' ); ?></strong> —
            <?php esc_html_e( 'il sito non è ancora collegato a Search Console / Analytics / PageSpeed Insights. È il singolo passo più importante per la SEO: senza, Google non sa che esisti.', 'justbitwp-starter' ); ?>
        </p>
        <p>
            <a href="<?php echo esc_url( $sitekit_url ); ?>" class="button button-primary">
                <?php esc_html_e( 'Connetti adesso (5 min)', 'justbitwp-starter' ); ?>
            </a>
            <a href="<?php echo esc_url( $dismiss_url ); ?>" class="button">
                <?php esc_html_e( 'Ricordamelo fra 7 giorni', 'justbitwp-starter' ); ?>
            </a>
            <a href="https://justbit.github.io/justbitwp-stack/#seo" target="_blank" rel="noopener" style="margin-left: 10px;">
                <?php esc_html_e( 'Guida completa SEO →', 'justbitwp-starter' ); ?>
            </a>
        </p>
    </div>
    <?php
} );

/**
 * Gestisce il dismiss della notice.
 */
add_action( 'admin_init', function() {
    if ( ! isset( $_GET['jbw_dismiss'] ) || $_GET['jbw_dismiss'] !== 'sitekit' ) return;
    check_admin_referer( 'jbw_dismiss_sitekit' );
    update_user_meta( get_current_user_id(), 'jbw_sitekit_notice_dismissed', time() );
    wp_safe_redirect( remove_query_arg( [ 'jbw_dismiss', '_wpnonce' ] ) );
    exit;
} );

/**
 * Verification meta tags — per Search Console, Bing Webmaster, ecc.
 *
 * Site Kit gestisce automaticamente la verification di Search Console
 * via DNS o file. Ma altri servizi (Bing, Yandex, Pinterest, Facebook
 * Business) richiedono ancora `<meta name="..." content="...">`.
 *
 * I valori si salvano come WP options (set via wp-admin → Reading o via
 * filter). Esempio:
 *
 *   add_filter( 'jbw_verify_meta', function( $tags ) {
 *       $tags['msvalidate.01']      = 'A1B2C3D4...';   // Bing
 *       $tags['yandex-verification'] = 'X1Y2Z3...';     // Yandex
 *       $tags['facebook-domain-verification'] = '...';  // FB Business
 *       $tags['pinterest-site-verification'] = '...';
 *       return $tags;
 *   } );
 */
add_action( 'wp_head', function() {
    $tags = apply_filters( 'jbw_verify_meta', [
        // Defaults vuoti — popolare via filter o option
        'msvalidate.01'                 => get_option( 'jbw_verify_bing', '' ),
        'yandex-verification'           => get_option( 'jbw_verify_yandex', '' ),
        'facebook-domain-verification'  => get_option( 'jbw_verify_facebook', '' ),
        'pinterest-site-verification'   => get_option( 'jbw_verify_pinterest', '' ),
    ] );

    foreach ( $tags as $name => $value ) {
        if ( empty( $value ) ) continue;
        echo '<meta name="' . esc_attr( $name ) . '" content="' . esc_attr( $value ) . '">' . "\n";
    }
}, 4 );

/**
 * Helper: ritorna l'URL del wizard di Site Kit (utile da template/blocchi).
 */
function jbw_sitekit_setup_url(): string {
    return admin_url( 'admin.php?page=googlesitekit-splash' );
}

/**
 * Helper: è connesso a Search Console?
 */
function jbw_sitekit_is_connected(): bool {
    $sc = get_option( 'googlesitekit_search-console_settings' );
    return ! empty( $sc['propertyID'] );
}

/**
 * Helper: è connesso a GA4?
 */
function jbw_sitekit_has_analytics(): bool {
    $ga = get_option( 'googlesitekit_analytics-4_settings' );
    return ! empty( $ga['measurementID'] );
}
