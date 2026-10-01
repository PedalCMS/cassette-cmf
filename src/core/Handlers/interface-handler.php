<?php
/**
 * Handler Interface
 *
 * Defines the contract for all registration handlers.
 *
 * @package PedalCMS\CassetteCMF
 * @since 1.0.0
 */

namespace PedalCMS\CassetteCMF\Core\Handlers;

use PedalCMS\CassetteCMF\Field\Field_Interface;

/**
 * Interface Handler_Interface
 *
 * All handlers must implement this interface.
 */
interface Handler_Interface {

	/**
	 * Initialize WordPress hooks
	 *
	 * @return void
	 */
	public function init_hooks(): void;

	/**
	 * Add fields for a context
	 *
	 * @param string $context Context identifier.
	 * @param array  $fields  Field configurations.
	 * @return void
	 */
	public function add_fields( string $context, array $fields ): void;

	/**
	 * Get fields for a context
	 *
	 * @param string $context Context identifier.
	 * @return array<string, Field_Interface>
	 */
	public function get_fields( string $context ): array;

	/**
	 * Check if context has fields
	 *
	 * @param string $context Context identifier.
	 * @return bool
	 */
	public function has_fields( string $context ): bool;
}
