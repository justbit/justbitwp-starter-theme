<?php
/**
 * Theme supports + i18n + content width.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'after_setup_theme', function() {
    load_theme_textdomain( 'justbitwp-starter', JBW_THEME_DIR . '/languages' );

    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'title-tag' );
    add_theme_support( 'automatic-feed-links' );
    add_theme_support( 'html5', [
        'search-form','comment-form','comment-list','gallery','caption','style','script'
    ] );
    add_theme_support( 'responsive-embeds' );
    add_theme_support( 'editor-styles' );
    add_theme_support( 'wp-block-styles' );
    add_theme_support( 'align-wide' );

    // Editor CSS file — gli stili che vedi nell'editor block.
    add_editor_style( 'assets/css/editor.css' );

    // Custom logo
    add_theme_support( 'custom-logo', [
        'height'      => 200,
        'width'       => 200,
        'flex-height' => true,
        'flex-width'  => true,
    ] );
} );
