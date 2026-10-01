<?php
/**
 * AbstractField base class for Cassette-CMF
 *
 * Provides common functionality and helpers for all field types.
 * Field classes should extend this to get standard behavior.
 *
 * @package PedalCMS\CassetteCMF
 * @since 1.0.0
 */

namespace PedalCMS\CassetteCMF\Field;

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
	 * Whether this field type renders a single control that a settings-page
	 * title can be wrapped in a <label for="..."> for.
	 *
	 * False for field types that render a group of controls (checkbox,
	 * radio), a container with no single focusable target of its own
	 * (tabs, metabox, group, repeater), or no control at all (custom_html).
	 *
	 * @var bool
	 */
	protected bool $uses_label_wrapper = true;

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
			'conditional'     => [],
			'attributes'      => [],
			'use_name_prefix' => true,
			'prepend'         => '',
			'append'          => '',
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
	 * Check whether this field type has a single control that a
	 * settings-page title can be wrapped in a <label for="..."> for.
	 *
	 * @return bool
	 */
	public function uses_label_wrapper(): bool {
		return $this->uses_label_wrapper;
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
	 * Get normalized conditional configuration.
	 *
	 * @return array<string, mixed>
	 */
	public function get_conditional_config(): array {
		return $this->normalize_conditional_config();
	}

	/**
	 * Check whether this field should validate for the current submission context.
	 *
	 * @param array<string, mixed>|null $submission_context Optional submitted data context.
	 * @return bool
	 */
	public function should_validate( ?array $submission_context = null ): bool {
		return $this->is_condition_met( $submission_context );
	}

	/**
	 * Check whether the field's conditional rules evaluate to visible.
	 *
	 * @param array<string, mixed>|null $submission_context Optional submitted data context.
	 * @return bool
	 */
	public function is_condition_met( ?array $submission_context = null ): bool {
		$conditional = $this->get_conditional_config();

		if ( empty( $conditional ) ) {
			return true;
		}

		if ( null === $submission_context ) {
			$submission_context = $this->get_submission_context();
		}

		$results = [];
		foreach ( $conditional['rules'] as $rule ) {
			$actual_value = $this->get_conditional_context_value( $rule['field'], $submission_context );
			$results[]    = $this->evaluate_conditional_rule( $rule, $actual_value );
		}

		if ( empty( $results ) ) {
			return true;
		}

		if ( 'OR' === $conditional['relation'] ) {
			return in_array( true, $results, true );
		}

		return ! in_array( false, $results, true );
	}

	/**
	 * Normalize conditional configuration.
	 *
	 * @param array<string, mixed>|null $config Optional config to normalize.
	 * @return array<string, mixed>
	 */
	protected function normalize_conditional_config( ?array $config = null ): array {
		$config = $config ?? $this->config;

		$raw_conditional = $config['conditional'] ?? [];

		if ( empty( $raw_conditional ) ) {
			return [];
		}

		if ( ! is_array( $raw_conditional ) ) {
			return [];
		}

		if ( isset( $raw_conditional['field'] ) ) {
			$raw_conditional = [
				'relation' => 'AND',
				'rules'    => [ $raw_conditional ],
			];
		}

		$relation = strtoupper( (string) ( $raw_conditional['relation'] ?? 'AND' ) );
		if ( ! in_array( $relation, [ 'AND', 'OR' ], true ) ) {
			$relation = 'AND';
		}

		$rules = [];
		foreach ( $raw_conditional['rules'] ?? [] as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$normalized_rule = $this->normalize_conditional_rule( $rule );
			if ( ! empty( $normalized_rule ) ) {
				$rules[] = $normalized_rule;
			}
		}

		if ( empty( $rules ) ) {
			return [];
		}

		return [
			'relation' => $relation,
			'rules'    => $rules,
		];
	}

	/**
	 * Normalize a conditional rule.
	 *
	 * @param array<string, mixed> $rule Rule configuration.
	 * @return array<string, mixed>
	 */
	protected function normalize_conditional_rule( array $rule ): array {
		$field = isset( $rule['field'] ) ? trim( (string) $rule['field'] ) : '';

		if ( '' === $field ) {
			return [];
		}

		$operator = $this->normalize_conditional_operator( (string) ( $rule['operator'] ?? $rule['compare'] ?? '==' ) );

		$normalized_rule = [
			'field'    => $field,
			'operator' => $operator,
		];

		if ( array_key_exists( 'value', $rule ) ) {
			$normalized_rule['value'] = $rule['value'];
		} elseif ( array_key_exists( 'values', $rule ) ) {
			$normalized_rule['value'] = $rule['values'];
		}

		if ( in_array( $operator, [ 'empty', 'not_empty' ], true ) ) {
			unset( $normalized_rule['value'] );
		}

		return $normalized_rule;
	}

	/**
	 * Normalize a conditional operator.
	 *
	 * @param string $operator Operator value.
	 * @return string
	 */
	protected function normalize_conditional_operator( string $operator ): string {
		$operator = strtolower( trim( $operator ) );

		$aliases = [
			'='         => '==',
			'eq'        => '==',
			'equals'    => '==',
			'==='       => '==',
			'neq'       => '!=',
			'not'       => '!=',
			'!=='       => '!=',
			'includes'  => 'in',
			'contains'  => 'in',
			'excludes'  => 'not_in',
			'between'   => 'in',
			'is_empty'  => 'empty',
			'empty'     => 'empty',
			'not_empty' => 'not_empty',
			'filled'    => 'not_empty',
		];

		$operator = $aliases[ $operator ] ?? $operator;

		$valid_operators = [ '==', '!=', '>', '>=', '<', '<=', 'in', 'not_in', 'empty', 'not_empty' ];

		if ( ! in_array( $operator, $valid_operators, true ) ) {
			return '==';
		}

		return $operator;
	}

	/**
	 * Resolve a controlling field value from the submission context.
	 *
	 * @param string               $field_name Controlling field name.
	 * @param array<string, mixed> $submission_context Submitted data context.
	 * @return mixed
	 */
	protected function get_conditional_context_value( string $field_name, array $submission_context ) {
		if ( array_key_exists( $field_name, $submission_context ) ) {
			return $submission_context[ $field_name ];
		}

		foreach ( $submission_context as $submitted_name => $submitted_value ) {
			if ( ! is_string( $submitted_name ) ) {
				continue;
			}

			if ( $this->matches_conditional_field_name( $submitted_name, $field_name ) ) {
				return $submitted_value;
			}
		}

		return null;
	}

	/**
	 * Determine whether a submitted field name matches a conditional controller name.
	 *
	 * @param string $submitted_name Submitted field name.
	 * @param string $field_name Conditional rule field name.
	 * @return bool
	 */
	protected function matches_conditional_field_name( string $submitted_name, string $field_name ): bool {
		return $submitted_name === $field_name
			|| $submitted_name === $field_name . '[]'
			|| $this->string_ends_with( $submitted_name, '_' . $field_name )
			|| $this->string_ends_with( $submitted_name, '_' . $field_name . '[]' )
			|| $this->string_ends_with( $submitted_name, '[' . $field_name . ']' )
			|| $this->string_ends_with( $submitted_name, '[' . $field_name . '][]' );
	}

	/**
	 * Polyfill-style string ends-with helper for PHP 7.4 compatibility.
	 *
	 * @param string $value Input string.
	 * @param string $suffix Suffix to test.
	 * @return bool
	 */
	protected function string_ends_with( string $value, string $suffix ): bool {
		if ( '' === $suffix ) {
			return true;
		}

		return substr( $value, -strlen( $suffix ) ) === $suffix;
	}

	/**
	 * Evaluate a single conditional rule.
	 *
	 * @param array<string, mixed> $rule Rule configuration.
	 * @param mixed                $actual_value Current submitted value.
	 * @return bool
	 */
	protected function evaluate_conditional_rule( array $rule, $actual_value ): bool {
		$operator       = $rule['operator'] ?? '==';
		$expected_value = $rule['value'] ?? null;

		switch ( $operator ) {
			case 'empty':
				return $this->is_empty_value( $actual_value );

			case 'not_empty':
				return ! $this->is_empty_value( $actual_value );

			case 'in':
				return $this->evaluate_inclusion_rule( $actual_value, $expected_value );

			case 'not_in':
				return ! $this->evaluate_inclusion_rule( $actual_value, $expected_value );

			case '>':
			case '>=':
			case '<':
			case '<=':
				return $this->evaluate_numeric_rule( $actual_value, $expected_value, $operator );

			case '!=':
				return ! $this->values_match( $actual_value, $expected_value );

			case '==':
			default:
				return $this->values_match( $actual_value, $expected_value );
		}
	}

	/**
	 * Evaluate inclusion-style conditional rules.
	 *
	 * @param mixed $actual_value Current submitted value.
	 * @param mixed $expected_value Expected value or values.
	 * @return bool
	 */
	protected function evaluate_inclusion_rule( $actual_value, $expected_value ): bool {
		$expected_values = is_array( $expected_value ) ? $expected_value : [ $expected_value ];

		if ( is_array( $actual_value ) ) {
			foreach ( $actual_value as $value ) {
				if ( $this->evaluate_inclusion_rule( $value, $expected_values ) ) {
					return true;
				}
			}

			return false;
		}

		foreach ( $expected_values as $expected ) {
			if ( $this->values_match( $actual_value, $expected ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Evaluate numeric-style conditional rules.
	 *
	 * @param mixed  $actual_value Current submitted value.
	 * @param mixed  $expected_value Expected threshold.
	 * @param string $operator Comparison operator.
	 * @return bool
	 */
	protected function evaluate_numeric_rule( $actual_value, $expected_value, string $operator ): bool {
		if ( is_array( $actual_value ) ) {
			$actual_value = reset( $actual_value );
		}

		if ( ! is_numeric( $actual_value ) || ! is_numeric( $expected_value ) ) {
			return false;
		}

		$actual_value   = (float) $actual_value;
		$expected_value = (float) $expected_value;

		switch ( $operator ) {
			case '>':
				return $actual_value > $expected_value;
			case '>=':
				return $actual_value >= $expected_value;
			case '<':
				return $actual_value < $expected_value;
			case '<=':
				return $actual_value <= $expected_value;
		}

		return false;
	}

	/**
	 * Check whether two values match for conditional evaluation.
	 *
	 * @param mixed $actual_value Current submitted value.
	 * @param mixed $expected_value Expected value.
	 * @return bool
	 */
	protected function values_match( $actual_value, $expected_value ): bool {
		if ( is_array( $actual_value ) ) {
			foreach ( $actual_value as $value ) {
				if ( $this->values_match( $value, $expected_value ) ) {
					return true;
				}
			}

			return false;
		}

		if ( is_bool( $actual_value ) ) {
			$actual_value = $actual_value ? '1' : '0';
		}

		if ( is_bool( $expected_value ) ) {
			$expected_value = $expected_value ? '1' : '0';
		}

		return (string) $actual_value === (string) $expected_value;
	}

	/**
	 * Check whether a value should be treated as empty.
	 *
	 * @param mixed $value Value to inspect.
	 * @return bool
	 */
	protected function is_empty_value( $value ): bool {
		if ( is_array( $value ) ) {
			return 0 === count( array_filter( $value, [ $this, 'is_non_empty_value' ] ) );
		}

		return null === $value || '' === $value;
	}

	/**
	 * Check whether a value is non-empty.
	 *
	 * @param mixed $value Value to inspect.
	 * @return bool
	 */
	protected function is_non_empty_value( $value ): bool {
		return ! $this->is_empty_value( $value );
	}

	/**
	 * Get wrapper data attributes for conditional configuration.
	 *
	 * @return array<string, string>
	 */
	protected function get_wrapper_data_attributes(): array {
		$attributes  = [];
		$conditional = $this->get_conditional_config();

		if ( empty( $conditional ) ) {
			return $attributes;
		}

		$json = $this->encode_json_attribute( $conditional );

		if ( false === $json ) {
			return $attributes;
		}

		$attributes['data-conditional'] = $json;

		return $attributes;
	}

	/**
	 * Get the current submitted form data.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_submission_context(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only access for conditional evaluation; save handlers verify nonces before persisting values.
		return $_POST;
	}

	/**
	 * Encode attribute payloads as JSON.
	 *
	 * @param array<string, mixed> $value Value to encode.
	 * @return string|false
	 */
	protected function encode_json_attribute( array $value ) {
		if ( function_exists( 'wp_json_encode' ) ) {
			return \wp_json_encode( $value );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Fallback when WordPress is not loaded, such as unit tests.
		return json_encode( $value );
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
			'conditional' => $this->get_conditional_config(),
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
		$classes    = [ 'cassette-cmf-field', 'cassette-cmf-field-' . $this->type ];
		$attributes = [
			'data-field-name' => $this->name,
			'data-field-type' => $this->type,
		];

		if ( ! empty( $this->config['class'] ) ) {
			$classes[] = $this->config['class'];
		}

		if ( ! empty( $this->config['required'] ) ) {
			$classes[] = 'cassette-cmf-field-required';
		}

		if ( ! empty( $this->get_conditional_config() ) ) {
			$classes[]  = 'cassette-cmf-field-conditional';
			$attributes = array_merge( $attributes, $this->get_wrapper_data_attributes() );
		}

		$attributes['class'] = implode( ' ', $classes );

		return '<div' . $this->build_attributes( $attributes ) . '>';
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
	 * Render input wrapper start
	 *
	 * Wraps a field's input element in a span, giving consumers a hook
	 * for adornments (icons, prefixes) or custom focus treatments without
	 * needing to restyle the input itself.
	 *
	 * By default the wrapper carries no layout styling, so it cannot
	 * affect the input's existing width/sizing. Pass $has_adornment when
	 * the field renders a prepend/append, which switches on the flex
	 * layout needed to sit them inline with the input.
	 *
	 * @param bool $has_adornment Whether a prepend/append is present.
	 * @return string
	 */
	protected function render_input_wrapper_start( bool $has_adornment = false ): string {
		$class = 'cassette-cmf-input-wrapper';

		if ( $has_adornment ) {
			$class .= ' has-adornment';
		}

		return '<span class="' . $this->esc_attr( $class ) . '">';
	}

	/**
	 * Render input wrapper end
	 *
	 * @return string
	 */
	protected function render_input_wrapper_end(): string {
		return '</span>';
	}

	/**
	 * Render the prepend adornment
	 *
	 * Content may include limited HTML (e.g. <strong>, <abbr>) and is
	 * sanitized with wp_kses_post() — the same allowance as post content,
	 * not raw/unescaped output.
	 *
	 * @return string
	 */
	protected function render_prepend(): string {
		$prepend = $this->config['prepend'] ?? '';

		if ( empty( $prepend ) ) {
			return '';
		}

		return '<span class="cassette-cmf-field-prepend">' . $this->kses_post( $prepend ) . '</span>';
	}

	/**
	 * Render the append adornment
	 *
	 * @return string
	 */
	protected function render_append(): string {
		$append = $this->config['append'] ?? '';

		if ( empty( $append ) ) {
			return '';
		}

		return '<span class="cassette-cmf-field-append">' . $this->kses_post( $append ) . '</span>';
	}

	/**
	 * Sanitize a string of limited HTML for safe output
	 *
	 * @param string $content Content to sanitize.
	 * @return string
	 */
	protected function kses_post( string $content ): string {
		if ( function_exists( 'wp_kses_post' ) ) {
			return \wp_kses_post( $content );
		}

		if ( function_exists( 'wp_strip_all_tags' ) ) {
			return \wp_strip_all_tags( $content );
		}

		// Fallback when WordPress is not loaded (e.g., in tests).
		return (string) preg_replace( '/<[^>]*>/', '', $content );
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
	public function get_field_id(): string {
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
