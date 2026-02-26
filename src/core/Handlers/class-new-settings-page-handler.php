<?php
/**
 * New Settings Page Handler
 *
 * Handles registration and field management for new settings pages.
 *
 * @package Pedalcms\CassetteCmf
 * @since 1.0.0
 */

namespace Pedalcms\CassetteCmf\Core\Handlers;

use Pedalcms\CassetteCmf\Settings\Settings_Page;
use Pedalcms\CassetteCmf\Field\Field_Interface;
use Pedalcms\CassetteCmf\Field\Container_Field_Interface;
use Pedalcms\CassetteCmf\Field\Field_Factory;
use Pedalcms\CassetteCmf\Field\Fields\Group_Field;
use Pedalcms\CassetteCmf\Field\Fields\Metabox_Field;

/**
 * Class New_Settings_Page_Handler
 *
 * Manages creation and field registration for new settings pages.
 */
class New_Settings_Page_Handler extends Abstract_Handler {

	/**
	 * Registered settings pages
	 *
	 * @var array<string, Settings_Page>
	 */
	private array $settings_pages = [];

	/**
	 * Invalid container fields (for admin notice)
	 *
	 * @var array<string, array<string>>
	 */
	private array $invalid_containers = [];

	/**
	 * Initialize WordPress hooks
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		if ( $this->hooks_initialized || ! $this->has_wordpress() ) {
			return;
		}

		add_action( 'admin_menu', [ $this, 'register_pages' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		$this->hooks_initialized = true;
	}

	/**
	 * Add a settings page
	 *
	 * @param string $page_id Page identifier.
	 * @param array  $args    Page configuration.
	 * @return self
	 */
	public function add_page( string $page_id, array $args ): self {
		$this->settings_pages[ $page_id ] = Settings_Page::from_array( $page_id, $args );
		return $this;
	}

	/**
	 * Add a Settings_Page instance
	 *
	 * @param Settings_Page $page Settings_Page instance.
	 * @return self
	 */
	public function add_page_instance( Settings_Page $page ): self {
		$this->settings_pages[ $page->get_page_id() ] = $page;
		return $this;
	}

	/**
	 * Get a settings page
	 *
	 * @param string $page_id Page identifier.
	 * @return Settings_Page|null
	 */
	public function get_page( string $page_id ): ?Settings_Page {
		return $this->settings_pages[ $page_id ] ?? null;
	}

	/**
	 * Get all settings pages
	 *
	 * @return array<string, Settings_Page>
	 */
	public function get_pages(): array {
		return $this->settings_pages;
	}

	/**
	 * Check if a page is registered
	 *
	 * @param string $page_id Page identifier.
	 * @return bool
	 */
	public function has_page( string $page_id ): bool {
		return isset( $this->settings_pages[ $page_id ] );
	}

	/**
	 * Register admin pages with WordPress
	 *
	 * @return void
	 */
	public function register_pages(): void {
		foreach ( $this->settings_pages as $page ) {
			$page->register();

			$hook_suffix = $page->get_hook_suffix();
			if ( $hook_suffix ) {
				add_action( 'load-' . $hook_suffix, [ $this, 'on_page_load' ] );
			}
		}
	}

	/**
	 * Handle page load for meta boxes and saving
	 *
	 * @return void
	 */
	public function on_page_load(): void {
		$this->register_page_meta_boxes();
		$this->handle_save();
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

		foreach ( $this->settings_pages as $page_id => $page ) {
			if ( ! $this->has_fields( $page_id ) ) {
				continue;
			}

			$this->register_page_settings( $page_id, $page );
		}

		// Show admin notice for invalid containers
		if ( ! empty( $this->invalid_containers ) ) {
			add_action( 'admin_notices', [ $this, 'show_invalid_container_notice' ] );
		}
	}

