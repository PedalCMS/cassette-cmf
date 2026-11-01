<?php
/**
 * SecretField - Write-only secret input (API keys, tokens)
 *
 * @package PedalCMS\CassetteCMF
 * @since 0.2.0
 */

namespace PedalCMS\CassetteCMF\Field\Fields;

use PedalCMS\CassetteCMF\Field\Abstract_Field;
use PedalCMS\CassetteCMF\Field\Field_Interface;

/**
 * Secret_Field class
 *
 * A password-like field for values that must never be echoed back: the stored
 * value is never put into HTML or REST output. Instead a mask (`••••` plus the
 * last four characters) is shown.
 *
 * Save semantics (classic form and REST alike):
 * - a new non-empty string replaces the stored value;
 * - an empty submission, `null`, an omitted key, or the unchanged mask keeps
 *   the stored value;
 * - the clear control (`name[clear]` in the form, `{ "clear": true }` in REST)
 *   empties it.
 *
 * With `encrypt => true` the value is stored encrypted with
 * sodium_crypto_secretbox, keyed from wp_salt( 'auth' ). Only
 * CassetteCMF::get_secret() returns the real value.
 *
 * For settings pages only. Not supported inside repeater rows.
 */
class Secret_Field extends Abstract_Field {

	/**
	 * Mask prefix (four U+2022 bullets).
	 *
	 * @var string
	 */
	public const MASK_PREFIX = '••••';

	/**
	 * Marker that tells an encrypted stored value from a plaintext one.
	 *
	 * @var string
	 */
	public const ENCRYPTED_PREFIX = 'cassette-cmf-secret:';

	/**
	 * Format version byte at the start of the encrypted payload.
	 *
	 * @var int
	 */
	private const FORMAT_VERSION = 1;

	/**
	 * Labels of fields whose stored value could not be decrypted.
	 *
	 * @var array<string, string>
	 */
	private static array $decrypt_failures = [];

	/**
	 * Option that stores this field's value (bound by the handlers).
	 *
	 * @var string
	 */
	private string $storage_option = '';

	/**
	 * Get field type defaults
	 *
	 * @return array<string, mixed>
	 */
	protected function get_defaults(): array {
		return array_merge(
			parent::get_defaults(),
			[
				'type'         => 'secret',
				'placeholder'  => '',
				'maxlength'    => '',
				'autocomplete' => 'new-password',
				'encrypt'      => false,
			]
		);
	}

	/**
	 * Bind the field to the option that stores it
	 *
	 * Needed so that an empty submission can keep the stored value. Handlers,
	 * the REST controller and the schema upgrader call this; the secret is
	 * not otherwise read by the field.
	 *
	 * @param string $option_name Option name.
	 * @return self
	 */
	public function bind_option( string $option_name ): self {
		$this->storage_option = $option_name;
		return $this;
	}

	/**
	 * Bind a field to its option when it is a secret field
	 *
	 * @param Field_Interface $field       Any field.
	 * @param string          $option_name Option name.
	 * @return void
	 */
	public static function bind_if_secret( Field_Interface $field, string $option_name ): void {
		if ( $field instanceof self ) {
			$field->bind_option( $option_name );
		}
	}

	/**
	 * Render the secret field
	 *
	 * The stored value is only used to derive the mask hint; it is never
	 * written to the markup.
	 *
	 * @param mixed $value Stored value (plain or encrypted).
	 * @return string HTML output.
	 */
	public function render( $value = null ): string {
		$mask = $this->mask( $value );

		$output  = $this->render_wrapper_start();
		$output .= $this->render_label();

		$attributes = [
			'type'         => 'password',
			'id'           => $this->get_field_id(),
			'name'         => $this->name . '[value]',
			'value'        => '',
			'class'        => 'regular-text',
			'autocomplete' => ! empty( $this->config['autocomplete'] ) ? (string) $this->config['autocomplete'] : 'new-password',
		];

		if ( ! empty( $this->config['placeholder'] ) ) {
			$attributes['placeholder'] = $this->config['placeholder'];
		} elseif ( '' !== $mask ) {
			$attributes['placeholder'] = $mask;
		}

		if ( ! empty( $this->config['maxlength'] ) ) {
			$attributes['maxlength'] = $this->config['maxlength'];
		}

		if ( ! empty( $this->config['disabled'] ) ) {
			$attributes['disabled'] = true;
		}

		$output .= '<input' . $this->build_attributes( $attributes ) . ' />';

		if ( '' !== $mask ) {
			$output .= '<p class="description cassette-cmf-secret-mask">'
				. $this->esc_html( $this->translate( 'Saved value:' ) ) . ' <code>' . $this->esc_html( $mask ) . '</code> '
				. $this->esc_html( $this->translate( 'Leave empty to keep it.' ) ) . '</p>';

			$clear_attributes = [
				'type'  => 'checkbox',
				'name'  => $this->name . '[clear]',
				'value' => '1',
			];

			if ( ! empty( $this->config['disabled'] ) ) {
				$clear_attributes['disabled'] = true;
			}

			$output .= '<label class="cassette-cmf-secret-clear"><input' . $this->build_attributes( $clear_attributes ) . ' /> '
				. $this->esc_html( $this->translate( 'Clear the saved value' ) ) . '</label>';
		}

		$output .= $this->render_description();
		$output .= $this->render_wrapper_end();

		return $output;
	}

