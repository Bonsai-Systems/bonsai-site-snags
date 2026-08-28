<?php
/**
 * AJAX handlers for the front-end snag layer.
 *
 * All actions are admin-only (capability checked) and nonce-protected.
 * Nothing here is ever exposed to logged-out visitors.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Site_Snags_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_site_snags_create', array( $this, 'create_snag' ) );
		add_action( 'wp_ajax_site_snags_update_status', array( $this, 'update_status' ) );
		add_action( 'wp_ajax_site_snags_update_priority', array( $this, 'update_priority' ) );
		add_action( 'wp_ajax_site_snags_update_assignee', array( $this, 'update_assignee' ) );
		add_action( 'wp_ajax_site_snags_update_note', array( $this, 'update_note' ) );
		add_action( 'wp_ajax_site_snags_delete', array( $this, 'delete_snag' ) );
		add_action( 'wp_ajax_site_snags_fetch_for_page', array( $this, 'fetch_for_page' ) );
	}

	/**
	 * Shared guard: nonce + capability. Dies with JSON error on failure.
	 */
	private function verify_request() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'site_snags_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'site-snags' ) ), 403 );
		}

		if ( ! site_snags_user_is_allowed() ) {
			wp_send_json_error( array( 'message' => __( 'Not permitted.', 'site-snags' ) ), 403 );
		}
	}

	/**
	 * Create a new snag from a click on the front end.
	 */
	public function create_snag() {
		$this->verify_request();

		$note       = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		$url        = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		$selector   = isset( $_POST['selector'] ) ? sanitize_text_field( wp_unslash( $_POST['selector'] ) ) : '';
		$offset_x   = isset( $_POST['offset_x'] ) ? (float) $_POST['offset_x'] : 0;
		$offset_y   = isset( $_POST['offset_y'] ) ? (float) $_POST['offset_y'] : 0;
		$page_title = isset( $_POST['page_title'] ) ? sanitize_text_field( wp_unslash( $_POST['page_title'] ) ) : '';
		$priority   = isset( $_POST['priority'] ) ? sanitize_text_field( wp_unslash( $_POST['priority'] ) ) : 'normal';
		if ( ! array_key_exists( $priority, site_snags_get_priorities() ) ) {
			$priority = 'normal';
		}

		$assignee = isset( $_POST['assignee'] ) ? absint( $_POST['assignee'] ) : 0;
		if ( $assignee && ! array_key_exists( $assignee, site_snags_get_allowed_users() ) ) {
			$assignee = 0;
		}

		if ( '' === $note || '' === $url ) {
			wp_send_json_error( array( 'message' => __( 'Missing note or URL.', 'site-snags' ) ), 400 );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'site_snag',
				'post_status' => 'publish',
				'post_title'  => wp_trim_words( $note, 8, '…' ),
				'post_author' => get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => $post_id->get_error_message() ), 500 );
		}

		update_post_meta( $post_id, '_snag_url', $url );
		update_post_meta( $post_id, '_snag_selector', $selector );
		update_post_meta( $post_id, '_snag_offset_x', max( 0, min( 100, $offset_x ) ) );
		update_post_meta( $post_id, '_snag_offset_y', max( 0, min( 100, $offset_y ) ) );
		update_post_meta( $post_id, '_snag_status', 'open' );
		update_post_meta( $post_id, '_snag_priority', $priority );
		update_post_meta( $post_id, '_snag_assignee', $assignee );
		update_post_meta( $post_id, '_snag_page_title', $page_title );
		update_post_meta( $post_id, '_snag_note_raw', $note );

		/**
		 * Fires after a snag is created from the front end.
		 *
		 * @param int $post_id  New snag post ID.
		 * @param int $actor_id User who created it.
		 */
		do_action( 'site_snags_snag_created', $post_id, get_current_user_id() );

		wp_send_json_success(
			array(
				'id'         => $post_id,
				'note'       => $note,
				'status'     => 'open',
				'priority'   => $priority,
				'assignee'   => $assignee,
				'offset_x'   => $offset_x,
				'offset_y'   => $offset_y,
				'selector'   => $selector,
				'author'     => wp_get_current_user()->display_name,
				'created_at' => current_time( 'd/m/Y H:i' ),
			)
		);
	}

	/**
	 * Toggle a snag between open/done.
	 */
	public function update_status() {
		$this->verify_request();

		$post_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$status  = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

		if ( ! $post_id || 'site_snag' !== get_post_type( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Snag not found.', 'site-snags' ) ), 404 );
		}

		if ( ! in_array( $status, array( 'open', 'done' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid status.', 'site-snags' ) ), 400 );
		}

		update_post_meta( $post_id, '_snag_status', $status );

		if ( 'done' === $status ) {
			/**
			 * Fires when a snag is marked done.
			 *
			 * @param int $post_id  Snag post ID.
			 * @param int $actor_id User who completed it.
			 */
			do_action( 'site_snags_snag_completed', $post_id, get_current_user_id() );
		}

		wp_send_json_success( array( 'id' => $post_id, 'status' => $status ) );
	}

	/**
	 * Set a snag's priority (urgent / normal / low).
	 */
	public function update_priority() {
		$this->verify_request();

		$post_id  = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$priority = isset( $_POST['priority'] ) ? sanitize_text_field( wp_unslash( $_POST['priority'] ) ) : '';

		if ( ! $post_id || 'site_snag' !== get_post_type( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Snag not found.', 'site-snags' ) ), 404 );
		}

		if ( ! array_key_exists( $priority, site_snags_get_priorities() ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid priority.', 'site-snags' ) ), 400 );
		}

		update_post_meta( $post_id, '_snag_priority', $priority );

		wp_send_json_success( array( 'id' => $post_id, 'priority' => $priority ) );
	}

	/**
	 * Assign a snag to a single user (or clear the assignment with 0).
	 * When assigned, notifications for that snag route to the assignee only.
	 */
	public function update_assignee() {
		$this->verify_request();

		$post_id  = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$assignee = isset( $_POST['assignee'] ) ? absint( $_POST['assignee'] ) : 0;

		if ( ! $post_id || 'site_snag' !== get_post_type( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Snag not found.', 'site-snags' ) ), 404 );
		}

		if ( $assignee && ! array_key_exists( $assignee, site_snags_get_allowed_users() ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid assignee.', 'site-snags' ) ), 400 );
		}

		$old = (int) get_post_meta( $post_id, '_snag_assignee', true );
		update_post_meta( $post_id, '_snag_assignee', $assignee );

		if ( $assignee && $assignee !== $old ) {
			/**
			 * Fires when a snag is assigned (or reassigned) to a user.
			 *
			 * @param int $post_id     Snag post ID.
			 * @param int $assignee_id User the snag is now assigned to.
			 * @param int $actor_id    User who made the change.
			 */
			do_action( 'site_snags_snag_assigned', $post_id, $assignee, get_current_user_id() );
		}

		wp_send_json_success( array( 'id' => $post_id, 'assignee' => $assignee ) );
	}

	/**
	 * Edit a snag's note text in place.
	 */
	public function update_note() {
		$this->verify_request();

		$post_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$note    = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

		if ( ! $post_id || 'site_snag' !== get_post_type( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Snag not found.', 'site-snags' ) ), 404 );
		}

		if ( '' === $note ) {
			wp_send_json_error( array( 'message' => __( 'Note cannot be empty.', 'site-snags' ) ), 400 );
		}

		update_post_meta( $post_id, '_snag_note_raw', $note );

		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => wp_trim_words( $note, 8, '…' ),
			)
		);

		/**
		 * Fires after a snag's note text is edited in place.
		 *
		 * @param int $post_id  Snag post ID.
		 * @param int $actor_id User who edited it.
		 */
		do_action( 'site_snags_snag_note_updated', $post_id, get_current_user_id() );

		wp_send_json_success( array( 'id' => $post_id, 'note' => $note ) );
	}

	/**
	 * Delete a snag outright (used by the front-end pin's delete control).
	 */
	public function delete_snag() {
		$this->verify_request();

		$post_id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( ! $post_id || 'site_snag' !== get_post_type( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Snag not found.', 'site-snags' ) ), 404 );
		}

		wp_trash_post( $post_id );

		wp_send_json_success( array( 'id' => $post_id ) );
	}

	/**
	 * Return all snags logged against the current URL, for re-rendering pins
	 * on page load.
	 */
	public function fetch_for_page() {
		$this->verify_request();

		$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';

		if ( '' === $url ) {
			wp_send_json_error( array( 'message' => __( 'Missing URL.', 'site-snags' ) ), 400 );
		}

		$query = new WP_Query(
			array(
				'post_type'      => 'site_snag',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => '_snag_url',
						'value' => $url,
					),
				),
			)
		);

		$snags = array();

		foreach ( $query->posts as $post ) {
			$snags[] = array(
				'id'         => $post->ID,
				'note'       => get_post_meta( $post->ID, '_snag_note_raw', true ),
				'status'     => get_post_meta( $post->ID, '_snag_status', true ),
				'priority'   => site_snags_get_priority( $post->ID ),
				'assignee'   => site_snags_get_snag_assignee( $post->ID ),
				'selector'   => get_post_meta( $post->ID, '_snag_selector', true ),
				'offset_x'   => (float) get_post_meta( $post->ID, '_snag_offset_x', true ),
				'offset_y'   => (float) get_post_meta( $post->ID, '_snag_offset_y', true ),
				'author'     => get_the_author_meta( 'display_name', $post->post_author ),
				'created_at' => get_the_date( 'd/m/Y H:i', $post ),
			);
		}

		wp_send_json_success( array( 'snags' => $snags ) );
	}
}
