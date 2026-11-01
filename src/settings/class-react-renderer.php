<?php
/**
 * React_Renderer - the `render_mode => 'react'` settings page
 *
 * Draws a settings page with a `@wordpress/components` app (built from
 * `src/assets/settings-app/` into `build/`) instead of the classic form. The
 * app reads and writes through the REST route (see docs/rest-api.md); the
 * classic renderer stays the default and is the fallback.
 *
 * @package PedalCMS\CassetteCMF
 * @since 0.2.0
 */

namespace PedalCMS\CassetteCMF\Settings;

use PedalCMS\CassetteCMF\Core\Handlers\New_Settings_Page_Handler;
use PedalCMS\CassetteCMF\Core\Manager;
use PedalCMS\CassetteCMF\Field\Abstract_Field;
use PedalCMS\CassetteCMF\Field\Container_Field_Interface;
use PedalCMS\CassetteCMF\Field\Field_Factory;
use PedalCMS\CassetteCMF\Field\Field_Interface;
use PedalCMS\CassetteCMF\Field\Fields\Custom_HTML_Field;
use PedalCMS\CassetteCMF\Field\Fields\Group_Field;
use PedalCMS\CassetteCMF\Field\Fields\Metabox_Field;
use PedalCMS\CassetteCMF\Field\Fields\Repeater_Field;
use PedalCMS\CassetteCMF\Field\Fields\Tabs_Field;
use PedalCMS\CassetteCMF\Rest\Settings_Controller;

/**
 * Class React_Renderer
 *
 * One instance per React page. `unavailable_reason()` is the runtime gate: when
 * the build is missing or WordPress is older than 6.7 (the library's declared
 * floor), the handler keeps the classic renderer and shows an admin notice.
 */
class React_Renderer {

	/**
	 * Oldest WordPress version the app is built for.
	 *
	 * @var string
	 */
	public const MIN_WP_VERSION = '6.7';

	/**
	 * Settings page handler.
	 *
	 * @var New_Settings_Page_Handler
	 */
	private New_Settings_Page_Handler $handler;

	/**
	 * Settings page.
	 *
	 * @var Settings_Page
	 */
	private Settings_Page $page;

	/**
	 * Labels of fields that are not drawn (`show_in_rest => false`).
	 *
	 * @var string[]
	 */
	private array $classic_only = [];

	/**
	 * Constructor
	 *
	 * @param New_Settings_Page_Handler $handler Handler owning the page.
	 * @param Settings_Page             $page    The page.
	 */
	public function __construct( New_Settings_Page_Handler $handler, Settings_Page $page ) {
		$this->handler = $handler;
		$this->page    = $page;
	}

	/**
	 * Directory holding the built app.
	 *
	 * @return string Path without trailing slash.
	 */
	public static function build_path(): string {
		$path = dirname( __DIR__, 2 ) . '/build';

		/**
		 * Filters the directory the React settings app is loaded from.
		 *
		 * @since 0.2.0
		 *
		 * @param string $path Directory without trailing slash.
		 */
		return untrailingslashit( (string) apply_filters( 'cassette_cmf_settings_app_build_path', $path ) );
	}

	/**
	 * URL of the build directory.
	 *
	 * @return string URL with trailing slash.
	 */
	public static function build_url(): string {
		$dir = str_replace( '\\', '/', self::build_path() ) . '/';
		$url = '';

		if ( defined( 'ABSPATH' ) && function_exists( 'site_url' ) ) {
			$url = str_replace( str_replace( '\\', '/', ABSPATH ), trailingslashit( site_url() ), $dir );
		} elseif ( defined( 'WP_CONTENT_DIR' ) && defined( 'WP_CONTENT_URL' ) ) {
			$url = str_replace( str_replace( '\\', '/', WP_CONTENT_DIR ), WP_CONTENT_URL, $dir );
		}

		/**
		 * Filters the URL the React settings app is loaded from.
		 *
		 * @since 0.2.0
		 *
		 * @param string $url URL with trailing slash.
		 */
		return trailingslashit( (string) apply_filters( 'cassette_cmf_settings_app_build_url', $url ) );
	}

