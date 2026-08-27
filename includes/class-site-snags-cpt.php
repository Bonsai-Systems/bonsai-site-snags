<?php
/**
 * Registers the `site_snag` custom post type and its meta.
 *
 * Deliberately built on plain post meta rather than ACF fields — this plugin
 * needs to work on sites that don't have ACF Pro active, since it may end up
 * distributed standalone.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Site_Snags_CPT {

	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_meta' ) );
	}

	/**
	 * Register the site_snag CPT. Admin-only, not queryable on the front end,
	 * no public archive/single template — it only ever appears in wp-admin.
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => __( 'Site Snags', 'site-snags' ),
			'singular_name'      => __( 'Snag', 'site-snags' ),
			'menu_name'          => __( 'Site Snags', 'site-snags' ),
			'add_new_item'       => __( 'Add New Snag', 'site-snags' ),
			'edit_item'          => __( 'Edit Snag', 'site-snags' ),
			'search_items'       => __( 'Search Snags', 'site-snags' ),
			'not_found'          => __( 'No snags found.', 'site-snags' ),
			'not_found_in_trash' => __( 'No snags found in trash.', 'site-snags' ),
		);

		$args = array(
			'labels'              => $labels,
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_icon'           => 'dashicons-flag',
			'menu_position'       => 100,
			'capability_type'     => 'post',
			/**
			 * Deliberately does NOT override the singular meta-cap keys
			 * (edit_post/read_post/delete_post). Doing so — even to a
			 * capability as innocuous-looking as `manage_options` — makes
			 * WordPress register that capability as a GLOBAL ALIAS back to
			 * the real meta cap (see `$post_type_meta_caps` in
			 * wp-includes/capabilities.php). Every unrelated
			 * `current_user_can( 'manage_options' )` check anywhere on the
			 * site then gets silently rerouted through
			 * `map_meta_cap( 'delete_post', $user_id )` with no post
			 * object, triggering WordPress's "called incorrectly" notice
			 * site-wide (reproduced with this plugin as the only active
			 * one — confirmed via `$post_type_meta_caps` in core).
			 *
			 * Leaving the singular keys at their WordPress defaults means
			 * no alias is registered. `map_meta_cap => true` still routes
			 * per-post edit/read/delete checks through the plural
			 * primitives below (author vs non-author), which are gated on
			 * SITE_SNAGS_CAP exactly as before.
			 */
			'capabilities'        => array(
				'edit_posts'             => SITE_SNAGS_CAP,
				'edit_others_posts'      => SITE_SNAGS_CAP,
				'edit_private_posts'     => SITE_SNAGS_CAP,
				'edit_published_posts'   => SITE_SNAGS_CAP,
				'publish_posts'          => SITE_SNAGS_CAP,
				'read_private_posts'     => SITE_SNAGS_CAP,
				'delete_posts'           => SITE_SNAGS_CAP,
				'delete_private_posts'   => SITE_SNAGS_CAP,
				'delete_published_posts' => SITE_SNAGS_CAP,
				'delete_others_posts'    => SITE_SNAGS_CAP,
			),
			'map_meta_cap'        => true,
			'hierarchical'        => false,
			'supports'            => array( 'title', 'editor', 'comments' ),
			'has_archive'         => false,
			'rewrite'             => false,
			'show_in_rest'        => false,
			'exclude_from_search' => true,
		);

		register_post_type( 'site_snag', $args );

		// Simple open/done taxonomy-free status — handled via post meta
		// (`_snag_status`) rather than post_status, so drafts/trash still work
		// normally in the admin list.
	}

	/**
	 * Register meta fields with sanitisation + auth callbacks so REST/AJAX
	 * writes are safe and typed.
	 */
	public function register_meta() {
		$common_args = array(
			'show_in_rest'  => false,
			'single'        => true,
			'auth_callback' => function () {
				return current_user_can( SITE_SNAGS_CAP );
			},
		);

		register_post_meta(
			'site_snag',
			'_snag_url',
			array_merge(
				$common_args,
				array(
					'type'              => 'string',
					'sanitize_callback' => 'esc_url_raw',
				)
			)
		);

		register_post_meta(
			'site_snag',
			'_snag_selector',
			array_merge(
				$common_args,
				array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				)
			)
		);

		register_post_meta(
			'site_snag',
			'_snag_offset_x',
			array_merge(
				$common_args,
				array(
					'type'              => 'number',
					'sanitize_callback' => array( $this, 'sanitize_percentage' ),
				)
			)
		);

		register_post_meta(
			'site_snag',
			'_snag_offset_y',
			array_merge(
				$common_args,
				array(
					'type'              => 'number',
					'sanitize_callback' => array( $this, 'sanitize_percentage' ),
				)
			)
		);

		register_post_meta(
			'site_snag',
			'_snag_status',
			array_merge(
				$common_args,
				array(
					'type'              => 'string',
					'default'           => 'open',
					'sanitize_callback' => array( $this, 'sanitize_status' ),
				)
			)
		);

		register_post_meta(
			'site_snag',
			'_snag_page_title',
			array_merge(
				$common_args,
				array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				)
			)
		);
	}

	/**
	 * Clamp a percentage value to 0–100.
	 *
	 * @param mixed $value Raw value.
	 * @return float
	 */
	public function sanitize_percentage( $value ) {
		$value = (float) $value;
		return max( 0, min( 100, $value ) );
	}

	/**
	 * Only allow known status values.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_status( $value ) {
		$value = sanitize_text_field( $value );
		return in_array( $value, array( 'open', 'done' ), true ) ? $value : 'open';
	}
}
