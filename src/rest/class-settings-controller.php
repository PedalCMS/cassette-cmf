<?php
/**
 * Settings_Controller - REST route for opted-in settings pages
 *
 * Registers `{namespace}/settings/{page_id}` for every settings page that sets
 * `show_in_rest => true`. See docs/rest-api.md.
 *
 * @package PedalCMS\CassetteCMF
 * @since 0.2.0
 */

namespace PedalCMS\CassetteCMF\Rest;

use PedalCMS\CassetteCMF\Core\Handlers\New_Settings_Page_Handler;
use PedalCMS\CassetteCMF\Field\Field_Interface;
use PedalCMS\CassetteCMF\Field\Fields\Password_Field;
use PedalCMS\CassetteCMF\Field\Fields\Secret_Field;
use PedalCMS\CassetteCMF\Settings\Settings_Page;
use PedalCMS\CassetteCMF\Settings\Settings_Upgrader;

/**
 * Class Settings_Controller
 *
 * One route per opted-in page, so each page serves its own schema on OPTIONS
 * and an unknown or not-opted-in page is simply a 404 (`rest_no_route`).
 *
 * Writes run the same pipeline as the classic form: the
 * `cassette_cmf_before_save_field` filters, the field's `sanitize()`, the
 * field's `validate()` (skipped when the field's conditional rules are not met),
 * then `update_option()`. If any submitted field is invalid nothing is written.
 */
class Settings_Controller extends \WP_REST_Controller {

	/**
	 * Settings page handler.
	 *
	 * @var New_Settings_Page_Handler
	 */
	private New_Settings_Page_Handler $handler;

	/**
	 * Constructor
	 *
	 * @param New_Settings_Page_Handler $handler   Handler owning the pages.
	 * @param string                    $rest_namespace REST namespace (the `rest_namespace` boot option).
	 */
	public function __construct( New_Settings_Page_Handler $handler, string $rest_namespace ) {
		$this->handler   = $handler;
		$this->namespace = $rest_namespace;
		$this->rest_base = 'settings';
	}

	/**
	 * Register the route of every opted-in page.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		foreach ( $this->handler->get_pages() as $page_id => $page ) {
			if ( ! $page->is_rest_enabled() ) {
				continue;
			}

			$page_id = (string) $page_id;

			register_rest_route(
				$this->namespace,
				'/' . $this->rest_base . '/(?P<page>' . preg_quote( $page_id, '@' ) . ')',
				[
					[
						'methods'             => \WP_REST_Server::READABLE,
						'callback'            => [ $this, 'get_item' ],
						'permission_callback' => [ $this, 'permissions_check' ],
					],
					[
						'methods'             => \WP_REST_Server::EDITABLE,
						'callback'            => [ $this, 'update_item' ],
						'permission_callback' => [ $this, 'permissions_check' ],
						'args'                => [
							'values' => [
								'description' => 'Field values keyed by field name (partial update).',
								'type'        => 'object',
								'required'    => true,
							],
						],
					],
					'schema' => function () use ( $page_id, $page ) {
						return $this->schema_for( $page_id, $page );
					},
				]
			);
		}
	}

	/**
	 * Permission check: the page's capability (default manage_options).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public function permissions_check( $request ) {
		$page = $this->handler->get_page( (string) $request['page'] );

		if ( null === $page || ! $page->is_rest_enabled() ) {
			return new \WP_Error( 'rest_no_route', __( 'No route was found matching the URL and request method.', 'cassette-cmf' ), [ 'status' => 404 ] );
		}

		if ( current_user_can( $this->capability( $page ) ) ) {
			return true;
		}

		return new \WP_Error(
			'rest_forbidden',
			__( 'Sorry, you are not allowed to manage these settings.', 'cassette-cmf' ),
			[ 'status' => rest_authorization_required_code() ]
		);
	}

	/**
	 * GET: current values.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_item( $request ) {
		$page_id = (string) $request['page'];

		$ready = $this->ensure_upgraded( $page_id );
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}

		return rest_ensure_response( [ 'values' => $this->read_values( $page_id, $this->fields( $page_id ) ) ] );
	}

	/**
	 * Current values of a page in REST shape
	 *
	 * What `GET` returns under `values`, without the HTTP layer. Used to seed the
	 * React settings app. Applies any pending schema upgrade first.
	 *
	 * @since 0.2.0
	 *
	 * @param string $page_id Page ID.
	 * @return array<string, mixed>
	 */
	public function get_values( string $page_id ): array {
		$this->ensure_upgraded( $page_id );

		return $this->read_values( $page_id, $this->fields( $page_id ) );
	}

