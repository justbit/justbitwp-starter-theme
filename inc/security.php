<?php
/**
 * Stub di sicurezza tema-specific.
 *
 * NB: il grosso dell'hardening WP (anti user enumeration, XML-RPC off,
 * security headers) vive nel plugin `justbit-wp-security`. Aggiungi qui
 * solo regole STRETTAMENTE legate al TEMA (es. nascondere field di un
 * pattern dal REST se contiene dati sensibili).
 */
if ( ! defined( 'ABSPATH' ) ) exit;
