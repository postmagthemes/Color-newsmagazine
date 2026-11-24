<?php
/**
 * Review request: dashboard admin notice.
 *
 * - The notice appears on the Dashboard after the theme has been in use for a few days.
 * - "Leave a review" opens the WordPress.org review form and hides the notice for good.
 * - "Maybe later" hides it for a while; "I already did" hides it for good.
 * - The choice is stored site-wide, so one admin's click covers all admins.
 *
 * @package color_newsmagazine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress.org review URL for the theme.
 *
 * @return string
 */
function color_newsmagazine_review_url() {
	return apply_filters( 'color_newsmagazine_review_url', 'https://wordpress.org/support/theme/color-newsmagazine/reviews/#new-post' );
}

/**
 * Days to wait after the theme is first used before asking for a review.
 *
 * @return int
 */
function color_newsmagazine_review_delay_days() {
	return absint( apply_filters( 'color_newsmagazine_review_delay_days', 7 ) );
}

/**
 * Days to wait after "Maybe later" before asking again.
 *
 * @return int
 */
function color_newsmagazine_review_snooze_days() {
	return absint( apply_filters( 'color_newsmagazine_review_snooze_days', 14 ) );
}

/**
 * Record when the theme was first used (also covers sites upgrading from an older version).
 */
function color_newsmagazine_review_record_install_time() {
	if ( ! get_option( 'color_newsmagazine_install_time' ) ) {
		update_option( 'color_newsmagazine_install_time', time(), false );
	}
}
add_action( 'admin_init', 'color_newsmagazine_review_record_install_time' );

/**
 * Build a nonce-protected action link for the notice buttons.
 *
 * @param string $todo review|later|done.
 * @return string
 */
function color_newsmagazine_review_action_url( $todo ) {
	return wp_nonce_url(
		add_query_arg(
			array(
				'action' => 'color_newsmagazine_review',
				'todo'   => $todo,
			),
			admin_url( 'admin-post.php' )
		),
		'color_newsmagazine_review_' . $todo
	);
}

/**
 * Whether the notice should show for the current user.
 *
 * @return bool
 */
function color_newsmagazine_review_should_show() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return false;
	}

	$state = get_option( 'color_newsmagazine_review_state' );

	if ( 'done' === $state ) {
		return false;
	}

	if ( is_numeric( $state ) && time() < (int) $state ) {
		return false;
	}

	$installed = (int) get_option( 'color_newsmagazine_install_time' );
	if ( ! $installed || ( time() - $installed ) < color_newsmagazine_review_delay_days() * DAY_IN_SECONDS ) {
		return false;
	}

	return true;
}

/**
 * Dashboard admin notice.
 */
function color_newsmagazine_review_admin_notice() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'dashboard' !== $screen->id ) {
		return;
	}

	if ( ! color_newsmagazine_review_should_show() ) {
		return;
	}
	?>
	<div class="notice notice-info color-newsmagazine-review-notice">
		<p>
			<?php
			printf(
				/* translators: %s: theme name */
				esc_html__( 'Hi! You have been using %s for a while now. We hope you are enjoying it. Could you spare a minute to leave a review on WordPress.org? It really helps us keep improving the theme.', 'color-newsmagazine' ),
				'<strong>' . esc_html__( 'Color Newsmagazine', 'color-newsmagazine' ) . '</strong>'
			);
			?>
		</p>
		<p>
			<a href="<?php echo esc_url( color_newsmagazine_review_action_url( 'review' ) ); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer">
				<span class="dashicons dashicons-star-filled" style="vertical-align: text-bottom;"></span>
				<?php esc_html_e( 'Leave a review', 'color-newsmagazine' ); ?>
			</a>
			&nbsp;
			<a href="<?php echo esc_url( color_newsmagazine_review_action_url( 'later' ) ); ?>" class="button">
				<?php esc_html_e( 'Maybe later', 'color-newsmagazine' ); ?>
			</a>
			&nbsp;
			<a href="<?php echo esc_url( color_newsmagazine_review_action_url( 'done' ) ); ?>">
				<?php esc_html_e( 'I already did', 'color-newsmagazine' ); ?>
			</a>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'color_newsmagazine_review_admin_notice' );

/**
 * Handle the notice buttons.
 */
function color_newsmagazine_review_handle_action() {
	$todo = isset( $_GET['todo'] ) ? sanitize_key( wp_unslash( $_GET['todo'] ) ) : '';

	if ( ! in_array( $todo, array( 'review', 'later', 'done' ), true ) ) {
		wp_die( esc_html__( 'Invalid request.', 'color-newsmagazine' ) );
	}

	check_admin_referer( 'color_newsmagazine_review_' . $todo );

	if ( ! current_user_can( 'edit_theme_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'color-newsmagazine' ) );
	}

	if ( 'later' === $todo ) {
		update_option( 'color_newsmagazine_review_state', time() + color_newsmagazine_review_snooze_days() * DAY_IN_SECONDS, false );
	} else {
		update_option( 'color_newsmagazine_review_state', 'done', false );
	}

	if ( 'review' === $todo ) {
		wp_redirect( color_newsmagazine_review_url() ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- fixed wordpress.org URL.
		exit;
	}

	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
	exit;
}
add_action( 'admin_post_color_newsmagazine_review', 'color_newsmagazine_review_handle_action' );