	/**
	 * POST / PUT / PATCH: partial update, all or nothing.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ) {
		$page_id = (string) $request['page'];

		$ready = $this->ensure_upgraded( $page_id );
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}

		$input = $request->get_param( 'values' );

		if ( ! is_array( $input ) || ( ! empty( $input ) && array_keys( $input ) === range( 0, count( $input ) - 1 ) ) ) {
			return new \WP_Error(
				'rest_invalid_param',
				__( 'Invalid parameter(s): values', 'cassette-cmf' ),
				[
					'status' => 400,
					'params' => [ 'values' => __( 'values must be an object.', 'cassette-cmf' ) ],
				]
			);
		}

		$fields   = $this->fields( $page_id );
		$errors   = [];
		$prepared = [];

		foreach ( $input as $name => $raw ) {
			$name = (string) $name;

			if ( ! isset( $fields[ $name ] ) || ! Settings_Schema::is_writable( $fields[ $name ] ) ) {
				$errors[ $name ] = [ __( 'This field does not exist or is not writable over REST.', 'cassette-cmf' ) ];
				continue;
			}

			$converted = Settings_Schema::from_rest_value( $fields[ $name ], $raw );

			if ( null !== $converted['error'] ) {
				$errors[ $name ] = [ sprintf( '%s %s', $fields[ $name ]->get_label(), $converted['error'] ) ];
				continue;
			}

			if ( ! $converted['skip'] ) {
				$prepared[ $name ] = $converted['value'];
			}
		}

		$to_write = [];
		$context  = $this->validation_context( $page_id, $fields, $prepared );

		foreach ( $prepared as $name => $raw ) {
			$field = $fields[ $name ];
			$value = $this->handler->filter_value_before_save( $raw, $name, $page_id );

			// A filter returning null means "do not save", as in the classic path.
			if ( null === $value ) {
				continue;
			}

			$sanitized = $field->sanitize( $value );

			if ( ! method_exists( $field, 'should_validate' ) || $field->should_validate( $context ) ) {
				$result = $field->validate( $sanitized );

				if ( empty( $result['valid'] ) ) {
					$errors[ $name ] = array_map( 'strval', (array) ( $result['errors'] ?? [] ) );
					continue;
				}
			}

			$to_write[ $field->get_option_name( $page_id ) ] = $sanitized;
		}

		if ( ! empty( $errors ) ) {
			return $this->invalid_response( $errors );
		}

		foreach ( $to_write as $option_name => $value ) {
			update_option( $option_name, $value );
		}

		return rest_ensure_response( [ 'values' => $this->read_values( $page_id, $fields ) ] );
	}

	/**
	 * Build the schema served on OPTIONS.
	 *
	 * Only users who may use the route get the field list; everyone else gets
	 * an empty object schema.
	 *
	 * @param string        $page_id Page ID.
	 * @param Settings_Page $page    Page.
	 * @return array<string, mixed>
	 */
	public function schema_for( string $page_id, Settings_Page $page ): array {
		if ( ! current_user_can( $this->capability( $page ) ) ) {
			return [
				'$schema' => 'http://json-schema.org/draft-04/schema#',
				'title'   => $page_id,
				'type'    => 'object',
			];
		}

		return Settings_Schema::build( $this->fields( $page_id ), $page_id );
	}

