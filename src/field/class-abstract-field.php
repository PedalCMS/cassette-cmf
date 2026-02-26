<?php
/**
 * AbstractField base class for Cassette-CMF
 *
 * Provides common functionality and helpers for all field types.
 * Field classes should extend this to get standard behavior.
 *
 * @package Pedalcms\CassetteCmf
 * @since 1.0.0
 */

namespace Pedalcms\CassetteCmf\Field;

/**
 * Abstract_Field - Base implementation for field types
 *
 * Provides common properties and helper methods that all fields can use.
 * Concrete field classes should extend this and implement render().
 */
abstract class Abstract_Field implements Field_Interface {

	/**
	 * Field name/identifier
	 *
	 * @var string
	 */
	protected string $name;

	/**
	 * Field type
	 *
	 * @var string
	 */
	protected string $type;

	/**
	 * Field configuration
	 *
	 * @var array<string, mixed>
	 */
	protected array $config = [];

	/**
	 * Validation rules
	 *
	 * @var array<string, mixed>
	 */
	protected array $validation_rules = [];

	/**
	 * Constructor
	 *
	 * @param string               $name   Field name/identifier.
	 * @param string               $type   Field type.
	 * @param array<string, mixed> $config Field configuration.
	 */
	public function __construct( string $name, string $type, array $config = [] ) {
		$this->name   = $name;
		$this->type   = $type;
		$this->config = array_merge( $this->get_defaults(), $config );

		// Extract validation rules if provided
		if ( isset( $this->config['validation'] ) ) {
			$this->validation_rules = $this->config['validation'];
		}
	}

	/**
	 * Get default configuration values
	 *
	 * @return array<string, mixed>
	 */
	protected function get_defaults(): array {
		return [
			'label'           => ucwords( str_replace( [ '_', '-' ], ' ', $this->name ) ),
			'description'     => '',
			'placeholder'     => '',
			'default'         => '',
			'required'        => false,
			'class'           => '',
			'attributes'      => [],
			'use_name_prefix' => true,
		];
	}

	/**
	 * Get the field name
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Get the field label
	 *
	 * @return string
	 */
	public function get_label(): string {
		return $this->config['label'] ?? '';
	}

	/**
	 * Get the field type
	 *
	 * @return string
	 */
	public function get_type(): string {
		return $this->type;
	}

	/**
	 * Get the option name for storing this field's value
	 *
	 * By default, option names are prefixed with the context (page_id) to avoid collisions.
	 * Set 'use_name_prefix' => false in field config to use just the field name.
	 *
	 * @param string $prefix The prefix (usually page_id or context).
	 * @return string The option name to use for storage.
	 */
	public function get_option_name( string $prefix = '' ): string {
		$use_prefix = $this->get_config( 'use_name_prefix', true );

		if ( $use_prefix && ! empty( $prefix ) ) {
			return $prefix . '_' . $this->name;
		}

		return $this->name;
	}

	/**
	 * Check if this field uses name prefix
	 *
	 * @return bool
	 */
	public function uses_name_prefix(): bool {
		return (bool) $this->get_config( 'use_name_prefix', true );
	}

	/**
	 * Get field configuration
	 *
	 * @param string $key          Configuration key.
	 * @param mixed  $default_value Default value if key not found.
	 * @return mixed
	 */
	public function get_config( string $key, $default_value = null ) {
		return $this->config[ $key ] ?? $default_value;
	}

	/**
	 * Set field configuration
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
	 * Get all configuration
	 *
	 * @return array<string, mixed>
	 */
	public function get_all_config(): array {
		return $this->config;
	}

	/**
	 * Sanitize the input value
	 *
	 * Default implementation - field types should override as needed.
	 *
	 * @param mixed $input Raw input value.
	 * @return mixed
	 */
	public function sanitize( $input ) {
		// Default: sanitize as text
		if ( is_string( $input ) ) {
			if ( function_exists( 'sanitize_text_field' ) ) {
				return \sanitize_text_field( $input );
			}
			// Fallback sanitization if WordPress function not available
			$sanitized = wp_strip_all_tags( $input );
			$sanitized = trim( preg_replace( '/\s+/', ' ', $sanitized ) );
			return $sanitized;
		}
		return $input;
	}

