<?php
/**
 * Provide a admin area view for the plugin
 *
 * @since 1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

?>
<div class="wrap">

	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<form action="options.php" method="post" class="ntl-options">

		<?php settings_fields( 'ntl_options' ); ?>

		<?php do_settings_sections( 'ntl_options' ); ?>

		<?php submit_button(); ?>

	</form>

</div><!-- .wrap -->