	/**
	 * REST-visible fields of a page, bound to their options.
	 *
	 * @param string $page_id Page ID.
	 * @return array<string, Field_Interface>
	 */
	private function fields( string $page_id ): array {
		$fields = Settings_Schema::collect_fields( $this->handler->get_top_level_fields( $page_id ) );

		foreach ( $fields as $field ) {
			Secret_Field::bind_if_secret( $field, $field->get_option_name( $page_id ) );
		}

		return $fields;
	}

	/**
	 * Current values in REST shape (write-only fields are not returned).
	 *
	 * @param string                         $page_id Page ID.
	 * @param array<string, Field_Interface> $fields  Fields.
	 * @return array<string, mixed>
	 */
	private function read_values( string $page_id, array $fields ): array {
		$unset  = new \stdClass();
		$values = [];

		foreach ( $fields as $name => $field ) {
			if ( $field instanceof Password_Field ) {
				continue;
			}

			$stored = get_option( $field->get_option_name( $page_id ), $unset );

			if ( $unset === $stored ) {
				$stored = $field->get_config( 'default', '' );
			}

			$values[ $name ] = Settings_Schema::to_rest_value( $field, $stored );
		}

		return $values;
	}

	/**
	 * Values used to evaluate conditional rules: stored values overlaid with the submitted ones.
	 *
	 * @param string                         $page_id  Page ID.
	 * @param array<string, Field_Interface> $fields   Fields.
	 * @param array<string, mixed>           $prepared Submitted values (classic raw shape).
	 * @return array<string, mixed>
	 */
	private function validation_context( string $page_id, array $fields, array $prepared ): array {
		$context = [];

		foreach ( $fields as $name => $field ) {
			$context[ $name ] = get_option( $field->get_option_name( $page_id ), '' );
		}

		return array_merge( $context, $prepared );
	}

	/**
	 * Run the schema upgrade of a versioned page before touching its data.
	 *
	 * @param string $page_id Page ID.
	 * @return true|\WP_Error
	 */
	private function ensure_upgraded( string $page_id ) {
		$status = $this->handler->get_upgrader()->maybe_upgrade( $page_id );

		if ( Settings_Upgrader::STATUS_LOCKED === $status ) {
			return new \WP_Error(
				'cassette_cmf_upgrade_in_progress',
				__( 'The settings are being upgraded. Try again in a moment.', 'cassette-cmf' ),
				[
					'status' => 503,
				]
			);
		}

		return true;
	}

	/**
	 * Build the 400 response with per-field errors.
	 *
	 * @param array<string, string[]> $errors Messages by field name.
	 * @return \WP_Error
	 */
	private function invalid_response( array $errors ): \WP_Error {
		$params  = [];
		$details = [];

		foreach ( $errors as $name => $messages ) {
			$message          = implode( ' ', $messages );
			$params[ $name ]  = $message;
			$details[ $name ] = [
				'code'    => 'rest_invalid_param',
				'message' => $message,
				'data'    => [ 'errors' => $messages ],
			];
		}

		return new \WP_Error(
			'rest_invalid_param',
			/* translators: %s: comma-separated field names */
			sprintf( __( 'Invalid parameter(s): %s', 'cassette-cmf' ), implode( ', ', array_keys( $errors ) ) ),
			[
				'status'  => 400,
				'params'  => $params,
				'details' => $details,
				'errors'  => $errors,
			]
		);
	}

	/**
	 * Capability required for a page.
	 *
	 * @param Settings_Page $page Page.
	 * @return string
	 */
	private function capability( Settings_Page $page ): string {
		$capability = $page->get_config( 'capability', 'manage_options' );

		return is_string( $capability ) && '' !== $capability ? $capability : 'manage_options';
	}
}
