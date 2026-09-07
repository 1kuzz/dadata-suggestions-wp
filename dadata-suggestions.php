<?php
/**
 * Plugin Name:       DaData INN Suggestions
 * Description:       Adds DaData-powered INN/company suggestions to selected WordPress form fields.
 * Version:           2.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            1kuzz
 * License:           MIT
 * Text Domain:       dadata-suggestions
 *
 * @package Dadata_Suggestions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DADATA_SUGG_VERSION', '2.0.0' );
define( 'DADATA_SUGG_OPTION', 'dadata_suggestions_options' );
define(
	'DADATA_SUGG_ENDPOINT',
	'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/party'
);

/**
 * Small DaData INN suggestions plugin.
 */
final class Dadata_Suggestions_Plugin {
	const REST_NAMESPACE = 'dadata-suggestions/v1';

	/**
	 * Boot hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend' ) );
		add_filter(
			'plugin_action_links_' . plugin_basename( __FILE__ ),
			array( __CLASS__, 'action_links' )
		);
	}

	/**
	 * Default settings.
	 *
	 * @return array<string, string>
	 */
	public static function defaults() {
		return array(
			'dadata_enabled'   => '0',
			'dadata_token'     => '',
			'inn_selector'     => '#company',
			'name_selector'    => '',
			'kpp_selector'     => '',
			'ogrn_selector'    => '',
			'address_selector' => '',
			'tracking_enabled' => '0',
		);
	}

	/**
	 * Current settings.
	 *
	 * @return array<string, string>
	 */
	public static function options() {
		$options = get_option( DADATA_SUGG_OPTION, array() );

		return wp_parse_args( is_array( $options ) ? $options : array(), self::defaults() );
	}

	/**
	 * Sanitize Settings API input.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, string>
	 */
	public static function sanitize_options( $input ) {
		$input = is_array( $input ) ? $input : array();
		$clean = self::defaults();

		$clean['dadata_enabled']   = empty( $input['dadata_enabled'] ) ? '0' : '1';
		$clean['tracking_enabled'] = empty( $input['tracking_enabled'] ) ? '0' : '1';

		$text_keys = array(
			'dadata_token',
			'inn_selector',
			'name_selector',
			'kpp_selector',
			'ogrn_selector',
			'address_selector',
		);

		foreach ( $text_keys as $key ) {
			$value = isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ? $input[ $key ] : '';
			$clean[ $key ] = sanitize_text_field( (string) wp_unslash( $value ) );
		}

		return $clean;
	}