	/**
	 * Sanitize a submitted value into what gets stored
	 *
	 * Idempotent: running it on its own output returns the same string, which
	 * matters because the classic save path sanitizes twice.
	 *
	 * @param mixed $input String, `null`, or `[ 'value' => string, 'clear' => truthy ]`.
	 * @return string Value to store.
	 */
	public function sanitize( $input ) {
		$value = $input;

		if ( is_array( $input ) ) {
			$clear = $input['clear'] ?? false;
			if ( ! empty( $clear ) && 'false' !== $clear ) {
				return '';
			}

			$value = $input['value'] ?? null;
		}

		if ( ! is_string( $value ) ) {
			return $this->existing_value();
		}

		$value = trim( $value );

		if ( '' === $value || 0 === strpos( $value, self::MASK_PREFIX ) ) {
			return $this->existing_value();
		}

		if ( $this->is_encrypted( $value ) ) {
			return $value;
		}

		if ( ! empty( $this->config['encrypt'] ) ) {
			return $this->encrypt( $value );
		}

		return $value;
	}

	/**
	 * Validate the value that will be stored
	 *
	 * Rules (`required`, `min`, `max`, ...) run against the plaintext.
	 *
	 * @param mixed $input Sanitized value.
	 * @return array
	 */
	public function validate( $input ): array {
		$plain = is_string( $input ) ? $this->reveal( $input ) : '';

		return parent::validate( $plain );
	}

	/**
	 * Whether a stored string carries the encrypted marker.
	 *
	 * @param string $stored Stored value.
	 * @return bool
	 */
	public function is_encrypted( string $stored ): bool {
		return 0 === strpos( $stored, self::ENCRYPTED_PREFIX );
	}

	/**
	 * Get the real value from a stored value (decrypting when needed)
	 *
	 * Returns '' when the value is unset or cannot be decrypted (for example
	 * after the AUTH salt changed); the latter also raises an admin notice.
	 *
	 * @param mixed $stored Stored value.
	 * @return string
	 */
	public function reveal( $stored ): string {
		if ( ! is_string( $stored ) || '' === $stored ) {
			return '';
		}

		if ( ! $this->is_encrypted( $stored ) ) {
			return $stored;
		}

		$plain = $this->decrypt( $stored );

		if ( null === $plain ) {
			$this->note_decrypt_failure();
			return '';
		}

		return $plain;
	}

	/**
	 * Get the display mask for a stored value
	 *
	 * `••••` followed by the last four characters, or only `••••` when the
	 * secret is four characters or shorter, or '' when nothing is stored.
	 *
	 * @param mixed $stored Stored value.
	 * @return string
	 */
	public function mask( $stored ): string {
		$plain = $this->reveal( $stored );

		if ( '' === $plain ) {
			return '';
		}

		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $plain ) : strlen( $plain );

		if ( $length <= 4 ) {
			return self::MASK_PREFIX;
		}

		$tail = function_exists( 'mb_substr' ) ? mb_substr( $plain, -4 ) : substr( $plain, -4 );

		return self::MASK_PREFIX . $tail;
	}

	/**
	 * Read the currently stored value of the bound option.
	 *
	 * @return string
	 */
	private function existing_value(): string {
		if ( '' === $this->storage_option || ! function_exists( 'get_option' ) ) {
			return '';
		}

		$stored = get_option( $this->storage_option, '' );

		return is_string( $stored ) ? $stored : '';
	}

	/**
	 * Encrypt a plaintext value.
	 *
	 * @param string $plain Plaintext.
	 * @return string Marker + base64( version byte + nonce + ciphertext ).
	 */
	private function encrypt( string $plain ): string {
		$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = sodium_crypto_secretbox( $plain, $nonce, $this->encryption_key() );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary payload encoding, not obfuscation.
		return self::ENCRYPTED_PREFIX . base64_encode( chr( self::FORMAT_VERSION ) . $nonce . $cipher );
	}

	/**
	 * Decrypt a stored value.
	 *
	 * @param string $stored Value carrying the encrypted marker.
	 * @return string|null Plaintext, or null when it cannot be decrypted.
	 */
	private function decrypt( string $stored ): ?string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Binary payload decoding, not obfuscation.
		$raw = base64_decode( substr( $stored, strlen( self::ENCRYPTED_PREFIX ) ), true );

		$min_length = 1 + SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES;

		if ( false === $raw || strlen( $raw ) < $min_length || ord( $raw[0] ) !== self::FORMAT_VERSION ) {
			return null;
		}

		$nonce  = substr( $raw, 1, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = substr( $raw, 1 + SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$plain  = sodium_crypto_secretbox_open( $cipher, $nonce, $this->encryption_key() );

		return false === $plain ? null : $plain;
	}

	/**
	 * Derive the secretbox key from the AUTH salt.
	 *
	 * @return string
	 */
	private function encryption_key(): string {
		$salt = function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : 'cassette-cmf';

		return sodium_crypto_generichash( $salt, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}

	/**
	 * Remember that this field's value could not be decrypted and show a notice.
	 *
	 * @return void
	 */
	private function note_decrypt_failure(): void {
		self::$decrypt_failures[ $this->name ] = $this->get_label();

		if ( function_exists( 'add_action' ) && false === has_action( 'admin_notices', [ self::class, 'print_decrypt_notice' ] ) ) {
			add_action( 'admin_notices', [ self::class, 'print_decrypt_notice' ] );
		}
	}

	/**
	 * Print the "re-enter the secret" admin notice.
	 *
	 * @return void
	 */
	public static function print_decrypt_notice(): void {
		if ( empty( self::$decrypt_failures ) || ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		foreach ( self::$decrypt_failures as $label ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: field label */
						__( 'Cassette-CMF could not decrypt the saved value of "%s" (the site security keys have probably changed). Please enter it again.', 'cassette-cmf' ),
						$label
					)
				)
			);
		}
	}

	/**
	 * Get the field schema (never includes a value)
	 *
	 * @return array<string, mixed>
	 */
	public function get_schema(): array {
		return array_merge(
			parent::get_schema(),
			[
				'encrypt' => ! empty( $this->config['encrypt'] ),
			]
		);
	}
}
