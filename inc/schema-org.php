<?php
/**
 * Schema.org JSON-LD helpers.
 *
 * Emette JSON-LD per le pagine principali:
 *   - WebSite (con SearchAction) sulla home
 *   - Organization (con sameAs social)
 *   - BreadcrumbList su tutte le pagine non-home
 *   - Article / BlogPosting sui post singoli
 *
 * Yoast SEO Premium emette il suo JSON-LD: questo modulo si disattiva
 * automaticamente quando Yoast è attivo. Per forzarlo:
 *   add_filter( 'jbw_schema_force', '__return_true' );
 *
 * Override-abile per ogni pagina via filtro `jbw_schema_data`.
 *
 * @package justbitwp-starter
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_head', function() {
    if ( defined( 'WPSEO_VERSION' ) && ! apply_filters( 'jbw_schema_force', false ) ) return;

    $graph = [];

    // Organization (sempre)
    $org = [
        '@type' => 'Organization',
        '@id'   => home_url( '/#org' ),
        'name'  => get_bloginfo( 'name' ),
        'url'   => home_url( '/' ),
    ];
    $logo = get_theme_mod( 'custom_logo' ) ? wp_get_attachment_url( get_theme_mod( 'custom_logo' ) ) : '';
    if ( $logo ) $org['logo'] = $logo;
    $sameAs = array_filter( apply_filters( 'jbw_schema_sameas', [] ) );
    if ( $sameAs ) $org['sameAs'] = array_values( $sameAs );
    $graph[] = $org;

    // WebSite (sempre, con SearchAction)
    $graph[] = [
        '@type'    => 'WebSite',
        '@id'      => home_url( '/#website' ),
        'url'      => home_url( '/' ),
        'name'     => get_bloginfo( 'name' ),
        'publisher'=> [ '@id' => home_url( '/#org' ) ],
        'potentialAction' => [
            '@type'       => 'SearchAction',
            'target'      => [
                '@type'       => 'EntryPoint',
                'urlTemplate' => home_url( '/?s={search_term_string}' ),
            ],
            'query-input' => 'required name=search_term_string',
        ],
    ];

    // Breadcrumb (non-home)
    if ( ! is_front_page() ) {
        $graph[] = jbw_schema_breadcrumb();
    }

    // Article / BlogPosting (post singoli)
    if ( is_singular( [ 'post' ] ) ) {
        $graph[] = jbw_schema_article();
    }

    $graph = apply_filters( 'jbw_schema_data', $graph );

    $data = [
        '@context' => 'https://schema.org',
        '@graph'   => array_values( array_filter( $graph ) ),
    ];

    echo "\n<script type=\"application/ld+json\">\n";
    echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
    echo "\n</script>\n";
}, 5 );

function jbw_schema_breadcrumb(): array {
    $items = [];
    $i = 1;
    $items[] = [ '@type' => 'ListItem', 'position' => $i++, 'name' => 'Home', 'item' => home_url( '/' ) ];

    if ( is_singular() ) {
        $type = get_post_type();
        $obj  = get_post_type_object( $type );
        $archive = get_post_type_archive_link( $type );
        if ( $archive && $obj ) {
            $items[] = [ '@type' => 'ListItem', 'position' => $i++, 'name' => $obj->labels->name, 'item' => $archive ];
        }
        $items[] = [ '@type' => 'ListItem', 'position' => $i++, 'name' => get_the_title(), 'item' => get_permalink() ];
    } elseif ( is_archive() ) {
        $items[] = [ '@type' => 'ListItem', 'position' => $i++, 'name' => get_the_archive_title(), 'item' => get_pagenum_link() ];
    }

    return [
        '@type' => 'BreadcrumbList',
        '@id'   => get_permalink() . '#breadcrumb',
        'itemListElement' => $items,
    ];
}

function jbw_schema_article(): array {
    $post_id = get_the_ID();
    $author  = get_the_author_meta( 'display_name' );
    $img     = function_exists( 'jbw_og_image_url' ) ? jbw_og_image_url( $post_id )
             : get_the_post_thumbnail_url( $post_id, 'full' );
    return [
        '@type'         => 'BlogPosting',
        '@id'           => get_permalink() . '#article',
        'mainEntityOfPage'=> get_permalink(),
        'headline'      => get_the_title(),
        'description'   => wp_trim_words( get_the_excerpt() ?: get_the_content(), 28 ),
        'image'         => $img ?: null,
        'datePublished' => get_the_date( 'c' ),
        'dateModified'  => get_the_modified_date( 'c' ),
        'author'        => [ '@type' => 'Person', 'name' => $author ],
        'publisher'     => [ '@id' => home_url( '/#org' ) ],
        'isPartOf'      => [ '@id' => home_url( '/#website' ) ],
    ];
}
