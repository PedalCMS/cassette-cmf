<?php
/**
 * Existing Post Type Handler
 *
 * Handles adding fields to existing WordPress post types.
 *
 * @package Pedalcms\CassetteCmf
 * @since 1.0.0
 */

namespace Pedalcms\CassetteCmf\Core\Handlers;

use Pedalcms\CassetteCmf\Field\Field_Interface;
use Pedalcms\CassetteCmf\Field\Container_Field_Interface;
use Pedalcms\CassetteCmf\Field\Fields\Metabox_Field;

/**
 * Class Existing_Post_Type_Handler
 *
 * Manages field registration for existing WordPress post types
 * like 'post', 'page', or any other registered post type.
 */
class Existing_Post_Type_Handler extends Abstract_Handler {

	/**
	 * Initialize WordPress hooks
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		if ( $this->hooks_initialized || ! $this->has_wordpress() ) {
			return;
		}

		add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
		add_action( 'save_post', [ $this, 'save_post_fields' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		$this->hooks_initialized = true;
	}

	/**
	 * Check if a post type exists in WordPress
	 *
	 * @param string $post_type Post type slug.
	 * @return bool
	 */
	public function post_type_exists( string $post_type ): bool {
		return function_exists( 'post_type_exists' ) && post_type_exists( $post_type );
	}

	/**
	 * Register meta boxes for existing post types
	 *
	 * @return void
	 */
	public function register_meta_boxes(): void {
		if ( ! function_exists( 'add_meta_box' ) ) {
			return;
		}

		foreach ( $this->fields as $post_type => $fields ) {
			if ( empty( $fields ) ) {
				continue;
			}

			// Verify this is actually an existing post type
			if ( ! $this->post_type_exists( $post_type ) ) {
				continue;
			}

			$this->register_post_type_meta_boxes( $post_type );
		}
	}

	/**
	 * Register meta boxes for a specific post type
	 *
	 * @param string $post_type Post type slug.
	 * @return void
	 */
	private function register_post_type_meta_boxes( string $post_type ): void {
		$metabox_fields = [];
		$regular_fields = [];

		// Separate Metabox_Field containers from regular fields
		foreach ( $this->get_fields( $post_type ) as $field ) {
			if ( $this->is_nested_field( $post_type, $field->get_name() ) ) {
				continue;
			}

			if ( $field instanceof Metabox_Field ) {
				$metabox_fields[] = $field;
			} else {
				$regular_fields[] = $field;
			}
		}

		// Register MetaboxField containers as meta boxes
		$registered_ids = [];
		foreach ( $metabox_fields as $field ) {
			$metabox_id = $field->get_metabox_id();

			if ( in_array( $metabox_id, $registered_ids, true ) ) {
				continue;
			}

			add_meta_box(
				$metabox_id,
				$field->get_metabox_title(),
				[ $this, 'render_metabox_container' ],
				$post_type,
				$field->get_context(),
				$field->get_priority(),
				[ 'metabox_field' => $field ]
			);

			$registered_ids[] = $metabox_id;
		}

		// Register default meta box for regular fields
		if ( ! empty( $regular_fields ) ) {
			$post_type_obj = get_post_type_object( $post_type );
			$label         = $post_type_obj->labels->singular_name ?? ucfirst( $post_type );

			add_meta_box(
				$post_type . '_cmf_fields',
				$label . ' Additional Fields',
				[ $this, 'render_default_meta_box' ],
				$post_type,
				'normal',
				'high',
				[ 'fields' => $regular_fields ]
			);
		}
	}

	/**
	 * Render a MetaboxField container
	 *
	 * @param \WP_Post $post Post object.
	 * @param array    $args Metabox arguments.
	 * @return void
	 */
	public function render_metabox_container( \WP_Post $post, array $args ): void {
		if ( ! isset( $args['args']['metabox_field'] ) ) {
			return;
		}

		$field = $args['args']['metabox_field'];

		if ( ! $field instanceof Metabox_Field ) {
			return;
		}

		// Render nonce once per post type
		$this->render_nonce_once( $post->post_type );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $field->render( null );
	}