	/**
	 * Why the React app cannot run on this site, or null when it can.
	 *
	 * @return string|null Human-readable reason.
	 */
	public static function unavailable_reason(): ?string {
		global $wp_version;

		if ( version_compare( (string) $wp_version, self::MIN_WP_VERSION, '<' ) ) {
			/* translators: 1: required WordPress version, 2: installed WordPress version */
			return sprintf( __( 'the React settings page needs WordPress %1$s or newer (this site runs %2$s)', 'cassette-cmf' ), self::MIN_WP_VERSION, (string) $wp_version );
		}

		$build = self::build_path();

		if ( ! is_readable( $build . '/settings-app.js' ) || ! is_readable( $build . '/settings-app.asset.php' ) ) {
			return __( 'the built settings app (build/settings-app.js) is missing', 'cassette-cmf' );
		}

		if ( function_exists( 'wp_script_is' ) && ! wp_script_is( 'wp-components', 'registered' ) ) {
			return __( 'the "wp-components" script is not available', 'cassette-cmf' );
		}

		return null;
	}

	/**
	 * Script and style handle of the app.
	 *
	 * @return string
	 */
	public static function handle(): string {
		return Manager::asset_handle() . '-settings-app';
	}

	/**
	 * Name of the window object carrying the page's config.
	 *
	 * @return string
	 */
	public static function js_global(): string {
		return Manager::js_global() . 'SettingsApp';
	}

	/**
	 * Output the page: a root element for the app plus a no-JavaScript notice.
	 *
	 * @return void
	 */
	public function render(): void {
		$id = 'cassette-cmf-settings-' . $this->page->get_page_id();
		?>
		<div class="wrap">
			<div id="<?php echo esc_attr( $id ); ?>" class="cassette-cmf-settings-app-root" data-config-global="<?php echo esc_attr( self::js_global() ); ?>"></div>
			<noscript>
				<div class="notice notice-error">
					<p><?php echo esc_html__( 'This settings page needs JavaScript. Please enable JavaScript in your browser to edit these settings.', 'cassette-cmf' ); ?></p>
				</div>
			</noscript>
		</div>
		<?php
	}

	/**
	 * Register and enqueue the app for the current screen.
	 *
	 * Call it only on this page's screen.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		$build = self::build_path();
		$url   = self::build_url();
		$asset = require $build . '/settings-app.asset.php';

		$dependencies = is_array( $asset['dependencies'] ?? null ) ? $asset['dependencies'] : [];
		$version      = (string) ( $asset['version'] ?? '' );
		$handle       = self::handle();

		wp_register_script( $handle, $url . 'settings-app.js', $dependencies, '' !== $version ? $version : false, true );
		wp_set_script_translations( $handle, 'cassette-cmf', dirname( __DIR__, 2 ) . '/languages' );

		wp_add_inline_script(
			$handle,
			'window.' . self::js_global() . ' = ' . wp_json_encode( $this->get_app_config(), JSON_HEX_TAG | JSON_HEX_AMP ) . ';',
			'before'
		);

		wp_register_style( $handle, $url . 'settings-app.css', [ 'wp-components' ], '' !== $version ? $version : false );
		wp_style_add_data( $handle, 'rtl', 'replace' );

		// The upload field opens the WordPress media modal.
		wp_enqueue_media();
		wp_enqueue_style( $handle );
		wp_enqueue_script( $handle );
	}

	/**
	 * Everything the app needs, as one JSON-ready array.
	 *
	 * @return array<string, mixed>
	 */
	public function get_app_config(): array {
		$page_id = $this->page->get_page_id();

		$this->classic_only = [];
		$fields             = $this->describe_top_level( $this->handler->get_top_level_fields( $page_id ) );
		$controller         = new Settings_Controller( $this->handler, Manager::rest_namespace() );

		return [
			'pageId'      => $page_id,
			'title'       => (string) $this->page->get_config( 'page_title', $page_id ),
			'description' => (string) $this->page->get_config( 'description', '' ),
			'restPath'    => '/' . Manager::rest_namespace() . '/settings/' . $page_id,
			'fields'      => $fields,
			'values'      => (object) $controller->get_values( $page_id ),
			'classicOnly' => $this->classic_only,
		];
	}

