<?php
/**
 * Admin functionality for Valserv Analytics for SentinelPro.
 *
 * @package ValservAnalyticsForSentinelPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the WordPress admin experience for the plugin.
 */
class Valserv_Analytics_Admin {

	/**
	 * Option name for plugin settings.
	 *
	 * @var string
	 */
	private $option_name;

	/**
	 * Constructor.
	 *
	 * @param string $option_name Name of the option used to persist settings.
	 */
	public function __construct( string $option_name ) {
		$this->option_name = $option_name;
	}

	/**
	 * Boots the admin hooks.
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'show_third_party_notice' ) );
	}

	/**
	 * Registers the settings and fields using the Settings API.
	 */
	public function register_settings(): void {
		register_setting(
			'valserv_analytics_settings_group',
			$this->option_name,
			array(
				'capability'        => 'manage_options',
				'sanitize_callback' => array( Valserv_Analytics_Settings::class, 'sanitize_settings' ),
				'default'           => Valserv_Analytics_Settings::get_default_settings(),
			)
		);

		add_settings_section(
			'valserv_analytics_main',
			__( 'SentinelPro Connection', 'valserv-analytics-for-sentinelpro' ),
			array( $this, 'render_settings_section' ),
			'valserv-analytics-settings'
		);

		add_settings_field(
			'account_name',
			__( 'Account Name', 'valserv-analytics-for-sentinelpro' ),
			array( $this, 'render_account_field' ),
			'valserv-analytics-settings',
			'valserv_analytics_main'
		);

		add_settings_field(
			'property_id',
			__( 'Property ID', 'valserv-analytics-for-sentinelpro' ),
			array( $this, 'render_property_id_field' ),
			'valserv-analytics-settings',
			'valserv_analytics_main'
		);

		add_settings_field(
			'api_key',
			__( 'API Key', 'valserv-analytics-for-sentinelpro' ),
			array( $this, 'render_api_key_field' ),
			'valserv-analytics-settings',
			'valserv_analytics_main'
		);

		add_settings_field(
			'enable_tracking',
			__( 'Enable Front-end Tracking', 'valserv-analytics-for-sentinelpro' ),
			array( $this, 'render_tracking_field' ),
			'valserv-analytics-settings',
			'valserv_analytics_main'
		);

		add_settings_field(
			'share_usage',
			__( 'Share Usage Metrics with SentinelPro', 'valserv-analytics-for-sentinelpro' ),
			array( $this, 'render_share_usage_field' ),
			'valserv-analytics-settings',
			'valserv_analytics_main'
		);
	}

	/**
	 * Adds the plugin settings page to the admin menu.
	 */
	public function add_menu(): void {
		add_menu_page(
			__( 'Valserv Analytics', 'valserv-analytics-for-sentinelpro' ),
			__( 'Valserv Analytics', 'valserv-analytics-for-sentinelpro' ),
			'manage_options',
			'vasp',
			array( $this, 'render_settings_page' ),
			'dashicons-chart-area'
		);
	}

	/**
	 * Enqueues admin assets for the plugin page only.
	 *
	 * @param string $hook Hook suffix provided by WordPress.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'toplevel_page_vasp' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'valserv-analytics-admin',
			plugins_url( 'assets/css/admin.css', VALSERV_ANALYTICS_PLUGIN_FILE ),
			array(),
			VALSERV_ANALYTICS_VERSION
		);

		wp_enqueue_script(
			'valserv-analytics-admin',
			plugins_url( 'assets/js/admin.js', VALSERV_ANALYTICS_PLUGIN_FILE ),
			array( 'wp-i18n' ),
			VALSERV_ANALYTICS_VERSION,
			true
		);

		wp_set_script_translations(
			'valserv-analytics-admin',
			'valserv-analytics-for-sentinelpro',
			plugin_dir_path( VALSERV_ANALYTICS_PLUGIN_FILE ) . 'languages'
		);

		wp_add_inline_script(
			'valserv-analytics-admin',
			'document.addEventListener("DOMContentLoaded",function(){var toggle=document.querySelector("[data-valserv-api-toggle]");var field=document.querySelector("[data-valserv-api-field]");if(!toggle||!field){return;}toggle.addEventListener("click",function(event){event.preventDefault();var type=field.getAttribute("type");field.setAttribute("type","password"===type?"text":"password");toggle.setAttribute("aria-pressed","password"===type);});});',
			'after'
		);
	}

	/**
	 * Outputs copy for the settings section.
	 */
	public function render_settings_section(): void {
		echo '<p>' . esc_html__( 'Enter the credentials provided by SentinelPro to connect your site. The plugin sends page view and basic usage information to SentinelPro when tracking is enabled.', 'valserv-analytics-for-sentinelpro' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'Note: The tracking script sends your configured account name, property ID, page view data, and anonymised usage metrics to collector.sentinelpro.com. No personal data such as passwords or email addresses is transmitted.', 'valserv-analytics-for-sentinelpro' ) . '</p>';
	}

