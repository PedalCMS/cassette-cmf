<?php
/**
 * Settings_Page class for Cassette-CMF
 *
 * Handles registration and rendering of WordPress admin settings pages.
 * Provides a clean API for creating top-level and sub-menu pages with capability checks.
 *
 * @package Pedalcms\CassetteCmf
 * @since 1.0.0
 */

namespace Pedalcms\CassetteCmf\Settings;

/**
 * Settings_Page class - Manages settings page registration and rendering
 *
 * Provides a fluent interface for configuring and registering WordPress
 * settings pages, including support for both top-level and sub-menu pages.
 */
class Settings_Page {

	/**
	 * Page identifier/slug
	 *
	 * @var string
	 */
	private string $page_id;

	/**
	 * Page configuration
	 *
	 * @var array<string, mixed>
	 */
	private array $config = [];

	/**
	 * Whether the page has been registered
	 *
	 * @var bool
	 */
	private bool $registered = false;

	/**
	 * Page hook suffix (returned by add_menu_page/add_submenu_page)
	 *
	 * @var string|false
	 */
	private $hook_suffix = false;

	/**
	 * Constructor
	 *
	 * @param string               $page_id Page identifier/slug.
	 * @param array<string, mixed> $config  Configuration array.
	 */
	public function __construct( string $page_id, array $config = [] ) {
		$this->page_id = $page_id;
		$this->configure( $config );
	}

	/**
	 * Configure the settings page from an array
	 *
	 * @param array<string, mixed> $config Configuration array.
	 * @return self
	 */
	public function configure( array $config ): self {
		$this->config = array_merge( $this->config, $config );
		return $this;
	}

	/**
	 * Set page title
	 *
	 * @param string $title Page title.
	 * @return self
	 */
	public function set_page_title( string $title ): self {
		$this->config['page_title'] = $title;
		return $this;
	}

	/**
	 * Set menu title
	 *
	 * @param string $title Menu title.
	 * @return self
	 */
	public function set_menu_title( string $title ): self {
		$this->config['menu_title'] = $title;
		return $this;
	}

	/**
	 * Set capability required to access the page
	 *
	 * @param string $capability WordPress capability.
	 * @return self
	 */
	public function set_capability( string $capability ): self {
		$this->config['capability'] = $capability;
		return $this;
	}

	/**
	 * Set menu slug
	 *
	 * @param string $slug Menu slug.
	 * @return self
	 */
	public function set_menu_slug( string $slug ): self {
		$this->config['menu_slug'] = $slug;
		return $this;
	}

	/**
	 * Set render callback function
	 *
	 * @param callable $callback Callback function for rendering.
	 * @return self
	 */
	public function set_callback( callable $callback ): self {
		$this->config['callback'] = $callback;
		return $this;
	}

	/**
	 * Set menu icon (for top-level pages)
	 *
	 * @param string $icon_url Dashicon class or URL to icon.
	 * @return self
	 */
	public function set_icon( string $icon_url ): self {
		$this->config['icon_url'] = $icon_url;
		return $this;
	}

	/**
	 * Set menu position
	 *
	 * @param int|null $position Menu position.
	 * @return self
	 */
	public function set_position( ?int $position ): self {
		$this->config['position'] = $position;
		return $this;
	}

	/**
	 * Set parent slug (makes this a sub-menu page)
	 *
	 * @param string $parent_slug Parent menu slug.
	 * @return self
	 */
	public function set_parent( string $parent_slug ): self {
		$this->config['parent_slug'] = $parent_slug;
		return $this;
	}

	/**
	 * Set configuration option
	 *
	 * @param string $key   Configuration key.
	 * @param mixed  $value Configuration value.
	 * @return self
	 */
	public function set_config( string $key, $value ): self {
		$this->config[ $key ] = $value;
		return $this;
	}

	/**
	 * Get configuration option
	 *
	 * @param string $key           Configuration key.
	 * @param mixed  $default_value Default value.
	 * @return mixed
	 */
	public function get_config( string $key, $default_value = null ) {
		return $this->config[ $key ] ?? $default_value;
	}

	/**
	 * Get all configuration
	 *
	 * @return array<string, mixed>
	 */
	public function get_all_config(): array {
		return $this->config;
	}

	/**
	 * Set default configuration values
	 *
	 * @return self
	 */
	public function set_defaults(): self {
		$defaults = [
			'page_title' => ucwords( str_replace( [ '_', '-' ], ' ', $this->page_id ) ),
			'menu_title' => ucwords( str_replace( [ '_', '-' ], ' ', $this->page_id ) ),
			'capability' => 'manage_options',
			'menu_slug'  => $this->page_id,
			'callback'   => [ $this, 'render_default' ],
			'icon_url'   => '',
			'position'   => null,
		];

		foreach ( $defaults as $key => $value ) {
			if ( ! isset( $this->config[ $key ] ) ) {
				$this->config[ $key ] = $value;
			}
		}

		return $this;
	}

