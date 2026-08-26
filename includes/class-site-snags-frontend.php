<?php
/**
 * Loads the front-end toggle/pin layer for logged-in admins only.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Site_Snags_Frontend {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_toggle' ) );

		// Also available in wp-admin (e.g. previewing a page in an iframe)
		// if ever needed — off by default, front end only for now.
	}

	/**
	 * Only load anything at all if the current user is allowed to snag —
	 * checks both the base capability and the Settings allow-list.
	 * Everyone else gets zero extra bytes.
	 */
	private function user_can_snag() {
		return is_user_logged_in() && site_snags_user_is_allowed();
	}

	/**
	 * Enqueue JS/CSS, localise the data the JS needs (nonce, ajax URL, current URL).
	 */
	public function enqueue_assets() {
		if ( ! $this->user_can_snag() ) {
			return;
		}

		wp_enqueue_style(
			'site-snags',
			SITE_SNAGS_URL . 'assets/css/site-snags.css',
			array(),
			SITE_SNAGS_VERSION
		);

		wp_enqueue_script(
			'site-snags',
			SITE_SNAGS_URL . 'assets/js/site-snags.js',
			array( 'jquery' ),
			SITE_SNAGS_VERSION,
			true
		);

		global $wp;
		$current_url = home_url( add_query_arg( array(), $wp->request ) );
		// Fall back to REQUEST_URI if $wp->request is empty (front page etc).
		if ( empty( $wp->request ) ) {
			$current_url = home_url( '/' );
		}

		wp_localize_script(
			'site-snags',
			'SiteSnags',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'site_snags_nonce' ),
				'pageUrl'   => esc_url_raw( $current_url ),
				'pageTitle' => wp_get_document_title(),
				'listUrl'   => admin_url( 'edit.php?post_type=site_snag' ),
				'i18n'      => array(
					'placeholder' => __( 'What needs fixing here?', 'site-snags' ),
					'save'        => __( 'Save', 'site-snags' ),
					'cancel'      => __( 'Cancel', 'site-snags' ),
					'markDone'    => __( 'Mark done', 'site-snags' ),
					'reopen'      => __( 'Reopen', 'site-snags' ),
					'delete'      => __( 'Delete', 'site-snags' ),
					'toggleOn'    => __( 'Snag mode: click anywhere to add a note', 'site-snags' ),
					'toggleOff'   => __( 'Snagging', 'site-snags' ),
				),
			)
		);
	}

	/**
	 * Output the bottom-right toggle button markup. JS handles everything else.
	 */
	public function render_toggle() {
		if ( ! $this->user_can_snag() ) {
			return;
		}
		?>
		<div id="site-snags-root" aria-hidden="false">
			<button type="button" id="site-snags-toggle" class="site-snags-toggle" aria-pressed="false">
				<span class="site-snags-toggle__icon" aria-hidden="true">＋</span>
				<span class="site-snags-toggle__label"><?php esc_html_e( 'Snags', 'site-snags' ); ?></span>
			</button>
		</div>
		<?php
	}
}