	/**
	 * Register admin settings.
	 */
	public static function register_settings() {
		register_setting(
			'dadata_suggestions',
			DADATA_SUGG_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_options' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Add settings page.
	 */
	public static function admin_menu() {
		add_options_page(
			__( 'DaData INN Suggestions', 'dadata-suggestions' ),
			__( 'DaData INN', 'dadata-suggestions' ),
			'manage_options',
			'dadata-suggestions',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Settings page markup.
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options = self::options();
		$name    = DADATA_SUGG_OPTION;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'DaData INN Suggestions', 'dadata-suggestions' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'dadata_suggestions' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'DaData INN suggestions', 'dadata-suggestions' ); ?></th>
						<td>
							<label>
								<input
									type="checkbox"
									name="<?php echo esc_attr( $name ); ?>[dadata_enabled]"
									value="1"
									<?php checked( $options['dadata_enabled'], '1' ); ?>
								/>
								<?php esc_html_e( 'Enable company lookup by INN', 'dadata-suggestions' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="dadata-token">
								<?php esc_html_e( 'DaData API token', 'dadata-suggestions' ); ?>
							</label>
						</th>
						<td>
							<input
								id="dadata-token"
								class="regular-text"
								type="password"
								autocomplete="off"
								name="<?php echo esc_attr( $name ); ?>[dadata_token]"
								value="<?php echo esc_attr( $options['dadata_token'] ); ?>"
							/>
							<p class="description">
								<?php esc_html_e( 'Used only by the server-side REST proxy.', 'dadata-suggestions' ); ?>
							</p>
						</td>
					</tr>
					<?php
					self::text_row(
						'inn_selector',
						__( 'INN/company field selector', 'dadata-suggestions' ),
						$options['inn_selector']
					);
					self::text_row(
						'name_selector',
						__( 'Company name field selector', 'dadata-suggestions' ),
						$options['name_selector']
					);
					self::text_row(
						'kpp_selector',
						__( 'KPP field selector', 'dadata-suggestions' ),
						$options['kpp_selector']
					);
					self::text_row(
						'ogrn_selector',
						__( 'OGRN field selector', 'dadata-suggestions' ),
						$options['ogrn_selector']
					);
					self::text_row(
						'address_selector',
						__( 'Address field selector', 'dadata-suggestions' ),
						$options['address_selector']
					);
					?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Link tracking', 'dadata-suggestions' ); ?></th>
						<td>
							<label>
								<input
									type="checkbox"
									name="<?php echo esc_attr( $name ); ?>[tracking_enabled]"
									value="1"
									<?php checked( $options['tracking_enabled'], '1' ); ?>
								/>
								<?php esc_html_e( 'Forward UTM and pageref to external links', 'dadata-suggestions' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render a selector row.
	 *
	 * @param string $key   Option key.
	 * @param string $label Row label.
	 * @param string $value Current value.
	 */
	private static function text_row( $key, $label, $value ) {
		$name = DADATA_SUGG_OPTION;
		?>
		<tr>
			<th scope="row">
				<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
			</th>
			<td>
				<input
					id="<?php echo esc_attr( $key ); ?>"
					class="regular-text"
					type="text"
					name="<?php echo esc_attr( $name ); ?>[<?php echo esc_attr( $key ); ?>]"
					value="<?php echo esc_attr( $value ); ?>"
					placeholder="#company"
				/>
			</td>
		</tr>
		<?php
	}

	/**
	 * Register REST endpoints.
	 */
	public static function register_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/party',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'rest_party' ),
				'permission_callback' => array( __CLASS__, 'rest_permission' ),
				'args'                => array(
					'query' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Verify REST nonce for public frontend requests.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function rest_permission( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		return $nonce && wp_verify_nonce( $nonce, 'wp_rest' );
	}

	/**
	 * Proxy an INN/company query to DaData.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_party( WP_REST_Request $request ) {
		$options = self::options();

		if ( '1' !== $options['dadata_enabled'] || '' === trim( $options['dadata_token'] ) ) {
			return new WP_Error(
				'dadata_disabled',
				__( 'DaData suggestions are disabled.', 'dadata-suggestions' ),
				array( 'status' => 403 )
			);
		}

		$query = trim( (string) $request->get_param( 'query' ) );
		$query = preg_replace( '/[^\p{L}\p{N}\s"\'.,\-]/u', '', $query );

		if ( null === $query || strlen( $query ) < 3 ) {
			return rest_ensure_response( array( 'suggestions' => array() ) );
		}

		$response = wp_remote_post(
			DADATA_SUGG_ENDPOINT,
			array(
				'headers' => array(
					'Accept'        => 'application/json',
					'Authorization' => 'Token ' . trim( $options['dadata_token'] ),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'query' => $query,
						'count' => 5,
					)
				),
				'timeout' => 8,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'dadata_request_failed',
				__( 'DaData API is unavailable.', 'dadata-suggestions' ),
				array( 'status' => 502 )
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error(
				'dadata_bad_response',
				__( 'DaData API returned an error.', 'dadata-suggestions' ),
				array( 'status' => 502 )
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if (
			! is_array( $body )
			|| ! isset( $body['suggestions'] )
			|| ! is_array( $body['suggestions'] )
		) {
			return rest_ensure_response( array( 'suggestions' => array() ) );
		}

		return rest_ensure_response(
			array(
				'suggestions' => array_map(
					array( __CLASS__, 'map_suggestion' ),
					array_slice( $body['suggestions'], 0, 5 )
				),
			)
		);
	}

	/**
	 * Keep only fields the frontend needs.
	 *
	 * @param array<string, mixed> $suggestion Raw DaData suggestion.
	 * @return array<string, string>
	 */
	private static function map_suggestion( $suggestion ) {
		$data = isset( $suggestion['data'] ) && is_array( $suggestion['data'] )
			? $suggestion['data']
			: array();
		$name = '';

		if ( isset( $data['name']['short_with_opf'] ) ) {
			$name = $data['name']['short_with_opf'];
		} elseif ( isset( $data['name']['full_with_opf'] ) ) {
			$name = $data['name']['full_with_opf'];
		}

		return array(
			'value'   => sanitize_text_field( isset( $suggestion['value'] ) ? $suggestion['value'] : '' ),
			'name'    => sanitize_text_field( $name ),
			'inn'     => sanitize_text_field( isset( $data['inn'] ) ? $data['inn'] : '' ),
			'kpp'     => sanitize_text_field( isset( $data['kpp'] ) ? $data['kpp'] : '' ),
			'ogrn'    => sanitize_text_field( isset( $data['ogrn'] ) ? $data['ogrn'] : '' ),
			'address' => sanitize_text_field(
				isset( $data['address']['value'] ) ? $data['address']['value'] : ''
			),
		);
	}

	/**
	 * Enqueue frontend scripts only when needed.
	 */
	public static function enqueue_frontend() {
		if ( is_admin() || is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$options = self::options();

		if ( '1' === $options['tracking_enabled'] ) {
			wp_enqueue_script(
				'dadata-suggestions-tracking',
				plugins_url( 'assets/tracking.js', __FILE__ ),
				array(),
				DADATA_SUGG_VERSION,
				true
			);
		}

		if (
			'1' !== $options['dadata_enabled']
			|| '' === trim( $options['dadata_token'] )
			|| '' === trim( $options['inn_selector'] )
		) {
			return;
		}

		wp_enqueue_script(
			'dadata-suggestions-inn',
			plugins_url( 'assets/dadata-inn.js', __FILE__ ),
			array(),
			DADATA_SUGG_VERSION,
			true
		);

		wp_localize_script(
			'dadata-suggestions-inn',
			'DadataSuggestions',
			array(
				'restUrl'  => esc_url_raw( rest_url( self::REST_NAMESPACE . '/party' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'selectors' => array(
					'inn'     => $options['inn_selector'],
					'name'    => $options['name_selector'],
					'kpp'     => $options['kpp_selector'],
					'ogrn'    => $options['ogrn_selector'],
					'address' => $options['address_selector'],
				),
			)
		);
	}

	/**
	 * Plugin row settings link.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'options-general.php?page=dadata-suggestions' ) ),
				esc_html__( 'Settings', 'dadata-suggestions' )
			)
		);

		return $links;
	}
}

Dadata_Suggestions_Plugin::init();
