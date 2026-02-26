<?php
/**
 * UploadField - Media upload field using WordPress media library
 *
 * @package Pedalcms\CassetteCmf
 * @since 1.0.0
 */

namespace Pedalcms\CassetteCmf\Field\Fields;

use Pedalcms\CassetteCmf\Field\Abstract_Field;

/**
 * Upload_Field class
 *
 * Renders a media upload field that integrates with WordPress media library.
 * Allows users to select or upload files (images, documents, etc.) using
 * the built-in WordPress media uploader.
 *
 * Configuration options:
 * - button_text: Text for the upload button (default: 'Select File')
 * - remove_text: Text for the remove button (default: 'Remove')
 * - allowed_types: Array of allowed mime types (default: all types)
 * - multiple: Allow multiple file selection (default: false)
 * - preview: Show preview for images (default: true)
 * - library_type: Filter media library by type ('image', 'video', 'audio', 'application')
 */
class Upload_Field extends Abstract_Field {

	/**
	 * Get field type defaults
	 *
	 * @return array<string, mixed>
	 */
	protected function get_defaults(): array {
		return array_merge(
			parent::get_defaults(),
			[
				'button_text'   => 'Select File',
				'remove_text'   => 'Remove',
				'allowed_types' => [],
				'multiple'      => false,
				'preview'       => true,
				'library_type'  => '',
			]
		);
	}

	/**
	 * Enqueue field assets
	 *
	 * Loads WordPress media scripts and styles.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( function_exists( 'wp_enqueue_media' ) ) {
			wp_enqueue_media();
		}
	}

	/**
	 * Render the upload field
	 *
	 * @param mixed $value Current field value (attachment ID or URL).
	 * @return string HTML output.
	 */
	public function render( $value = null ): string {
		$output  = $this->render_wrapper_start();
		$output .= $this->render_label();

		$field_value   = $value ?? $this->config['default'] ?? '';
		$attachment_id = is_numeric( $field_value ) ? (int) $field_value : 0;
		$preview_url   = '';
		$file_name     = '';

		// Get attachment details if we have an ID.
		if ( $attachment_id && function_exists( 'wp_get_attachment_url' ) ) {
			$preview_url   = wp_get_attachment_url( $attachment_id );
			$attached_file = get_attached_file( $attachment_id );
			$file_name     = basename( $attached_file ? $attached_file : '' );
		} elseif ( ! empty( $field_value ) && is_string( $field_value ) ) {
			// Value might be a URL.
			$preview_url = $field_value;
			$file_name   = basename( $field_value );
		}

		$is_image     = $this->is_image( $preview_url );
		$has_value    = ! empty( $field_value );
		$show_preview = $this->config['preview'] && $is_image && $has_value;
		$library_type = $this->config['library_type'];

		// Hidden input for the value.
		$output .= sprintf(
			'<input type="hidden" id="%s" name="%s" value="%s" class="cassette-cmf-upload-value" />',
			$this->esc_attr( $this->get_field_id() ),
			$this->esc_attr( $this->name ),
			$this->esc_attr( (string) $field_value )
		);

		// Upload container.
		$output .= '<div class="cassette-cmf-upload-container">';

		// Preview area.
		$output .= '<div class="cassette-cmf-upload-preview" style="' . ( $show_preview ? '' : 'display:none;' ) . '">';
		if ( $show_preview ) {
			$output .= sprintf(
				'<img src="%s" alt="%s" style="max-width:150px;max-height:150px;" />',
				$this->esc_attr( $preview_url ),
				$this->esc_attr( $file_name )
			);
		}
		$output .= '</div>';

		// File name display (for non-images).
		$output .= '<div class="cassette-cmf-upload-filename" style="' . ( $has_value && ! $is_image ? '' : 'display:none;' ) . '">';
		if ( $has_value && ! $is_image ) {
			$output .= '<span class="dashicons dashicons-media-default"></span> ';
			$output .= $this->esc_html( $file_name );
		}
		$output .= '</div>';

		// Buttons.
		$output .= '<div class="cassette-cmf-upload-buttons">';

		// Upload button.
		$button_attrs = [
			'type'  => 'button',
			'class' => 'button cassette-cmf-upload-button',
		];

		if ( ! empty( $library_type ) ) {
			$button_attrs['data-library-type'] = $library_type;
		}

		if ( ! empty( $this->config['multiple'] ) ) {
			$button_attrs['data-multiple'] = 'true';
		}

		$button_attrs['data-field-id'] = $this->get_field_id();

		$output .= '<button' . $this->build_attributes( $button_attrs ) . '>';
		$output .= $this->esc_html( $this->config['button_text'] );
		$output .= '</button>';

		// Remove button.
		$output .= sprintf(
			' <button type="button" class="button cassette-cmf-upload-remove" data-field-id="%s" style="%s">%s</button>',
			$this->esc_attr( $this->get_field_id() ),
			$has_value ? '' : 'display:none;',
			$this->esc_html( $this->config['remove_text'] )
		);

		$output .= '</div>'; // .cassette-cmf-upload-buttons
		$output .= '</div>'; // .cassette-cmf-upload-container

		$output .= $this->render_description();
		$output .= $this->render_wrapper_end();

		// Inline script for media uploader (will be moved to external JS in production).
		$output .= $this->render_inline_script();

		return $output;
	}

