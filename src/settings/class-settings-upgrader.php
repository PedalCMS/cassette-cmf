<?php
/**
 * Settings_Upgrader - schema version tracking and upgrade callbacks
 *
 * Opt-in per settings page through the `schema_version` (int >= 1) and
 * `upgrade` ( callable( array $old, int $from ): array ) page config keys.
 * Pages without them are never touched and no option is written for them.
 *
 * @package PedalCMS\CassetteCMF
 * @since 0.2.0
 */

namespace PedalCMS\CassetteCMF\Settings;

use PedalCMS\CassetteCMF\Core\Handlers\New_Settings_Page_Handler;
use PedalCMS\CassetteCMF\Field\Field_Interface;
use PedalCMS\CassetteCMF\Field\Fields\Secret_Field;
use PedalCMS\CassetteCMF\Rest\Settings_Schema;

/**
 * Class Settings_Upgrader
 *
 * Option names:
 * - `cassette_cmf_schema_version_{page_id}`: stored version (autoload off).
 * - `cassette_cmf_schema_lock_{page_id}`: lock, value is the expiry timestamp.
 * - `cassette_cmf_schema_error_{page_id}`: last failure, cleared on success.
 *
 * The names are fixed (not derived from boot options) on purpose: they are
 * already scoped by the page ID, which is unique on a site (two copies that
 * register the same page ID collide on the menu slug and the field options
 * first), and a stable name keeps the version readable when a site switches
 * between plain and prefixed copies of the library.
 */
class Settings_Upgrader {

	public const STATUS_CURRENT  = 'current';
	public const STATUS_UPGRADED = 'upgraded';
	public const STATUS_LOCKED   = 'locked';
	public const STATUS_FAILED   = 'failed';

	/**
	 * Lock lifetime in seconds.
	 *
	 * @var int
	 */
	private const LOCK_TTL = 60;

	/**
	 * Handler that owns the pages.
	 *
	 * @var New_Settings_Page_Handler
	 */
	private New_Settings_Page_Handler $handler;

	/**
	 * Status per page for this request.
	 *
	 * @var array<string, string>
	 */
	private array $done = [];

	/**
	 * Whether the failure notice hook has been added.
	 *
	 * @var bool
	 */
	private bool $notice_hooked = false;

	/**
	 * Constructor
	 *
	 * @param New_Settings_Page_Handler $handler Settings page handler.
	 */
	public function __construct( New_Settings_Page_Handler $handler ) {
		$this->handler = $handler;
	}

	/**
	 * Name of the stored version option.
	 *
	 * @param string $page_id Page ID.
	 * @return string
	 */
	public static function version_option( string $page_id ): string {
		return 'cassette_cmf_schema_version_' . $page_id;
	}

	/**
	 * Name of the lock option.
	 *
	 * @param string $page_id Page ID.
	 * @return string
	 */
	public static function lock_option( string $page_id ): string {
		return 'cassette_cmf_schema_lock_' . $page_id;
	}

	/**
	 * Name of the last-failure option.
	 *
	 * @param string $page_id Page ID.
	 * @return string
	 */
	public static function error_option( string $page_id ): string {
		return 'cassette_cmf_schema_error_' . $page_id;
	}

	/**
	 * Check every versioned page (hooked to admin_init).
	 *
	 * @return void
	 */
	public function run_all(): void {
		foreach ( $this->handler->get_pages() as $page_id => $page ) {
			if ( null !== $page->get_schema_version() ) {
				$this->maybe_upgrade( (string) $page_id );
			}
		}

		$this->hook_failure_notice();
	}

	/**
	 * Bring one page up to its declared schema version
	 *
	 * Safe to call as often as you like: after the first check in a request
	 * it returns the cached status.
	 *
	 * @param string $page_id Page ID.
	 * @return string One of the STATUS_* constants.
	 */
	public function maybe_upgrade( string $page_id ): string {
		if ( isset( $this->done[ $page_id ] ) ) {
			return $this->done[ $page_id ];
		}

		$page    = $this->handler->get_page( $page_id );
		$current = null === $page ? null : $page->get_schema_version();

		if ( null === $page || null === $current || ! function_exists( 'get_option' ) ) {
			return self::STATUS_CURRENT;
		}

		$status = $this->upgrade_page( $page, $page_id, $current );

		// A locked page is retried on the next call.
		if ( self::STATUS_LOCKED !== $status ) {
			$this->done[ $page_id ] = $status;
		}

		return $status;
	}

