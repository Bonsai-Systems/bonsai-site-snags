<?php
/**
 * Enhances the default wp-admin list table for site_snag so it works as a
 * usable QA punch-list: status column, page link, open/done filter.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Site_Snags_Admin_List {

	public function __construct() {
		add_filter( 'manage_site_snag_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_site_snag_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_filter( 'views_edit-site_snag', array( $this, 'status_views' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_by_status' ) );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
	}

	/**
	 * Define columns: title (note), page, status, author, date.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function columns( $columns ) {
		$new = array(
			'cb'          => $columns['cb'],
			'title'       => __( 'Note', 'site-snags' ),
			'snag_page'   => __( 'Page', 'site-snags' ),
			'snag_status' => __( 'Status', 'site-snags' ),
			'author'      => $columns['author'] ?? __( 'Logged by', 'site-snags' ),
			'date'        => $columns['date'],
		);
		return $new;
	}

	/**
	 * Render custom column content.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function render_column( $column, $post_id ) {
		if ( 'snag_page' === $column ) {
			$url        = get_post_meta( $post_id, '_snag_url', true );
			$page_title = get_post_meta( $post_id, '_snag_page_title', true );
			if ( $url ) {
				printf(
					'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
					esc_url( $url ),
					esc_html( $page_title ? $page_title : $url )
				);
			}
		}

		if ( 'snag_status' === $column ) {
			$status = get_post_meta( $post_id, '_snag_status', true );
			$status = $status ? $status : 'open';
			printf(
				'<span class="site-snags-status site-snags-status--%1$s">%2$s</span>',
				esc_attr( $status ),
				'done' === $status ? esc_html__( 'Done', 'site-snags' ) : esc_html__( 'Open', 'site-snags' )
			);
		}
	}

	/**
	 * Add Open/Done view links above the list table, like the default
	 * All/Published/Trash views.
	 *
	 * @param array $views Existing views.
	 * @return array
	 */
	public function status_views( $views ) {
		$base = admin_url( 'edit.php?post_type=site_snag' );

		$open_count = $this->count_by_status( 'open' );
		$done_count = $this->count_by_status( 'done' );

		$current = isset( $_GET['snag_status'] ) ? sanitize_text_field( wp_unslash( $_GET['snag_status'] ) ) : '';

		$views['snag_open'] = sprintf(
			'<a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
			esc_url( add_query_arg( 'snag_status', 'open', $base ) ),
			'open' === $current ? ' class="current"' : '',
			esc_html__( 'Open', 'site-snags' ),
			$open_count
		);

		$views['snag_done'] = sprintf(
			'<a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
			esc_url( add_query_arg( 'snag_status', 'done', $base ) ),
			'done' === $current ? ' class="current"' : '',
			esc_html__( 'Done', 'site-snags' ),
			$done_count
		);

		return $views;
	}

	/**
	 * Count snags by status for the view counts above.
	 *
	 * @param string $status open|done.
	 * @return int
	 */
	private function count_by_status( $status ) {
		$query = new WP_Query(
			array(
				'post_type'      => 'site_snag',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_snag_status',
						'value' => $status,
					),
				),
			)
		);
		return (int) $query->found_posts;
	}

	/**
	 * Apply the open/done filter to the main list query.
	 *
	 * @param WP_Query $query Current query.
	 */
	public function filter_by_status( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'site_snag' !== $query->get( 'post_type' ) ) {
			return;
		}

		if ( empty( $_GET['snag_status'] ) ) {
			return;
		}

		$status = sanitize_text_field( wp_unslash( $_GET['snag_status'] ) );
		if ( ! in_array( $status, array( 'open', 'done' ), true ) ) {
			return;
		}

		$query->set(
			'meta_query',
			array(
				array(
					'key'   => '_snag_status',
					'value' => $status,
				),
			)
		);
	}

	/**
	 * Add a "View on page" quick link to row actions.
	 *
	 * @param array   $actions Existing actions.
	 * @param WP_Post $post    Current post.
	 * @return array
	 */
	public function row_actions( $actions, $post ) {
		if ( 'site_snag' !== $post->post_type ) {
			return $actions;
		}

		$url = get_post_meta( $post->ID, '_snag_url', true );
		if ( $url ) {
			$actions['view_on_page'] = sprintf(
				'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
				esc_url( $url ),
				esc_html__( 'View on page', 'site-snags' )
			);
		}

		return $actions;
	}
}
