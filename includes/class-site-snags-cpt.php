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
		add_action( 'add_meta_boxes_site_snag', array( $this, 'add_note_meta_box' ) );
		add_action( 'save_post_site_snag', array( $this, 'save_note_meta_box' ), 10, 2 );
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
			// No 'editor' — the snag note is stored in the `_snag_note_raw`
			// meta field, not post_content, so the WYSIWYG box was always
			// empty and unused on the edit screen.
			'supports'            => array( 'title', 'comments' ),
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
			'_snag_priority',
			array_merge(
				$common_args,
				array(
					'type'              => 'string',
					'default'           => 'normal',
					'sanitize_callback' => array( $this, 'sanitize_priority' ),
				)
			)
		);

		register_post_meta(
			'site_snag',
			'_snag_assignee',
			array_merge(
				$common_args,
				array(
					'type'              => 'integer',
					'default'           => 0,
					'sanitize_callback' => array( $this, 'sanitize_assignee' ),
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
	 * Register the Note meta box on the snag edit screen.
	 *
	 * Replaces the (unused) post_content editor: the full snag note is
	 * stored in `_snag_note_raw`, while the post title only holds the
	 * first few words of it. The note and priority are read-only here;
	 * the assignee dropdown is the one editable control.
	 */
	public function add_note_meta_box() {
		add_meta_box(
			'site_snags_note',
			__( 'Note', 'site-snags' ),
			array( $this, 'render_note_meta_box' ),
			'site_snag',
			'normal',
			'high'
		);
	}

	/**
	 * Output the full snag note text (read only) plus an editable assignee
	 * dropdown.
	 *
	 * @param WP_Post $post Current snag post.
	 */
	public function render_note_meta_box( $post ) {
		$note = get_post_meta( $post->ID, '_snag_note_raw', true );

		$priorities = site_snags_get_priorities();
		$priority   = site_snags_get_priority( $post->ID );

		printf(
			'<p style="margin:0 0 8px;"><span style="display:inline-block;width:12px;height:12px;border-radius:3px;vertical-align:-1px;margin-right:6px;background:%1$s;"></span><strong>%2$s</strong> %3$s</p>',
			esc_attr( $priorities[ $priority ]['color'] ),
			esc_html__( 'Priority:', 'site-snags' ),
			esc_html( $priorities[ $priority ]['label'] )
		);

		if ( '' === $note || null === $note ) {
			echo '<p class="description">' . esc_html__( 'No note recorded for this snag.', 'site-snags' ) . '</p>';
		} else {
			echo '<p style="white-space:pre-wrap;margin:0;">' . esc_html( $note ) . '</p>';
		}

		$assignee_id = site_snags_get_snag_assignee( $post->ID );
		$assignable  = site_snags_get_assignable_users();

		wp_nonce_field( 'site_snags_note_meta', 'site_snags_note_meta_nonce' );

		echo '<p style="margin:14px 0 4px;"><label for="site_snags_assignee"><strong>' . esc_html__( 'Assigned to', 'site-snags' ) . '</strong></label></p>';
		echo '<select name="site_snags_assignee" id="site_snags_assignee" style="max-width:100%;">';
		printf(
			'<option value="0">%s</option>',
			esc_html__( '— Unassigned (notify everyone) —', 'site-snags' )
		);
		foreach ( $assignable as $id => $name ) {
			printf(
				'<option value="%1$d"%2$s>%3$s</option>',
				(int) $id,
				selected( $assignee_id, $id, false ),
				esc_html( $name )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'When set, only this person is emailed about the snag — not the whole allow-list.', 'site-snags' ) . '</p>';
	}

	/**
	 * Persist the assignee dropdown from the Note meta box.
	 *
	 * @param int     $post_id Snag post ID.
	 * @param WP_Post $post    Snag post object.
	 */
	public function save_note_meta_box( $post_id, $post ) {
		if ( ! isset( $_POST['site_snags_note_meta_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['site_snags_note_meta_nonce'] ) ), 'site_snags_note_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( SITE_SNAGS_CAP ) ) {
			return;
		}
		if ( ! array_key_exists( 'site_snags_assignee', $_POST ) ) {
			return;
		}

		$new = $this->sanitize_assignee( wp_unslash( $_POST['site_snags_assignee'] ) );
		$old = (int) get_post_meta( $post_id, '_snag_assignee', true );

		if ( $new === $old ) {
			return;
		}

		update_post_meta( $post_id, '_snag_assignee', $new );

		if ( $new ) {
			/**
			 * Fires when a snag is assigned (or reassigned) to a user.
			 *
			 * @param int $post_id     Snag post ID.
			 * @param int $assignee_id User the snag is now assigned to.
			 * @param int $actor_id    User who made the change.
			 */
			do_action( 'site_snags_snag_assigned', $post_id, $new, get_current_user_id() );
		}
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

	/**
	 * Only allow known priority slugs.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_priority( $value ) {
		$value = sanitize_text_field( $value );
		return array_key_exists( $value, site_snags_get_priorities() ) ? $value : 'normal';
	}

	/**
	 * Only allow a user ID that is currently eligible to be an assignee.
	 * Anything else (0, empty, a user who has left the allow-list) stores
	 * as 0 — "unassigned".
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public function sanitize_assignee( $value ) {
		$value = absint( $value );
		if ( ! $value ) {
			return 0;
		}
		$allowed = site_snags_get_allowed_users();
		return isset( $allowed[ $value ] ) ? $value : 0;
	}
}
