<?php
/**
 * Admin diagnostics page.
 *
 * @package StoreMailGuardian
 */

namespace SMG;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the admin page and handles form actions.
 */
class Admin_Page {
	const MENU_SLUG = 'store-mail-guardian';

	/**
	 * Diagnostics service.
	 *
	 * @var Diagnostics
	 */
	private $diagnostics;

	/**
	 * Test email service.
	 *
	 * @var Test_Email
	 */
	private $test_email;

	/**
	 * Error guidance service.
	 *
	 * @var Error_Guidance
	 */
	private $error_guidance;

	/**
	 * Constructor.
	 *
	 * @param Diagnostics $diagnostics Diagnostics service.
	 * @param Test_Email  $test_email Test email service.
	 */
	public function __construct( Diagnostics $diagnostics, Test_Email $test_email ) {
		$this->diagnostics    = $diagnostics;
		$this->test_email     = $test_email;
		$this->error_guidance = new Error_Guidance();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_smg_send_test_email', array( $this, 'handle_send_test_email' ) );
	}

	/**
	 * Register admin menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Mail Guardian', 'store-mail-guardian' ),
			__( 'Mail Guardian', 'store-mail-guardian' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' ),
			'dashicons-email-alt2',
			58
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_' . self::MENU_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'store-mail-guardian-admin',
			SMG_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			SMG_VERSION
		);
	}

	/**
	 * Handle test email form submission.
	 *
	 * @return void
	 */
	public function handle_send_test_email() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'store-mail-guardian' ) );
		}

		check_admin_referer( 'smg_send_test_email', 'smg_nonce' );

		$recipient = isset( $_POST['smg_test_recipient'] ) ? sanitize_email( wp_unslash( $_POST['smg_test_recipient'] ) ) : '';
		$result    = $this->test_email->send( $recipient );
		$notice    = ! empty( $result['success'] ) ? 'sent' : 'failed';
		$url       = add_query_arg(
			array(
				'page'       => self::MENU_SLUG,
				'smg_notice' => $notice,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Render admin page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$report        = $this->diagnostics->get_report();
		$latest_result = $this->test_email->get_latest_result();
		$notice        = isset( $_GET['smg_notice'] ) ? sanitize_key( wp_unslash( $_GET['smg_notice'] ) ) : '';
		?>
		<div class="wrap smg-wrap">
			<div class="smg-header">
				<div>
					<h1><?php echo esc_html__( 'Store Mail Guardian', 'store-mail-guardian' ); ?></h1>
					<p><?php echo esc_html__( 'A quick health check for WooCommerce transactional email setup.', 'store-mail-guardian' ); ?></p>
				</div>
				<?php echo wp_kses_post( $this->render_status_badge( $report['overall_status'] ) ); ?>
			</div>

			<?php $this->render_notice( $notice ); ?>

			<div class="smg-dashboard">
				<section class="smg-card smg-card--hero smg-card--<?php echo esc_attr( $report['overall_status'] ); ?>">
					<span class="smg-eyebrow"><?php echo esc_html__( 'Overall status', 'store-mail-guardian' ); ?></span>
					<div class="smg-hero-status">
						<?php echo wp_kses_post( $this->render_status_indicator( $report['overall_status'] ) ); ?>
						<strong><?php echo esc_html( $this->get_status_label( $report['overall_status'] ) ); ?></strong>
					</div>
					<p><?php echo esc_html( $this->get_status_summary( $report ) ); ?></p>
				</section>

				<section class="smg-card">
					<h2><?php echo esc_html__( 'Send test email', 'store-mail-guardian' ); ?></h2>
					<form class="smg-test-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="smg_send_test_email" />
						<?php wp_nonce_field( 'smg_send_test_email', 'smg_nonce' ); ?>
						<label for="smg_test_recipient"><?php echo esc_html__( 'Recipient', 'store-mail-guardian' ); ?></label>
						<input type="email" id="smg_test_recipient" name="smg_test_recipient" value="<?php echo esc_attr( $report['site_admin_email'] ); ?>" required />
						<p class="description"><?php echo esc_html__( 'Sends a plain WordPress test email through the current mail configuration.', 'store-mail-guardian' ); ?></p>
						<?php submit_button( __( 'Send test email', 'store-mail-guardian' ), 'primary', 'submit', false ); ?>
					</form>
				</section>
			</div>

			<div class="smg-grid">
				<section class="smg-card">
					<h2><?php echo esc_html__( 'Diagnostics', 'store-mail-guardian' ); ?></h2>
					<div class="smg-check-list">
						<?php
						$this->render_check_item( __( 'WooCommerce active', 'store-mail-guardian' ), $report['woocommerce_active'] ? 'healthy' : 'critical', $report['woocommerce_active'] ? __( 'Detected', 'store-mail-guardian' ) : __( 'Not detected', 'store-mail-guardian' ) );
						$this->render_check_item( __( 'WooCommerce email classes available', 'store-mail-guardian' ), $report['woocommerce_email_classes_available'] ? 'healthy' : 'critical', $report['woocommerce_email_classes_available'] ? __( 'Available', 'store-mail-guardian' ) : __( 'Unavailable', 'store-mail-guardian' ) );
						$this->render_check_item( __( 'Sender email address valid', 'store-mail-guardian' ), $report['sender']['is_valid'] ? 'healthy' : 'critical', $report['sender']['is_valid'] ? __( 'Valid', 'store-mail-guardian' ) : __( 'Needs attention', 'store-mail-guardian' ) );
						?>
					</div>
				</section>

				<section class="smg-card">
					<h2><?php echo esc_html__( 'Sender settings', 'store-mail-guardian' ); ?></h2>
					<div class="smg-detail-list">
						<?php $this->render_detail_item( __( 'From email', 'store-mail-guardian' ), $report['sender']['from_email'] ); ?>
						<?php $this->render_detail_item( __( 'From name', 'store-mail-guardian' ), $report['sender']['from_name'] ); ?>
						<?php $this->render_detail_item( __( 'Site admin email', 'store-mail-guardian' ), $report['site_admin_email'] ); ?>
					</div>
					<?php if ( ! empty( $report['sender']['contains_localhost'] ) ) : ?>
						<div class="smg-callout smg-callout--critical">
							<strong><?php echo esc_html__( 'Localhost sender detected', 'store-mail-guardian' ); ?></strong>
							<p><?php echo esc_html__( 'Email providers often reject messages sent from localhost sender addresses. Configure a real From Email before relying on transactional emails.', 'store-mail-guardian' ); ?></p>
						</div>
					<?php endif; ?>
				</section>
			</div>

			<section class="smg-card">
				<h2><?php echo esc_html__( 'SMTP plugin detection', 'store-mail-guardian' ); ?></h2>
				<?php $this->render_smtp_plugins( $report ); ?>
			</section>

			<section class="smg-card">
				<h2><?php echo esc_html__( 'Enabled WooCommerce email notifications', 'store-mail-guardian' ); ?></h2>
				<?php $this->render_woocommerce_emails( $report ); ?>
			</section>

			<section class="<?php echo esc_attr( $this->get_latest_result_card_class( $latest_result ) ); ?>">
				<h2><?php echo esc_html__( 'Latest test result', 'store-mail-guardian' ); ?></h2>
				<?php $this->render_latest_result( $latest_result ); ?>
			</section>
		</div>
		<?php
	}

	/**
	 * Render a post-send notice.
	 *
	 * @param string $notice Notice key.
	 * @return void
	 */
	private function render_notice( $notice ) {
		if ( 'sent' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Test email sent successfully.', 'store-mail-guardian' ) . '</p></div>';
			return;
		}

		if ( 'failed' === $notice ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Test email failed. Review the latest result below.', 'store-mail-guardian' ) . '</p></div>';
		}
	}

	/**
	 * Render WooCommerce email cards.
	 *
	 * @param array $report Diagnostic report.
	 * @return void
	 */
	private function render_woocommerce_emails( $report ) {
		if ( empty( $report['woocommerce_active'] ) ) {
			echo '<p class="smg-muted">' . esc_html__( 'WooCommerce is not active, so email notifications cannot be listed.', 'store-mail-guardian' ) . '</p>';
			return;
		}

		if ( empty( $report['enabled_woocommerce_emails'] ) ) {
			echo '<p class="smg-muted">' . esc_html__( 'No enabled WooCommerce email notifications were found.', 'store-mail-guardian' ) . '</p>';
			return;
		}
		?>
		<div class="smg-email-grid">
			<?php foreach ( $report['enabled_woocommerce_emails'] as $email ) : ?>
				<article class="smg-mini-card">
					<strong><?php echo esc_html( $email['title'] ); ?></strong>
					<code><?php echo esc_html( $email['id'] ); ?></code>
					<?php if ( ! empty( $email['recipient'] ) ) : ?>
						<span><?php echo esc_html( $email['recipient'] ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $email['description'] ) ) : ?>
						<p><?php echo esc_html( $email['description'] ); ?></p>
					<?php endif; ?>
				</article>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render SMTP plugin cards and recommendation.
	 *
	 * @param array $report Diagnostic report.
	 * @return void
	 */
	private function render_smtp_plugins( $report ) {
		if ( empty( $report['active_smtp_plugins'] ) ) {
			?>
			<div class="smg-callout smg-callout--warning">
				<strong><?php echo esc_html__( 'No SMTP plugin detected', 'store-mail-guardian' ); ?></strong>
				<p><?php echo esc_html__( 'No SMTP plugin detected. Transactional emails may fail on many hosting environments.', 'store-mail-guardian' ); ?></p>
			</div>
			<?php
		}
		?>
		<div class="smg-plugin-grid">
			<?php foreach ( $report['smtp_plugins'] as $plugin ) : ?>
				<article class="smg-mini-card">
					<div class="smg-mini-card__header">
						<strong><?php echo esc_html( $plugin['name'] ); ?></strong>
						<?php echo wp_kses_post( $this->render_status_badge( $plugin['active'] ? 'healthy' : ( $plugin['installed'] ? 'warning' : 'critical' ), $plugin['active'] ? __( 'Active', 'store-mail-guardian' ) : ( $plugin['installed'] ? __( 'Installed', 'store-mail-guardian' ) : __( 'Missing', 'store-mail-guardian' ) ) ) ); ?>
					</div>
					<span class="smg-muted"><?php echo esc_html( $plugin['file'] ); ?></span>
				</article>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render latest email result.
	 *
	 * @param array $latest_result Latest stored result.
	 * @return void
	 */
	private function render_latest_result( $latest_result ) {
		if ( empty( $latest_result ) ) {
			echo '<p class="smg-muted">' . esc_html__( 'No test email has been sent yet.', 'store-mail-guardian' ) . '</p>';
			return;
		}

		$is_success    = ! empty( $latest_result['success'] );
		$error_message = isset( $latest_result['error_message'] ) ? $latest_result['error_message'] : '';
		$guidance      = $is_success ? array() : $this->error_guidance->get_guidance( $error_message );
		?>
		<div class="smg-result-summary">
			<?php echo wp_kses_post( $this->render_status_indicator( $is_success ? 'healthy' : 'critical' ) ); ?>
			<div>
				<strong><?php echo esc_html( $is_success ? __( 'Success', 'store-mail-guardian' ) : __( 'Failure', 'store-mail-guardian' ) ); ?></strong>
				<p><?php echo esc_html( $is_success ? __( 'WordPress accepted the test email for sending.', 'store-mail-guardian' ) : __( 'WordPress could not send the test email.', 'store-mail-guardian' ) ); ?></p>
			</div>
		</div>

		<div class="smg-detail-list smg-detail-list--columns">
			<?php $this->render_detail_item( __( 'Timestamp', 'store-mail-guardian' ), isset( $latest_result['timestamp'] ) ? $latest_result['timestamp'] : '' ); ?>
			<?php $this->render_detail_item( __( 'Recipient', 'store-mail-guardian' ), isset( $latest_result['recipient'] ) ? $latest_result['recipient'] : '' ); ?>
			<?php $this->render_detail_item( __( 'Result', 'store-mail-guardian' ), $is_success ? __( 'Success', 'store-mail-guardian' ) : __( 'Failure', 'store-mail-guardian' ) ); ?>
			<?php $this->render_detail_item( __( 'Raw error', 'store-mail-guardian' ), $error_message ? $error_message : __( 'None reported', 'store-mail-guardian' ) ); ?>
		</div>

		<?php if ( ! $is_success ) : ?>
			<div class="smg-guidance">
				<h3><?php echo esc_html( $guidance['title'] ); ?></h3>
				<p><?php echo esc_html( $guidance['explanation'] ); ?></p>
				<strong><?php echo esc_html__( 'Suggested next action', 'store-mail-guardian' ); ?></strong>
				<ul>
					<?php foreach ( $guidance['actions'] as $action ) : ?>
						<li><?php echo esc_html( $action ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render a diagnostic check item.
	 *
	 * @param string $label Check label.
	 * @param string $status Status key.
	 * @param string $value Display value.
	 * @return void
	 */
	private function render_check_item( $label, $status, $value ) {
		?>
		<div class="smg-check-item">
			<?php echo wp_kses_post( $this->render_status_indicator( $status ) ); ?>
			<div>
				<strong><?php echo esc_html( $label ); ?></strong>
				<span><?php echo esc_html( $value ); ?></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a label/value pair.
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 * @return void
	 */
	private function render_detail_item( $label, $value ) {
		?>
		<div class="smg-detail-item">
			<span><?php echo esc_html( $label ); ?></span>
			<strong><?php echo esc_html( $value ); ?></strong>
		</div>
		<?php
	}

	/**
	 * Render a status badge.
	 *
	 * @param string $status Status key.
	 * @param string $label Optional label.
	 * @return string
	 */
	private function render_status_badge( $status, $label = '' ) {
		$label = $label ? $label : $this->get_status_label( $status );

		return sprintf(
			'<span class="smg-status-badge smg-status-badge--%1$s"><span></span>%2$s</span>',
			esc_attr( $status ),
			esc_html( $label )
		);
	}

	/**
	 * Render a visual status indicator.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	private function render_status_indicator( $status ) {
		return sprintf(
			'<span class="smg-indicator smg-indicator--%1$s" aria-hidden="true"></span>',
			esc_attr( $status )
		);
	}

	/**
	 * Get latest result card CSS class.
	 *
	 * @param array $latest_result Latest stored result.
	 * @return string
	 */
	private function get_latest_result_card_class( $latest_result ) {
		$class = 'smg-card smg-card--latest';

		if ( ! empty( $latest_result ) && empty( $latest_result['success'] ) ) {
			$class .= ' smg-card--critical smg-card--prominent';
		}

		return $class;
	}

	/**
	 * Get a short overall status summary.
	 *
	 * @param array $report Diagnostic report.
	 * @return string
	 */
	private function get_status_summary( $report ) {
		if ( 'critical' === $report['overall_status'] ) {
			return __( 'One or more mail checks need immediate attention before relying on transactional email.', 'store-mail-guardian' );
		}

		if ( 'warning' === $report['overall_status'] ) {
			return __( 'Core checks are available, but the current setup may still fail without a dedicated SMTP configuration.', 'store-mail-guardian' );
		}

		return __( 'Core WooCommerce mail checks and SMTP detection look healthy.', 'store-mail-guardian' );
	}

	/**
	 * Get translated overall status label.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	private function get_status_label( $status ) {
		$labels = array(
			'healthy'  => __( 'Healthy', 'store-mail-guardian' ),
			'warning'  => __( 'Warning', 'store-mail-guardian' ),
			'critical' => __( 'Critical', 'store-mail-guardian' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $labels['warning'];
	}
}