	/**
	 * Register settings for a single page
	 *
	 * @param string        $page_id Page identifier.
	 * @param Settings_Page $page    Settings page instance.
	 * @return void
	 */
	private function register_page_settings( string $page_id, Settings_Page $page ): void {
		$menu_slug             = $page->get_menu_slug();
		$default_section_id    = $page_id . '_section';
		$default_section_added = false;

		foreach ( $this->get_fields( $page_id ) as $field ) {
			if ( ! $field instanceof Field_Interface ) {
				continue;
			}

			$field_name   = $field->get_name();
			$option_name  = $field->get_option_name( $page_id );
			$is_nested    = $this->is_nested_field( $page_id, $field_name );
			$is_container = $field instanceof Container_Field_Interface;
			$is_group     = $field instanceof Group_Field;
			$is_metabox   = $field instanceof Metabox_Field;

			// Validate container usage
			if ( ! $is_nested && $is_container && ! $is_group && ! $is_metabox ) {
				$this->track_invalid_container( $page_id, $field );
				continue;
			}

			// Register setting for non-container fields
			if ( ! $is_container ) {
				$this->register_single_setting( $page_id, $menu_slug, $field, $option_name );
			}

			// Skip nested fields (rendered by parent)
			if ( $is_nested ) {
				continue;
			}

			// Handle Group fields as sections
			if ( $is_group ) {
				$this->register_group_section( $page_id, $menu_slug, $field );
				continue;
			}

			// Metabox fields handled separately
			if ( $is_metabox ) {
				continue;
			}

			// Add default section if needed
			if ( ! $default_section_added ) {
				add_settings_section(
					$default_section_id,
					__( 'Settings', 'cassette-cmf' ),
					'__return_empty_string',
					$menu_slug
				);
				$default_section_added = true;
			}

			// Add field to default section
			add_settings_field(
				$field_name,
				$field->get_label(),
				[ $this, 'render_field' ],
				$menu_slug,
				$default_section_id,
				[
					'field'       => $field,
					'option_name' => $option_name,
					'page_id'     => $page_id,
				]
			);
		}
	}

	/**
	 * Register a single setting
	 *
	 * @param string          $page_id     Page identifier.
	 * @param string          $menu_slug   Menu slug.
	 * @param Field_Interface $field       Field instance.
	 * @param string          $option_name Option name.
	 * @return void
	 */
	private function register_single_setting(
		string $page_id,
		string $menu_slug,
		Field_Interface $field,
		string $option_name
	): void {
		register_setting(
			$menu_slug,
			$option_name,
			[ 'sanitize_callback' => [ $field, 'sanitize' ] ]
		);

		$field_name = $field->get_name();

		add_filter(
			'pre_update_option_' . $option_name,
			function ( $new_value ) use ( $page_id, $field_name ) {
				return $this->apply_before_save_filters( $new_value, $field_name, $page_id );
			},
			10,
			1
		);
	}

