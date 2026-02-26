<?php
/**
 * New Taxonomy Handler
 *
 * Handles registration of new custom taxonomies and their term fields.
 *
 * @package Pedalcms\CassetteCmf
 * @since 1.0.0
 */

namespace Pedalcms\CassetteCmf\Core\Handlers;

use Pedalcms\CassetteCmf\Field\Field_Interface;
use Pedalcms\CassetteCmf\Field\Container_Field_Interface;

/**
 * Class New_Taxonomy_Handler
 *
 * Manages registration of new custom taxonomies and adding fields
 * to their term add/edit screens.
 */
class New_Taxonomy_Handler extends Abstract_Handler {

	/**
	 * Registered taxonomies with their args
	 *
	 * @var array<string, array>
	 */
	private array $taxonomies = [];

	/**
	 * Initialize WordPress hooks
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		if ( $this->hooks_initialized || ! $this->has_wordpress() ) {
			return;
		}

		add_action( 'init', [ $this, 'register_taxonomies' ], 10 );
		add_action( 'admin_init', [ $this, 'register_taxonomy_fields' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		$this->hooks_initialized = true;
	}

	/**
	 * Add a taxonomy for registration
	 *
	 * @param string $taxonomy    Taxonomy slug.
	 * @param array  $args        Taxonomy arguments.
	 * @param array  $object_type Optional. Object types to associate (default: ['post']).
	 * @return void
	 */
	public function add_taxonomy( string $taxonomy, array $args, array $object_type = [ 'post' ] ): void {
		$this->taxonomies[ $taxonomy ] = [
			'args'        => $args,
			'object_type' => $object_type,
		];
	}

	/**
	 * Get registered taxonomy info
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @return array|null Taxonomy configuration or null.
	 */
	public function get_taxonomy( string $taxonomy ): ?array {
		return $this->taxonomies[ $taxonomy ] ?? null;
	}

	/**
	 * Get all registered taxonomies
	 *
	 * @return array<string, array>
	 */
	public function get_taxonomies(): array {
		return $this->taxonomies;
	}