	/**
	 * Describe a page's top-level fields the way the classic renderer draws them.
	 *
	 * A top-level container that is neither a group nor a metabox is invalid on
	 * a settings page and is skipped with its children, as classic does.
	 *
	 * @param array<int|string, Field_Interface> $fields Top-level fields.
	 * @return array<int, array<string, mixed>>
	 */
	private function describe_top_level( array $fields ): array {
		$nodes = [];

		foreach ( $fields as $field ) {
			if ( ! $field instanceof Field_Interface ) {
				continue;
			}

			if ( $field instanceof Container_Field_Interface && ! $field instanceof Group_Field && ! $field instanceof Metabox_Field ) {
				continue;
			}

			$node = $this->describe_field( $field );
			if ( null !== $node ) {
				$nodes[] = $node;
			}
		}

		return $nodes;
	}

	/**
	 * Describe a list of field configs (nested fields, tab fields, repeater sub-fields).
	 *
	 * @param mixed $configs Field config arrays.
	 * @return array<int, array<string, mixed>>
	 */
	private function describe_configs( $configs ): array {
		$nodes = [];

		if ( ! is_array( $configs ) ) {
			return $nodes;
		}

		foreach ( $configs as $config ) {
			if ( ! is_array( $config ) || empty( $config['name'] ) || empty( $config['type'] ) ) {
				continue;
			}

			try {
				$field = Field_Factory::create( $config );
			} catch ( \InvalidArgumentException $e ) {
				continue;
			}

			$node = $this->describe_field( $field );
			if ( null !== $node ) {
				$nodes[] = $node;
			}
		}

		return $nodes;
	}

	/**
	 * Describe one field as a node of the tree the app renders.
	 *
	 * @param Field_Interface $field Field.
	 * @return array<string, mixed>|null Null for a field the app does not draw.
	 */
	private function describe_field( Field_Interface $field ): ?array {
		if ( false === $field->get_config( 'show_in_rest' ) ) {
			$label                = $field->get_label();
			$this->classic_only[] = '' !== $label ? $label : $field->get_name();
			return null;
		}

		$type = $field->get_type();
		$node = [
			'type'        => $type,
			'name'        => $field->get_name(),
			'label'       => $field->get_label(),
			'description' => (string) $field->get_config( 'description', '' ),
			'placeholder' => (string) $field->get_config( 'placeholder', '' ),
			'required'    => ! empty( $field->get_config( 'required' ) ),
			'readonly'    => ! empty( $field->get_config( 'readonly' ) ),
			'disabled'    => ! empty( $field->get_config( 'disabled' ) ),
			'default'     => $field->get_config( 'default', '' ),
			'conditional' => $this->conditional_of( $field ),
		];

		if ( $field instanceof Metabox_Field ) {
			$node['title']    = $field->get_metabox_title();
			$node['children'] = $this->describe_configs( $field->get_nested_fields() );
			return $node;
		}

		if ( $field instanceof Group_Field ) {
			$node['children'] = $this->describe_configs( $field->get_nested_fields() );
			return $node;
		}

		if ( $field instanceof Tabs_Field ) {
			$node['orientation'] = 'vertical' === $field->get_config( 'orientation' ) ? 'vertical' : 'horizontal';
			$node['defaultTab']  = (string) $field->get_config( 'default_tab', '' );
			$node['tabs']        = $this->describe_tabs( $field );
			return $node;
		}

		if ( $field instanceof Repeater_Field ) {
			$node['subFields']     = $this->describe_configs( $field->get_sub_fields() );
			$node['minRows']       = (int) $field->get_config( 'min_rows', 0 );
			$node['maxRows']       = (int) $field->get_config( 'max_rows', 0 );
			$node['buttonLabel']   = (string) $field->get_config( 'button_label', '' );
			$node['rowLabel']      = (string) $field->get_config( 'row_label', '' );
			$node['rowLabelField'] = (string) $field->get_config( 'row_label_field', '' );
			return $node;
		}

		if ( $field instanceof Custom_HTML_Field ) {
			$node['html'] = $field->get_content_html();
			return $node;
		}

		return $this->add_type_details( $node, $field );
	}