	/**
	 * Validate the input value
	 *
	 * @param mixed $input Input value to validate.
	 * @return array
	 */
	public function validate( $input ): array {
		$errors = [];

		// Check required
		if ( ! empty( $this->config['required'] ) && empty( $input ) ) {
			/* translators: %s: field label */
			$errors[] = sprintf( $this->translate( '%s is required.' ), $this->get_label() );
		}

		// Apply custom validation rules
		foreach ( $this->validation_rules as $rule => $rule_value ) {
			$error = $this->apply_validation_rule( $rule, $rule_value, $input );
			if ( $error ) {
				$errors[] = $error;
			}
		}

		return [
			'valid'  => empty( $errors ),
			'errors' => $errors,
		];
	}

	/**
	 * Apply a specific validation rule
	 *
	 * @param string $rule       Rule name.
	 * @param mixed  $rule_value Rule value/parameter.
	 * @param mixed  $input      Input to validate.
	 * @return string|null Error message or null if valid.
	 */
	protected function apply_validation_rule( string $rule, $rule_value, $input ): ?string {
		switch ( $rule ) {
			case 'min':
				if ( is_numeric( $input ) && $input < $rule_value ) {
					/* translators: 1: field label, 2: minimum value */
					return sprintf( $this->translate( '%1$s must be at least %2$s.' ), $this->get_label(), $rule_value );
				}
				if ( is_string( $input ) && strlen( $input ) < $rule_value ) {
					/* translators: 1: field label, 2: minimum character length */
					return sprintf( $this->translate( '%1$s must be at least %2$s characters.' ), $this->get_label(), $rule_value );
				}
				break;

			case 'max':
				if ( is_numeric( $input ) && $input > $rule_value ) {
					/* translators: 1: field label, 2: maximum value */
					return sprintf( $this->translate( '%1$s must be at most %2$s.' ), $this->get_label(), $rule_value );
				}
				if ( is_string( $input ) && strlen( $input ) > $rule_value ) {
					/* translators: 1: field label, 2: maximum character length */
					return sprintf( $this->translate( '%1$s must be at most %2$s characters.' ), $this->get_label(), $rule_value );
				}
				break;

			case 'pattern':
				if ( is_string( $input ) && ! preg_match( $rule_value, $input ) ) {
					/* translators: %s: field label */
					return sprintf( $this->translate( '%s format is invalid.' ), $this->get_label() );
				}
				break;

			case 'email':
				// Skip email validation for empty values when field is not required
				if ( $rule_value && ! empty( $input ) ) {
					$is_valid_email = function_exists( 'is_email' )
						? \is_email( $input )
						: filter_var( $input, FILTER_VALIDATE_EMAIL );
					if ( ! $is_valid_email ) {
						/* translators: %s: field label */
						return sprintf( $this->translate( '%s must be a valid email address.' ), $this->get_label() );
					}
				}
				break;

			case 'url':
				// Skip URL validation for empty values when field is not required
				if ( $rule_value && ! empty( $input ) && ! filter_var( $input, FILTER_VALIDATE_URL ) ) {
					/* translators: %s: field label */
					return sprintf( $this->translate( '%s must be a valid URL.' ), $this->get_label() );
				}
				break;
		}

		return null;
	}

	/**
	 * Get the field schema
	 *
	 * @return array<string, mixed>
	 */
	public function get_schema(): array {
		return [
			'name'        => $this->name,
			'type'        => $this->type,
			'label'       => $this->get_label(),
			'description' => $this->config['description'] ?? '',
			'required'    => $this->config['required'] ?? false,
			'default'     => $this->config['default'] ?? '',
			'validation'  => $this->validation_rules,
		];
	}

