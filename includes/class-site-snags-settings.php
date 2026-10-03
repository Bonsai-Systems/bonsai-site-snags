<?php
/**
 * Settings screen: tick which users are allowed to see the front-end
 * toggle and log snags. Lives under Bonsai → Site Snags → Settings in
 * wp-admin, alongside an "All snags" tab linking to the snag list.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Site_Snags_Settings {

	const OPTION_KEY = 'site_snags_allowed_users';
	const NONCE      = 'site_snags_settings_nonce';
	const PAGE_SLUG  = 'site-snags';

	public function __construct() {
		add_filter( 'bonsai_hub_modules', array( $this, 'register_hub_module' ) );
		add_action( 'admin_post_site_snags_save_settings', array( $this, 'save_settings' ) );
		add_filter( 'parent_file', array( $this, 'highlight_parent_menu' ) );
		add_filter( 'submenu_file', array( $this, 'highlight_submenu' ) );
	}

	/**
	 * Registers Bonsai → Site Snags with the shared Bonsai menu: an
	 * "All snags" tab linking to the post type list, and Settings.
	 *
	 * Anyone with SITE_SNAGS_CAP gets the menu item; those who can't manage
	 * settings are sent straight to the list by the hub. The old
	 * edit.php?post_type=site_snag&page=site-snags-settings URL redirects to
	 * the Settings tab.
	 *
	 * @param array $modules Modules registered so far.
	 * @return array
	 */
	public function register_hub_module( $modules ) {
		$modules[ self::PAGE_SLUG ] = array(
			'label'       => __( 'Site Snags', 'site-snags' ),
			'title'       => __( 'Site Snags', 'site-snags' ),
			'description' => __( 'Choose who can log snags from the front end, and who gets emailed about snag activity.', 'site-snags' ),
			'version'     => SITE_SNAGS_VERSION,
			'repo'        => 'https://github.com/Bonsai-Systems/bonsai-site-snags',
			'capability'  => SITE_SNAGS_CAP,
			'enqueue'     => array( $this, 'enqueue_assets' ),
			'legacy'      => array( 'site-snags-settings' => 'settings' ),
			'tabs'        => array(
				'snags'    => array(
					'label' => __( 'All snags', 'site-snags' ),
					'url'   => admin_url( 'edit.php?post_type=site_snag' ),
				),
				'settings' => array(
					'label'      => __( 'Settings', 'site-snags' ),
					'render'     => array( $this, 'render_settings_page' ),
					'capability' => 'manage_options',
				),
			),
		);

		return $modules;
	}

	/**
	 * Settings screen styles. Called by the hub on this screen only, after
	 * the shared Bonsai styles.
	 */
	public function enqueue_assets() {
		wp_enqueue_style( 'site-snags-admin-settings', SITE_SNAGS_URL . 'assets/css/admin-settings.css', array( 'bonsai-hub-ui' ), SITE_SNAGS_VERSION );
	}

	/**
	 * Keeps Bonsai open in the sidebar on the snag list and edit screens,
	 * which have no menu item of their own any more.
	 *
	 * @param string $parent_file Parent menu slug for the current screen.
	 * @return string
	 */
	public function highlight_parent_menu( $parent_file ) {
		global $typenow;

		return 'site_snag' === $typenow ? 'bonsai' : $parent_file;
	}

	/**
	 * Highlights Bonsai → Site Snags on the snag list and edit screens.
	 *
	 * @param string|null $submenu_file Submenu slug for the current screen.
	 * @return string|null
	 */
	public function highlight_submenu( $submenu_file ) {
		global $typenow;

		return 'site_snag' === $typenow ? self::PAGE_SLUG : $submenu_file;
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
	 * Render the Settings tab. The hub prints the page wrap, header, notices
	 * and tabs around it, and has already checked manage_options.
	 */
	public function render_settings_page() {
		$saved_setting = get_option( self::OPTION_KEY, false );
		$is_configured = ( false !== $saved_setting );
		$allowed_ids   = $is_configured ? array_map( 'intval', $saved_setting ) : array();
		$eligible      = $this->get_eligible_users();
		?>
		<div class="site-snags-settings">
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
	 * Redirect back to the Settings tab. ?settings-updated makes the hub
	 * show its "Settings saved." notice.
	 */
	private function redirect_to_settings() {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => self::PAGE_SLUG,
					'tab'              => 'settings',
					'settings-updated' => 'true',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
