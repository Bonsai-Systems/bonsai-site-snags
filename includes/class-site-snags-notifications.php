<?php
/**
 * Email notifications for snag activity.
 *
 * Listens for the three `do_action()` hooks fired by Site_Snags_Ajax and emails
 * everyone on the allow-list (see site_snags_get_notification_recipients()),
 * minus whoever triggered the change. Delivery is plain wp_mail(); failures are
 * logged, never surfaced to the user.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Site_Snags_Notifications {

	public function __construct() {
		add_action( 'site_snags_snag_created', array( $this, 'on_created' ), 10, 2 );
		add_action( 'site_snags_snag_note_updated', array( $this, 'on_note_updated' ), 10, 2 );
		add_action( 'site_snags_snag_completed', array( $this, 'on_completed' ), 10, 2 );
	}

	/**
	 * Whether a given event should send mail right now.
	 *
	 * @param string $event created|note_updated|completed.
	 * @return bool
	 */
	private function is_event_enabled( $event ) {
		$settings = site_snags_get_notification_settings();
		return ! empty( $settings['enabled'] ) && ! empty( $settings['events'][ $event ] );
	}

	/**
	 * A snag was created on the front end.
	 *
	 * @param int $post_id  Snag post ID.
	 * @param int $actor_id User who created it.
	 */
	public function on_created( $post_id, $actor_id ) {
		if ( ! $this->is_event_enabled( 'created' ) ) {
			return;
		}
		$this->dispatch(
			'created',
			$post_id,
			$actor_id,
			/* translators: %s: page title or URL the snag was logged on. */
			__( 'New snag on %s', 'site-snags' ),
			/* translators: 1: user display name, 2: page title or URL. */
			__( '%1$s added a new snag on "%2$s".', 'site-snags' )
		);
	}

	/**
	 * A snag's note was edited.
	 *
	 * @param int $post_id  Snag post ID.
	 * @param int $actor_id User who edited it.
	 */
	public function on_note_updated( $post_id, $actor_id ) {
		if ( ! $this->is_event_enabled( 'note_updated' ) ) {
			return;
		}
		$this->dispatch(
			'note_updated',
			$post_id,
			$actor_id,
			/* translators: %s: page title or URL the snag was logged on. */
			__( 'Snag updated on %s', 'site-snags' ),
			/* translators: 1: user display name, 2: page title or URL. */
			__( '%1$s edited the note on a snag on "%2$s".', 'site-snags' )
		);
	}

	/**
	 * A snag was marked done.
	 *
	 * @param int $post_id  Snag post ID.
	 * @param int $actor_id User who completed it.
	 */
	public function on_completed( $post_id, $actor_id ) {
		if ( ! $this->is_event_enabled( 'completed' ) ) {
			return;
		}
		$this->dispatch(
			'completed',
			$post_id,
			$actor_id,
			/* translators: %s: page title or URL the snag was logged on. */
			__( 'Snag marked done on %s', 'site-snags' ),
			/* translators: 1: user display name, 2: page title or URL. */
			__( '%1$s marked a snag as done on "%2$s".', 'site-snags' )
		);
	}

	/**
	 * Build and send the notification email to every recipient.
	 *
	 * @param string $event        Event key, passed through to the filter.
	 * @param int    $post_id      Snag post ID.
	 * @param int    $actor_id     User who triggered the event (never emailed).
	 * @param string $subject_tmpl sprintf template, one %s for the page title.
	 * @param string $line_tmpl    sprintf template, %1$s actor, %2$s page title.
	 */
	private function dispatch( $event, $post_id, $actor_id, $subject_tmpl, $line_tmpl ) {
		$recipients = site_snags_get_notification_recipients( $actor_id );
		if ( empty( $recipients ) ) {
			return;
		}

		$actor      = get_userdata( $actor_id );
		$actor_name = $actor ? $actor->display_name : __( 'Someone', 'site-snags' );

		$url        = get_post_meta( $post_id, '_snag_url', true );
		$page_title = get_post_meta( $post_id, '_snag_page_title', true );
		$page_title = $page_title ? $page_title : $url;
		$note       = get_post_meta( $post_id, '_snag_note_raw', true );
		$status     = 'done' === get_post_meta( $post_id, '_snag_status', true )
			? __( 'Done', 'site-snags' )
			: __( 'Open', 'site-snags' );
		$edit_link  = get_edit_post_link( $post_id, 'raw' );

		$subject = sprintf(
			'[%1$s] %2$s',
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			sprintf( $subject_tmpl, $page_title )
		);

		$body_lines = array(
			sprintf( $line_tmpl, $actor_name, $page_title ),
			'',
			/* translators: %s: the snag note text. */
			sprintf( __( 'Note: %s', 'site-snags' ), $note ),
			/* translators: %s: Open or Done. */
			sprintf( __( 'Status: %s', 'site-snags' ), $status ),
			'',
		);

		if ( $url ) {
			/* translators: %s: front-end URL the snag was logged on. */
			$body_lines[] = sprintf( __( 'Page: %s', 'site-snags' ), $url );
		}
		if ( $edit_link ) {
			/* translators: %s: wp-admin edit URL for the snag. */
			$body_lines[] = sprintf( __( 'Manage: %s', 'site-snags' ), $edit_link );
		}

		/**
		 * Filter the notification email before it is sent.
		 *
		 * @param array  $email    { 'subject' => string, 'body' => string, 'headers' => array }.
		 * @param string $event    created|note_updated|completed.
		 * @param int    $post_id  Snag post ID.
		 * @param int    $actor_id User who triggered the event.
		 */
		$email = apply_filters(
			'site_snags_notification_email',
			array(
				'subject' => $subject,
				'body'    => implode( "\n", $body_lines ),
				'headers' => array(),
			),
			$event,
			$post_id,
			$actor_id
		);

		foreach ( $recipients as $user ) {
			try {
				wp_mail( $user->user_email, $email['subject'], $email['body'], $email['headers'] );
			} catch ( Exception $e ) {
				error_log( 'Site Snags: notification email to ' . $user->user_email . ' failed — ' . $e->getMessage() );
			}
		}
	}
}
