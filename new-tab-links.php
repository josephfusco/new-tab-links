<?php
/**
 * Plugin Name:    New Tab Links
 * Description:    Supplies the data for the "New Tab Links" chrome extension via the WP REST API.
 * Version:        1.0.0
 * Author:         Joseph Fusco
 * Author URI:     https://josephfus.co
 * License:        GPLv2 or later
 * Text Domain:    newtab-links
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add settings link on plugin page.
 *
 * @param $links
 * @return $links
 */
function ntl_plugin_settings_link( $links ) {
	$settings_link = '<a href="' . admin_url( 'edit.php?post_type=new_tab_links' ) . '">Settings</a>';

	array_unshift( $links, $settings_link );

	return $links;
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'ntl_plugin_settings_link' );

/**
 * Create the `new_tab_links` custom post type.
 */
function ntl_create_post_type() {

	// Register `new_tab_links` custom post type.
	$labels = array(
		'name'               => _x( 'New Tab Links', 'post type general name', 'newtab-links' ),
		'singular_name'      => _x( 'New Tab Link', 'post type singular name', 'newtab-links' ),
		'menu_name'          => _x( 'New Tab Links', 'admin menu', 'newtab-links' ),
		'name_admin_bar'     => _x( 'New Tab Link', 'add new on admin bar', 'newtab-links' ),
		'add_new'            => _x( 'Add New', 'Link', 'newtab-links' ),
		'add_new_item'       => __( 'Add New Link', 'newtab-links' ),
		'new_item'           => __( 'New Link', 'newtab-links' ),
		'edit_item'          => __( 'Edit Link', 'newtab-links' ),
		'view_item'          => __( 'View Link', 'newtab-links' ),
		'all_items'          => __( 'All Links', 'newtab-links' ),
		'search_items'       => __( 'Search Links', 'newtab-links' ),
		'parent_item_colon'  => __( 'Parent Links:', 'newtab-links' ),
		'not_found'          => __( 'No links found.', 'newtab-links' ),
		'not_found_in_trash' => __( 'No links found in Trash.', 'newtab-links' ),
	);
	$args  = array(
		'labels'              => $labels,
		'description'         => __( 'Description.', 'newtab-links' ),
		'menu_icon'           => 'dashicons-admin-links',
		'show_in_menu'        => true,
		'query_var'           => true,
		'capability_type'     => 'post',
		'has_archive'         => false,
		'hierarchical'        => false,
		'menu_position'       => null,
		'supports'            => array( 'title', 'thumbnail' ),
		'public'              => false,
		'exclude_from_search' => true,
		'show_in_admin_bar'   => false,
		'show_in_nav_menus'   => false,
		'show_in_rest'        => false,
		'publicly_queryable'  => false,
		'query_var'           => false,
		'rewrite'             => false,
		'show_ui'             => true,
	);
	register_post_type( 'new_tab_links', $args );

}
add_action( 'init', 'ntl_create_post_type' );

/**
 * Move Featured Image Metabox on 'new_tab_links' post type.
 */
function ntl_image_metabox() {
	remove_meta_box( 'postimagediv', 'new_tab_links', 'side' );
	add_meta_box(
		'postimagediv',
		__( 'Screenshot', 'newtab-links' ),
		'post_thumbnail_meta_box',
		'new_tab_links',
		'normal',
		'high'
	);
}
add_action( 'do_meta_boxes', 'ntl_image_metabox' );

/*
 * Change the featured image metabox link text
 *
 * @param  string $content Featured image link text
 * @return string $content Featured image link text, filtered
 */
function ntl_change_featured_image_text( $content ) {
	if ( 'new_tab_links' === get_post_type() ) {
		$content = str_replace( 'Set featured image', __( 'Set site screenshot image', 'newtab-links' ), $content );
		$content = str_replace( 'Remove featured image', __( 'Remove site screenshot image', 'newtab-links' ), $content );
	}

	return $content;
}
add_filter( 'admin_post_thumbnail_html', 'ntl_change_featured_image_text' );

/**
 * Register the metabox.
 */
function ntl_link_meta_box() {

	$screens = array( 'new_tab_links' );

	foreach ( $screens as $screen ) {
		add_meta_box(
			'new_tab_links',
			__( 'URL', 'newtab-links' ),
			'ntl_metabox_cb',
			$screen
		);
	}
}
add_action( 'add_meta_boxes', 'ntl_link_meta_box' );

