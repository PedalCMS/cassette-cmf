<?php
/**
 * ContainerFieldInterface for Cassette-CMF
 *
 * Defines the contract for container fields that contain other fields
 * but don't store their own values. Container fields are organizational
 * and their nested fields save/load independently using their own field names.
 *
 * @package Pedalcms\CassetteCmf
 * @since 1.0.0
 */

namespace Pedalcms\CassetteCmf\Field;

/**
 * ContainerFieldInterface - Contract for container field types
 *
 * Container fields (like tabs, accordions, groups) contain other fields
 * but don't store values themselves. They serve purely organizational purposes.
 */
interface Container_Field_Interface extends Field_Interface {

	/**
	 * Get all nested field configurations
	 *
	 * Returns an array of field configurations that are nested within this container.
	 * These fields will be registered individually so they can save/load on their own.
	 *
	 * @return array<array<string, mixed>> Array of field configurations.
	 */
	public function get_nested_fields(): array;

	/**
	 * Check if this is a container field
	 *
	 * Always returns true for container fields.
	 *
	 * @return bool
	 */
	public function is_container(): bool;
}