	/**
	 * Renders the account field.
	 */
	public function render_account_field(): void {
		$settings     = Valserv_Analytics_Settings::get_settings();
		$account_name = $settings['account_name'];

		printf(
			'<input type="text" id="valserv-account-name" name="%1$s[account_name]" value="%2$s" class="regular-text" autocomplete="off" />',
			esc_attr( $this->option_name ),
			esc_attr( $account_name )
		);
		echo '<p class="description">' . esc_html__( 'Example: mycompany', 'valserv-analytics-for-sentinelpro' ) . '</p>';
	}

	/**
	 * Renders the property ID field.
	 */
	public function render_property_id_field(): void {
		$settings = Valserv_Analytics_Settings::get_settings();
		$property = $settings['property_id'];

		printf(
			'<input type="text" id="valserv-property-id" name="%1$s[property_id]" value="%2$s" class="regular-text" autocomplete="off" />',
			esc_attr( $this->option_name ),
			esc_attr( $property )
		);
	}

	/**
	 * Renders the API key field with masking and reveal toggle.
	 */
	public function render_api_key_field(): void {
		$settings = Valserv_Analytics_Settings::get_settings();
		$api_key  = $settings['api_key'];

		printf(
			'<div class="valserv-api-key"><input type="password" id="valserv-api-key" name="%1$s[api_key]" value="%2$s" class="regular-text" data-valserv-api-field="1" autocomplete="new-password" /><button type="button" class="button" data-valserv-api-toggle="1">%3$s</button></div>',
			esc_attr( $this->option_name ),
			esc_attr( $api_key ),
			esc_html__( 'Reveal', 'valserv-analytics-for-sentinelpro' )
		);
		echo '<p class="description">' . esc_html__( 'Store this securely. Use the toggle to confirm the value if needed.', 'valserv-analytics-for-sentinelpro' ) . '</p>';
	}

	/**
	 * Renders the tracking toggle field.
	 */
	public function render_tracking_field(): void {
		$settings        = Valserv_Analytics_Settings::get_settings();
		$enable_tracking = ! empty( $settings['enable_tracking'] );

		printf(
			'<label><input type="checkbox" name="%1$s[enable_tracking]" value="1" %2$s /> %3$s</label>',
			esc_attr( $this->option_name ),
			checked( $enable_tracking, true, false ),
			esc_html__( 'Load the SentinelPro tracker on the front-end of this site.', 'valserv-analytics-for-sentinelpro' )
		);
	}

	/**
	 * Renders the share usage toggle field.
	 */
	public function render_share_usage_field(): void {
		$settings    = Valserv_Analytics_Settings::get_settings();
		$share_usage = ! empty( $settings['share_usage'] );

		printf(
			'<label><input type="checkbox" name="%1$s[share_usage]" value="1" %2$s /> %3$s</label>',
			esc_attr( $this->option_name ),
			checked( $share_usage, true, false ),
			esc_html__( 'Allow anonymised plugin usage data to be sent to SentinelPro to help improve the service.', 'valserv-analytics-for-sentinelpro' )
		);
	}

	/**
	 * Displays an admin notice about third-party service connections.
	 */
	public function show_third_party_notice(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'toplevel_page_vasp' !== $screen->id ) {
			return;
		}

		$settings = Valserv_Analytics_Settings::get_settings();
		if ( empty( $settings['enable_tracking'] ) ) {
			return;
		}

		?>
		<div class="notice notice-info">
			<p>
				<strong><?php esc_html_e( 'Third-Party Service Connection:', 'valserv-analytics-for-sentinelpro' ); ?></strong>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: hostname of the third-party service */
						__( 'This plugin connects to %s to load the SentinelPro tracking script when front-end tracking is enabled.', 'valserv-analytics-for-sentinelpro' ),
						'collector.sentinelpro.com'
					)
				);
				?>
				<a href="https://sentinelpro.ai/privacy" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'View Privacy Policy', 'valserv-analytics-for-sentinelpro' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Renders the main plugin settings page.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'valserv-analytics-for-sentinelpro' ) );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Valserv Analytics for SentinelPro', 'valserv-analytics-for-sentinelpro' ) . '</h1>';
		echo '<form action="' . esc_url( admin_url( 'options.php' ) ) . '" method="post">';
		settings_fields( 'valserv_analytics_settings_group' );
		do_settings_sections( 'valserv-analytics-settings' );
		submit_button( __( 'Save Changes', 'valserv-analytics-for-sentinelpro' ) );
		echo '</form>';
		echo '</div>';
	}
}
