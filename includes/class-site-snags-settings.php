<?php
/**
 * Settings screen: tick which users are allowed to see the front-end
 * toggle and log snags. Lives under Site Snags > Settings in wp-admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Site_Snags_Settings {

	const OPTION_KEY = 'site_snags_allowed_users';
	const NONCE      = 'site_snags_settings_nonce';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_post_site_snags_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_notices', array( $this, 'saved_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Bonsai admin styles, on this settings screen only.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'site_snag_page_site-snags-settings' !== $hook_suffix ) {
			return;
		}
		Site_Snags_Admin_UI::enqueue();
		wp_enqueue_style( 'site-snags-admin-settings', SITE_SNAGS_URL . 'assets/css/admin-settings.css', array( Site_Snags_Admin_UI::HANDLE ), SITE_SNAGS_VERSION );
	}

	/**
	 * Add "Settings" as a submenu under the Site Snags CPT menu.
	 */
	public function add_settings_page() {
		add_submenu_page(
			'edit.php?post_type=site_snag',
			__( 'Site Snags Settings', 'site-snags' ),
			__( 'Settings', 'site-snags' ),
			'manage_options',
			'site-snags-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Users eligible to appear in the checklist at all — anyone holding the
	 * base capability. No point listing subscribers who could never snag
	 * regardless of the allow-list.
	 *
	 * @return WP_User[]
	 */
	private function get_eligible_users() {
		return get_users(
			array(
				'capability' => SITE_SNAGS_CAP,
				'orderby'    => 'display_name',
				'order'      => 'ASC',
			)
		);
	}

	/**
	 * Render the settings screen.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$saved_setting = get_option( self::OPTION_KEY, false );
		$is_configured = ( false !== $saved_setting );
		$allowed_ids   = $is_configured ? array_map( 'intval', $saved_setting ) : array();
		$eligible      = $this->get_eligible_users();
		?>
		<div class="wrap bonsai-ui site-snags-settings">
			<?php
			Site_Snags_Admin_UI::header(
				__( 'Site Snags — Settings', 'site-snags' ),
				__( 'Choose who can log snags from the front end, and who gets emailed about snag activity.', 'site-snags' ),
				array(
					array(
						'label' => __( 'All snags', 'site-snags' ),
						'url'   => admin_url( 'edit.php?post_type=site_snag' ),
					),
				)
			);
			?>

			<section class="bonsai-ui-card" aria-labelledby="site-snags-access-title">
					<div class="bonsai-ui-card__head">
						<h2 class="bonsai-ui-card__title" id="site-snags-access-title"><?php esc_html_e( 'Who can snag', 'site-snags' ); ?></h2>
						<?php if ( $is_configured ) : ?>
							<span class="bonsai-ui-badge bonsai-ui-badge--success"><?php esc_html_e( 'Custom allow-list', 'site-snags' ); ?></span>
						<?php else : ?>
							<span class="bonsai-ui-badge"><?php esc_html_e( 'Everyone with capability', 'site-snags' ); ?></span>
						<?php endif; ?>
					</div>
					<p class="bonsai-ui-card__intro">
						<?php esc_html_e( 'By default, everyone who holds the required capability can use the front-end snag toggle. Tick specific people below to restrict it to just them.', 'site-snags' ); ?>
					</p>

				<?php if ( empty( $eligible ) ) : ?>
					<p><em><?php esc_html_e( 'No users currently hold the required capability, so there is nobody to list here yet.', 'site-snags' ); ?></em></p>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="site_snags_save_settings" />
						<?php wp_nonce_field( self::NONCE, 'site_snags_settings_nonce_field' ); ?>

						<table class="widefat striped site-snags-users">
							<thead>
								<tr>
									<th scope="col" class="site-snags-users__check"><span class="screen-reader-text"><?php esc_html_e( 'Allowed', 'site-snags' ); ?></span></th>
									<th scope="col"><?php esc_html_e( 'User', 'site-snags' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Role', 'site-snags' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $eligible as $user ) : ?>
									<tr>
										<td>
											<input
												type="checkbox"
												name="site_snags_allowed_users[]"
												id="site-snags-user-<?php echo esc_attr( $user->ID ); ?>"
												value="<?php echo esc_attr( $user->ID ); ?>"
												<?php checked( ! $is_configured || in_array( $user->ID, $allowed_ids, true ) ); ?>
											/>
										</td>
										<td>
											<label for="site-snags-user-<?php echo esc_attr( $user->ID ); ?>">
												<?php echo esc_html( $user->display_name ); ?>
												<span class="site-snags-users__email">(<?php echo esc_html( $user->user_email ); ?>)</span>
											</label>
										</td>
										<td><?php echo esc_html( implode( ', ', $user->roles ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>

						<p class="description site-snags-users__status">
							<?php
							if ( $is_configured ) {
								esc_html_e( 'Custom allow-list is active — only ticked users see the toggle.', 'site-snags' );
							} else {
								esc_html_e( 'Not yet configured — every user with the required capability currently has access. Saving this form (with your chosen ticks) turns on the restricted list.', 'site-snags' );
							}
							?>
						</p>

						<?php submit_button( __( 'Save Settings', 'site-snags' ) ); ?>
					</form>

					<?php if ( $is_configured ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="site-snags-reset">
							<input type="hidden" name="action" value="site_snags_save_settings" />
							<input type="hidden" name="site_snags_reset" value="1" />
							<?php wp_nonce_field( self::NONCE, 'site_snags_settings_nonce_field' ); ?>
							<?php submit_button( __( 'Reset to "everyone with capability"', 'site-snags' ), 'secondary', 'submit', false ); ?>
						</form>
					<?php endif; ?>
				<?php endif; ?>
			</section>

			<?php $notify = site_snags_get_notification_settings(); ?>
			<section class="bonsai-ui-card" aria-labelledby="site-snags-notify-title">
					<div class="bonsai-ui-card__head">
						<h2 class="bonsai-ui-card__title" id="site-snags-notify-title"><?php esc_html_e( 'Email notifications', 'site-snags' ); ?></h2>
						<?php if ( ! empty( $notify['enabled'] ) ) : ?>
							<span class="bonsai-ui-badge bonsai-ui-badge--success"><?php esc_html_e( 'On', 'site-snags' ); ?></span>
						<?php else : ?>
							<span class="bonsai-ui-badge"><?php esc_html_e( 'Off', 'site-snags' ); ?></span>
						<?php endif; ?>
					</div>
					<p class="bonsai-ui-card__intro">
						<?php esc_html_e( 'Email the people who can use snagging (the allow-list above, or everyone with the capability if it is unconfigured) when snag activity happens. Whoever performed the action is never emailed about their own change.', 'site-snags' ); ?>
					</p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="site_snags_save_settings" />
					<input type="hidden" name="site_snags_notifications_submit" value="1" />
					<?php wp_nonce_field( self::NONCE, 'site_snags_settings_nonce_field' ); ?>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="site-snags-notifications-enabled"><?php esc_html_e( 'Notifications', 'site-snags' ); ?></label></th>
							<td>
								<label>
									<input type="checkbox" id="site-snags-notifications-enabled" name="site_snags_notifications_enabled" value="1" <?php checked( ! empty( $notify['enabled'] ) ); ?> />
									<?php esc_html_e( 'Send email notifications', 'site-snags' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Notify on', 'site-snags' ); ?></th>
							<td>
								<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Notify on', 'site-snags' ); ?></legend>
								<label>
									<input type="checkbox" name="site_snags_notification_events[created]" value="1" <?php checked( ! empty( $notify['events']['created'] ) ); ?> />
									<?php esc_html_e( 'A snag is added', 'site-snags' ); ?>
								</label>
								<label>
									<input type="checkbox" name="site_snags_notification_events[note_updated]" value="1" <?php checked( ! empty( $notify['events']['note_updated'] ) ); ?> />
									<?php esc_html_e( 'A snag note is edited', 'site-snags' ); ?>
								</label>
								<label>
									<input type="checkbox" name="site_snags_notification_events[completed]" value="1" <?php checked( ! empty( $notify['events']['completed'] ) ); ?> />
									<?php esc_html_e( 'A snag is marked done', 'site-snags' ); ?>
								</label>
								<label>
									<input type="checkbox" name="site_snags_notification_events[commented]" value="1" <?php checked( ! empty( $notify['events']['commented'] ) ); ?> />
									<?php esc_html_e( 'A comment is added to a snag', 'site-snags' ); ?>
								</label>
								<label>
									<input type="checkbox" name="site_snags_notification_events[assigned]" value="1" <?php checked( ! empty( $notify['events']['assigned'] ) ); ?> />
									<?php esc_html_e( 'A snag is assigned to someone', 'site-snags' ); ?>
								</label>
								</fieldset>
								<p class="description">
									<?php esc_html_e( 'When a snag has an assignee, every email for that snag goes to that person only — not the whole list above.', 'site-snags' ); ?>
								</p>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Save Notification Settings', 'site-snags' ) ); ?>
				</form>
			</section>
		</div>
		<?php
	}

	/**
	 * Handle the settings form submission.
	 */
	public function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not permitted.', 'site-snags' ) );
		}

		check_admin_referer( self::NONCE, 'site_snags_settings_nonce_field' );

		// Notification settings form — handled separately from the allow-list.
		if ( ! empty( $_POST['site_snags_notifications_submit'] ) ) {
			$events_in = ( isset( $_POST['site_snags_notification_events'] ) && is_array( $_POST['site_snags_notification_events'] ) )
				? array_map( 'sanitize_text_field', wp_unslash( $_POST['site_snags_notification_events'] ) )
				: array();

			update_option(
				'site_snags_notification_settings',
				array(
					'enabled' => empty( $_POST['site_snags_notifications_enabled'] ) ? 0 : 1,
					'events'  => array(
						'created'      => empty( $events_in['created'] ) ? 0 : 1,
						'note_updated' => empty( $events_in['note_updated'] ) ? 0 : 1,
						'completed'    => empty( $events_in['completed'] ) ? 0 : 1,
						'commented'    => empty( $events_in['commented'] ) ? 0 : 1,
						'assigned'     => empty( $events_in['assigned'] ) ? 0 : 1,
					),
				)
			);

			$this->redirect_to_settings();
		}

		if ( ! empty( $_POST['site_snags_reset'] ) ) {
			delete_option( self::OPTION_KEY );
		} else {
			$submitted = isset( $_POST['site_snags_allowed_users'] ) && is_array( $_POST['site_snags_allowed_users'] )
				? array_map( 'absint', wp_unslash( $_POST['site_snags_allowed_users'] ) )
				: array();

			// Only keep IDs that are actually valid, eligible users —
			// belt and braces against a tampered submission.
			$eligible_ids = wp_list_pluck( $this->get_eligible_users(), 'ID' );
			$submitted    = array_values( array_intersect( $submitted, $eligible_ids ) );

			update_option( self::OPTION_KEY, $submitted );
		}

		$this->redirect_to_settings();
	}

	/**
	 * Redirect back to the settings screen with the "saved" flag set.
	 */
	private function redirect_to_settings() {
		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => 'site_snag',
					'page'      => 'site-snags-settings',
					'updated'   => '1',
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Simple "Settings saved" admin notice on the settings screen.
	 */
	public function saved_notice() {
		if ( ! isset( $_GET['page'] ) || 'site-snags-settings' !== $_GET['page'] ) {
			return;
		}
		if ( empty( $_GET['updated'] ) ) {
			return;
		}
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Site Snags settings saved.', 'site-snags' ); ?></p>
		</div>
		<?php
	}
}
