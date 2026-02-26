<?php
/**
 * Existing Settings Page Handler
 *
 * Handles adding fields to existing WordPress settings pages.
 *
 * @package Pedalcms\CassetteCmf
 * @since 1.0.0
 */

namespace Pedalcms\CassetteCmf\Core\Handlers;

use Pedalcms\CassetteCmf\Field\Field_Interface;
use Pedalcms\CassetteCmf\Field\Container_Field_Interface;
use Pedalcms\CassetteCmf\Field\Field_Factory;
use Pedalcms\CassetteCmf\Field\Fields\Group_Field;
use Pedalcms\CassetteCmf\Field\Fields\Metabox_Field;

/**
 * Class Existing_Settings_Page_Handler
 *
 * Manages field registration for existing WordPress settings pages
 * like 'general', 'reading', 'writing', 'discussion', 'media', 'permalink'.
 */
class Existing_Settings_Page_Handler extends Abstract_Handler {

	/**
	 * Known WordPress settings pages and their option groups
	 *
	 * @var array<string, string>
	 */
	private const WORDPRESS_PAGES = [
		'general'    => 'general',
		'writing'    => 'writing',
		'reading'    => 'reading',
		'discussion' => 'discussion',
		'media'      => 'media',
		'permalink'  => 'permalink',
		'privacy'    => 'privacy',
	];

	/**
	 * Initialize WordPress hooks
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		if ( $this->hooks_initialized || ! $this->has_wordpress() ) {
			return;
		}

		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		$this->hooks_initialized = true;
	}

	/**
	 * Check if a page ID is a known WordPress settings page
	 *
	 * @param string $page_id Page identifier.
	 * @return bool
	 */
	public function is_wordpress_page( string $page_id ): bool {
		return isset( self::WORDPRESS_PAGES[ $page_id ] );
	}

	/**
	 * Get the option group for a WordPress settings page
	 *
	 * @param string $page_id Page identifier.
	 * @return string
	 */
	public function get_option_group( string $page_id ): string {
		return self::WORDPRESS_PAGES[ $page_id ] ?? $page_id;
	}

	/**
	 * Register settings with WordPress Settings API
	 *
	 * @return void
	 */
	public function register_settings(): void {
		if ( ! function_exists( 'register_setting' ) ) {
			return;
		}

		foreach ( $this->fields as $page_id => $fields ) {
			if ( empty( $fields ) ) {
				continue;
			}

			$this->register_page_fields( $page_id );
		}
	}

	/**
	 * Register fields for a settings page
	 *
	 * @param string $page_id Page identifier.
	 * @return void
	 */
	private function register_page_fields( string $page_id ): void {
		$option_group = $this->get_option_group( $page_id );

		foreach ( $this->get_fields( $page_id ) as $field ) {
			if ( ! $field instanceof Field_Interface ) {
				continue;
			}

			$field_name = $field->get_name();

			// Skip nested fields
			if ( $this->is_nested_field( $page_id, $field_name ) ) {
				continue;
			}

			// Handle Group fields as sections
			if ( $field instanceof Group_Field ) {
				$this->register_group_section( $page_id, $option_group, $field );
				continue;
			}

			// Skip metabox fields on existing pages
			if ( $field instanceof Metabox_Field ) {
				continue;
			}

			// Skip container fields (they don't store data)
			if ( $field instanceof Container_Field_Interface ) {
				continue;
			}

			// Register regular field
			$this->register_single_field( $page_id, $option_group, $field );
		}
	}

	/**
	 * Register a single field
	 *
	 * @param string          $page_id      Page identifier.
	 * @param string          $option_group Option group.
	 * @param Field_Interface $field        Field instance.
	 * @return void
	 */
	private function register_single_field( string $page_id, string $option_group, Field_Interface $field ): void {
		$option_name = $field->get_option_name( $page_id );
		$field_name  = $field->get_name();

		// Register setting with WordPress
		register_setting(
			$option_group,
			$option_name,
			[
				'type'              => 'string',
				'sanitize_callback' => [ $field, 'sanitize' ],
				'show_in_rest'      => false,
			]
		);

		// Explicitly add to allowed options (required for existing pages)
		$this->add_to_allowed_options( $page_id, $option_name );

		// Add before-save filter
		add_filter(
			'pre_update_option_' . $option_name,
			function ( $new_value ) use ( $page_id, $field_name ) {
				return $this->apply_before_save_filters( $new_value, $field_name, $page_id );
			},
			10,
			1
		);

		// Add settings field
		add_settings_field(
			$field_name,
			$field->get_label(),
			[ $this, 'render_field' ],
			$page_id,
			'default',
			[
				'field'       => $field,
				'option_name' => $option_name,
				'page_id'     => $page_id,
			]
		);
	}