	/**
	 * Render default meta box with regular fields
	 *
	 * @param \WP_Post $post Post object.
	 * @param array    $args Metabox arguments.
	 * @return void
	 */
	public function render_default_meta_box( \WP_Post $post, array $args ): void {
		$fields = $args['args']['fields'] ?? [];

		if ( empty( $fields ) ) {
			return;
		}

		// Render nonce
		$this->render_nonce_once( $post->post_type );

		echo '<div class="cassette-cmf-fields">';

		foreach ( $fields as $field ) {
			if ( ! $field instanceof Field_Interface ) {
				continue;
			}

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $this->render_cpt_field_html( $field, $post->ID );
		}

		echo '</div>';
	}

	/**
	 * Render nonce field once per post type
	 *
	 * @param string $post_type Post type slug.
	 * @return void
	 */
	private function render_nonce_once( string $post_type ): void {
		static $rendered = [];

		if ( isset( $rendered[ $post_type ] ) ) {
			return;
		}

		$this->render_nonce_field(
			'save_' . $post_type . '_fields',
			$post_type . '_fields_nonce'
		);

		$rendered[ $post_type ] = true;
	}

	/**
	 * Save post fields
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_post_fields( $post_id ): void {
		$post_id = (int) $post_id;

		// Skip autosaves and revisions
		if ( $this->should_skip_save( $post_id ) ) {
			return;
		}

		$post_type = get_post_type( $post_id );

		if ( ! $post_type || ! $this->has_fields( $post_type ) ) {
			return;
		}

		// Verify nonce.
		$nonce_name = $post_type . '_fields_nonce';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is being verified on the next lines.
		if ( ! isset( $_POST[ $nonce_name ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is being verified here.
		$nonce = sanitize_text_field( wp_unslash( $_POST[ $nonce_name ] ) );
		if ( ! $this->verify_nonce( $nonce, 'save_' . $post_type . '_fields' ) ) {
			return;
		}

		// Check permissions
		if ( ! $this->can_edit_post( $post_id, $post_type ) ) {
			return;
		}

		// Save each field
		foreach ( $this->get_fields( $post_type ) as $field ) {
			if ( $field instanceof Container_Field_Interface ) {
				// Container fields don't store values
				continue;
			}

			$this->save_single_field( $field, $post_id, $post_type );
		}
	}

	/**
	 * Check if save should be skipped
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function should_skip_save( int $post_id ): bool {
		if ( function_exists( 'wp_is_post_autosave' ) && wp_is_post_autosave( $post_id ) ) {
			return true;
		}

		if ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if user can edit post
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Post type.
	 * @return bool
	 */
	private function can_edit_post( int $post_id, string $post_type ): bool {
		if ( ! function_exists( 'current_user_can' ) ) {
			return true;
		}

		$post_type_obj = get_post_type_object( $post_type );
		$capability    = $post_type_obj->cap->edit_post ?? 'edit_post';

		return current_user_can( $capability, $post_id );
	}

	/**
	 * Save a single field value
	 *
	 * @param Field_Interface $field     Field instance.
	 * @param int             $post_id   Post ID.
	 * @param string          $post_type Post type.
	 * @return void
	 */
	private function save_single_field( Field_Interface $field, int $post_id, string $post_type ): void {
		$field_name = $field->get_name();

		// Check if value was submitted.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in save_fields().
		if ( ! isset( $_POST[ $field_name ] ) ) {
			// Delete meta if it existed.
			if ( metadata_exists( 'post', $post_id, $field_name ) ) {
				delete_post_meta( $post_id, $field_name );
			}
			return;
		}

		// Get and sanitize value.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in save_fields().
		$raw_value = wp_unslash( $_POST[ $field_name ] );

		// Apply filters
		$value = $this->apply_before_save_filters( $raw_value, $field_name, $post_type );
		if ( null === $value ) {
			return;
		}

		// Sanitize and validate
		$result = $this->sanitize_and_validate( $field, $value );

		if ( $result['valid'] ) {
			update_post_meta( $post_id, $field_name, $result['value'] );
		}
	}

	/**
	 * Enqueue assets for existing post type edit screens
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

		// Check if we're on a post type edit screen with our fields
		foreach ( $this->fields as $post_type => $fields ) {
			if ( $screen->post_type === $post_type && ! empty( $fields ) ) {
				$this->enqueue_field_assets( $post_type );
				$this->enqueue_common_assets();
				break;
			}
		}
	}

	/**
	 * Enqueue assets for fields
	 *
	 * @param string $post_type Post type slug.
	 * @return void
	 */
	private function enqueue_field_assets( string $post_type ): void {
		foreach ( $this->get_fields( $post_type ) as $field ) {
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
