<?php
/**
 * Get Field Tests
 *
 * Tests for the CassetteCmf::get_field() static method.
 * Tests retrieval of field values from all default field types across different contexts.
 *
 * @package Pedalcms\CassetteCmf\Tests\Unit
 */

use Pedalcms\CassetteCmf\CassetteCmf;
use Pedalcms\CassetteCmf\Core\Manager;
use Pedalcms\CassetteCmf\Field\Field_Factory;

require_once __DIR__ . '/CassetteCmf_UnitTestCase.php';

/**
 * Class Test_Get_Field
 *
 * Tests for retrieving field values using Manager::get_field().
 */
class Test_Get_Field extends CassetteCmf_UnitTestCase {

	/**
	 * Manager instance
	 *
	 * @var Manager
	 */
	private Manager $manager;

	/**
	 * Test post ID
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * Test term ID
	 *
	 * @var int
	 */
	private int $term_id;

	/**
	 * Settings page ID
	 *
	 * @var string
	 */
	private string $settings_page_id = 'test-settings';

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		// Reset the Manager singleton.
		$reflection = new ReflectionClass( Manager::class );
		$instance   = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );

		// Reset Field_Factory.
		Field_Factory::reset();

		// Set current user as admin.
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		// Initialize manager.
		$this->manager = Manager::init();

		// Create a test post.
		$this->post_id = self::factory()->post->create( [ 'post_type' => 'post' ] );

		// Create a test taxonomy term.
		$term          = self::factory()->term->create_and_get( [ 'taxonomy' => 'category' ] );
		$this->term_id = $term->term_id;
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down(): void {
		// Clean up options.
		$options = [
			$this->settings_page_id . '_text_field',
			$this->settings_page_id . '_textarea_field',
			$this->settings_page_id . '_number_field',
			$this->settings_page_id . '_email_field',
			$this->settings_page_id . '_url_field',
			$this->settings_page_id . '_password_field',
			$this->settings_page_id . '_date_field',
			$this->settings_page_id . '_color_field',
			$this->settings_page_id . '_select_field',
			$this->settings_page_id . '_checkbox_field',
			$this->settings_page_id . '_radio_field',
			$this->settings_page_id . '_wysiwyg_field',
		];

		foreach ( $options as $option ) {
			delete_option( $option );
		}

		parent::tear_down();
	}

	// =========================================================================
	// POST META TESTS (CPT Fields)
	// =========================================================================

	/**
	 * Test retrieving text field from post meta.
	 */
	public function test_get_text_field_from_post(): void {
		$field_name = 'text_field';
		$value      = 'Hello World';

		update_post_meta( $this->post_id, $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->post_id, 'post' );

		$this->assertSame( $value, $result );
	}

	/**
	 * Test retrieving textarea field from post meta.
	 */
	public function test_get_textarea_field_from_post(): void {
		$field_name = 'textarea_field';
		$value      = "Line 1\nLine 2\nLine 3";

		update_post_meta( $this->post_id, $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->post_id, 'post' );

		$this->assertSame( $value, $result );
	}

	/**
	 * Test retrieving number field from post meta.
	 */
	public function test_get_number_field_from_post(): void {
		$field_name = 'number_field';
		$value      = 42;

		update_post_meta( $this->post_id, $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->post_id, 'post' );

		$this->assertEquals( $value, $result );
	}

	/**
	 * Test retrieving number field with decimal from post meta.
	 */
	public function test_get_number_field_with_decimal_from_post(): void {
		$field_name = 'price_field';
		$value      = 99.99;

		update_post_meta( $this->post_id, $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->post_id, 'post' );

		$this->assertEquals( $value, $result );
	}

	/**
	 * Test retrieving email field from post meta.
	 */
	public function test_get_email_field_from_post(): void {
		$field_name = 'email_field';
		$value      = 'test@example.com';

		update_post_meta( $this->post_id, $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->post_id, 'post' );

		$this->assertSame( $value, $result );
	}

	/**
	 * Test retrieving URL field from post meta.
	 */
	public function test_get_url_field_from_post(): void {
		$field_name = 'url_field';
		$value      = 'https://example.com/path';

		update_post_meta( $this->post_id, $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->post_id, 'post' );

		$this->assertSame( $value, $result );
	}

	/**
	 * Test retrieving password field from post meta.
	 */
	public function test_get_password_field_from_post(): void {
		$field_name = 'password_field';
		$value      = 'secret_password_123!';

		update_post_meta( $this->post_id, $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->post_id, 'post' );

		$this->assertSame( $value, $result );
	}

	/**
	 * Test retrieving date field from post meta.
	 */
	public function test_get_date_field_from_post(): void {
		$field_name = 'date_field';
		$value      = '2026-01-29';

		update_post_meta( $this->post_id, $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->post_id, 'post' );

		$this->assertSame( $value, $result );
	}

	/**
	 * Test retrieving color field from post meta.
	 */
	public function test_get_color_field_from_post(): void {
		$field_name = 'color_field';
		$value      = '#ff5500';

		update_post_meta( $this->post_id, $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->post_id, 'post' );

		$this->assertSame( $value, $result );
	}

	/**
	 * Test retrieving select field from post meta.
	 */
	public function test_get_select_field_from_post(): void {
		$field_name = 'select_field';
		$value      = 'option_2';

		update_post_meta( $this->post_id, $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->post_id, 'post' );

		$this->assertSame( $value, $result );
	}

	/**
	 * Test retrieving checkbox field (single) from post meta.
	 */
	public function test_get_checkbox_field_single_from_post(): void {
		$field_name = 'checkbox_field';
		$value      = '1';

		update_post_meta( $this->post_id, $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->post_id, 'post' );

		$this->assertSame( $value, $result );
	}

	/**
	 * Test retrieving checkbox field (multiple) from post meta.
	 */
	public function test_get_checkbox_field_multiple_from_post(): void {
		$field_name = 'checkbox_multi_field';
		$value      = [ 'option_1', 'option_3' ];

		update_post_meta( $this->post_id, $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->post_id, 'post' );

		$this->assertIsArray( $result );
		$this->assertSame( $value, $result );
	}

	/**
	 * Test retrieving radio field from post meta.
	 */
	public function test_get_radio_field_from_post(): void {
		$field_name = 'radio_field';
		$value      = 'radio_option_2';

		update_post_meta( $this->post_id, $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->post_id, 'post' );

		$this->assertSame( $value, $result );
	}

	/**
	 * Test retrieving wysiwyg field from post meta.
	 */
	public function test_get_wysiwyg_field_from_post(): void {
		$field_name = 'wysiwyg_field';
		$value      = '<p>This is <strong>rich</strong> content.</p>';

		update_post_meta( $this->post_id, $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->post_id, 'post' );

		$this->assertSame( $value, $result );
	}

	/**
	 * Test retrieving upload field from post meta.
	 */
	public function test_get_upload_field_from_post(): void {
		$field_name = 'upload_field';
		$value      = 'https://example.com/wp-content/uploads/2026/01/image.jpg';

		update_post_meta( $this->post_id, $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->post_id, 'post' );

		$this->assertSame( $value, $result );
	}

	/**
	 * Test retrieving repeater field from post meta.
	 */
	public function test_get_repeater_field_from_post(): void {
		$field_name = 'repeater_field';
		$value      = [
			[
				'name'  => 'Item 1',
				'price' => 10,
			],
			[
				'name'  => 'Item 2',
				'price' => 20,
			],
			[
				'name'  => 'Item 3',
				'price' => 30,
			],
		];

		update_post_meta( $this->post_id, $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->post_id, 'post' );

		$this->assertIsArray( $result );
		$this->assertCount( 3, $result );
		$this->assertSame( 'Item 1', $result[0]['name'] );
	}

	// =========================================================================
	// TERM META TESTS (Taxonomy Fields)
	// =========================================================================

	/**
	 * Test retrieving text field from term meta.
	 */
	public function test_get_text_field_from_term(): void {
		$field_name = 'term_text_field';
		$value      = 'Term Value';

		update_term_meta( $this->term_id, $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->term_id, 'term' );

		$this->assertSame( $value, $result );
	}

	/**
	 * Test retrieving color field from term meta.
	 */
	public function test_get_color_field_from_term(): void {
		$field_name = 'term_color';
		$value      = '#0073aa';

		update_term_meta( $this->term_id, $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->term_id, 'term' );

		$this->assertSame( $value, $result );
	}

	/**
	 * Test retrieving checkbox field from term meta.
	 */
	public function test_get_checkbox_field_from_term(): void {
		$field_name = 'is_featured';
		$value      = '1';

		update_term_meta( $this->term_id, $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->term_id, 'term' );

		$this->assertSame( $value, $result );
	}

	/**
	 * Test retrieving textarea field from term meta.
	 */
	public function test_get_textarea_field_from_term(): void {
		$field_name = 'term_description';
		$value      = "Custom term description.\nMultiple lines.";

		update_term_meta( $this->term_id, $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->term_id, 'term' );

		$this->assertSame( $value, $result );
	}

	/**
	 * Test retrieving number field from term meta.
	 */
	public function test_get_number_field_from_term(): void {
		$field_name = 'display_order';
		$value      = 5;

		update_term_meta( $this->term_id, $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->term_id, 'term' );

		$this->assertEquals( $value, $result );
	}

	// =========================================================================
	// SETTINGS OPTIONS TESTS (Settings Page Fields)
	// =========================================================================

	/**
	 * Test retrieving text field from settings.
	 */
	public function test_get_text_field_from_settings(): void {
		$field_name = 'text_field';
		$value      = 'Settings Text Value';

		update_option( $this->settings_page_id . '_' . $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->settings_page_id, 'settings' );

		$this->assertSame( $value, $result );
	}

	/**
	 * Test retrieving textarea field from settings.
	 */
	public function test_get_textarea_field_from_settings(): void {
		$field_name = 'textarea_field';
		$value      = "Settings textarea\nWith multiple lines";

		update_option( $this->settings_page_id . '_' . $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->settings_page_id, 'settings' );

		$this->assertSame( $value, $result );
	}

	/**
	 * Test retrieving number field from settings.
	 */
	public function test_get_number_field_from_settings(): void {
		$field_name = 'number_field';
		$value      = 100;

		update_option( $this->settings_page_id . '_' . $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->settings_page_id, 'settings' );

		$this->assertEquals( $value, $result );
	}

	/**
	 * Test retrieving email field from settings.
	 */
	public function test_get_email_field_from_settings(): void {
		$field_name = 'email_field';
		$value      = 'admin@example.com';

		update_option( $this->settings_page_id . '_' . $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->settings_page_id, 'settings' );

		$this->assertSame( $value, $result );
	}

	/**
	 * Test retrieving URL field from settings.
	 */
	public function test_get_url_field_from_settings(): void {
		$field_name = 'url_field';
		$value      = 'https://example.com';

		update_option( $this->settings_page_id . '_' . $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->settings_page_id, 'settings' );

		$this->assertSame( $value, $result );
	}

	/**
	 * Test retrieving password field from settings.
	 */
	public function test_get_password_field_from_settings(): void {
		$field_name = 'password_field';
		$value      = 'api_key_12345';

		update_option( $this->settings_page_id . '_' . $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->settings_page_id, 'settings' );

		$this->assertSame( $value, $result );
	}

	/**
	 * Test retrieving date field from settings.
	 */
	public function test_get_date_field_from_settings(): void {
		$field_name = 'date_field';
		$value      = '2026-12-31';

		update_option( $this->settings_page_id . '_' . $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->settings_page_id, 'settings' );

		$this->assertSame( $value, $result );
	}

	/**
	 * Test retrieving color field from settings.
	 */
	public function test_get_color_field_from_settings(): void {
		$field_name = 'color_field';
		$value      = '#2271b1';

		update_option( $this->settings_page_id . '_' . $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->settings_page_id, 'settings' );

		$this->assertSame( $value, $result );
	}

	/**
	 * Test retrieving select field from settings.
	 */
	public function test_get_select_field_from_settings(): void {
		$field_name = 'select_field';
		$value      = 'EUR';

		update_option( $this->settings_page_id . '_' . $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->settings_page_id, 'settings' );

		$this->assertSame( $value, $result );
	}

	/**
	 * Test retrieving checkbox field from settings.
	 */
	public function test_get_checkbox_field_from_settings(): void {
		$field_name = 'checkbox_field';
		$value      = '1';

		update_option( $this->settings_page_id . '_' . $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->settings_page_id, 'settings' );

		$this->assertSame( $value, $result );
	}

	/**
	 * Test retrieving radio field from settings.
	 */
	public function test_get_radio_field_from_settings(): void {
		$field_name = 'radio_field';
		$value      = 'dark';

		update_option( $this->settings_page_id . '_' . $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->settings_page_id, 'settings' );

		$this->assertSame( $value, $result );
	}

	/**
	 * Test retrieving wysiwyg field from settings.
	 */
	public function test_get_wysiwyg_field_from_settings(): void {
		$field_name = 'wysiwyg_field';
		$value      = '<h1>Welcome</h1><p>Settings content.</p>';

		update_option( $this->settings_page_id . '_' . $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->settings_page_id, 'settings' );

		$this->assertSame( $value, $result );
	}

	// =========================================================================
	// DEFAULT VALUE TESTS
	// =========================================================================

	/**
	 * Test default value returned when post meta is empty.
	 */
	public function test_default_value_returned_for_empty_post_meta(): void {
		$result = $this->manager->get_field( 'nonexistent_field', $this->post_id, 'post', 'default_value' );

		$this->assertSame( 'default_value', $result );
	}

	/**
	 * Test default value returned when term meta is empty.
	 */
	public function test_default_value_returned_for_empty_term_meta(): void {
		$result = $this->manager->get_field( 'nonexistent_field', $this->term_id, 'term', 'term_default' );

		$this->assertSame( 'term_default', $result );
	}

	/**
	 * Test default value returned when option is empty.
	 */
	public function test_default_value_returned_for_empty_option(): void {
		$result = $this->manager->get_field( 'nonexistent_field', $this->settings_page_id, 'settings', 'settings_default' );

		$this->assertSame( 'settings_default', $result );
	}

	/**
	 * Test default value with array type.
	 */
	public function test_default_value_array_type(): void {
		$default = [ 'option1', 'option2' ];

		$result = $this->manager->get_field( 'nonexistent_checkbox', $this->post_id, 'post', $default );

		$this->assertSame( $default, $result );
	}

	/**
	 * Test default value with integer type.
	 */
	public function test_default_value_integer_type(): void {
		$result = $this->manager->get_field( 'nonexistent_number', $this->post_id, 'post', 0 );

		$this->assertSame( 0, $result );
	}

	// =========================================================================
	// HELPER FUNCTION TESTS
	// =========================================================================

	/**
	 * Test CassetteCmf::get_field static method for post.
	 */
	public function test_helper_function_for_post(): void {
		$field_name = 'helper_test_field';
		$value      = 'Helper Test Value';

		update_post_meta( $this->post_id, $field_name, $value );

		$result = CassetteCmf::get_field( $field_name, $this->post_id, 'post' );

		$this->assertSame( $value, $result );
	}

	/**
	 * Test CassetteCmf::get_field static method for term.
	 */
	public function test_helper_function_for_term(): void {
		$field_name = 'helper_term_field';
		$value      = 'Helper Term Value';

		update_term_meta( $this->term_id, $field_name, $value );

		$result = CassetteCmf::get_field( $field_name, $this->term_id, 'term' );

		$this->assertSame( $value, $result );
	}

	/**
	 * Test CassetteCmf::get_field static method for settings.
	 */
	public function test_helper_function_for_settings(): void {
		$field_name = 'helper_settings_field';
		$value      = 'Helper Settings Value';

		update_option( $this->settings_page_id . '_' . $field_name, $value );

		$result = CassetteCmf::get_field( $field_name, $this->settings_page_id, 'settings' );

		$this->assertSame( $value, $result );
	}

	/**
	 * Test CassetteCmf::get_field with default value.
	 */
	public function test_helper_function_with_default(): void {
		$result = CassetteCmf::get_field( 'nonexistent', $this->post_id, 'post', 'fallback' );

		$this->assertSame( 'fallback', $result );
	}

	// =========================================================================
	// EDGE CASE TESTS
	// =========================================================================

	/**
	 * Test invalid context type returns default.
	 */
	public function test_invalid_context_type_returns_default(): void {
		update_post_meta( $this->post_id, 'some_field', 'some_value' );

		$result = $this->manager->get_field( 'some_field', $this->post_id, 'invalid_type', 'default' );

		$this->assertSame( 'default', $result );
	}

	/**
	 * Test context type is case-sensitive.
	 */
	public function test_context_type_case_sensitive(): void {
		update_post_meta( $this->post_id, 'case_field', 'case_value' );

		// Should fail with uppercase
		$result = $this->manager->get_field( 'case_field', $this->post_id, 'POST', 'default' );

		$this->assertSame( 'default', $result );
	}

	/**
	 * Test empty field name.
	 */
	public function test_empty_field_name_returns_default(): void {
		$result = $this->manager->get_field( '', $this->post_id, 'post', 'empty_default' );

		$this->assertSame( 'empty_default', $result );
	}

	/**
	 * Test zero as a valid value (not treated as empty).
	 */
	public function test_zero_is_valid_value(): void {
		$field_name = 'zero_field';
		update_post_meta( $this->post_id, $field_name, 0 );

		$result = $this->manager->get_field( $field_name, $this->post_id, 'post', 'default' );

		// Note: get_post_meta returns '0' as string, which is not empty
		$this->assertEquals( 0, $result );
	}

	/**
	 * Test false as a valid value.
	 */
	public function test_false_as_value(): void {
		$field_name = 'false_field';
		update_post_meta( $this->post_id, $field_name, false );

		$result = $this->manager->get_field( $field_name, $this->post_id, 'post', 'default' );

		// WordPress stores false as empty string
		$this->assertSame( 'default', $result );
	}

	/**
	 * Test post ID defaults to 'post' context type.
	 */
	public function test_default_context_type_is_post(): void {
		$field_name = 'default_context_field';
		$value      = 'Default Context Value';

		update_post_meta( $this->post_id, $field_name, $value );

		$result = $this->manager->get_field( $field_name, $this->post_id );

		$this->assertSame( $value, $result );
	}

	/**
	 * Test individual get methods directly.
	 */
	public function test_get_post_field_directly(): void {
		$field_name = 'direct_post_field';
		$value      = 'Direct Post Value';

		update_post_meta( $this->post_id, $field_name, $value );

		$result = $this->manager->get_post_field( $field_name, $this->post_id );

		$this->assertSame( $value, $result );
	}

	/**
	 * Test individual get_term_field method directly.
	 */
	public function test_get_term_field_directly(): void {
		$field_name = 'direct_term_field';
		$value      = 'Direct Term Value';

		update_term_meta( $this->term_id, $field_name, $value );

		$result = $this->manager->get_term_field( $field_name, $this->term_id );

		$this->assertSame( $value, $result );
	}

	/**
	 * Test individual get_settings_field method directly.
	 */
	public function test_get_settings_field_directly(): void {
		$field_name = 'direct_settings_field';
		$value      = 'Direct Settings Value';

		update_option( $this->settings_page_id . '_' . $field_name, $value );

		$result = $this->manager->get_settings_field( $field_name, $this->settings_page_id );

		$this->assertSame( $value, $result );
	}
}
