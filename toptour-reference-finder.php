<?php
/**
 * Plugin Name: TOPTOUR Reference Finder
 * Plugin URI: https://toptour.sk
 * Description: Internal TOPTOUR plugin for collecting real guest references, review findings, source links and photo evidence before any later evaluation logic.
 * Version: 0.2.19
 * Author: TOPTOUR
 * Author URI: https://toptour.sk
 * Text Domain: toptour-reference-finder
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * @package Toptour_Ref
 * @version 0.2.19
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'TOPTOUR_REF_VERSION', '0.2.19' );
define( 'TOPTOUR_REF_PLUGIN_FILE', __FILE__ );
define( 'TOPTOUR_REF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TOPTOUR_REF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TOPTOUR_REF_DB_VERSION', '0.2.4' );

/**
 * Load the main loader class.
 */
require_once TOPTOUR_REF_PLUGIN_DIR . 'includes/class-loader.php';

/**
 * Begin plugin execution.
 */
Toptour_Ref_Loader::run();

/**
 * Register activation hook.
 */
register_activation_hook( __FILE__, array( 'Toptour_Ref_Installer', 'activate' ) );

/**
 * Register deactivation hook.
 */
register_deactivation_hook( __FILE__, array( 'Toptour_Ref_Installer', 'deactivate' ) );
