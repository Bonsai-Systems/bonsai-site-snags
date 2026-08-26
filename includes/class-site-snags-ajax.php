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
		update_post_meta( $post_id, '_snag_page_title', $page_title );
		update_post_meta( $post_id, '_snag_note_raw', $note );

		wp_send_json_success(
			array(
				'id'         => $post_id,
				'note'       => $note,
				'status'     => 'open',
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

		wp_send_json_success( array( 'id' => $post_id, 'status' => $status ) );
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