	/**
	 * Check if URL points to an image
	 *
	 * @param string $url URL to check.
	 * @return bool
	 */
	protected function is_image( string $url ): bool {
		if ( empty( $url ) ) {
			return false;
		}

		$image_extensions = [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico' ];
		$url_path         = wp_parse_url( $url, PHP_URL_PATH );
		$extension        = strtolower( pathinfo( $url_path ? $url_path : '', PATHINFO_EXTENSION ) );

		return in_array( $extension, $image_extensions, true );
	}

	/**
	 * Render inline script for media uploader
	 *
	 * @return string
	 */
	protected function render_inline_script(): string {
		static $script_rendered = false;

		// Only render the script once per page.
		if ( $script_rendered ) {
			return '';
		}

		$script_rendered = true;

		return '
<script type="text/javascript">
(function($) {
	"use strict";

	$(document).on("click", ".cassette-cmf-upload-button", function(e) {
		e.preventDefault();

		var button = $(this);
		var fieldId = button.data("field-id");
		var container = button.closest(".cassette-cmf-upload-container");
		var input = container.siblings(".cassette-cmf-upload-value");
		var preview = container.find(".cassette-cmf-upload-preview");
		var filename = container.find(".cassette-cmf-upload-filename");
		var removeBtn = container.find(".cassette-cmf-upload-remove");
		var libraryType = button.data("library-type") || "";
		var multiple = button.data("multiple") === true;

		var frame = wp.media({
			title: "Select or Upload File",
			button: { text: "Use this file" },
			library: libraryType ? { type: libraryType } : {},
			multiple: multiple
		});

		frame.on("select", function() {
			var attachment = frame.state().get("selection").first().toJSON();

			input.val(attachment.id).trigger("change");

			// Update preview or filename.
			if (attachment.type === "image" && attachment.sizes) {
				var imgUrl = attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
				preview.html("<img src=\"" + imgUrl + "\" style=\"max-width:150px;max-height:150px;\" />").show();
				filename.hide();
			} else {
				preview.hide();
				filename.html("<span class=\"dashicons dashicons-media-default\"></span> " + attachment.filename).show();
			}

			removeBtn.show();
		});

		frame.open();
	});

	$(document).on("click", ".cassette-cmf-upload-remove", function(e) {
		e.preventDefault();

		var button = $(this);
		var container = button.closest(".cassette-cmf-upload-container");
		var input = container.siblings(".cassette-cmf-upload-value");
		var preview = container.find(".cassette-cmf-upload-preview");
		var filename = container.find(".cassette-cmf-upload-filename");

		input.val("").trigger("change");
		preview.hide().empty();
		filename.hide().empty();
		button.hide();
	});
})(jQuery);
</script>';
	}

	/**
	 * Sanitize upload input
	 *
	 * @param mixed $input Input value.
	 * @return mixed
	 */
	public function sanitize( $input ) {
		if ( empty( $input ) ) {
			return '';
		}

		// If it's a numeric ID, validate it's a valid attachment.
		if ( is_numeric( $input ) ) {
			$attachment_id = (int) $input;

			if ( function_exists( 'wp_attachment_is_image' ) ) {
				// Check if attachment exists.
				$attachment = get_post( $attachment_id );
				if ( $attachment && 'attachment' === $attachment->post_type ) {
					return $attachment_id;
				}
			}

			return $attachment_id;
		}

		// If it's a URL, sanitize it.
		if ( is_string( $input ) && filter_var( $input, FILTER_VALIDATE_URL ) ) {
			return esc_url_raw( $input );
		}

		return '';
	}

	/**
	 * Validate upload input
	 *
	 * @param mixed $input Input value.
	 * @return array
	 */
	public function validate( $input ): array {
		$result = parent::validate( $input );

		if ( ! empty( $input ) ) {
			// Validate allowed types if specified.
			if ( ! empty( $this->config['allowed_types'] ) && is_numeric( $input ) ) {
				$mime_type = get_post_mime_type( (int) $input );

				if ( $mime_type && ! in_array( $mime_type, $this->config['allowed_types'], true ) ) {
					$result['valid']    = false;
					$result['errors'][] = sprintf(
						/* translators: %s: field label */
						'%s has an invalid file type.',
						$this->get_label()
					);
				}
			}
		}

		return $result;
	}
}
