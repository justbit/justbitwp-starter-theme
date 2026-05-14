<?php
/**
 * Justbit WP Starter — functions.php
 *
 * Bootstrap del tema. Carica i moduli da inc/ e applica i setup base.
 * Lascia questo file SOTTILE: la logica vera vive nei moduli inc/<area>.php.
 *
 * @package justbitwp-starter
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'JBW_THEME_VERSION', '0.1.0' );
define( 'JBW_THEME_DIR', get_template_directory() );
define( 'JBW_THEME_URI', get_template_directory_uri() );

// Autoload dei moduli in inc/
foreach ( glob( JBW_THEME_DIR . '/inc/*.php' ) as $file ) {
    require_once $file;
}