	/**
	 * Register all taxonomies with WordPress
	 *
	 * @return void
	 */
	public function register_taxonomies(): void {
		if ( ! function_exists( 'register_taxonomy' ) ) {
			return;
		}

		foreach ( $this->taxonomies as $taxonomy => $config ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				register_taxonomy( $taxonomy, $config['object_type'], $config['args'] );
			}
		}
	}

	/**
	 * Register hooks for taxonomy term fields
	 *
	 * @return void
	 */
	public function register_taxonomy_fields(): void {
		foreach ( $this->fields as $taxonomy => $fields ) {
			if ( empty( $fields ) ) {
				continue;
			}

			// Only handle taxonomies we're creating
			if ( ! isset( $this->taxonomies[ $taxonomy ] ) ) {
				continue;
			}

			// Add form fields hook (new term form)
			add_action( "{$taxonomy}_add_form_fields", [ $this, 'render_add_term_fields' ], 10, 1 );

			// Edit form fields hook (edit term form)
			add_action( "{$taxonomy}_edit_form_fields", [ $this, 'render_edit_term_fields' ], 10, 2 );

			// Save hooks
			add_action( "created_{$taxonomy}", [ $this, 'save_term_fields' ], 10, 2 );
			add_action( "edited_{$taxonomy}", [ $this, 'save_term_fields' ], 10, 2 );

			// Column hooks for displaying custom field values
			add_filter( "manage_edit-{$taxonomy}_columns", [ $this, 'add_term_columns' ] );
			add_filter( "manage_{$taxonomy}_custom_column", [ $this, 'render_term_column' ], 10, 3 );
		}
	}

	/**
	 * Render fields on the add term form
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @return void
	 */
	public function render_add_term_fields( string $taxonomy ): void {
		if ( ! $this->has_fields( $taxonomy ) ) {
			return;
		}

		$this->render_nonce_field(
			'save_' . $taxonomy . '_term_fields',
			$taxonomy . '_term_fields_nonce'
		);

		foreach ( $this->get_fields( $taxonomy ) as $field ) {
			if ( $this->is_nested_field( $taxonomy, $field->get_name() ) ) {
				continue;
			}

			// Container fields don't store values directly
			if ( $field instanceof Container_Field_Interface ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Field handles escaping.
				echo $field->render( null );
				continue;
			}

			$this->render_add_form_field( $field );
		}
	}

	/**
	 * Render a single field for the add term form
	 *
	 * @param Field_Interface $field Field instance.
	 * @return void
	 */
	private function render_add_form_field( Field_Interface $field ): void {
		echo '<div class="form-field cassette-cmf-term-field">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Field handles escaping.
		echo $field->render( '' );
		echo '</div>';
	}

	/**
	 * Render fields on the edit term form
	 *
	 * @param \WP_Term $term     Term object.
	 * @param string   $taxonomy Taxonomy slug.
	 * @return void
	 */
	public function render_edit_term_fields( \WP_Term $term, string $taxonomy ): void {
		if ( ! $this->has_fields( $taxonomy ) ) {
			return;
		}

		$this->render_nonce_field(
			'save_' . $taxonomy . '_term_fields',
			$taxonomy . '_term_fields_nonce'
		);

		foreach ( $this->get_fields( $taxonomy ) as $field ) {
			if ( $this->is_nested_field( $taxonomy, $field->get_name() ) ) {
				continue;
			}

			// Container fields don't store values directly
			if ( $field instanceof Container_Field_Interface ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Field handles escaping.
				echo $field->render( (string) $term->term_id );
				continue;
			}

			$this->render_edit_form_field( $field, $term );
		}
	}

	/**
	 * Render a single field for the edit term form
	 *
	 * @param Field_Interface $field Field instance.
	 * @param \WP_Term        $term  Term object.
	 * @return void
	 */
	private function render_edit_form_field( Field_Interface $field, \WP_Term $term ): void {
		$field_name = $field->get_name();
		$value      = get_term_meta( $term->term_id, $field_name, true );

		echo '<tr class="form-field cassette-cmf-term-field">';
		echo '<th scope="row">';
		echo '<label for="' . esc_attr( $field_name ) . '">' . esc_html( $field->get_label() ) . '</label>';
		echo '</th>';
		echo '<td>';

		// Render field without the outer wrapper and label
		$field_html = $field->render( $value );
		// Remove the label as it's already in the <th>
		$field_html = preg_replace( '/<label[^>]*class="[^"]*cassette-cmf-field-label[^"]*"[^>]*>.*?<\/label>/s', '', $field_html, 1 );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Field handles escaping.
		echo $field_html;

		echo '</td>';
		echo '</tr>';
	}

	/**
	 * Save term fields
	 *
	 * @param int $term_id Term ID.
	 * @param int $tt_id   Term taxonomy ID.
	 * @return void
	 */
	public function save_term_fields( int $term_id, int $tt_id ): void {
		$term = get_term( $term_id );
		if ( ! $term instanceof \WP_Term ) {
			return;
		}

		$taxonomy = $term->taxonomy;

		if ( ! $this->has_fields( $taxonomy ) ) {
			return;
		}

		// Verify nonce
		$nonce_name = $taxonomy . '_term_fields_nonce';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified on next lines.
		if ( ! isset( $_POST[ $nonce_name ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified here.
		$nonce = sanitize_text_field( wp_unslash( $_POST[ $nonce_name ] ) );
		if ( ! $this->verify_nonce( $nonce, 'save_' . $taxonomy . '_term_fields' ) ) {
			return;
		}

		// Check capabilities
		if ( ! $this->can_edit_term( $taxonomy ) ) {
			return;
		}

		// Save each field
		foreach ( $this->get_fields( $taxonomy ) as $field ) {
			if ( $field instanceof Container_Field_Interface ) {
				continue;
			}

			$this->save_single_term_field( $field, $term_id, $taxonomy );
		}
	}

	/**
	 * Check if user can edit terms
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @return bool
	 */
	private function can_edit_term( string $taxonomy ): bool {
		if ( ! function_exists( 'current_user_can' ) ) {
			return true;
		}

		$taxonomy_obj = get_taxonomy( $taxonomy );
		if ( ! $taxonomy_obj ) {
			return false;
		}

		$capability = $taxonomy_obj->cap->edit_terms ?? 'manage_categories';

		return current_user_can( $capability );
	}

	/**
	 * Save a single term field value
	 *
	 * @param Field_Interface $field    Field instance.
	 * @param int             $term_id  Term ID.
	 * @param string          $taxonomy Taxonomy slug.
	 * @return void
	 */
	private function save_single_term_field( Field_Interface $field, int $term_id, string $taxonomy ): void {
		$field_name = $field->get_name();

		// Check if value was submitted.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in save_term_fields().
		if ( ! isset( $_POST[ $field_name ] ) ) {
			// Delete meta if it existed.
			if ( metadata_exists( 'term', $term_id, $field_name ) ) {
				delete_term_meta( $term_id, $field_name );
			}
			return;
		}

		// Get and sanitize value.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified, sanitization happens below.
		$raw_value = wp_unslash( $_POST[ $field_name ] );

		// Apply filters
		$value = $this->apply_before_save_filters( $raw_value, $field_name, $taxonomy );
		if ( null === $value ) {
			return;
		}

		// Sanitize and validate
		$result = $this->sanitize_and_validate( $field, $value );

		if ( $result['valid'] ) {
			update_term_meta( $term_id, $field_name, $result['value'] );
		}
	}

	/**
	 * Add columns for term fields
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_term_columns( array $columns ): array {
		// Get the current taxonomy from the screen
		$screen = get_current_screen();
		if ( ! $screen || empty( $screen->taxonomy ) ) {
			return $columns;
		}

		$taxonomy = $screen->taxonomy;

		if ( ! $this->has_fields( $taxonomy ) ) {
			return $columns;
		}

		foreach ( $this->get_fields( $taxonomy ) as $field ) {
			if ( $this->is_nested_field( $taxonomy, $field->get_name() ) ) {
				continue;
			}

			// Skip container fields
			if ( $field instanceof Container_Field_Interface ) {
				continue;
			}

			// Only add if show_in_columns is enabled
			$show_in_columns = $field->get_config( 'show_in_columns', false );
			if ( ! empty( $show_in_columns ) ) {
				$columns[ $field->get_name() ] = $field->get_label();
			}
		}

		return $columns;
	}

	/**
	 * Render term column content
	 *
	 * @param string $content     Column content.
	 * @param string $column_name Column name.
	 * @param int    $term_id     Term ID.
	 * @return string Modified content.
	 */
	public function render_term_column( string $content, string $column_name, int $term_id ): string {
		$term = get_term( $term_id );
		if ( ! $term instanceof \WP_Term ) {
			return $content;
		}

		$taxonomy = $term->taxonomy;

		if ( ! $this->has_fields( $taxonomy ) ) {
			return $content;
		}

		$fields = $this->get_fields( $taxonomy );

		if ( ! isset( $fields[ $column_name ] ) ) {
			return $content;
		}

		$field = $fields[ $column_name ];
		$value = get_term_meta( $term_id, $column_name, true );

		// Format value for display
		if ( is_array( $value ) ) {
			return esc_html( implode( ', ', $value ) );
		}

		return esc_html( (string) $value );
	}

	/**
	 * Enqueue assets for taxonomy term edit screens
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || empty( $screen->taxonomy ) ) {
			return;
		}

		$taxonomy = $screen->taxonomy;

		// Check if we have fields for this taxonomy and it's one we registered
		if ( isset( $this->taxonomies[ $taxonomy ] ) && $this->has_fields( $taxonomy ) ) {
			$this->enqueue_field_assets( $taxonomy );
			$this->enqueue_common_assets();
		}
	}

	/**
	 * Enqueue assets for fields
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @return void
	 */
	private function enqueue_field_assets( string $taxonomy ): void {
		foreach ( $this->get_fields( $taxonomy ) as $field ) {
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