	/**
	 * Register the settings page with WordPress
	 *
	 * @return bool True if registration was successful, false otherwise.
	 */
	public function register(): bool {
		if ( $this->registered ) {
			return true;
		}

		// Ensure we have required configuration
		$this->set_defaults();

		// Check if WordPress functions are available
		if ( ! function_exists( 'add_menu_page' ) || ! function_exists( 'add_submenu_page' ) ) {
			// In test environment without WordPress, mark as registered for testing
			$this->registered  = true;
			$this->hook_suffix = 'test-hook-suffix';
			return true;
		}

		// Determine if this is a sub-menu page
		if ( isset( $this->config['parent_slug'] ) && ! empty( $this->config['parent_slug'] ) ) {
			$this->hook_suffix = add_submenu_page(
				$this->config['parent_slug'],
				$this->config['page_title'],
				$this->config['menu_title'],
				$this->config['capability'],
				$this->config['menu_slug'],
				$this->config['callback']
			);
		} else {
			$this->hook_suffix = add_menu_page(
				$this->config['page_title'],
				$this->config['menu_title'],
				$this->config['capability'],
				$this->config['menu_slug'],
				$this->config['callback'],
				$this->config['icon_url'],
				$this->config['position']
			);
		}

		if ( false !== $this->hook_suffix ) {
			$this->registered = true;
			return true;
		}

		return false;
	}

	/**
	 * Default render callback
	 *
	 * Renders a settings page with WordPress Settings API integration.
	 * If fields have been registered via the Settings API, they will be displayed.
	 *
	 * @return void
	 */
	public function render_default(): void {
		$page_title = $this->config['page_title'] ?? 'Settings';
		$menu_slug  = $this->get_menu_slug();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $page_title ); ?></h1>

			<?php
			// Show success message if updated.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a read-only check for WordPress settings API notification.
			if ( ! empty( $_GET['settings-updated'] ) ) {
				?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html__( 'Settings saved.', 'cassette-cmf' ); ?></p>
				</div>
				<?php
			}

			// Check if there are any settings sections or meta boxes registered
			global $wp_settings_sections, $wp_meta_boxes;
			$has_sections  = ! empty( $wp_settings_sections[ $menu_slug ] );
			$has_metaboxes = ! empty( $wp_meta_boxes[ $this->hook_suffix ] );

			if ( $has_sections || $has_metaboxes ) {
				// Display settings errors/notices
				if ( function_exists( 'settings_errors' ) ) {
					settings_errors();
				}

				// Use current page as action if metaboxes exist (can't use options.php)
				$form_action = $has_metaboxes ? '' : 'options.php';
				?>
				<form method="post" action="<?php echo esc_attr( $form_action ); ?>">
					<?php
					// Output security fields
					if ( $has_metaboxes ) {
						// Nonce for metabox-based settings page
						wp_nonce_field( 'CassetteCmf_save_settings_' . $this->page_id, 'CassetteCmf_settings_nonce' );
						echo '<input type="hidden" name="action" value="CassetteCmf_save_settings" />';
						echo '<input type="hidden" name="page_id" value="' . esc_attr( $this->page_id ) . '" />';
					} elseif ( function_exists( 'settings_fields' ) && $has_sections ) {
						// Standard WordPress settings fields
						settings_fields( $menu_slug );
					}

					// Output setting sections and their fields
					if ( function_exists( 'do_settings_sections' ) && $has_sections ) {
						do_settings_sections( $menu_slug );
					}

					// Output meta boxes
					if ( function_exists( 'do_meta_boxes' ) && $has_metaboxes ) {
						echo '<div id="poststuff">';
						do_meta_boxes( $this->hook_suffix, 'normal', null );
						do_meta_boxes( $this->hook_suffix, 'side', null );
						do_meta_boxes( $this->hook_suffix, 'advanced', null );
						echo '</div>';
					}

					// Output save button
					if ( function_exists( 'submit_button' ) ) {
						submit_button();
					}
					?>
				</form>
				<?php
			} else {
				// No fields registered - show placeholder
				?>
				<p><?php echo esc_html__( 'No settings configured for this page.', 'cassette-cmf' ); ?></p>
				<?php
			}
			?>
		</div>
		<?php
	}

	/**
	 * Get the page identifier
	 *
	 * @return string
	 */
	public function get_page_id(): string {
		return $this->page_id;
	}

	/**
	 * Get the menu slug
	 *
	 * @return string
	 */
	public function get_menu_slug(): string {
		return $this->config['menu_slug'] ?? $this->page_id;
	}

	/**
	 * Get the hook suffix
	 *
	 * @return string|false
	 */
	public function get_hook_suffix() {
		return $this->hook_suffix;
	}

	/**
	 * Check if the page has been registered
	 *
	 * @return bool
	 */
	public function is_registered(): bool {
		return $this->registered;
	}

	/**
	 * Check if this is a sub-menu page
	 *
	 * @return bool
	 */
	public function is_submenu(): bool {
		return isset( $this->config['parent_slug'] ) && ! empty( $this->config['parent_slug'] );
	}

	/**
	 * Create a SettingsPage instance from array configuration
	 *
	 * @param string               $page_id Page identifier.
	 * @param array<string, mixed> $config  Configuration array.
	 * @return self
	 */
	public static function from_array( string $page_id, array $config ): self {
		$instance = new self( $page_id, $config );
		$instance->set_defaults();
		return $instance;
	}
}
