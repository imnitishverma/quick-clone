<?php
/**
 * Plugin Name: Quick Clone
 * Plugin URI: https://help.nitishverma.com/quick-clone-plugin/ 
 * Description: One-click functionality to duplicate any WordPress Post, Page, or Custom Post Type, including custom fields and taxonomies.
 * Version: 1.0.0
 * Author: Nitish Verma
 * Author URI: https://help.nitishverma.com/
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: quick-clone
 * Domain Path: /languages 
 */

// Security check: Direct file access se bachne ke liye
if ( ! defined( 'ABSPATH' ) ) {
    exit; 
}

/**
 * 1. Admin Actions mein Duplicate link add karta hai.
 */
function qc_add_duplicate_link( $actions, $post ) {

    // WordPress Standard: User ki capability check
    if ( current_user_can( 'edit_posts' ) ) {
        $nonce_url = wp_create_nonce( basename( __FILE__ ) );
        
        $link_url = admin_url( 
            'admin.php?action=qc_duplicate_post&post=' . $post->ID . '&duplicate_nonce=' . $nonce_url
        );

        $actions['duplicate'] = '<a href="' . esc_url( $link_url ) . '" title="Duplicate this item" rel="permalink">' . __( 'Duplicate', 'quick-clone' ) . '</a>';
    }

    return $actions;
}

add_filter( 'post_row_actions', 'qc_add_duplicate_link', 10, 2 );
add_filter( 'page_row_actions', 'qc_add_duplicate_link', 10, 2 );


/**
 * 2. Custom Meta Data (Custom Fields) ko clone karta hai.
 */
function qc_clone_post_meta( $old_id, $new_id ) {
    
    $post_meta = get_post_meta( $old_id );
    
    if ( empty( $post_meta ) ) {
        return;
    }

    foreach ( $post_meta as $key => $values ) {
        // WordPress Standard: Internal keys aur sirf featured image (thumbnail) ko allow karna
        if ( substr( $key, 0, 1 ) == '_' && $key != '_thumbnail_id') {
            continue; 
        }

        foreach ( $values as $value ) {
            add_post_meta( $new_post_id, $key, maybe_unserialize( $value ) );
        }
    }
}


/**
 * 3. Core Duplication logic chalaata hai.
 */
function qc_duplicate_post_action() {

    // Security Check 1: Capability check
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( 'Permission denied.' );
    }

    // Security Check 2: Nonce verification
    // FIX: wp_unslash() aur sanitize_key() se $_GET['duplicate_nonce'] ko sanitize kiya gaya hai.
    if ( ! isset( $_GET['duplicate_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['duplicate_nonce'] ) ), basename( __FILE__ ) ) ) {
        return; 
    }

    $post_id = ( isset( $_GET['post'] ) ) ? absint( $_GET['post'] ) : 0;
    $post = get_post( $post_id );

    if ( $post === null ) {
        wp_die( 'Post not found.' );
    }

    $args = array(
        'post_author'    => get_current_user_id(),
        'post_content'   => $post->post_content,
        'post_excerpt'   => $post->post_excerpt,
        'post_name'      => '', 
        'post_status'    => 'draft', 
        'post_title'     => $post->post_title . ' (Copy)', 
        'post_type'      => $post->post_type,
        'post_parent'    => $post->post_parent,
    );

    $new_post_id = wp_insert_post( $args );

    $taxonomies = get_object_taxonomies( $post->post_type );
    foreach ( $taxonomies as $taxonomy ) {
        $terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'slugs' ) );
        wp_set_object_terms( $new_post_id, $terms, $taxonomy, false );
    }

    qc_clone_post_meta( $post_id, $new_post_id );

    // FIX: wp_safe_redirect() ka istemal kiya gaya hai (Line 106 ka fix)
    wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $new_post_id ) );
    exit; // Redirection ke baad exit zaroori hai
}

add_action( 'admin_action_qc_duplicate_post', 'qc_duplicate_post_action' );