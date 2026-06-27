<?php
/**
 * Plugin Name: OFG Call Button
 * Plugin URI: https://github.com/rotcivh/ofg-call-button
 * Description: Adds a configurable floating call button with per-page overrides and optional local click statistics.
 * Version: 1.1.9
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: weblogbaz
 * Author URI: https://profiles.wordpress.org/weblogbaz/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ofg-call-button
 * Domain Path: /languages
 *
 * @package Ofogh_Call_Button
 */

defined( 'ABSPATH' ) || exit;

define( 'OFOGH_CALL_BTN_VERSION', '1.1.9' );
define( 'OFOGH_CALL_BTN_FILE', __FILE__ );
define( 'OFOGH_CALL_BTN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OFOGH_CALL_BTN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin controller.
 */
final class Ofogh_Call_Btn_Plugin {

	const OPTION_NAME  = 'ofogh_call_btn_options';
	const CLICKS_NAME  = 'ofogh_call_btn_clicks';
	const EVENTS_TABLE = 'ofogh_call_btn_events';
	const DEDUP_NAME   = 'ofogh_call_btn_click_dedup';
	const NONCE_ACTION = 'ofogh_call_btn_rest';
	const REST_NS      = 'ofogh-call-btn/v1';
	const REST_ROUTE   = '/click';
	const DEDUP_WINDOW = 60;

	/**
	 * Current plugin options.
	 *
	 * @var array
	 */
	private $options = array();

	/**
	 * Boot hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'load_options' ), 1 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_button' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
	}

	/**
	 * Create/update storage used by detailed reports.
	 */
	public static function activate() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = $wpdb->prefix . self::EVENTS_TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table_name} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				clicked_at datetime NOT NULL,
				clicked_at_gmt datetime NOT NULL,
				day_key char(8) NOT NULL,
				page_id bigint(20) unsigned NOT NULL DEFAULT 0,
				page_title text NULL,
				page_url text NULL,
				landing_url text NULL,
				referrer_url text NULL,
				source varchar(120) NOT NULL DEFAULT '',
				medium varchar(80) NOT NULL DEFAULT '',
				phone varchar(60) NOT NULL DEFAULT '',
				user_agent text NULL,
				is_mobile tinyint(1) NOT NULL DEFAULT 0,
				created_at_gmt datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY day_key (day_key),
				KEY clicked_at_gmt (clicked_at_gmt),
				KEY page_id (page_id),
				KEY source (source)
			) {$charset_collate};"
		);

	}

	/**
	 * Load option values after translations are available.
	 */
	public function load_options() {
		$this->options = $this->get_options();
	}

	/**
	 * Default option values.
	 *
	 * @return array
	 */
	private function defaults() {
		return array(
			'screen_size'       => 860,
			'move_left'         => 3,
			'move_top'          => 50,
			'move_top_tablet'   => 50,
			'move_top_desktop'  => 50,
			'bg_color'          => '#1a1919',
			'call_text'         => __( 'Call Now', 'ofg-call-button' ),
			'call_text_color'   => '#ffffff',
			'call_color'        => '#00cc33',
			'phone_number'      => '',
			'phone_number_1'    => '',
			'phone_number_2'    => '',
			'hide_text_mobile'  => 0,
			'hide_text_tablet'  => 0,
			'hide_text_desktop' => 0,
			'wave_effect'       => 1,
			'shake_effect'      => 1,
			'drag_enabled'      => 1,
			'icon_style'        => 'classic',
			'tracking_enabled'  => 0,
		);
	}

	/**
	 * Get merged options.
	 *
	 * @return array
	 */
	private function get_options() {
		$options = get_option( self::OPTION_NAME, array() );
		return wp_parse_args( is_array( $options ) ? $options : array(), $this->defaults() );
	}

	/**
	 * Register settings with sanitization.
	 */
	public function register_settings() {
		register_setting(
			'ofogh_call_btn_settings',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_options' ),
				'default'           => $this->defaults(),
			)
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Posted values.
	 * @return array
	 */
	public function sanitize_options( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = $this->defaults();
		$output   = array();

		$output['screen_size']      = $this->clamp_int( $input['screen_size'] ?? $defaults['screen_size'], 240, 2560 );
		$output['move_left']        = $this->clamp_int( $input['move_left'] ?? $defaults['move_left'], 0, 95 );
		$output['move_top']         = $this->clamp_int( $input['move_top'] ?? $defaults['move_top'], 0, 95 );
		$output['move_top_tablet']  = $this->clamp_int( $input['move_top_tablet'] ?? $defaults['move_top_tablet'], 0, 95 );
		$output['move_top_desktop'] = $this->clamp_int( $input['move_top_desktop'] ?? $defaults['move_top_desktop'], 0, 95 );

		$output['bg_color']        = $this->sanitize_hex( $input['bg_color'] ?? $defaults['bg_color'], $defaults['bg_color'] );
		$output['call_text_color'] = $this->sanitize_hex( $input['call_text_color'] ?? $defaults['call_text_color'], $defaults['call_text_color'] );
		$output['call_color']      = $this->sanitize_hex( $input['call_color'] ?? $defaults['call_color'], $defaults['call_color'] );
		$output['call_text']       = sanitize_text_field( $input['call_text'] ?? $defaults['call_text'] );

		$output['phone_number']   = $this->sanitize_phone( $input['phone_number'] ?? '' );
		$output['phone_number_1'] = $this->sanitize_phone( $input['phone_number_1'] ?? '' );
		$output['phone_number_2'] = $this->sanitize_phone( $input['phone_number_2'] ?? '' );

		$output['hide_text_mobile']  = empty( $input['hide_text_mobile'] ) ? 0 : 1;
		$output['hide_text_tablet']  = empty( $input['hide_text_tablet'] ) ? 0 : 1;
		$output['hide_text_desktop'] = empty( $input['hide_text_desktop'] ) ? 0 : 1;
		$output['wave_effect']       = empty( $input['wave_effect'] ) ? 0 : 1;
		$output['shake_effect']      = empty( $input['shake_effect'] ) ? 0 : 1;
		$output['drag_enabled']      = empty( $input['drag_enabled'] ) ? 0 : 1;
		$output['tracking_enabled']  = empty( $input['tracking_enabled'] ) ? 0 : 1;

		$output['icon_style'] = in_array( $input['icon_style'] ?? 'classic', array( 'classic', 'solid' ), true ) ? $input['icon_style'] : 'classic';

		return $output;
	}

	/**
	 * Register admin menu pages.
	 */
	public function register_admin_pages() {
		add_menu_page(
			__( 'OFG Call Button', 'ofg-call-button' ),
			__( 'OFG Call Button', 'ofg-call-button' ),
			'manage_options',
			'ofg-call-button',
			array( $this, 'render_settings_page' ),
			'dashicons-phone',
			58
		);

		add_submenu_page(
			'ofg-call-button',
			__( 'Settings', 'ofg-call-button' ),
			__( 'Settings', 'ofg-call-button' ),
			'manage_options',
			'ofg-call-button',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'ofg-call-button',
			__( 'Reports', 'ofg-call-button' ),
			__( 'Reports', 'ofg-call-button' ),
			'manage_options',
			'ofg-call-button-reports',
			array( $this, 'render_reports_page' )
		);
	}

	/**
	 * Load admin assets.
	 *
	 * @param string $hook Current admin screen.
	 */
	public function admin_assets( $hook ) {
		$is_settings_page = 'toplevel_page_ofg-call-button' === $hook;
		$is_reports_page  = 'ofg-call-button_page_ofg-call-button-reports' === $hook;
		$is_plugin_page   = $is_settings_page || $is_reports_page;
		$is_dashboard   = 'index.php' === $hook;

		if ( ! $is_plugin_page && ! $is_dashboard ) {
			return;
		}

		if ( $is_settings_page ) {
			wp_enqueue_style( 'wp-color-picker' );
		}

		wp_enqueue_script( 'ofogh-call-btn-admin', OFOGH_CALL_BTN_URL . 'assets/js/admin.js', array( 'jquery', 'wp-color-picker' ), OFOGH_CALL_BTN_VERSION, true );
		wp_localize_script(
			'ofogh-call-btn-admin',
			'ofoghCallBtnAdmin',
			array(
				'jalaliDates' => $this->is_persian_locale() ? 1 : 0,
			)
		);

		wp_enqueue_style( 'ofogh-call-btn-admin', OFOGH_CALL_BTN_URL . 'assets/css/admin.css', array(), OFOGH_CALL_BTN_VERSION );
		wp_add_inline_style( 'ofogh-call-btn-admin', $this->admin_inline_css( $hook ) );
	}

	/**
	 * Inline admin overrides.
	 *
	 * @param string $hook Current admin screen.
	 * @return string
	 */
	private function admin_inline_css( $hook ) {
		$css = '
			.ofogh-call-btn-admin,
			.ofogh-call-btn-admin *,
			.ofogh-call-btn-widget,
			.ofogh-call-btn-widget * {
				font-family: Tahoma, Arial, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif !important;
				letter-spacing: 0 !important;
			}
			.ofogh-call-btn-report-page,
			.ofogh-call-btn-report-page h1,
			.ofogh-call-btn-report-page h2,
			.ofogh-call-btn-report-page h3,
			.ofogh-call-btn-report-page p,
			.ofogh-call-btn-report-page span,
			.ofogh-call-btn-report-page label,
			.ofogh-call-btn-report-page th,
			.ofogh-call-btn-report-page td,
			.ofogh-call-btn-report-page input,
			.ofogh-call-btn-report-page button {
				font-family: Tahoma, Arial, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif !important;
			}
			.ofogh-call-btn-report-page {
				background:
					linear-gradient(180deg, rgba(245, 247, 250, 0.96), rgba(236, 241, 246, 0.94)),
					linear-gradient(135deg, rgba(15, 122, 95, 0.04), rgba(181, 137, 59, 0.05));
			}
			.ofogh-call-btn-report-page .ofogh-call-btn-report-hero {
				background:
					linear-gradient(135deg, rgba(16, 24, 39, 0.98), rgba(24, 32, 44, 0.96) 58%, rgba(15, 122, 95, 0.82)),
					linear-gradient(135deg, rgba(181, 137, 59, 0.18), rgba(255, 255, 255, 0));
				border: 1px solid rgba(255,255,255,.08);
				box-shadow: 0 28px 72px rgba(15,23,42,.18);
			}
			.ofogh-call-btn-report-page .ofogh-call-btn-report-hero h1,
			.ofogh-call-btn-report-page .ofogh-call-btn-report-hero .description,
			.ofogh-call-btn-report-page .ofogh-call-btn-report-meta span,
			.ofogh-call-btn-report-page .ofogh-call-btn-report-meta strong {
				color: #fff;
			}
			.ofogh-call-btn-report-page .ofogh-call-btn-report-meta > div {
				background: rgba(255,255,255,.08);
				border-color: rgba(255,255,255,.12);
				box-shadow: none;
				backdrop-filter: blur(8px);
			}
			.ofogh-call-btn-report-page .ofogh-call-btn-report-meta span {
				opacity: .7;
			}
			.ofogh-call-btn-report-page .ofogh-call-btn-report-meta strong {
				font-size: 15px;
			}
			.ofogh-call-btn-report-page .ofogh-call-btn-report-hero .description {
				max-width: 52ch;
			}
			.ofogh-call-btn-report-page .ofogh-call-btn-stats > div {
				background:
					radial-gradient(circle at 18% 15%, rgba(255,255,255,.12), transparent 30%),
					linear-gradient(145deg, #0f172a, #253244 62%, #0f7a5f);
				border-color: rgba(255,255,255,.10);
				box-shadow: 0 20px 44px rgba(15,23,42,.18);
			}
			.ofogh-call-btn-report-page .ofogh-call-btn-stats span {
				opacity: .82;
			}
			.ofogh-call-btn-report-page .ofogh-call-btn-stats strong {
				font-variant-numeric: tabular-nums;
			}
			.ofogh-call-btn-report-page .ofogh-call-btn-report-meta > div,
			.ofogh-call-btn-report-page .ofogh-call-btn-panel,
			.ofogh-call-btn-report-page .ofogh-call-btn-table-wrap {
				backdrop-filter: blur(14px);
			}
			.ofogh-call-btn-report-page .ofogh-call-btn-report-grid .ofogh-call-btn-panel {
				transition: transform .18s ease, box-shadow .18s ease;
			}
			.ofogh-call-btn-report-page .ofogh-call-btn-report-grid .ofogh-call-btn-panel:hover {
				transform: translateY(-2px);
				box-shadow: 0 20px 42px rgba(15,23,42,.10);
			}
			.ofogh-call-btn-report-page .ofogh-call-btn-table-wrap {
				border: 1px solid rgba(216,222,232,.95);
			}
			.ofogh-call-btn-report-page .ofogh-call-btn-report-table-panel {
				padding: 0;
			}
			.ofogh-call-btn-report-page .ofogh-call-btn-report-table-panel > h2 {
				margin: 0;
				padding: 18px 18px 16px;
				border-bottom: 1px solid #eef2f7;
			}
			.ofogh-call-btn-report-page .ofogh-call-btn-report-table-panel .ofogh-call-btn-table-wrap {
				border: 0;
				border-radius: 0 0 14px 14px;
				box-shadow: none;
			}
			.ofogh-call-btn-report-page .ofogh-call-btn-report-grid .ofogh-call-btn-panel {
				background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(247,249,252,.94));
			}
			.ofogh-call-btn-report-page .ofogh-call-btn-report-grid .ofogh-call-btn-panel h2 {
				background:
					linear-gradient(135deg, rgba(24,32,44,.98), rgba(37,50,68,.98));
				color: #fff;
			}
			.ofogh-call-btn-report-page .ofogh-call-btn-report-grid .ofogh-call-btn-panel table thead th {
				color: #fff;
				background: linear-gradient(135deg, #18202c, #253244);
			}
			.ofogh-call-btn-report-page .ofogh-call-btn-table-wrap table thead th {
				background: linear-gradient(135deg, #18202c, #253244);
			}
			.ofogh-call-btn-report-page .ofogh-call-btn-table-wrap table tbody tr:hover td {
				background: rgba(15, 122, 95, 0.04);
			}
		';

		if ( 'ofg-call-button_page_ofg-call-button-reports' === $hook ) {
			$css .= '
				.ofogh-call-btn-report-page {
					max-width: 1360px;
				}
				.ofogh-call-btn-report-page .ofogh-call-btn-report-hero {
					grid-template-columns: minmax(0, 1.15fr) minmax(340px, 0.85fr);
					padding: 24px;
				}
				.ofogh-call-btn-report-page .ofogh-call-btn-report-hero h1 {
					font-size: 30px;
					font-weight: 900;
				}
				.ofogh-call-btn-report-page .ofogh-call-btn-report-meta {
					margin-top: 4px;
				}
				.ofogh-call-btn-report-page .ofogh-call-btn-stats {
					align-self: start;
				}
				.ofogh-call-btn-report-page .ofogh-call-btn-stats > div {
					min-height: 136px;
					padding: 20px 20px 18px;
				}
				.ofogh-call-btn-report-page .ofogh-call-btn-stats strong {
					font-size: 36px;
				}
				.ofogh-call-btn-report-page .ofogh-call-btn-report-filters-panel {
					padding: 18px 20px 20px;
				}
				.ofogh-call-btn-report-page .ofogh-call-btn-report-filters {
					display: grid;
					grid-template-columns: repeat(4, minmax(0, 1fr));
					gap: 14px 16px;
					align-items: end;
				}
				.ofogh-call-btn-report-page .ofogh-call-btn-report-filters label {
					gap: 8px;
				}
				.ofogh-call-btn-report-page .ofogh-call-btn-report-filters .button {
					grid-column: 1 / -1;
					justify-self: start;
					padding-inline: 22px;
				}
				.ofogh-call-btn-report-page .ofogh-call-btn-report-grid {
					grid-template-columns: repeat(3, minmax(0, 1fr));
					gap: 18px;
				}
				.ofogh-call-btn-report-page .ofogh-call-btn-report-grid .ofogh-call-btn-panel {
					min-height: 100%;
				}
				.ofogh-call-btn-report-page .ofogh-call-btn-report-grid .ofogh-call-btn-panel h2 {
					font-size: 16px;
					padding: 18px 20px 16px;
				}
				.ofogh-call-btn-report-page .ofogh-call-btn-report-grid .ofogh-call-btn-panel table td,
				.ofogh-call-btn-report-page .ofogh-call-btn-report-grid .ofogh-call-btn-panel table th {
					padding: 12px 18px;
				}
				.ofogh-call-btn-report-page .ofogh-call-btn-report-grid .ofogh-call-btn-panel table tbody tr:hover td {
					background: rgba(15, 122, 95, 0.04);
				}
			';
		}

		return $css;
	}

	/**
	 * Whether current site/admin locale is Persian.
	 *
	 * @return bool
	 */
	private function is_persian_locale() {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		return 0 === strpos( strtolower( (string) $locale ), 'fa' );
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->options = $this->get_options();
		?>
		<div class="wrap ofogh-call-btn-admin">
			<h1><?php esc_html_e( 'OFG Call Button', 'ofg-call-button' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'ofogh_call_btn_settings' ); ?>
				<div class="ofogh-call-btn-grid">
					<section class="ofogh-call-btn-panel">
						<h2><?php esc_html_e( 'Display', 'ofg-call-button' ); ?></h2>
						<?php $this->number_field( 'screen_size', __( 'Display below screen width', 'ofg-call-button' ), 'px' ); ?>
						<?php $this->number_field( 'move_top', __( 'Top on mobile', 'ofg-call-button' ), '%' ); ?>
						<?php $this->number_field( 'move_top_tablet', __( 'Top on tablet', 'ofg-call-button' ), '%' ); ?>
						<?php $this->number_field( 'move_top_desktop', __( 'Top on desktop', 'ofg-call-button' ), '%' ); ?>
						<?php $this->number_field( 'move_left', __( 'Left position', 'ofg-call-button' ), '%' ); ?>
					</section>

					<section class="ofogh-call-btn-panel">
						<h2><?php esc_html_e( 'Phone Numbers', 'ofg-call-button' ); ?></h2>
						<?php $this->text_field( 'phone_number', __( 'Primary phone number', 'ofg-call-button' ), '+15555550100' ); ?>
						<?php $this->text_field( 'phone_number_1', __( 'Alternative phone number', 'ofg-call-button' ), '+15555550101' ); ?>
						<?php $this->text_field( 'phone_number_2', __( 'Alternative phone number', 'ofg-call-button' ), '+15555550102' ); ?>
						<p class="description"><?php esc_html_e( 'If alternatives are filled, one number is selected for each page view.', 'ofg-call-button' ); ?></p>
					</section>

					<section class="ofogh-call-btn-panel">
						<h2><?php esc_html_e( 'Appearance', 'ofg-call-button' ); ?></h2>
						<?php $this->text_field( 'call_text', __( 'Button text', 'ofg-call-button' ), __( 'Call Now', 'ofg-call-button' ) ); ?>
						<?php $this->color_field( 'call_text_color', __( 'Text color', 'ofg-call-button' ) ); ?>
						<?php $this->color_field( 'bg_color', __( 'Bar background', 'ofg-call-button' ) ); ?>
						<?php $this->color_field( 'call_color', __( 'Button color', 'ofg-call-button' ) ); ?>
						<label class="ofogh-call-btn-row">
							<span><?php esc_html_e( 'Icon style', 'ofg-call-button' ); ?></span>
							<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[icon_style]">
								<option value="classic" <?php selected( $this->options['icon_style'], 'classic' ); ?>><?php esc_html_e( 'Classic', 'ofg-call-button' ); ?></option>
								<option value="solid" <?php selected( $this->options['icon_style'], 'solid' ); ?>><?php esc_html_e( 'Solid', 'ofg-call-button' ); ?></option>
							</select>
						</label>
					</section>

					<section class="ofogh-call-btn-panel">
						<h2><?php esc_html_e( 'Behavior', 'ofg-call-button' ); ?></h2>
						<?php $this->checkbox_field( 'hide_text_mobile', __( 'Hide text on mobile', 'ofg-call-button' ) ); ?>
						<?php $this->checkbox_field( 'hide_text_tablet', __( 'Hide text on tablet', 'ofg-call-button' ) ); ?>
						<?php $this->checkbox_field( 'hide_text_desktop', __( 'Hide text on desktop', 'ofg-call-button' ) ); ?>
						<?php $this->checkbox_field( 'wave_effect', __( 'Enable wave effect', 'ofg-call-button' ) ); ?>
						<?php $this->checkbox_field( 'shake_effect', __( 'Enable shake effect', 'ofg-call-button' ) ); ?>
						<?php $this->checkbox_field( 'drag_enabled', __( 'Enable drag and drop', 'ofg-call-button' ) ); ?>
						<?php $this->checkbox_field( 'tracking_enabled', __( 'Enable local click statistics', 'ofg-call-button' ) ); ?>
					</section>
				</div>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render reports page.
	 */
	public function render_reports_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		self::activate();

		$filters = $this->report_filters();
		$totals  = $this->report_totals( $filters );
		$pages   = $this->report_grouped_rows( $filters, 'page' );
		$sources = $this->report_grouped_rows( $filters, 'source' );
		$events  = $this->report_events( $filters );
		?>
		<div class="wrap ofogh-call-btn-admin ofogh-call-btn-report-page">
			<div class="ofogh-call-btn-report-hero">
				<div class="ofogh-call-btn-report-hero__copy">
					<h1><?php esc_html_e( 'Call Reports', 'ofg-call-button' ); ?></h1>
					<p class="description"><?php esc_html_e( 'Local call reports show click activity by page, source, referrer, and landing page.', 'ofg-call-button' ); ?></p>
					<div class="ofogh-call-btn-report-meta">
						<div><span><?php esc_html_e( 'From', 'ofg-call-button' ); ?></span><strong><?php echo esc_html( $this->format_report_date( $filters['date_from'] ) ); ?></strong></div>
						<div><span><?php esc_html_e( 'To', 'ofg-call-button' ); ?></span><strong><?php echo esc_html( $this->format_report_date( $filters['date_to'] ) ); ?></strong></div>
						<div><span><?php esc_html_e( 'Source', 'ofg-call-button' ); ?></span><strong><?php echo esc_html( '' !== $filters['source'] ? $filters['source'] : __( 'Unknown', 'ofg-call-button' ) ); ?></strong></div>
					</div>
				</div>
				<div class="ofogh-call-btn-stats">
					<div><strong><?php echo esc_html( number_format_i18n( (int) $totals['total'] ) ); ?></strong><span><?php esc_html_e( 'Total calls', 'ofg-call-button' ); ?></span></div>
					<div><strong><?php echo esc_html( number_format_i18n( (int) $totals['pages'] ) ); ?></strong><span><?php esc_html_e( 'Called pages', 'ofg-call-button' ); ?></span></div>
					<div><strong><?php echo esc_html( number_format_i18n( (int) $totals['sources'] ) ); ?></strong><span><?php esc_html_e( 'Sources', 'ofg-call-button' ); ?></span></div>
				</div>
			</div>

			<section class="ofogh-call-btn-panel ofogh-call-btn-report-filters-panel">
				<form method="get" class="ofogh-call-btn-report-filters">
					<input type="hidden" name="page" value="ofg-call-button-reports">
					<?php wp_nonce_field( 'ofg_call_btn_reports_filter', 'ofg_call_btn_reports_nonce', false ); ?>
				<label>
					<span><?php esc_html_e( 'From', 'ofg-call-button' ); ?></span>
					<?php $this->date_filter_field( 'date_from', $filters['date_from'] ); ?>
				</label>
				<label>
					<span><?php esc_html_e( 'To', 'ofg-call-button' ); ?></span>
					<?php $this->date_filter_field( 'date_to', $filters['date_to'] ); ?>
				</label>
					<label>
						<span><?php esc_html_e( 'Source', 'ofg-call-button' ); ?></span>
						<input type="text" name="source" value="<?php echo esc_attr( $filters['source'] ); ?>" placeholder="google, direct, referral">
					</label>
					<label>
						<span><?php esc_html_e( 'Search', 'ofg-call-button' ); ?></span>
						<input type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Page, referrer, source, phone', 'ofg-call-button' ); ?>">
					</label>
					<?php submit_button( __( 'Filter', 'ofg-call-button' ), 'secondary', '', false ); ?>
				</form>
			</section>

			<div class="ofogh-call-btn-report-grid">
				<?php $this->render_report_table( __( 'Calls by page', 'ofg-call-button' ), $pages, array( __( 'Page', 'ofg-call-button' ), __( 'Calls', 'ofg-call-button' ) ) ); ?>
				<?php $this->render_report_table( __( 'Calls by source', 'ofg-call-button' ), $sources, array( __( 'Source', 'ofg-call-button' ), __( 'Calls', 'ofg-call-button' ) ) ); ?>
			</div>

			<section class="ofogh-call-btn-panel ofogh-call-btn-report-table-panel">
				<h2><?php esc_html_e( 'Recent call details', 'ofg-call-button' ); ?></h2>
				<div class="ofogh-call-btn-table-wrap">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Time', 'ofg-call-button' ); ?></th>
								<th><?php esc_html_e( 'Page', 'ofg-call-button' ); ?></th>
								<th><?php esc_html_e( 'Phone', 'ofg-call-button' ); ?></th>
								<th><?php esc_html_e( 'Source', 'ofg-call-button' ); ?></th>
								<th><?php esc_html_e( 'Referrer', 'ofg-call-button' ); ?></th>
								<th><?php esc_html_e( 'Landing page', 'ofg-call-button' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $events ) ) : ?>
								<tr><td colspan="6"><?php esc_html_e( 'No call events found for these filters.', 'ofg-call-button' ); ?></td></tr>
							<?php endif; ?>
							<?php foreach ( $events as $event ) : ?>
								<tr>
									<td><?php echo esc_html( $this->format_mysql_datetime( $event['clicked_at'] ) ); ?></td>
									<td>
										<a href="<?php echo esc_url( $event['page_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $event['page_title'] ? $event['page_title'] : $event['page_url'] ); ?></a>
									</td>
									<td><?php echo esc_html( $event['phone'] ); ?></td>
									<td><?php echo esc_html( trim( $event['source'] . ( $event['medium'] ? ' / ' . $event['medium'] : '' ) ) ); ?></td>
									<td><?php echo $event['referrer_url'] ? '<a href="' . esc_url( $event['referrer_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $this->short_url( $event['referrer_url'] ) ) . '</a>' : esc_html__( 'Direct/unknown', 'ofg-call-button' ); ?></td>
									<td><?php echo $event['landing_url'] ? '<a href="' . esc_url( $event['landing_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $this->short_url( $event['landing_url'] ) ) . '</a>' : ''; ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</section>
		</div>
		<?php
	}

	/**
	 * Render a report date field, using a scoped Jalali UI for Persian sites.
	 *
	 * @param string $name Field name.
	 * @param string $value Gregorian date in Y-m-d.
	 */
	private function date_filter_field( $name, $value ) {
		if ( ! $this->is_persian_locale() ) {
			?>
			<input type="date" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>">
			<?php
			return;
		}

		?>
		<span class="ofogh-call-btn-jalali-field">
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" data-ofogh-gregorian-date>
			<input type="text" class="ofogh-call-btn-jalali-input" value="" inputmode="numeric" autocomplete="off" dir="ltr" data-ofogh-jalali-date data-target="<?php echo esc_attr( $name ); ?>" data-gregorian="<?php echo esc_attr( $value ); ?>" placeholder="1405/04/02">
			<button type="button" class="ofogh-call-btn-jalali-trigger" data-ofogh-jalali-trigger aria-label="Open calendar">▾</button>
		</span>
		<?php
	}

	/**
	 * Format report date for display.
	 *
	 * @param string $date Gregorian date in Y-m-d.
	 * @return string
	 */
	private function format_report_date( $date ) {
		if ( ! $this->is_persian_locale() || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $date, $matches ) ) {
			return (string) $date;
		}

		$jalali = $this->gregorian_to_jalali( (int) $matches[1], (int) $matches[2], (int) $matches[3] );
		return sprintf( '%04d/%02d/%02d', $jalali[0], $jalali[1], $jalali[2] );
	}

	/**
	 * Convert Gregorian date to Jalali date.
	 *
	 * @param int $gy Gregorian year.
	 * @param int $gm Gregorian month.
	 * @param int $gd Gregorian day.
	 * @return array
	 */
	private function gregorian_to_jalali( $gy, $gm, $gd ) {
		$g_d_m = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );

		if ( $gy > 1600 ) {
			$jy = 979;
			$gy -= 1600;
		} else {
			$jy = 0;
			$gy -= 621;
		}

		$gy2  = $gm > 2 ? $gy + 1 : $gy;
		$days = ( 365 * $gy ) + intdiv( $gy2 + 3, 4 ) - intdiv( $gy2 + 99, 100 ) + intdiv( $gy2 + 399, 400 ) - 80 + $gd + $g_d_m[ $gm - 1 ];
		$jy  += 33 * intdiv( $days, 12053 );
		$days %= 12053;
		$jy  += 4 * intdiv( $days, 1461 );
		$days %= 1461;

		if ( $days > 365 ) {
			$jy  += intdiv( $days - 1, 365 );
			$days = ( $days - 1 ) % 365;
		}

		$jm = $days < 186 ? 1 + intdiv( $days, 31 ) : 7 + intdiv( $days - 186, 30 );
		$jd = 1 + ( $days < 186 ? $days % 31 : ( $days - 186 ) % 30 );

		return array( $jy, $jm, $jd );
	}

	/**
	 * Render a number setting row.
	 *
	 * @param string $key Option key.
	 * @param string $label Field label.
	 * @param string $suffix Unit suffix.
	 */
	private function number_field( $key, $label, $suffix ) {
		?>
		<label class="ofogh-call-btn-row">
			<span><?php echo esc_html( $label ); ?></span>
			<span><input type="number" name="<?php echo esc_attr( self::OPTION_NAME . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( (int) $this->options[ $key ] ); ?>" min="0" step="1"> <?php echo esc_html( $suffix ); ?></span>
		</label>
		<?php
	}

	/**
	 * Render a text setting row.
	 *
	 * @param string $key Option key.
	 * @param string $label Field label.
	 * @param string $placeholder Placeholder text.
	 */
	private function text_field( $key, $label, $placeholder ) {
		?>
		<label class="ofogh-call-btn-row">
			<span><?php echo esc_html( $label ); ?></span>
			<input type="text" name="<?php echo esc_attr( self::OPTION_NAME . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $this->options[ $key ] ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>">
		</label>
		<?php
	}

	/**
	 * Render a color setting row.
	 *
	 * @param string $key Option key.
	 * @param string $label Field label.
	 */
	private function color_field( $key, $label ) {
		?>
		<label class="ofogh-call-btn-row">
			<span><?php echo esc_html( $label ); ?></span>
			<input type="text" class="ofogh-call-btn-color" name="<?php echo esc_attr( self::OPTION_NAME . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $this->options[ $key ] ); ?>">
		</label>
		<?php
	}

	/**
	 * Render a checkbox setting row.
	 *
	 * @param string $key Option key.
	 * @param string $label Field label.
	 */
	private function checkbox_field( $key, $label ) {
		?>
		<label class="ofogh-call-btn-check">
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME . '[' . $key . ']' ); ?>" value="1" <?php checked( ! empty( $this->options[ $key ] ) ); ?>>
			<span><?php echo esc_html( $label ); ?></span>
		</label>
		<?php
	}

	/**
	 * Register post/page/product overrides.
	 */
	public function register_meta_box() {
		foreach ( array( 'post', 'page', 'product' ) as $screen ) {
			add_meta_box(
				'ofogh_call_btn_meta',
				__( 'OFG Call Button', 'ofg-call-button' ),
				array( $this, 'render_meta_box' ),
				$screen,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render meta box.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_meta_box( $post ) {
		$hidden = (int) get_post_meta( $post->ID, '_ofogh_call_btn_hidden', true );
		$phone  = (string) get_post_meta( $post->ID, '_ofogh_call_btn_phone', true );

		wp_nonce_field( 'ofogh_call_btn_meta', 'ofogh_call_btn_meta_nonce' );
		?>
		<p>
			<label>
				<input type="checkbox" name="ofogh_call_btn_hidden" value="1" <?php checked( $hidden, 1 ); ?>>
				<?php esc_html_e( 'Disable on this content', 'ofg-call-button' ); ?>
			</label>
		</p>
		<p>
			<label for="ofogh-call-btn-phone"><?php esc_html_e( 'Custom phone number', 'ofg-call-button' ); ?></label>
			<input id="ofogh-call-btn-phone" class="widefat" type="text" name="ofogh_call_btn_phone" value="<?php echo esc_attr( $phone ); ?>" placeholder="+15555550100">
		</p>
		<?php
	}

	/**
	 * Save meta box.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_meta_box( $post_id ) {
		$nonce = sanitize_text_field( (string) filter_input( INPUT_POST, 'ofogh_call_btn_meta_nonce', FILTER_UNSAFE_RAW ) );

		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'ofogh_call_btn_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		$post_type = get_post_type( $post_id );
		if ( ! $post_type || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$hidden = null === filter_input( INPUT_POST, 'ofogh_call_btn_hidden', FILTER_UNSAFE_RAW ) ? 0 : 1;
		update_post_meta( $post_id, '_ofogh_call_btn_hidden', $hidden );

		$phone = $this->sanitize_phone( sanitize_text_field( (string) filter_input( INPUT_POST, 'ofogh_call_btn_phone', FILTER_UNSAFE_RAW ) ) );
		if ( '' === $phone ) {
			delete_post_meta( $post_id, '_ofogh_call_btn_phone' );
		} else {
			update_post_meta( $post_id, '_ofogh_call_btn_phone', $phone );
		}
	}

	/**
	 * Load frontend assets.
	 */
	public function frontend_assets() {
		if ( ! $this->should_render_button() ) {
			return;
		}

		wp_enqueue_style( 'ofogh-call-btn', OFOGH_CALL_BTN_URL . 'assets/css/frontend.css', array(), OFOGH_CALL_BTN_VERSION );
		wp_add_inline_style( 'ofogh-call-btn', $this->dynamic_css() );

		wp_enqueue_script( 'ofogh-call-btn', OFOGH_CALL_BTN_URL . 'assets/js/frontend.js', array(), OFOGH_CALL_BTN_VERSION, true );
		wp_localize_script(
			'ofogh-call-btn',
			'ofoghCallBtn',
			array(
				'drag'      => ! empty( $this->options['drag_enabled'] ),
				'tracking'  => ! empty( $this->options['tracking_enabled'] ),
				'endpoint'  => esc_url_raw( rest_url( self::REST_NS . self::REST_ROUTE ) ),
				'nonce'     => wp_create_nonce( self::NONCE_ACTION ),
				'pageId'    => (int) get_queried_object_id(),
				'isMobile'  => wp_is_mobile() ? 1 : 0,
			)
		);
	}

	/**
	 * Render frontend button.
	 */
	public function render_button() {
		if ( ! $this->should_render_button() ) {
			return;
		}

		$phone = $this->get_current_phone();
		if ( '' === $phone ) {
			return;
		}

		$classes = array(
			'ofogh-call-btn',
			'ofogh-call-btn--' . sanitize_html_class( $this->options['icon_style'] ),
		);

		if ( ! empty( $this->options['wave_effect'] ) ) {
			$classes[] = 'ofogh-call-btn--wave';
		}

		if ( ! empty( $this->options['shake_effect'] ) ) {
			$classes[] = 'ofogh-call-btn--shake';
		}

		?>
		<div id="ofogh-call-btn" class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
			<div class="ofogh-call-btn__inner">
				<span class="ofogh-call-btn__text"><?php echo esc_html( $this->options['call_text'] ); ?></span>
				<a class="ofogh-call-btn__link" href="<?php echo esc_url( 'tel:' . $phone ); ?>" title="<?php echo esc_attr( $this->options['call_text'] ); ?>" aria-label="<?php echo esc_attr( $this->options['call_text'] ); ?>" data-ofogh-call-btn-track data-phone="<?php echo esc_attr( $phone ); ?>">
					<span class="ofogh-call-btn__ring" aria-hidden="true"></span>
					<span class="ofogh-call-btn__fill" aria-hidden="true"></span>
					<span class="ofogh-call-btn__icon" aria-hidden="true"></span>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Check whether frontend button can be shown.
	 *
	 * @return bool
	 */
	private function should_render_button() {
		if ( is_admin() || wp_doing_ajax() || wp_is_json_request() ) {
			return false;
		}

		if ( '' === $this->options['phone_number'] ) {
			return false;
		}

		$post_id = get_queried_object_id();
		if ( $post_id && (int) get_post_meta( $post_id, '_ofogh_call_btn_hidden', true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Current phone number.
	 *
	 * @return string
	 */
	private function get_current_phone() {
		$post_id = get_queried_object_id();
		$custom  = $post_id ? $this->sanitize_phone( get_post_meta( $post_id, '_ofogh_call_btn_phone', true ) ) : '';

		if ( '' !== $custom ) {
			return $custom;
		}

		$numbers = array_filter(
			array(
				$this->options['phone_number'],
				$this->options['phone_number_1'],
				$this->options['phone_number_2'],
			)
		);

		if ( empty( $numbers ) ) {
			return '';
		}

		return (string) $numbers[ array_rand( $numbers ) ];
	}

	/**
	 * Dynamic frontend CSS.
	 *
	 * @return string
	 */
	private function dynamic_css() {
		$desktop_text = empty( $this->options['hide_text_desktop'] ) ? 'inline-flex' : 'none';
		$tablet_text  = empty( $this->options['hide_text_tablet'] ) ? 'inline-flex' : 'none';
		$mobile_text  = empty( $this->options['hide_text_mobile'] ) ? 'inline-flex' : 'none';

		return sprintf(
			'
			@media screen and (max-width:%1$dpx){#ofogh-call-btn{display:block;}}
			@media screen and (min-width:1025px){#ofogh-call-btn{top:%2$d%%;}#ofogh-call-btn .ofogh-call-btn__text{display:%7$s;}}
			@media screen and (min-width:681px) and (max-width:1024px){#ofogh-call-btn{top:%3$d%%;}#ofogh-call-btn .ofogh-call-btn__text{display:%8$s;}}
			@media screen and (max-width:680px){#ofogh-call-btn{top:%4$d%%;}#ofogh-call-btn .ofogh-call-btn__text{display:%9$s;}}
			#ofogh-call-btn{left:%5$d%%;background:%10$s;}
			#ofogh-call-btn .ofogh-call-btn__text{color:%11$s;}
			#ofogh-call-btn .ofogh-call-btn__icon{background-color:%12$s;}
			#ofogh-call-btn .ofogh-call-btn__fill{background-color:%12$s;}
			#ofogh-call-btn .ofogh-call-btn__ring{border-color:%12$s;}
			',
			(int) $this->options['screen_size'],
			(int) $this->options['move_top_desktop'],
			(int) $this->options['move_top_tablet'],
			(int) $this->options['move_top'],
			(int) $this->options['move_left'],
			'',
			$desktop_text,
			$tablet_text,
			$mobile_text,
			esc_html( $this->options['bg_color'] ),
			esc_html( $this->options['call_text_color'] ),
			esc_html( $this->options['call_color'] )
		);
	}

	/**
	 * Register click tracking endpoint.
	 */
	public function register_rest_routes() {
		register_rest_route(
			self::REST_NS,
			self::REST_ROUTE,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'track_click' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'phone' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'page_id' => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'page_url' => array(
						'type'              => 'string',
						'sanitize_callback' => 'esc_url_raw',
					),
					'landing_url' => array(
						'type'              => 'string',
						'sanitize_callback' => 'esc_url_raw',
					),
					'referrer_url' => array(
						'type'              => 'string',
						'sanitize_callback' => 'esc_url_raw',
					),
					'is_mobile' => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Save a click event.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function track_click( WP_REST_Request $request ) {
		if ( empty( $this->options['tracking_enabled'] ) ) {
			return new WP_Error( 'ofogh_call_btn_tracking_disabled', __( 'Tracking is disabled.', 'ofg-call-button' ), array( 'status' => 400 ) );
		}

		if ( ! $this->verify_tracking_nonce( $request ) || $this->is_known_bot( $request ) || $this->is_duplicate_event( $request ) ) {
			return rest_ensure_response( array( 'success' => true ) );
		}

		$clicks = $this->get_clicks();
		$day    = $this->current_stats_day();

		$clicks[ $day ] = isset( $clicks[ $day ] ) ? (int) $clicks[ $day ] + 1 : 1;
		update_option( self::CLICKS_NAME, $clicks, false );
		$this->insert_click_event( $request, $day );

		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * Store a detailed call event.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @param string          $day Day key.
	 */
	private function insert_click_event( WP_REST_Request $request, $day ) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::EVENTS_TABLE;

		$page_id    = absint( $request->get_param( 'page_id' ) );
		$page_url   = esc_url_raw( (string) $request->get_param( 'page_url' ) );

		if ( '' === $page_url ) {
			$page_url = $page_id ? get_permalink( $page_id ) : home_url( '/' );
		}

		$landing_url  = esc_url_raw( (string) $request->get_param( 'landing_url' ) );
		$referrer_url = esc_url_raw( (string) $request->get_param( 'referrer_url' ) );
		$traffic      = $this->classify_traffic( $page_url, $landing_url, $referrer_url );
		$page_title   = $page_id ? get_the_title( $page_id ) : '';

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$table_name,
			array(
				'clicked_at'     => current_time( 'mysql' ),
				'clicked_at_gmt' => current_time( 'mysql', true ),
				'day_key'        => $day,
				'page_id'        => $page_id,
				'page_title'     => $page_title ? wp_strip_all_tags( $page_title ) : '',
				'page_url'       => $page_url,
				'landing_url'    => $landing_url,
				'referrer_url'   => $referrer_url,
				'source'         => $traffic['source'],
				'medium'         => $traffic['medium'],
				'phone'          => $this->sanitize_phone( $request->get_param( 'phone' ) ),
				'user_agent'     => sanitize_textarea_field( (string) $request->get_header( 'User-Agent' ) ),
				'is_mobile'      => empty( $request->get_param( 'is_mobile' ) ) ? 0 : 1,
				'created_at_gmt' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Classify source/medium from URLs.
	 *
	 * @param string $page_url Current page URL.
	 * @param string $landing_url Landing URL.
	 * @param string $referrer_url Referrer URL.
	 * @return array
	 */
	private function classify_traffic( $page_url, $landing_url, $referrer_url ) {
		$campaign_url = $landing_url ? $landing_url : $page_url;
		$params       = $this->url_query_params( $campaign_url );

		if ( ! empty( $params['utm_source'] ) ) {
			return array(
				'source' => sanitize_text_field( $params['utm_source'] ),
				'medium' => ! empty( $params['utm_medium'] ) ? sanitize_text_field( $params['utm_medium'] ) : 'campaign',
			);
		}

		if ( '' === $referrer_url ) {
			return array( 'source' => 'direct', 'medium' => 'none' );
		}

		$host = wp_parse_url( $referrer_url, PHP_URL_HOST );
		$host = $host ? strtolower( preg_replace( '/^www\./', '', $host ) ) : '';
		$site = wp_parse_url( home_url(), PHP_URL_HOST );
		$site = $site ? strtolower( preg_replace( '/^www\./', '', $site ) ) : '';

		if ( $host && $site && $host === $site ) {
			return array( 'source' => 'internal', 'medium' => 'internal' );
		}

		$search_engines = array(
			'google.'      => 'google',
			'bing.com'     => 'bing',
			'yahoo.'       => 'yahoo',
			'duckduckgo.'  => 'duckduckgo',
			'yandex.'      => 'yandex',
			'baidu.'       => 'baidu',
		);

		foreach ( $search_engines as $needle => $source ) {
			if ( false !== strpos( $host, $needle ) ) {
				return array( 'source' => $source, 'medium' => 'organic' );
			}
		}

		$social_sources = array( 'instagram.com', 'facebook.com', 't.co', 'twitter.com', 'x.com', 'linkedin.com', 'telegram.org', 't.me', 'pinterest.', 'youtube.com' );
		foreach ( $social_sources as $needle ) {
			if ( false !== strpos( $host, $needle ) ) {
				return array( 'source' => $host, 'medium' => 'social' );
			}
		}

		return array( 'source' => $host ? $host : 'referral', 'medium' => 'referral' );
	}

	/**
	 * Extract query parameters from a URL.
	 *
	 * @param string $url URL.
	 * @return array
	 */
	private function url_query_params( $url ) {
		$query = wp_parse_url( $url, PHP_URL_QUERY );
		if ( ! $query ) {
			return array();
		}

		$params = array();
		wp_parse_str( $query, $params );
		return is_array( $params ) ? $params : array();
	}

	/**
	 * Verify REST nonce for click submissions.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return bool
	 */
	private function verify_tracking_nonce( WP_REST_Request $request ) {
		$nonce = (string) $request->get_param( 'nonce' );
		if ( '' === $nonce ) {
			$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		}

		return (bool) wp_verify_nonce( $nonce, self::NONCE_ACTION );
	}

	/**
	 * Detect common automated requests.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return bool
	 */
	private function is_known_bot( WP_REST_Request $request ) {
		$user_agent = strtolower( (string) $request->get_header( 'User-Agent' ) );
		if ( '' === $user_agent ) {
			return true;
		}

		foreach ( array( 'bot', 'crawler', 'spider', 'scraper', 'curl', 'wget', 'python', 'requests', 'headless', 'selenium', 'puppeteer', 'playwright', 'monitoring', 'uptimerobot' ) as $pattern ) {
			if ( false !== strpos( $user_agent, $pattern ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Deduplicate repeated clicks.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return bool
	 */
	private function is_duplicate_event( WP_REST_Request $request ) {
		$user_agent  = (string) $request->get_header( 'User-Agent' );
		$phone       = sanitize_text_field( (string) $request->get_param( 'phone' ) );
		$page_id     = absint( $request->get_param( 'page_id' ) );
		$fingerprint = hash( 'sha256', $user_agent . '|' . $phone . '|' . $page_id );
		$stored      = get_option( self::DEDUP_NAME, array() );
		$stored      = is_array( $stored ) ? $stored : array();
		$now         = current_time( 'timestamp' );

		foreach ( $stored as $key => $timestamp ) {
			if ( $now - (int) $timestamp >= self::DEDUP_WINDOW ) {
				unset( $stored[ $key ] );
			}
		}

		if ( isset( $stored[ $fingerprint ] ) ) {
			update_option( self::DEDUP_NAME, $stored, false );
			return true;
		}

		$stored[ $fingerprint ] = $now;
		update_option( self::DEDUP_NAME, $stored, false );

		return false;
	}

	/**
	 * Register dashboard stats widget.
	 */
	public function register_dashboard_widget() {
		if ( empty( $this->options['tracking_enabled'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'ofogh_call_btn_clicks_widget',
			__( 'OFG Call Button Clicks', 'ofg-call-button' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render dashboard stats widget.
	 */
	public function render_dashboard_widget() {
		$clicks = $this->get_clicks();
		$rows   = $this->recent_click_rows( 30 );
		$total  = array_sum( array_map( 'intval', $clicks ) );
		$today  = $this->clicks_for_day( $this->current_stats_day() );
		$week   = 0;

		for ( $i = 0; $i < 7; $i++ ) {
			$week += $this->clicks_for_day( $this->stats_day_offset( $i ) );
		}
		?>
		<div class="ofogh-call-btn-widget">
			<div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:12px;">
				<div><strong><?php esc_html_e( 'Total clicks', 'ofg-call-button' ); ?>:</strong> <?php echo esc_html( number_format_i18n( $total ) ); ?></div>
				<div><strong><?php esc_html_e( 'Today', 'ofg-call-button' ); ?>:</strong> <?php echo esc_html( number_format_i18n( $today ) ); ?></div>
				<div><strong><?php esc_html_e( 'Last 7 days', 'ofg-call-button' ); ?>:</strong> <?php echo esc_html( number_format_i18n( $week ) ); ?></div>
			</div>
			<?php echo $this->chart_svg( $rows ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php
	}

	/**
	 * Read and sanitize report filters.
	 *
	 * @return array
	 */
	private function report_filters() {
		$default_from = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		$default_to   = gmdate( 'Y-m-d' );
		$has_filters  = null !== filter_input( INPUT_GET, 'date_from', FILTER_UNSAFE_RAW )
			|| null !== filter_input( INPUT_GET, 'date_to', FILTER_UNSAFE_RAW )
			|| null !== filter_input( INPUT_GET, 'source', FILTER_UNSAFE_RAW )
			|| null !== filter_input( INPUT_GET, 's', FILTER_UNSAFE_RAW );
		$nonce        = sanitize_text_field( (string) filter_input( INPUT_GET, 'ofg_call_btn_reports_nonce', FILTER_UNSAFE_RAW ) );

		if ( $has_filters && ! wp_verify_nonce( $nonce, 'ofg_call_btn_reports_filter' ) ) {
			return array(
				'date_from' => $default_from,
				'date_to'   => $default_to,
				'source'    => '',
				'search'    => '',
			);
		}

		$date_from = sanitize_text_field( (string) filter_input( INPUT_GET, 'date_from', FILTER_UNSAFE_RAW ) );
		$date_to   = sanitize_text_field( (string) filter_input( INPUT_GET, 'date_to', FILTER_UNSAFE_RAW ) );
		$date_from = '' !== $date_from ? $date_from : $default_from;
		$date_to   = '' !== $date_to ? $date_to : $default_to;

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
			$date_from = $default_from;
		}

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
			$date_to = $default_to;
		}

		return array(
			'date_from' => $date_from,
			'date_to'   => $date_to,
			'source'    => sanitize_text_field( (string) filter_input( INPUT_GET, 'source', FILTER_UNSAFE_RAW ) ),
			'search'    => sanitize_text_field( (string) filter_input( INPUT_GET, 's', FILTER_UNSAFE_RAW ) ),
		);
	}

	/**
	 * Build SQL WHERE parts for report queries.
	 *
	 * @param array $filters Report filters.
	 * @return array
	 */
	private function report_where_sql( $filters ) {
		global $wpdb;

		$where  = array( 'clicked_at >= %s', 'clicked_at <= %s' );
		$values = array( $filters['date_from'] . ' 00:00:00', $filters['date_to'] . ' 23:59:59' );

		if ( '' !== $filters['source'] ) {
			$where[]  = 'source LIKE %s';
			$values[] = '%' . $wpdb->esc_like( $filters['source'] ) . '%';
		}

		if ( '' !== $filters['search'] ) {
			$like    = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$where[] = '(page_title LIKE %s OR page_url LIKE %s OR landing_url LIKE %s OR referrer_url LIKE %s OR source LIKE %s OR medium LIKE %s OR phone LIKE %s)';
			for ( $i = 0; $i < 7; $i++ ) {
				$values[] = $like;
			}
		}

		return array(
			'where'  => 'WHERE ' . implode( ' AND ', $where ),
			'values' => $values,
		);
	}

	/**
	 * Report totals.
	 *
	 * @param array $filters Report filters.
	 * @return array
	 */
	private function report_totals( $filters ) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::EVENTS_TABLE;
		$where      = $this->report_where_sql( $filters );
		$query      = $wpdb->prepare(
			"SELECT COUNT(*) AS total, COUNT(DISTINCT page_url) AS pages, COUNT(DISTINCT source) AS sources FROM {$table_name} {$where['where']}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$where['values']
		);
		$row        = $wpdb->get_row( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return wp_parse_args(
			is_array( $row ) ? $row : array(),
			array(
				'total'   => 0,
				'pages'   => 0,
				'sources' => 0,
			)
		);
	}

	/**
	 * Grouped report rows.
	 *
	 * @param array  $filters Report filters.
	 * @param string $group Group type.
	 * @return array
	 */
	private function report_grouped_rows( $filters, $group ) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::EVENTS_TABLE;
		$where      = $this->report_where_sql( $filters );

		if ( 'page' === $group ) {
			$query = $wpdb->prepare(
				"SELECT CASE WHEN page_title <> '' THEN page_title ELSE page_url END AS label, COUNT(*) AS calls FROM {$table_name} {$where['where']} GROUP BY page_url, page_title ORDER BY calls DESC LIMIT 10", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				$where['values']
			);
			return $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		}

		$query = $wpdb->prepare(
			"SELECT source AS label, COUNT(*) AS calls FROM {$table_name} {$where['where']} GROUP BY source ORDER BY calls DESC LIMIT 10", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$where['values']
		);
		return $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	/**
	 * Recent detailed report events.
	 *
	 * @param array $filters Report filters.
	 * @return array
	 */
	private function report_events( $filters ) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::EVENTS_TABLE;
		$where      = $this->report_where_sql( $filters );
		$query      = $wpdb->prepare(
			"SELECT clicked_at, page_title, page_url, landing_url, referrer_url, source, medium, phone FROM {$table_name} {$where['where']} ORDER BY clicked_at_gmt DESC LIMIT 200", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$where['values']
		);
		return $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	/**
	 * Render a compact grouped report table.
	 *
	 * @param string $title Table title.
	 * @param array  $rows Rows.
	 * @param array  $headers Header labels.
	 */
	private function render_report_table( $title, $rows, $headers ) {
		?>
		<section class="ofogh-call-btn-panel">
			<h2><?php echo esc_html( $title ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html( $headers[0] ); ?></th>
						<th><?php echo esc_html( $headers[1] ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="2"><?php esc_html_e( 'No data.', 'ofg-call-button' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row['label'] ? $row['label'] : __( 'Unknown', 'ofg-call-button' ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (int) $row['calls'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</section>
		<?php
	}

	/**
	 * Format a MySQL datetime in site timezone.
	 *
	 * @param string $datetime Datetime.
	 * @return string
	 */
	private function format_mysql_datetime( $datetime ) {
		$timestamp = strtotime( $datetime );
		return $timestamp ? wp_date( 'Y-m-d H:i:s', $timestamp, wp_timezone() ) : $datetime;
	}

	/**
	 * Shorten URL for report cells.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private function short_url( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$path = wp_parse_url( $url, PHP_URL_PATH );
		$text = trim( ( $host ? $host : '' ) . ( $path ? $path : '' ) );

		if ( '' === $text ) {
			$text = $url;
		}

		return strlen( $text ) > 64 ? substr( $text, 0, 61 ) . '...' : $text;
	}

	/**
	 * Click counters.
	 *
	 * @return array
	 */
	private function get_clicks() {
		$clicks = get_option( self::CLICKS_NAME, array() );
		return is_array( $clicks ) ? $clicks : array();
	}

	/**
	 * Clicks by day.
	 *
	 * @param string $day Date in Ymd.
	 * @return int
	 */
	private function clicks_for_day( $day ) {
		$clicks = $this->get_clicks();
		$total  = 0;

		foreach ( $this->stats_day_keys( $day ) as $key ) {
			if ( isset( $clicks[ $key ] ) ) {
				$total += (int) $clicks[ $key ];
			}
		}

		return $total;
	}

	/**
	 * Recent daily rows.
	 *
	 * @param int $days Days count.
	 * @return array
	 */
	private function recent_click_rows( $days ) {
		$rows = array();

		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$day    = $this->stats_day_offset( $i );
			$rows[] = array(
				'day'    => $day,
				'clicks' => $this->clicks_for_day( $day ),
			);
		}

		return $rows;
	}

	/**
	 * Build dashboard SVG.
	 *
	 * @param array $rows Chart rows.
	 * @return string
	 */
	private function chart_svg( $rows ) {
		$width       = 640;
		$height      = 180;
		$padding_x   = 14;
		$padding_y   = 14;
		$chart_width = $width - ( $padding_x * 2 );
		$chart_h     = $height - ( $padding_y * 2 ) - 14;
		$base_y      = $height - 18;
		$max_value   = max( 1, (int) max( wp_list_pluck( $rows, 'clicks' ) ) );
		$step        = count( $rows ) > 1 ? $chart_width / ( count( $rows ) - 1 ) : $chart_width;
		$path        = array();

		foreach ( $rows as $index => $row ) {
			$x      = $padding_x + ( $index * $step );
			$y      = $padding_y + ( $chart_h - ( ( (int) $row['clicks'] / $max_value ) * $chart_h ) );
			$path[] = sprintf( '%s %.2f %.2f', 0 === $index ? 'M' : 'L', $x, $y );
		}

		$area = implode( ' ', $path ) . sprintf( ' L %.2f %.2f L %.2f %.2f Z', $padding_x + ( ( count( $rows ) - 1 ) * $step ), $base_y, $padding_x, $base_y );

		ob_start();
		?>
		<div class="ofogh-call-btn-chart" style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:10px 8px 0;">
			<svg viewBox="0 0 <?php echo esc_attr( (string) $width ); ?> <?php echo esc_attr( (string) $height ); ?>" role="img" aria-label="<?php esc_attr_e( 'Call button clicks over the last 30 days', 'ofg-call-button' ); ?>" style="display:block;width:100%;height:auto;overflow:visible;">
				<line x1="<?php echo esc_attr( (string) $padding_x ); ?>" y1="<?php echo esc_attr( (string) $base_y ); ?>" x2="<?php echo esc_attr( (string) ( $width - $padding_x ) ); ?>" y2="<?php echo esc_attr( (string) $base_y ); ?>" stroke="#c3c4c7" stroke-width="1" />
				<path d="<?php echo esc_attr( $area ); ?>" fill="#1f7a4d" fill-opacity="0.14" />
				<path d="<?php echo esc_attr( implode( ' ', $path ) ); ?>" fill="none" stroke="#1f7a4d" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
				<?php foreach ( $rows as $index => $row ) : ?>
					<?php
					$value = (int) $row['clicks'];
					$x     = $padding_x + ( $index * $step );
					$y     = $padding_y + ( $chart_h - ( ( $value / $max_value ) * $chart_h ) );
					$label = $this->format_day( $row['day'] ) . ' - ' . sprintf(
						/* translators: %d: clicks count. */
						_n( '%d click', '%d clicks', $value, 'ofg-call-button' ),
						$value
					);
					?>
					<circle cx="<?php echo esc_attr( sprintf( '%.2f', $x ) ); ?>" cy="<?php echo esc_attr( sprintf( '%.2f', $y ) ); ?>" r="4" fill="<?php echo esc_attr( $value > 0 ? '#1f7a4d' : '#fff' ); ?>" stroke="#1f7a4d" stroke-width="1.5">
						<title><?php echo esc_html( $label ); ?></title>
					</circle>
				<?php endforeach; ?>
			</svg>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Format day label.
	 *
	 * @param string $day Ymd date.
	 * @return string
	 */
	private function format_day( $day ) {
		if ( ! preg_match( '/^\d{8}$/', $day ) ) {
			return $day;
		}

		$date = DateTimeImmutable::createFromFormat( 'Ymd', $day, wp_timezone() );
		if ( ! $date ) {
			return $day;
		}

		return wp_date( 'd M Y', $date->getTimestamp(), wp_timezone() );
	}

	/**
	 * Current stats day key in site timezone.
	 *
	 * @return string
	 */
	private function current_stats_day() {
		return $this->stats_day_offset( 0 );
	}

	/**
	 * Stats day key offset from today.
	 *
	 * @param int $days_ago Days before today.
	 * @return string
	 */
	private function stats_day_offset( $days_ago ) {
		$date = new DateTimeImmutable( 'now', wp_timezone() );

		if ( $days_ago > 0 ) {
			$date = $date->modify( '-' . absint( $days_ago ) . ' days' );
		}

		return $date->format( 'Ymd' );
	}

	/**
	 * Possible stats keys for current and legacy date storage.
	 *
	 * @param string $day Day key in Ymd.
	 * @return array
	 */
	private function stats_day_keys( $day ) {
		$keys = array( $day );

		if ( preg_match( '/^\d{8}$/', $day ) ) {
			$date = DateTimeImmutable::createFromFormat( 'Ymd H:i:s', $day . ' 12:00:00', wp_timezone() );

			if ( $date ) {
				$keys[] = date_i18n( 'Ymd', $date->getTimestamp() );
				$keys[] = $this->normalize_digits( date_i18n( 'Ymd', $date->getTimestamp() ) );
			}
		}

		return array_values( array_unique( array_filter( $keys ) ) );
	}

	/**
	 * Normalize Persian and Arabic digits to ASCII.
	 *
	 * @param string $value Value to normalize.
	 * @return string
	 */
	private function normalize_digits( $value ) {
		return strtr(
			(string) $value,
			array(
				'۰' => '0',
				'۱' => '1',
				'۲' => '2',
				'۳' => '3',
				'۴' => '4',
				'۵' => '5',
				'۶' => '6',
				'۷' => '7',
				'۸' => '8',
				'۹' => '9',
				'٠' => '0',
				'١' => '1',
				'٢' => '2',
				'٣' => '3',
				'٤' => '4',
				'٥' => '5',
				'٦' => '6',
				'٧' => '7',
				'٨' => '8',
				'٩' => '9',
			)
		);
	}

	/**
	 * Clamp integer values.
	 *
	 * @param mixed $value Value.
	 * @param int   $min Minimum.
	 * @param int   $max Maximum.
	 * @return int
	 */
	private function clamp_int( $value, $min, $max ) {
		return max( $min, min( $max, absint( $value ) ) );
	}

	/**
	 * Sanitize hex color with fallback.
	 *
	 * @param string $value Color.
	 * @param string $fallback Fallback.
	 * @return string
	 */
	private function sanitize_hex( $value, $fallback ) {
		$color = sanitize_hex_color( $value );
		return $color ? $color : $fallback;
	}

	/**
	 * Sanitize phone number for tel links.
	 *
	 * @param mixed $number Raw number.
	 * @return string
	 */
	private function sanitize_phone( $number ) {
		$number = preg_replace( '/[^0-9+*#,;]/', '', (string) $number );
		return substr( $number, 0, 40 );
	}

}

register_activation_hook( OFOGH_CALL_BTN_FILE, array( 'Ofogh_Call_Btn_Plugin', 'activate' ) );

new Ofogh_Call_Btn_Plugin();