	/**
	 * Add the type-specific keys of a value-storing field.
	 *
	 * @param array<string, mixed> $node  Node so far.
	 * @param Field_Interface      $field Field.
	 * @return array<string, mixed>
	 */
	private function add_type_details( array $node, Field_Interface $field ): array {
		$type    = $field->get_type();
		$options = $field->get_config( 'options' );

		if ( is_array( $options ) && ! empty( $options ) ) {
			$node['options'] = [];
			foreach ( $options as $value => $label ) {
				$node['options'][] = [
					'value' => (string) $value,
					'label' => is_scalar( $label ) ? (string) $label : (string) $value,
				];
			}
		}

		if ( 'select' === $type ) {
			$node['multiple'] = ! empty( $field->get_config( 'multiple' ) );
		}

		if ( in_array( $type, [ 'number', 'date' ], true ) ) {
			foreach ( [ 'min', 'max', 'step' ] as $key ) {
				$value = $field->get_config( $key );
				if ( null !== $value && '' !== $value ) {
					$node[ $key ] = $value;
				}
			}
		}

		if ( in_array( $type, [ 'text', 'email', 'url', 'password', 'textarea' ], true ) ) {
			$maxlength = (int) $field->get_config( 'maxlength', 0 );
			if ( $maxlength > 0 ) {
				$node['maxlength'] = $maxlength;
			}
		}

		if ( 'textarea' === $type ) {
			$rows = (int) $field->get_config( 'rows', 0 );
			if ( $rows > 0 ) {
				$node['rows'] = $rows;
			}
		}

		if ( 'upload' === $type ) {
			$node['buttonText']  = (string) $field->get_config( 'button_text', '' );
			$node['removeText']  = (string) $field->get_config( 'remove_text', '' );
			$node['preview']     = ! empty( $field->get_config( 'preview', true ) );
			$node['libraryType'] = (string) $field->get_config( 'library_type', '' );
		}

		return $node;
	}

	/**
	 * Describe the tabs of a tabs field.
	 *
	 * @param Tabs_Field $field Tabs field.
	 * @return array<int, array<string, mixed>>
	 */
	private function describe_tabs( Tabs_Field $field ): array {
		$tabs = [];

		foreach ( (array) $field->get_config( 'tabs', [] ) as $index => $tab ) {
			if ( ! is_array( $tab ) ) {
				continue;
			}

			$id = isset( $tab['id'] ) && '' !== (string) $tab['id'] ? (string) $tab['id'] : 'tab-' . $index;

			$tabs[] = [
				'id'     => $id,
				'title'  => (string) ( $tab['label'] ?? $tab['title'] ?? $id ),
				'fields' => $this->describe_configs( $tab['fields'] ?? [] ),
			];
		}

		return $tabs;
	}

	/**
	 * The normalized conditional rules of a field, or null.
	 *
	 * @param Field_Interface $field Field.
	 * @return array<string, mixed>|null
	 */
	private function conditional_of( Field_Interface $field ): ?array {
		if ( ! $field instanceof Abstract_Field ) {
			return null;
		}

		$conditional = $field->get_conditional_config();

		return empty( $conditional ) ? null : $conditional;
	}
}