	/**
	 * Register a Group field as a section with nested fields
	 *
	 * @param string          $page_id      Page identifier.
	 * @param string          $option_group Option group.
	 * @param Field_Interface $field        Group field instance.
	 * @return void
	 */
	private function register_group_section( string $page_id, string $option_group, Field_Interface $field ): void {
		$section_id = $page_id . '_' . $field->get_name();

		// Add section
		add_settings_section(
			$section_id,
			$field->get_label(),
			function () use ( $field ) {
				$description = $field->get_config( 'description', '' );
				if ( ! empty( $description ) ) {
					echo '<p class="description">' . esc_html( $description ) . '</p>';
				}
			},
			$page_id
		);

		// Register nested fields
		if ( ! $field instanceof Container_Field_Interface ) {
			return;
		}

		foreach ( $field->get_nested_fields() as $nested_config ) {
			if ( empty( $nested_config['name'] ) ) {
				continue;
			}

			try {
				$nested_field  = Field_Factory::create( $nested_config );
				$nested_name   = $nested_field->get_name();
				$nested_option = $nested_field->get_option_name( $page_id );

				// Register the setting
				register_setting(
					$option_group,
					$nested_option,
					[
						'type'              => 'string',
						'sanitize_callback' => [ $nested_field, 'sanitize' ],
						'show_in_rest'      => false,
					]
				);

				// Add to allowed options
				$this->add_to_allowed_options( $page_id, $nested_option );

				// Add before-save filter
				add_filter(
					'pre_update_option_' . $nested_option,
					function ( $new_value ) use ( $page_id, $nested_name ) {
						return $this->apply_before_save_filters( $new_value, $nested_name, $page_id );
					},
					10,
					1
				);

				// Add field to section
				add_settings_field(
					$nested_name,
					$nested_field->get_label(),
					[ $this, 'render_field' ],
					$page_id,
					$section_id,
					[
						'field'       => $nested_field,
						'option_name' => $nested_option,
						'page_id'     => $page_id,
					]
				);
			} catch ( \InvalidArgumentException $e ) {
				continue;
			}
		}
	}

	/**
	 * Add option to WordPress allowed options
	 *
	 * This is required for existing settings pages to accept new options.
	 *
	 * @param string $page_id     Page identifier.
	 * @param string $option_name Option name to allow.
	 * @return void
	 */
	private function add_to_allowed_options( string $page_id, string $option_name ): void {
		add_filter(
			'allowed_options',
			function ( $allowed_options ) use ( $page_id, $option_name ) {
				if ( ! isset( $allowed_options[ $page_id ] ) ) {
					$allowed_options[ $page_id ] = [];
				}

				if ( ! in_array( $option_name, $allowed_options[ $page_id ], true ) ) {
					$allowed_options[ $page_id ][] = $option_name;
				}

				return $allowed_options;
			}
		);
	}

	/**
	 * Render a settings field
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_field( array $args ): void {
		if ( empty( $args['field'] ) || ! $args['field'] instanceof Field_Interface ) {
			return;
		}

		$field       = $args['field'];
		$option_name = $args['option_name'] ?? $field->get_name();
		$page_id     = $args['page_id'] ?? '';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->render_settings_field_html( $field, $option_name, $page_id );
	}

	/**
	 * Enqueue assets for existing settings pages
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		// Check if we're on an existing settings page with our fields
		foreach ( $this->fields as $page_id => $fields ) {
			// Match screen for options-{page_id}.php pages
			$expected_screen = 'options-' . $page_id;

			if ( $screen->id === $expected_screen || $screen->base === $expected_screen ) {
				$this->enqueue_field_assets( $page_id );
				$this->enqueue_common_assets();
				break;
			}
		}
	}

	/**
	 * Enqueue assets for fields
	 *
	 * @param string $page_id Page identifier.
	 * @return void
	 */
	private function enqueue_field_assets( string $page_id ): void {
		foreach ( $this->get_fields( $page_id ) as $field ) {
			if ( method_exists( $field, 'enqueue_assets' ) ) {
				$field->enqueue_assets();
			}
		}
	}

	/**
	 * Enqueue common Cassette-CMF assets
	 *
	 * @return void
	 */
	private function enqueue_common_assets(): void {
		if ( ! function_exists( 'wp_enqueue_style' ) ) {
			return;
		}

		$url     = $this->get_assets_url();
		$version = $this->get_version();

		wp_enqueue_style( 'cassette-cmf', $url . 'css/cassette-cmf.css', [], $version );
		wp_enqueue_script( 'cassette-cmf', $url . 'js/cassette-cmf.js', [ 'jquery', 'wp-color-picker' ], $version, true );
		wp_enqueue_style( 'wp-color-picker' );

		do_action( 'cassette_cmf_enqueue_common_assets' );
	}
}
