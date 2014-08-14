<?php
/**
 * Plugin Name: Cares Theme Extras
 * Description: Adds CARES theme-specific extras to CARES theme
 * Version: 0.1
 * Author: CARES
 * Author URI: http://cares.mizzou.edu
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU 
 * General Public License version 2, as published by the Free Software Foundation.  You may NOT assume 
 * that you can use any other version of the GPL.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without 
 * even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @package   CaresExtras
 * @version   0.1.0
 * @since     0.1.0
 * @author    Melissa De Bartolomeo
 * @license   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

class Cares_Extras {

	/**
	 * PHP5 constructor method.
	 *
	 * @since  0.1.0
	 * @access public
	 * @return void
	 */
	public function __construct() {

		/* Set the constants needed by the plugin. */
		add_action( 'plugins_loaded', array( &$this, 'constants' ), 2 );

		/* Internationalize the text strings used. */
		// add_action( 'plugins_loaded', array( &$this, 'i18n' ), 2 );

		/* Load the functions files. */
		add_action( 'plugins_loaded', array( &$this, 'includes' ), 7 );

		/* Load the admin files. */
		add_action( 'plugins_loaded', array( &$this, 'admin' ), 12 );

		/* Register activation hook. */
		// register_activation_hook( __FILE__, array( &$this, 'activation' ) );
	}

	/**
	 * Defines constants used by the plugin.
	 *
	 * @since  0.1.0
	 * @access public
	 * @return void
	 */
	public function constants() {

		/* Set constant path to the plugin directory. */
		define( 'CARESEXTRA_DIR', trailingslashit( plugin_dir_path( __FILE__ ) ) );

		/* Set the constant path to the plugin directory URI. */
		define( 'CARESEXTRA_URI', trailingslashit( plugin_dir_url( __FILE__ ) ) );

		/* Set the constant path to the includes directory. */
		define( 'CARESEXTRA_INCLUDES', CARESEXTRA_DIR . trailingslashit( 'includes' ) );

		/* Set the constant path to the includes directory. */
		define( 'CARESEXTRA_TEMPLATES', CARESEXTRA_DIR . trailingslashit( 'templates' ) );

		/* Set the constant path to the admin directory. */
		define( 'CARESEXTRA_ADMIN', CARESEXTRA_DIR . trailingslashit( 'admin' ) );
	}

	/**
	 * Loads the initial files needed by the plugin.
	 *
	 * @since  0.1.0
	 * @access public
	 * @return void
	 */
	public function includes() {

		require_once( CARESEXTRA_INCLUDES . 'functions.php' );
		require_once( CARESEXTRA_INCLUDES . 'meta.php' );
		require_once( CARESEXTRA_INCLUDES . 'template-tags.php' );
		require_once( CARESEXTRA_INCLUDES . 'extras.php' );

	}

	/**
	 * Loads the admin functions and files.
	 *
	 * @since  0.1.0
	 * @access public
	 * @return void
	 */
	public function admin() {

		if ( is_admin() )
			require_once( CARESEXTRA_ADMIN . 'admin.php' );
	}

}

	new Cares_Extras();