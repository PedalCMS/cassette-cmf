<?php
/**
 * Classic settings page save: the page's own capability decides.
 *
 * @package PedalCMS\CassetteCMF\Tests\Unit
 */

use PedalCMS\CassetteCMF\Core\Manager;
use PedalCMS\CassetteCMF\Field\Field_Factory;

require_once __DIR__ . '/CassetteCMF_UnitTestCase.php';

/**
 * Class Test_Settings_Save_Capability
 */
class Test_Settings_Save_Capability extends CassetteCMF_UnitTestCase {

	/**
	 * Manager.
	 *
	 * @var Manager
	 */
	private Manager $manager;

	/**
	 * Set up: fresh manager, redirects become exceptions.
	 */
	public function set_up(): void {
		parent::set_up();

		$reflection = new ReflectionClass( Manager::class );
		$instance   = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );
		Field_Factory::reset();

		$this->manager = Manager::init();

		add_filter(
			'wp_redirect',
			static function () {
				throw new RuntimeException( 'redirected' );
			}
		);
	}

	/**
	 * Tear down.
	 */
	public function tear_down(): void {
		unset( $_POST['action'], $_POST['page_id'], $_POST['CassetteCMF_settings_nonce'], $_POST['capsave_note'] );
		remove_all_filters( 'wp_redirect' );
		parent::tear_down();
	}

	/**
	 * Register a page with one text field.
	 *
	 * @param string               $id   Page id.
	 * @param array<string, mixed> $page Extra page keys.
	 */
	private function register( string $id, array $page = [] ): void {
		$this->manager->register_from_array(
			[
				'settings_pages' => [
					array_merge(
						[
							'id'         => $id,
							'page_title' => $id,
							'fields'     => [
								[
									'name' => 'note',
									'type' => 'text',
								],
							],
						],
						$page
					),
				],
			]
		);
	}

	/**
	 * Submit the classic form as the given user.
	 *
	 * @param string $id   Page id.
	 * @param string $role Role of the current user.
	 * @return string 'saved', or the exception message of a refused save.
	 */
	private function submit( string $id, string $role, string $extra_cap = '' ): string {
		$user_id = self::factory()->user->create( [ 'role' => $role ] );
		if ( '' !== $extra_cap ) {
			get_user_by( 'id', $user_id )->add_cap( $extra_cap );
		}
		wp_set_current_user( $user_id );

		$_POST['action']                     = 'CassetteCMF_save_settings';
		$_POST['page_id']                    = $id;
		$_POST['CassetteCMF_settings_nonce'] = wp_create_nonce( 'CassetteCMF_save_settings_' . $id );
		$_POST[ $id . '_note' ]              = 'saved value';

		try {
			$this->manager->get_new_settings_handler()->on_page_load();
		} catch ( RuntimeException $e ) {
			return 'redirected' === $e->getMessage() ? 'saved' : $e->getMessage();
		} catch ( WPDieException $e ) {
			return 'refused';
		} finally {
			unset( $_POST[ $id . '_note' ] );
		}

		return 'no-op';
	}

	/**
	 * A page without a capability is unchanged: administrators save.
	 */
	public function test_default_page_lets_administrators_save(): void {
		$this->register( 'capsave_default' );

		$this->assertSame( 'saved', $this->submit( 'capsave_default', 'administrator' ) );
		$this->assertSame( 'saved value', get_option( 'capsave_default_note' ) );
	}

	/**
	 * A page without a capability is unchanged: editors cannot save.
	 */
	public function test_default_page_refuses_editors(): void {
		$this->register( 'capsave_default2' );

		$this->assertSame( 'refused', $this->submit( 'capsave_default2', 'editor' ) );
		$this->assertFalse( get_option( 'capsave_default2_note' ) );
	}

	/**
	 * A custom capability can save.
	 */
	public function test_custom_capability_can_save(): void {
		$this->register( 'capsave_custom', [ 'capability' => 'edit_others_posts' ] );

		$this->assertSame( 'saved', $this->submit( 'capsave_custom', 'editor' ) );
		$this->assertSame( 'saved value', get_option( 'capsave_custom_note' ) );
	}

	/**
	 * A user without the custom capability cannot save, even as an administrator-less role.
	 */
	public function test_user_without_the_capability_cannot_save(): void {
		$this->register( 'capsave_custom2', [ 'capability' => 'edit_others_posts' ] );

		$this->assertSame( 'refused', $this->submit( 'capsave_custom2', 'subscriber' ) );
		$this->assertFalse( get_option( 'capsave_custom2_note' ) );
	}
}