	/**
	 * Escape attribute value
	 *
	 * @param string $text
	 * @return string
	 */
	protected function esc_attr( string $text ): string {
		if ( function_exists( 'esc_attr' ) ) {
			return \esc_attr( $text );
		}
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Escape HTML
	 *
	 * @param string $text
	 * @return string
	 */
	protected function esc_html( string $text ): string {
		if ( function_exists( 'esc_html' ) ) {
			return \esc_html( $text );
		}
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Translate text with fallback
	 *
	 * @param string $text       Text to translate.
	 * @param string $text_domain Text domain.
	 * @return string Translated text or original if WordPress not available.
	 */
	protected function translate( string $text, string $text_domain = 'cassette-cmf' ): string {
		if ( function_exists( '__' ) ) {
			// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.WP.I18n.NonSingularStringLiteralDomain -- Dynamic text for abstraction layer.
			return \__( $text, $text_domain );
		}
		// Fallback when WordPress not loaded (e.g., in tests).
		return $text;
	}

	/**
	 * Render wrapper start
	 *
	 * @return string
	 */
	protected function render_wrapper_start(): string {
		$classes = [ 'cassette-cmf-field', 'cassette-cmf-field-' . $this->type ];

		if ( ! empty( $this->config['class'] ) ) {
			$classes[] = $this->config['class'];
		}

		if ( ! empty( $this->config['required'] ) ) {
			$classes[] = 'cassette-cmf-field-required';
		}

		return sprintf(
			'<div class="%s" data-field-name="%s" data-field-type="%s">',
			$this->esc_attr( implode( ' ', $classes ) ),
			$this->esc_attr( $this->name ),
			$this->esc_attr( $this->type )
		);
	}

	/**
	 * Render field wrapper end
	 *
	 * @return string
	 */
	protected function render_wrapper_end(): string {
		return '</div>';
	}

	/**
	 * Render field label
	 *
	 * @param bool $hide_label Whether to hide the label (for contexts where label is rendered elsewhere).
	 * @return string
	 */
	protected function render_label( bool $hide_label = false ): string {
		// Check if label should be hidden
		if ( $hide_label ) {
			return '';
		}

		$label = $this->get_label();

		if ( empty( $label ) ) {
			return '';
		}

		$required = ! empty( $this->config['required'] ) ? ' <span class="required">*</span>' : '';

		return sprintf(
			'<label for="%s" class="cassette-cmf-field-label">%s%s</label>',
			$this->esc_attr( $this->get_field_id() ),
			$this->esc_html( $label ),
			$required
		);
	}

	/**
	 * Render field description
	 *
	 * @return string
	 */
	protected function render_description(): string {
		$description = $this->config['description'] ?? '';

		if ( empty( $description ) ) {
			return '';
		}

		return sprintf(
			'<p class="description cassette-cmf-field-description">%s</p>',
			$this->esc_html( $description )
		);
	}

	/**
	 * Get field HTML ID
	 *
	 * @return string
	 */
	protected function get_field_id(): string {
		$key = function_exists( 'sanitize_key' )
			? \sanitize_key( $this->name )
			: strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $this->name ) );
		return 'cassette-cmf-field-' . $key;
	}

	/**
	 * Build HTML attributes string
	 *
	 * @param array<string, mixed> $attributes Attributes array.
	 * @return string
	 */
	protected function build_attributes( array $attributes ): string {
		$attr_string = '';

		foreach ( $attributes as $key => $value ) {
			if ( is_bool( $value ) ) {
				if ( $value ) {
					$attr_string .= ' ' . $this->esc_attr( $key );
				}
			} else {
				$attr_string .= sprintf( ' %s="%s"', $this->esc_attr( $key ), $this->esc_attr( (string) $value ) );
			}
		}

		return $attr_string;
	}

	/**
	 * Enqueue field assets (CSS and JS)
	 *
	 * Default implementation does nothing. Override in field classes
	 * that need to load custom assets.
	 *
	 * Example:
	 * ```php
	 * public function enqueue_assets(): void {
	 *     wp_enqueue_style( 'my-field-style', plugin_dir_url( __FILE__ ) . 'assets/style.css' );
	 *     wp_enqueue_script( 'my-field-script', plugin_dir_url( __FILE__ ) . 'assets/script.js', ['jquery'], '1.0', true );
	 * }
	 * ```
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		// Default: no assets to enqueue
		// Override in concrete field classes that need custom assets
	}

	/**
	 * Render the field HTML
	 *
	 * Must be implemented by concrete field classes.
	 *
	 * @param mixed $value Current field value.
	 * @return string
	 */
	abstract public function render( $value = null ): string;
}
