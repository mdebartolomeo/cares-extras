<?php
/**
 * Various functions, filters, and actions used by the plugin.
 *
 * @package    CaresExtras
 * @subpackage Includes
 * @since      0.1.0
 * @author     David Cavins
 * @license    http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

/* Register meta on the 'init' hook. */
add_action( 'init', 'caresextra_register_meta' );

/**
 * Registers custom metadata for the plugin. Defined at wp-includes/meta.php, line 1170 
 *
 * @since  0.1.0
 * @access public
 * @return void
 */
function caresextra_register_meta() {
	// Third argument is a sanitization callback.
	register_meta( 'post', 'post_client', 'caresextra_sanitize_meta' );
	register_meta( 'post', 'post_featured', 'caresextra_sanitize_meta' );
}

/**
 * Callback function for sanitizing meta when add_metadata() or update_metadata() is called by WordPress. 
 * If a developer wants to set up a custom method for sanitizing the data, they should use the 
 * "sanitize_{$meta_type}_meta_{$meta_key}" filter hook to do so.
 *
 * @since  0.1.0
 * @access public
 * @param  mixed  $meta_value The value of the data to sanitize.
 * @param  string $meta_key   The meta key name.
 * @param  string $meta_type  The type of metadata (post, comment, user, etc.)
 * @return mixed  $meta_value
 */
function caresextra_sanitize_meta( $meta_value, $meta_key, $meta_type ) {

	if ( 'portfolio_item_url' === $meta_key )
		return esc_url( $meta_value );

	return sanitize_text_field( $meta_value );
}

?>