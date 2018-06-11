<?php
/**
 * Plugin Name:    New Tab Links
 * Description:    Supplies the data for the "New Tab Links" chrome extension via the WP REST API.
 * Version:        1.0.0
 * Author:         Joseph Fusco
 * Author URI:     https://josephfus.co
 * License:        GPLv2 or later
 * Text Domain:    new-tab-links
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
	$settings_link = '<a href="' . admin_url( 'edit.php?post_type=ntl' ) . '">Settings</a>';

	array_unshift( $links, $settings_link );

	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'ntl_plugin_settings_link' );

/**
 * Create the `ntl` custom post type.
 */
function ntl_create_post_type() {

	// Register `ntl` custom post type.
	$labels = array(
		'name'               => _x( 'New Tab Links', 'post type general name', 'new-tab-links' ),
		'singular_name'      => _x( 'New Tab Link', 'post type singular name', 'new-tab-links' ),
		'menu_name'          => _x( 'New Tab Links', 'admin menu', 'new-tab-links' ),
		'name_admin_bar'     => _x( 'New Tab Link', 'add new on admin bar', 'new-tab-links' ),
		'add_new'            => _x( 'Add New', 'Link', 'new-tab-links' ),
		'add_new_item'       => __( 'Add New Link', 'new-tab-links' ),
		'new_item'           => __( 'New Link', 'new-tab-links' ),
		'edit_item'          => __( 'Edit Link', 'new-tab-links' ),
		'view_item'          => __( 'View Link', 'new-tab-links' ),
		'all_items'          => __( 'All Links', 'new-tab-links' ),
		'search_items'       => __( 'Search Links', 'new-tab-links' ),
		'parent_item_colon'  => __( 'Parent Links:', 'new-tab-links' ),
		'not_found'          => __( 'No links found.', 'new-tab-links' ),
		'not_found_in_trash' => __( 'No links found in Trash.', 'new-tab-links' ),
	);
	$args   = array(
		'labels'              => $labels,
		'description'         => __( 'Description.', 'new-tab-links' ),
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
 * Move Featured Image Metabox on 'ntl' post type.
 */
function ntl_image_metabox() {
	remove_meta_box( 'postimagediv', 'ntl', 'side' );
	add_meta_box(
		'postimagediv',
		__( 'Screenshot', 'new-tab-links' ),
		'post_thumbnail_meta_box',
		'ntl',
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
	if ( 'ntl' === get_post_type() ) {
		$content = str_replace( 'Set featured image', __( 'Set site screenshot image', 'new-tab-links' ), $content );
		$content = str_replace( 'Remove featured image', __( 'Remove site screenshot image', 'new-tab-links' ), $content );
	}

	return $content;
}
add_filter( 'admin_post_thumbnail_html', 'ntl_change_featured_image_text' );

/**
 * Register the metabox.
 */
function ntl_link_meta_box() {

	$screens = array( 'ntl' );

	foreach ( $screens as $screen ) {
		add_meta_box(
			'ntl',
			__( 'URL', 'new-tab-links' ),
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
	if ( isset( $_POST['post_type'] ) && 'ntl' == $_POST['post_type'] ) {
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
	$user_data = sanitize_text_field( $_POST['url'] );

	// Update the meta field in the database.
	update_post_meta( $post_id, '_url', $user_data );
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
add_filter( 'manage_ntl_posts_columns', 'ntl_columns_head' );

/**
 * Add url data to admin column.
 *
 * @param $column_name
 * @param $post_ID
 */
function ntl_columns_content( $column_name, $post_ID ) {

	if ( 'url' === $column_name ) {

		$url = get_post_meta( $post_ID, '_url', true );
		echo '<a href="' . esc_url( $url ) . '" rel="noopener noreferrer" target="_blank">' . esc_url( $url ) . '</a>';

	}
}
add_action( 'manage_ntl_posts_custom_column', 'ntl_columns_content', 10, 2 );

/**
 * Register the submenu page under Appearance.
 */
function ntl_register_submenu_page() {
	add_submenu_page(
		'options-general.php',
		'New Tab Links',
		'New Tab Links',
		'manage_options',
		'ntl',
		'ntl_submenu_page_cb'
	);
}
add_action( 'admin_menu', 'ntl_register_submenu_page' );

/**
 * Displays the admin facing options page.
 */
function ntl_submenu_page_cb() {
	include_once 'includes/new-tab-links-admin-display.php';
}

/**
 * Link to plugin settings from plugins page.
 *
 * @since 1.0.0
 * @param Array $links
 * @return Array
 */
function ntl_add_action_links( $links ) {
	$mylinks = array(
		'<a href="' . admin_url( 'options-general.php?page=ntl' ) . '">Settings</a>',
	);

	return array_merge( $links, $mylinks );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'ntl_add_action_links' );

/**
 * Provides default values for the Display Options.
 */
function ntl_default_options() {

	$defaults = array(
		'name' => '',
		'logo' => '',
	);

	return apply_filters( 'ntl_default_options', $defaults );
}

/**
 * Add this plugin settings.
 */
function ntl_add_settings() {
	$options = get_option( 'ntl_options' );

	if ( false === $options ) {
		add_option( 'ntl_options', apply_filters( 'ntl_default_options', ntl_default_options() ) );
	}

	add_settings_section(
		'ntl_general_settings_section',
		__( 'Plugin Settings', 'new-tab-links' ),
		'ntl_general_settings_section_cb',
		'ntl_options'
	);

	add_settings_field(
		'name',
		__( 'Name', 'new-tab-links' ),
		'ntl_name_cb',
		'ntl_options',
		'ntl_general_settings_section'
	);

	add_settings_field(
		'logo',
		__( 'Logo', 'new-tab-links' ),
		'ntl_logo_cb',
		'ntl_options',
		'ntl_general_settings_section'
	);

	register_setting(
		'ntl_options',
		'ntl_options'
	);
}
add_action( 'admin_init', 'ntl_add_settings' );

/**
 * Description for the general settings section.
 */
function ntl_general_settings_section_cb() {

}

/**
 * Callback for the `name` option.
 */
function ntl_name_cb() {
	$options = get_option( 'ntl_options' );
	?>
	<input type="text" class="regular-text" name="ntl_options[name]" value="<?php echo esc_html( $options['name'] ); ?>">
	<p class="description"><?php esc_html_e( 'The name of the new tab page.', 'new-tab-links' ); ?></p>
	<?php
}

/**
 * Callback for the `logo` option.
 */
function ntl_logo_cb() {
	$options = get_option( 'ntl_options' );
	?>
	<input type="text" class="large-text" name="ntl_options[logo]" value="<?php echo esc_html( $options['logo'] ); ?>">
	<p class="description"><?php esc_html_e( 'Link to the main logo for the new tab page.', 'new-tab-links' ); ?></p>
	<?php
}

/**
 * Register the plugin's REST routes.
 */
add_action( 'rest_api_init', function () {

	/**
	 * Register `links` endpoint.
	 * @link wp-json/ntl/v1/links
	 */
	register_rest_route( 'ntl/v1', 'links', array(
		'methods'  => 'GET',
		'callback' => 'ntl_route_cb__links',
		'args'     => array(
			'id' => array(
				'validate_callback' => function( $param, $request, $key ) {
					return is_numeric( $param );
				},
			),
		),
	));

	/**
	 * Register `info` endpoint.
	 * @link wp-json/ntl/v1/info
	 */
	register_rest_route( 'ntl/v1', 'info', array(
		'methods'  => 'GET',
		'callback' => 'ntl_route_cb__info',
		'args'     => array(
			'id' => array(
				'validate_callback' => function( $param, $request, $key ) {
					return is_numeric( $param );
				},
			),
		),
	));
});

/**
 * The callback for the `links` REST route.
 */
function ntl_route_cb__links() {
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
		return new WP_Error( 'no_links', __( 'No links found', 'new-tab-links' ), array( 'status' => 404 ) );
	}

	return $data;
}

/**
 * The callback for the `info` REST route.
 */
function ntl_route_cb__info() {
	$data    = array();
	$options = get_option( 'ntl_options' );

	$data['name'] = esc_html( $options['name'] );
	$data['logo'] = esc_url( $options['logo'] );

	return $data;
}
