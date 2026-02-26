<?php
/**
 * Abstract Handler
 *
 * Base class for all registration handlers.
 *
 * @package Pedalcms\CassetteCmf
 * @since 1.0.0
 */

namespace Pedalcms\CassetteCmf\Core\Handlers;

use Pedalcms\CassetteCmf\Core\Traits\Field_Registration_Trait;
use Pedalcms\CassetteCmf\Core\Traits\Field_Rendering_Trait;
use Pedalcms\CassetteCmf\Core\Traits\Field_Saving_Trait;

/**
 * Abstract class Abstract_Handler
 *
 * Provides common functionality for all handlers.
 */
abstract class Abstract_Handler implements Handler_Interface {

	use Field_Registration_Trait;
	use Field_Rendering_Trait;
	use Field_Saving_Trait;

	/**
	 * Whether hooks have been initialized
	 *
	 * @var bool
	 */
	protected bool $hooks_initialized = false;

	/**
	 * Check if hooks are initialized
	 *
	 * @return bool
	 */
	public function are_hooks_initialized(): bool {
		return $this->hooks_initialized;
	}

	/**
	 * Check if WordPress functions are available
	 *
	 * @return bool
	 */
	protected function has_wordpress(): bool {
		return function_exists( 'add_action' );
	}

	/**
	 * Get Cassette-CMF assets URL
	 *
	 * @return string Assets URL with trailing slash.
	 */
	protected function get_assets_url(): string {
		// Go up two levels from Handlers to reach src directory, then add /assets/
		$dir        = dirname( dirname( __DIR__ ) );
		$assets_dir = $dir . '/assets/';

		if ( defined( 'ABSPATH' ) && function_exists( 'site_url' ) ) {
			$abspath     = str_replace( '\\', '/', ABSPATH );
			$assets_path = str_replace( '\\', '/', $assets_dir );

			return str_replace( $abspath, trailingslashit( site_url() ), $assets_path );
		}

		if ( defined( 'WP_CONTENT_DIR' ) && defined( 'WP_CONTENT_URL' ) ) {
			$content_dir = str_replace( '\\', '/', WP_CONTENT_DIR );
			$assets_path = str_replace( '\\', '/', $assets_dir );

			return str_replace( $content_dir, WP_CONTENT_URL, $assets_path );
		}

		return '';
	}

	/**
	 * Get Cassette-CMF version
	 *
	 * @return string Version string.
	 */
	protected function get_version(): string {
		$composer_path = dirname( dirname( __DIR__ ) ) . '/composer.json';

		if ( file_exists( $composer_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$json = json_decode( file_get_contents( $composer_path ), true );
			if ( isset( $json['version'] ) ) {
				return $json['version'];
			}
		}

		return gmdate( 'YmdHis', filemtime( __FILE__ ) );
	}
}