	/**
	 * Core of maybe_upgrade().
	 *
	 * @param Settings_Page $page    Page.
	 * @param string        $page_id Page ID.
	 * @param int           $current Declared version.
	 * @return string
	 */
	private function upgrade_page( Settings_Page $page, string $page_id, int $current ): string {
		$fields = $this->storage_fields( $page_id );
		$stored = $this->stored_version( $page_id );
		$old    = $this->read_values( $page_id, $fields );

		if ( null === $stored ) {
			// A brand-new install has no values: nothing to upgrade.
			if ( empty( $old ) ) {
				$this->write_version( $page_id, $current );
				return self::STATUS_CURRENT;
			}

			// Existing data from before versioning is version 1.
			$stored = 1;
		}

		if ( $stored >= $current ) {
			// Equal: up to date (and records the implicit 1). Greater: a downgrade, left alone.
			if ( null === $this->stored_version( $page_id ) ) {
				$this->write_version( $page_id, $stored );
			}
			return self::STATUS_CURRENT;
		}

		if ( ! $this->acquire_lock( $page_id ) ) {
			return self::STATUS_LOCKED;
		}

		try {
			// Another request may have finished while we waited for the lock.
			$latest = $this->stored_version( $page_id );
			if ( null !== $latest && $latest >= $current ) {
				return self::STATUS_CURRENT;
			}
			if ( null !== $latest ) {
				$stored = $latest;
			}

			return $this->run_upgrade( $page, $page_id, $fields, $stored, $current );
		} finally {
			$this->release_lock( $page_id );
		}
	}

	/**
	 * Run the callback, write the result, bump the version.
	 *
	 * @param Settings_Page                  $page    Page.
	 * @param string                         $page_id Page ID.
	 * @param array<string, Field_Interface> $fields  Storable fields.
	 * @param int                            $from    Stored version.
	 * @param int                            $to      Declared version.
	 * @return string
	 */
	private function run_upgrade( Settings_Page $page, string $page_id, array $fields, int $from, int $to ): string {
		$callback = $page->get_upgrade_callback();

		if ( null === $callback ) {
			// Version tracking only (e.g. JSON configs): nothing to migrate.
			$this->write_version( $page_id, $to );
			return self::STATUS_UPGRADED;
		}

		try {
			if ( ! is_callable( $callback ) ) {
				throw new \UnexpectedValueException( 'The "upgrade" setting is not callable.' );
			}

			// Re-read inside the lock: the values may have changed meanwhile.
			$result = call_user_func( $callback, $this->read_values( $page_id, $fields ), $from );

			if ( ! is_array( $result ) ) {
				throw new \UnexpectedValueException( 'The upgrade callback must return an array.' );
			}

			// Sanitize everything first so a failure writes nothing.
			$sanitized = [];
			foreach ( $result as $name => $value ) {
				if ( isset( $fields[ $name ] ) ) {
					$sanitized[ $name ] = $fields[ $name ]->sanitize( $value );
				}
			}
		} catch ( \Throwable $error ) {
			$this->record_failure( $page_id, $from, $to, $error );
			return self::STATUS_FAILED;
		}

		foreach ( $sanitized as $name => $value ) {
			update_option( $fields[ $name ]->get_option_name( $page_id ), $value );
		}

		$this->write_version( $page_id, $to );
		delete_option( self::error_option( $page_id ) );

		return self::STATUS_UPGRADED;
	}

	/**
	 * Log a failure, fire the action and remember it for the admin notice.
	 *
	 * @param string     $page_id Page ID.
	 * @param int        $from    Stored version.
	 * @param int        $to      Declared version.
	 * @param \Throwable $error   What went wrong.
	 * @return void
	 */
	private function record_failure( string $page_id, int $from, int $to, \Throwable $error ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Documented, WP_DEBUG-only log of a failed upgrade.
			error_log(
				sprintf(
					'Cassette-CMF: schema upgrade of settings page "%1$s" from version %2$d to %3$d failed: %4$s',
					$page_id,
					$from,
					$to,
					$error->getMessage()
				)
			);
		}

		update_option(
			self::error_option( $page_id ),
			[
				'from'    => $from,
				'to'      => $to,
				'message' => $error->getMessage(),
			],
			false
		);

		/**
		 * Fires when a settings schema upgrade fails. The data and the stored version are left untouched.
		 *
		 * @since 0.2.0
		 *
		 * @param string     $page_id Settings page ID.
		 * @param int        $from    Stored version.
		 * @param int        $to      Declared version.
		 * @param \Throwable $error   The exception, or an UnexpectedValueException for a bad return value.
		 */
		do_action( 'cassette_cmf_schema_upgrade_failed', $page_id, $from, $to, $error );