/**
 * The callback for out custom metabox.
 *
 * @param $post
 */
function ntl_metabox_cb( $post ) {

	// Add a nonce field so we can check for it later.
	wp_nonce_field( 'ntl_url_nonce', 'ntl_url_nonce' );

	$url = get_post_meta( $post->ID, '_url', true );

	echo '<input class="code" name="url" size="30" maxlength="255" value="' . esc_url( $url ) . '">';
	echo '<p>Example: <code>http://wordpress.org/</code> — don’t forget the <code>http://</code></p>';
}

/**
 * When the post is saved, saves our custom data.
 *
 * @param int $post_id
 */
function ntl_save_meta_box_data( $post_id ) {

	// Check if our nonce is set.
	if ( ! isset( $_POST['ntl_url_nonce'] ) ) {
		return;
	}

	// Verify that the nonce is valid.
	if ( ! wp_verify_nonce( $_POST['ntl_url_nonce'], 'ntl_url_nonce' ) ) {
		return;
	}

	// If this is an autosave, our form has not been submitted, so we don't want to do anything.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// Check the user's permissions.
	if ( isset( $_POST['post_type'] ) && 'new_tab_links' == $_POST['post_type'] ) {
		if ( ! current_user_can( 'edit_page', $post_id ) ) {
			return;
		}
	} else {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
	}

	/* OK, it's safe for us to save the data now. */

	// Make sure that it is set.
	if ( ! isset( $_POST['url'] ) ) {
		return;
	}

	// Sanitize user input.
	$my_data = sanitize_text_field( $_POST['url'] );

	// Update the meta field in the database.
	update_post_meta( $post_id, '_url', $my_data );
}
add_action( 'save_post', 'ntl_save_meta_box_data' );

/**
 * Add 'url' admin column.
 *
 * @param $columns
 * @return $columns
 */
function ntl_columns_head( $columns ) {
	$columns['url'] = 'URL';

	unset( $columns['date'] );

	return $columns;
}
add_filter( 'manage_new_tab_links_posts_columns', 'ntl_columns_head' );

/**
 * Add url data to admin column.
 *
 * @param $column_name
 * @param $post_ID
 */
function ntl_columns_content( $column_name, $post_ID ) {

	if ( 'url' === $column_name ) {

		$url  = get_post_meta( $post_ID, '_url', true );
		$link = '<a href="' . $url . '" rel="noopener noreferrer" target="_blank">' . $url . '</a>';

		echo esc_url( $link );

	}
}
add_action( 'manage_new_tab_links_posts_custom_column', 'ntl_columns_content', 10, 2 );

function ntl_route_cb() {
	$data = array();

	$query = new WP_Query( array(
		'post_type'   => 'new_tab_links',
		'post_status' => 'publish',
	));

	// The Loop.
	if ( $query->have_posts() ) {
		$i = 1;
		while ( $query->have_posts() ) {
			$query->the_post();

			$id = get_the_ID();

			$data[ $i ]['id']                      = $id;
			$data[ $i ]['title']                   = get_the_title();
			$data[ $i ]['url']                     = get_post_meta( $id, '_url', true );
			$data[ $i ]['screenshot']['full']      = get_the_post_thumbnail_url( $id, 'full' );
			$data[ $i ]['screenshot']['large']     = get_the_post_thumbnail_url( $id, 'large' );
			$data[ $i ]['screenshot']['medium']    = get_the_post_thumbnail_url( $id, 'medium' );
			$data[ $i ]['screenshot']['thumbnail'] = get_the_post_thumbnail_url( $id, 'thumbnail' );

			$i++;
		}

		// Change associative array into indexed array.
		$data = array_values( $data );

		// Restore original Post Data.
		wp_reset_postdata();

	} else {
		return new WP_Error( 'no_links', 'No links found', array( 'status' => 404 ) );
	}

	return $data;
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'new_tab_links/v1', 'links', array(
		'methods'  => 'GET',
		'callback' => 'ntl_route_cb',
		'args'     => array(
			'id' => array(
				'validate_callback' => function( $param, $request, $key ) {
					return is_numeric( $param );
				},
			),
		),
	));
});