	/**
	 * Register a Group field as a WordPress Settings section
	 *
	 * @param string          $page_id   Page identifier.
	 * @param string          $menu_slug Menu slug.
	 * @param Field_Interface $field     Group field instance.
	 * @return void
	 */
	private function register_group_section( string $page_id, string $menu_slug, Field_Interface $field ): void {
		$section_id = $page_id . '_' . $field->get_name();

		add_settings_section(
			$section_id,
			$field->get_label(),
			function () use ( $field ) {
				$description = $field->get_config( 'description', '' );
				if ( ! empty( $description ) ) {
					echo '<p class="description">' . esc_html( $description ) . '</p>';
				}
			},
			$menu_slug
		);

		// Register nested fields in this section
		if ( ! $field instanceof Container_Field_Interface ) {
			return;
		}

		foreach ( $field->get_nested_fields() as $nested_config ) {
			if ( empty( $nested_config['name'] ) ) {
				continue;
			}

			try {
				$nested_field  = Field_Factory::create( $nested_config );
				$nested_option = $nested_field->get_option_name( $page_id );

				add_settings_field(
					$nested_field->get_name(),
					$nested_field->get_label(),
					[ $this, 'render_field' ],
					$menu_slug,
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
	 * Register meta boxes for the current settings page
	 *
	 * @return void
	 */
	private function register_page_meta_boxes(): void {
		if ( ! function_exists( 'add_meta_box' ) ) {
			return;
		}

		foreach ( $this->settings_pages as $page_id => $page ) {
			$hook_suffix = $page->get_hook_suffix();

			if ( ! $hook_suffix || ! $this->has_fields( $page_id ) ) {
				continue;
			}

			foreach ( $this->get_fields( $page_id ) as $field ) {
				if ( ! $field instanceof Metabox_Field ) {
					continue;
				}

				if ( $this->is_nested_field( $page_id, $field->get_name() ) ) {
					continue;
				}

				add_meta_box(
					$field->get_metabox_id(),
					$field->get_metabox_title(),
					[ $this, 'render_metabox' ],
					$hook_suffix,
					$field->get_context(),
					$field->get_priority(),
					[
						'metabox_field' => $field,
						'page_id'       => $page_id,
					]
				);
			}
		}
	}

	/**
	 * Render a metabox
	 *
	 * @param mixed $context Context object.
	 * @param array $args    Metabox arguments.
	 * @return void
	 */
	public function render_metabox( $context, array $args ): void {
		if ( ! isset( $args['args']['metabox_field'], $args['args']['page_id'] ) ) {
			return;
		}

		$field   = $args['args']['metabox_field'];
		$page_id = $args['args']['page_id'];

		if ( ! $field instanceof Metabox_Field ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $field->render( $page_id );
	}

	/**
	 * Handle form save
	 *
	 * @return void
	 */
	private function handle_save(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified below after identifying the correct form.
		if ( empty( $_POST['action'] ) || 'CassetteCmf_save_settings' !== $_POST['action'] ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified below.
		$page_id = isset( $_POST['page_id'] ) ? sanitize_text_field( wp_unslash( $_POST['page_id'] ) ) : '';

		if ( empty( $page_id ) || ! isset( $this->settings_pages[ $page_id ] ) ) {
			return;
		}

		// Verify nonce.
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce is being verified on the next line.
		$nonce = isset( $_POST['CassetteCmf_settings_nonce'] )
			? sanitize_text_field( wp_unslash( $_POST['CassetteCmf_settings_nonce'] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! $this->verify_nonce( $nonce, 'CassetteCmf_save_settings_' . $page_id ) ) {
			wp_die( esc_html__( 'Security check failed', 'cassette-cmf' ) );
		}

		// Check capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'cassette-cmf' ) );
		}

		// Save fields
		$this->save_page_fields( $page_id );

		// Redirect with success
		$this->redirect_after_save( $page_id, true );
	}

	/**
	 * Save fields for a page
	 *
	 * @param string $page_id Page identifier.
	 * @return void
	 */
	private function save_page_fields( string $page_id ): void {
		foreach ( $this->get_fields( $page_id ) as $field ) {
			if ( $field instanceof Container_Field_Interface ) {
				$this->process_container_fields(
					$field,
					$page_id,
					[ $this, 'save_single_field' ]
				);
			} else {
				$this->save_single_field( $field, $page_id );
			}
		}
	}

	/**
	 * Save a single field
	 *
	 * @param Field_Interface $field   Field instance.
	 * @param string          $page_id Page identifier.
	 * @return void
	 */
	public function save_single_field( Field_Interface $field, string $page_id ): void {
		$field_name  = $field->get_name();
		$option_name = $field->get_option_name( $page_id );
		$value       = $this->get_submitted_value( $option_name, $field_name );

		// Apply filters
		$value = $this->apply_before_save_filters( $value, $field_name, $page_id );
		if ( null === $value ) {
			return;
		}

		// Sanitize and validate
		$result = $this->sanitize_and_validate( $field, $value );

		if ( ! $result['valid'] ) {
			$this->add_field_error( $option_name, $field->get_label(), $result['errors'] );
			return;
		}

		update_option( $option_name, $result['value'] );
	}

	/**
	 * Redirect after save
	 *
	 * @param string $page_id Page identifier.
	 * @param bool   $success Whether save was successful.
	 * @return void
	 */
	private function redirect_after_save( string $page_id, bool $success ): void {
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		$url = add_query_arg(
			[
				'page'             => $page_id,
				'settings-updated' => $success ? 'true' : 'false',
			],
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Enqueue assets for settings pages
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

		// Check if we're on one of our settings pages
		foreach ( $this->settings_pages as $page_id => $page ) {
			$hook_suffix = $page->get_hook_suffix();

			if ( $screen->id === $hook_suffix && $this->has_fields( $page_id ) ) {
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

	/**
	 * Track invalid container field
	 *
	 * @param string          $page_id Page identifier.
	 * @param Field_Interface $field   Field instance.
	 * @return void
	 */
	private function track_invalid_container( string $page_id, Field_Interface $field ): void {
		if ( ! isset( $this->invalid_containers[ $page_id ] ) ) {
			$this->invalid_containers[ $page_id ] = [];
		}

		$this->invalid_containers[ $page_id ][] = sprintf(
			'%s (%s)',
			$field->get_label() ? $field->get_label() : $field->get_name(),
			$field->get_type()
		);
	}

	/**
	 * Show admin notice for invalid container fields
	 *
	 * @return void
	 */
	public function show_invalid_container_notice(): void {
		foreach ( $this->invalid_containers as $page_id => $fields ) {
			?>
			<div class="notice notice-error">
				<p><strong>Cassette-CMF Configuration Error:</strong>
				Container fields on settings page "<?php echo esc_html( $page_id ); ?>" must be wrapped in a metabox.</p>
				<ul style="list-style: disc; margin-left: 20px;">
					<?php foreach ( $fields as $field_info ) : ?>
						<li><?php echo esc_html( $field_info ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php
		}
	}
}
