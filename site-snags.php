<?php
/**
 * Plugin Name: Bonsai Site Snags
 * Plugin URI:  https://bonsaidigitalcollective.co.uk/
 * Description: Lightweight front-end QA/snagging layer for admins. Toggle it on, click anywhere on the page to drop a note, tick it off when fixed.
 * Version:     1.4.0
 * Author:      The Bonsai Digital Collective
 * Author URI:  https://bonsaidigitalcollective.co.uk/
 * Text Domain: site-snags
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Internal tool for Bonsai builds. Built with an eye to being spun out as a
 * sellable plugin later.
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

define( 'SITE_SNAGS_VERSION', '1.4.0' );
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
require_once SITE_SNAGS_PATH . 'includes/class-site-snags-notifications.php';

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
 * The snag priority levels: slug => label + swatch colour.
 *
 * Order matters — it's the order the swatches render in the front-end
 * popover and the wp-admin filter dropdown. Filterable so a site can
 * relabel or recolour them without touching the plugin.
 *
 * @return array<string,array{label:string,color:string}>
 */
function site_snags_get_priorities() {
	return apply_filters(
		'site_snags_priorities',
		array(
			'urgent' => array(
				'label' => __( 'Urgent', 'site-snags' ),
				'color' => '#e5484d',
			),
			'normal' => array(
				'label' => __( 'Normal', 'site-snags' ),
				'color' => '#f5a623',
			),
			'low'    => array(
				'label' => __( 'Not urgent', 'site-snags' ),
				'color' => '#46a758',
			),
		)
	);
}

/**
 * The stored priority slug for a snag, guaranteed to be a valid key.
 * Snags created before this feature existed have no meta row — they
 * read as 'normal'.
 *
 * @param int $post_id Snag post ID.
 * @return string
 */
function site_snags_get_priority( $post_id ) {
	$priority = get_post_meta( $post_id, '_snag_priority', true );
	return array_key_exists( $priority, site_snags_get_priorities() ) ? $priority : 'normal';
}

/**
 * Human-readable label for a snag's current priority.
 *
 * @param int $post_id Snag post ID.
 * @return string
 */
function site_snags_get_priority_label( $post_id ) {
	$priorities = site_snags_get_priorities();
	return $priorities[ site_snags_get_priority( $post_id ) ]['label'];
}

/**
 * Default notification settings, merged over whatever the site has saved.
 *
 * Defaults to fully on so that turning the feature on is zero-config — a
 * fresh install with no saved option emails the allow-list about all three
 * events. Site owners dial it back on the Settings screen.
 *
 * @return array { 'enabled' => int, 'events' => array<string,int> }
 */
function site_snags_get_notification_settings() {
	$defaults = array(
		'enabled' => 1,
		'events'  => array(
			'created'      => 1,
			'note_updated' => 1,
			'completed'    => 1,
			'commented'    => 1,
		),
	);

	$saved = get_option( 'site_snags_notification_settings', array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	$settings           = wp_parse_args( $saved, $defaults );
	$settings['events'] = wp_parse_args(
		isset( $saved['events'] ) && is_array( $saved['events'] ) ? $saved['events'] : array(),
		$defaults['events']
	);

	return $settings;
}

/**
 * Users who should receive snag activity emails: everyone the permission
 * model currently allows to snag, with a real email address, minus the
 * person who triggered the event.
 *
 * @param int $exclude_user_id Optional. User to leave out (the actor).
 * @return WP_User[] Keyed by user ID.
 */
function site_snags_get_notification_recipients( $exclude_user_id = 0 ) {
	$allowed_users = get_option( 'site_snags_allowed_users', false );

	if ( false === $allowed_users ) {
		// Allow-list never configured — everyone with the capability.
		$users = get_users( array( 'capability' => SITE_SNAGS_CAP ) );
	} else {
		$ids   = array_map( 'intval', (array) $allowed_users );
		$users = $ids ? get_users( array( 'include' => $ids ) ) : array();
	}

	$recipients = array();

	foreach ( $users as $user ) {
		if ( $exclude_user_id && (int) $user->ID === (int) $exclude_user_id ) {
			continue;
		}
		if ( ! is_email( $user->user_email ) ) {
			continue;
		}
		if ( ! site_snags_user_is_allowed( $user->ID ) ) {
			continue;
		}
		$recipients[ $user->ID ] = $user;
	}

	/**
	 * Filter the list of users notified about snag activity.
	 *
	 * @param WP_User[] $recipients      Keyed by user ID.
	 * @param int       $exclude_user_id The actor being left out.
	 */
	return apply_filters( 'site_snags_notification_recipients', $recipients, $exclude_user_id );
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
		new Site_Snags_Notifications();

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