		$this->hook_failure_notice();
	}

	/**
	 * Show failures as an admin notice (users with the page capability only).
	 *
	 * @return void
	 */
	private function hook_failure_notice(): void {
		if ( $this->notice_hooked || ! function_exists( 'add_action' ) ) {
			return;
		}

		$this->notice_hooked = true;

		add_action( 'admin_notices', [ $this, 'print_failure_notices' ] );
	}

	/**
	 * Print the failure notices.
	 *
	 * @return void
	 */
	public function print_failure_notices(): void {
		foreach ( $this->handler->get_pages() as $page_id => $page ) {
			$error = get_option( self::error_option( (string) $page_id ), null );

			if ( ! is_array( $error ) || ! current_user_can( (string) $page->get_config( 'capability', 'manage_options' ) ) ) {
				continue;
			}

			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: settings page ID, 2: old version, 3: new version, 4: error message */
						__( 'Cassette-CMF could not upgrade the settings "%1$s" from version %2$d to %3$d. Your data was left unchanged. %4$s', 'cassette-cmf' ),
						(string) $page_id,
						(int) ( $error['from'] ?? 0 ),
						(int) ( $error['to'] ?? 0 ),
						(string) ( $error['message'] ?? '' )
					)
				)
			);
		}
	}

	/**
	 * Storable fields of a page (flat registry, bound to their options).
	 *
	 * @param string $page_id Page ID.
	 * @return array<string, Field_Interface>
	 */
	private function storage_fields( string $page_id ): array {
		$fields = Settings_Schema::storage_fields( $this->handler->get_fields( $page_id ) );

		foreach ( $fields as $field ) {
			Secret_Field::bind_if_secret( $field, $field->get_option_name( $page_id ) );
		}

		return $fields;
	}

	/**
	 * Read the stored values that exist (field name => stored value).
	 *
	 * @param string                         $page_id Page ID.
	 * @param array<string, Field_Interface> $fields  Storable fields.
	 * @return array<string, mixed>
	 */
	private function read_values( string $page_id, array $fields ): array {
		$unset  = new \stdClass();
		$values = [];

		foreach ( $fields as $name => $field ) {
			$value = get_option( $field->get_option_name( $page_id ), $unset );

			if ( $value !== $unset ) {
				$values[ $name ] = $value;
			}
		}

		return $values;
	}

	/**
	 * Stored version, or null when none is stored.
	 *
	 * @param string $page_id Page ID.
	 * @return int|null
	 */
	private function stored_version( string $page_id ): ?int {
		$stored = get_option( self::version_option( $page_id ), null );

		return is_numeric( $stored ) ? (int) $stored : null;
	}

	/**
	 * Store the version (autoload off).
	 *
	 * @param string $page_id Page ID.
	 * @param int    $version Version.
	 * @return void
	 */
	private function write_version( string $page_id, int $version ): void {
		update_option( self::version_option( $page_id ), $version, false );
	}

	/**
	 * Take the lock
	 *
	 * Atomic: a single INSERT IGNORE on the unique option_name. A lock whose
	 * expiry has passed is removed (compare-and-delete on its exact value, so
	 * only one request wins) and taken.
	 *
	 * @param string $page_id Page ID.
	 * @return bool Whether the lock is now held by this request.
	 */
	private function acquire_lock( string $page_id ): bool {
		global $wpdb;

		$name = self::lock_option( $page_id );
		$ttl  = (int) apply_filters( 'cassette_cmf_schema_upgrade_lock_ttl', self::LOCK_TTL, $page_id );

		for ( $attempt = 0; $attempt < 2; $attempt++ ) {
			$expires = (string) ( time() + max( 1, $ttl ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic lock; core has no insert-if-absent for options.
			$inserted = $wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
					$name,
					$expires
				)
			);

			if ( $inserted ) {
				$this->flush_option_cache( $name );
				return true;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads the lock row directly, bypassing the object cache.
			$held = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );

			if ( null !== $held && (int) $held >= time() ) {
				return false;
			}

			// Expired (or vanished): remove exactly that stale row and try once more.
			if ( null !== $held ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Compare-and-delete of the stale lock.
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $name, $held ) );
				$this->flush_option_cache( $name );
			}
		}

		return false;
	}

	/**
	 * Release the lock.
	 *
	 * @param string $page_id Page ID.
	 * @return void
	 */
	private function release_lock( string $page_id ): void {
		global $wpdb;

		$name = self::lock_option( $page_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Matches acquire_lock().
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", $name ) );
		$this->flush_option_cache( $name );
	}

	/**
	 * Drop cached copies of a directly written option.
	 *
	 * @param string $name Option name.
	 * @return void
	 */
	private function flush_option_cache( string $name ): void {
		wp_cache_delete( $name, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
	}
}
