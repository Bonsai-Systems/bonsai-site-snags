<?php
/**
 * Plugin Name: Bonsai Site Snags
 * Plugin URI:  https://bonsaidigitalcollective.co.uk/
 * Description: Lightweight front-end QA/snagging layer for admins. Toggle it on, click anywhere on the page to drop a note, tick it off when fixed. Not public facing.
 * Version:     1.2.0
 * Author:      The Bonsai Digital Collective
 * Author URI:  https://bonsaidigitalcollective.co.uk/
 * Text Domain: site-snags
 * Requires PHP: 7.4
 *
 * Internal tool for Bonsai builds. Built with an eye to being spun out as a
 * sellable plugin later — keep it decoupled from any specific child theme.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
|--------------------------------------------------------------------------
| Plugin Update Checker (via Composer)
|--------------------------------------------------------------------------
*/
require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$site_snags_update_checker = PucFactory::buildUpdateChecker(
	'https://github.com/Bonsai-Systems/bonsai_site_snags',
	__FILE__,
	'bonsai-site-snags',
	6
);

$site_snags_update_checker->setBranch( 'main' );
$site_snags_update_checker->getVcsApi()->enableReleaseAssets();

define( 'SITE_SNAGS_VERSION', '1.2.0' );
define( 'SITE_SNAGS_PATH', plugin_dir_path( __FILE__ ) );
define( 'SITE_SNAGS_URL', plugin_dir_url( __FILE__ ) );
define( 'SITE_SNAGS_CAP', apply_filters( 'site_snags_capability', 'manage_options' ) );

/**
 * Load plugin classes.
 */
require_once SITE_SNAGS_PATH . 'includes/class-site-snags-cpt.php';
require_once SITE_SNAGS_PATH . 'includes/class-site-snags-ajax.php';
require_once SITE_SNAGS_PATH . 'includes/class-site-snags-frontend.php';
require_once SITE_SNAGS_PATH . 'includes/class-site-snags-admin-list.php';
require_once SITE_SNAGS_PATH . 'includes/class-site-snags-settings.php';

/**
 * Central permission check — used by both the front-end enqueue and the
 * AJAX handlers, so there's exactly one place that decides who can snag.
 *
 * Logic: user must hold SITE_SNAGS_CAP (or whatever site_snags_capability
 * filters it to) as a baseline. On top of that, if the site owner has
 * saved the allow-list on the Settings screen, the user must also be
 * explicitly ticked. If the allow-list has never been saved (option is
 * `false`, its default), everyone with the base capability is allowed —
 * so activating the plugin doesn't silently lock everyone out.
 *
 * @param int $user_id Optional. Defaults to the current user.
 * @return bool
 */
function site_snags_user_is_allowed( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();

	if ( ! $user_id || ! user_can( $user_id, SITE_SNAGS_CAP ) ) {
		return false;
	}

	$allowed_users = get_option( 'site_snags_allowed_users', false );

	// Allow-list never configured — fall back to capability-only check.
	if ( false === $allowed_users ) {
		return true;
	}

	return in_array( (int) $user_id, array_map( 'intval', $allowed_users ), true );
}

/**
 * Bootstraps the plugin. Kept as a thin coordinator — each class owns one
 * concern (CPT registration, AJAX handlers, front-end output, admin list UI).
 */
final class Site_Snags {

	/**
	 * Singleton instance.
	 *
	 * @var Site_Snags|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Site_Snags
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wire everything up.
	 */
	private function __construct() {
		new Site_Snags_CPT();
		new Site_Snags_Ajax();
		new Site_Snags_Frontend();
		new Site_Snags_Admin_List();
		new Site_Snags_Settings();

		register_activation_hook( __FILE__, array( $this, 'on_activation' ) );
	}

	/**
	 * Flush rewrite rules on activation so the CPT archive/list works cleanly.
	 */
	public function on_activation() {
		// Trigger CPT registration before flushing.
		( new Site_Snags_CPT() )->register_post_type();
		flush_rewrite_rules();
	}
}

Site_Snags::instance();
