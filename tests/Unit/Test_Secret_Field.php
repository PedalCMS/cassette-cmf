<?php
/**
 * Secret field tests
 *
 * @package PedalCMS\CassetteCMF\Tests\Unit
 */

use PedalCMS\CassetteCMF\CassetteCMF;
use PedalCMS\CassetteCMF\Core\Manager;
use PedalCMS\CassetteCMF\Field\Field_Factory;
use PedalCMS\CassetteCMF\Field\Fields\Secret_Field;

require_once __DIR__ . '/CassetteCMF_UnitTestCase.php';

/**
 * Class Test_Secret_Field
 */
class Test_Secret_Field extends CassetteCMF_UnitTestCase {

	private const PAGE   = 'secret-page';
	private const OPTION = 'secret-page_token';
	private const SECRET = 'sk-test-ABCDEFGH-9876';

	/**
	 * Manager.
	 *
	 * @var Manager
	 */
	private Manager $manager;

	/**
	 * Set up: manager with a page holding a plain and an encrypted secret.
	 */
	public function set_up(): void {
		parent::set_up();

		$reflection = new ReflectionClass( Manager::class );
		$instance   = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );
		Field_Factory::reset();

		$GLOBALS['wp_rest_server'] = null;
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$this->manager = Manager::init();
		$this->manager->register_from_array(
			[
				'settings_pages' => [
					[
						'id'           => self::PAGE,
						'page_title'   => 'Secrets',
						'menu_title'   => 'Secrets',
						'show_in_rest' => true,
						'fields'       => [
							[
								'name'  => 'token',
								'type'  => 'secret',
								'label' => 'Token',
							],
							[
								'name'    => 'vault',
								'type'    => 'secret',
								'label'   => 'Vault key',
								'encrypt' => true,
							],
						],
					],
				],
			]
		);
	}

	/**
	 * Tear down.
	 */
	public function tear_down(): void {
		$GLOBALS['wp_rest_server'] = null;
		unset( $_POST );
		$_POST = [];
		parent::tear_down();
	}

	/**
	 * Get a registered secret field.
	 *
	 * @param string $name Field name.
	 */
	private function field( string $name = 'token' ): Secret_Field {
		$field = $this->manager->get_new_settings_handler()->get_fields( self::PAGE )[ $name ];
		$this->assertInstanceOf( Secret_Field::class, $field );

		return $field;
	}

	/**
	 * Classic save of one field with a posted value.
	 *
	 * @param string $name  Field name.
	 * @param mixed  $value Posted value.
	 */
	private function classic_save( string $name, $value ): void {
		$_POST = [ self::PAGE . '_' . $name => wp_slash( $value ) ];
		$this->manager->get_new_settings_handler()->save_single_field( $this->field( $name ), self::PAGE );
		$_POST = [];
	}

	/**
	 * REST write.
	 *
	 * @param array<string, mixed> $values Values.
	 */
	private function rest_write( array $values ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/cassette-cmf/v1/settings/' . self::PAGE );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( [ 'values' => $values ] ) );

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * REST read.
	 */
	private function rest_read(): WP_REST_Response {
		return rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/cassette-cmf/v1/settings/' . self::PAGE ) );
	}

	// ---------------------------------------------------------------------

	/**
	 * The type is registered and password is untouched.
	 */
	public function test_type_registered(): void {
		$this->assertTrue( Field_Factory::has_type( 'secret' ) );
		$this->assertTrue( Field_Factory::has_type( 'password' ) );
		$this->assertInstanceOf(
			Secret_Field::class,
			Field_Factory::create(
				[
					'name' => 'x',
					'type' => 'secret',
				]
			)
		); // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
	}

	/**
	 * Mask rules.
	 */
	public function test_mask(): void {
		$field = $this->field();

		$this->assertSame( '', $field->mask( '' ) );
		$this->assertSame( '', $field->mask( null ) );
		$this->assertSame( '••••', $field->mask( 'abcd' ) );
		$this->assertSame( '••••2345', $field->mask( 'abcde12345' ) );
		$this->assertSame( '••••9876', $field->mask( self::SECRET ) );
	}

	/**
	 * The rendered HTML never contains the value, only the mask.
	 */
	public function test_render_never_contains_the_value(): void {
		$field = $this->field();

		$unset = $field->render( '' );
		$this->assertStringNotContainsString( 'cassette-cmf-secret-clear', $unset );
		$this->assertStringContainsString( 'value=""', $unset );
		$this->assertStringContainsString( 'name="token[value]"', $unset );

		$set = $field->render( self::SECRET );
		$this->assertStringNotContainsString( self::SECRET, $set );
		$this->assertStringContainsString( '••••9876', $set );
		$this->assertStringContainsString( 'name="token[clear]"', $set );
		$this->assertStringContainsString( 'value=""', $set );
	}

	/**
	 * The page markup (settings field wrapper) keeps the option name and hides the value.
	 */
	public function test_page_markup_never_contains_the_value(): void {
		update_option( self::OPTION, self::SECRET );

		$method = new ReflectionMethod( $this->manager->get_new_settings_handler(), 'render_settings_field_html' );
		$method->setAccessible( true );
		$html = $method->invoke( $this->manager->get_new_settings_handler(), $this->field(), self::OPTION, self::PAGE );

		$this->assertStringNotContainsString( self::SECRET, $html );
		$this->assertStringContainsString( 'name="' . self::OPTION . '[value]"', $html );
		$this->assertStringContainsString( 'name="' . self::OPTION . '[clear]"', $html );
	}

	/**
	 * Classic save: replace, keep, clear.
	 */
	public function test_classic_replace_keep_clear(): void {
		$this->classic_save( 'token', [ 'value' => 'first-secret-1111' ] );
		$this->assertSame( 'first-secret-1111', get_option( self::OPTION ) );

		// Empty submission keeps.
		$this->classic_save( 'token', [ 'value' => '' ] );
		$this->assertSame( 'first-secret-1111', get_option( self::OPTION ) );

		// Plain string (options.php style) empty keeps too.
		$this->classic_save( 'token', '' );
		$this->assertSame( 'first-secret-1111', get_option( self::OPTION ) );

		// Unchanged mask keeps.
		$this->classic_save( 'token', [ 'value' => '••••1111' ] );
		$this->assertSame( 'first-secret-1111', get_option( self::OPTION ) );

		// New value replaces.
		$this->classic_save( 'token', [ 'value' => 'second-secret-2222' ] );
		$this->assertSame( 'second-secret-2222', get_option( self::OPTION ) );

		// Clear control empties it, even when a value is typed alongside.
		$this->classic_save(
			'token',
			[
				'value' => 'ignored',
				'clear' => '1',
			]
		);
		$this->assertSame( '', get_option( self::OPTION ) );
	}

	/**
	 * Classic save through the registered setting (options.php path).
	 */
	public function test_registered_setting_path_keeps_and_replaces(): void {
		$this->manager->get_new_settings_handler()->register_settings();

		update_option( self::OPTION, 'stored-secret-0001' );

		update_option( self::OPTION, '' );
		$this->assertSame( 'stored-secret-0001', get_option( self::OPTION ) );

		update_option( self::OPTION, [ 'value' => 'brand-new-secret-02' ] );
		$this->assertSame( 'brand-new-secret-02', get_option( self::OPTION ) );

		update_option( self::OPTION, [ 'clear' => '1' ] );
		$this->assertSame( '', get_option( self::OPTION ) );
	}

	/**
	 * REST: replace, keep (mask, null, omitted), clear.
	 */
	public function test_rest_replace_keep_clear(): void {
		$response = $this->rest_write( [ 'token' => 'rest-secret-5555' ] );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'rest-secret-5555', get_option( self::OPTION ) );
		$this->assertSame( '••••5555', $response->get_data()['values']['token'] );

		$this->rest_write( [ 'token' => '••••5555' ] );
		$this->assertSame( 'rest-secret-5555', get_option( self::OPTION ) );

		$this->rest_write( [ 'token' => null ] );
		$this->assertSame( 'rest-secret-5555', get_option( self::OPTION ) );

		$this->rest_write( [ 'vault' => 'something' ] );
		$this->assertSame( 'rest-secret-5555', get_option( self::OPTION ) );

		$this->rest_write( [ 'token' => '' ] );
		$this->assertSame( 'rest-secret-5555', get_option( self::OPTION ) );

		$response = $this->rest_write( [ 'token' => [ 'clear' => true ] ] );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '', get_option( self::OPTION ) );
		$this->assertSame( '', $response->get_data()['values']['token'] );
	}

	/**
	 * REST: bad shapes are rejected and nothing is written.
	 */
	public function test_rest_rejects_bad_shapes(): void {
		update_option( self::OPTION, 'keep-me-please-1' );

		foreach ( [ [ 'clear' => false ], [ 'clear' => 'yes' ], [ 'value' => 'x' ], 42, true ] as $bad ) {
			$response = $this->rest_write( [ 'token' => $bad ] );
			$this->assertSame( 400, $response->get_status(), wp_json_encode( $bad ) );
		}

		$this->assertSame( 'keep-me-please-1', get_option( self::OPTION ) );
	}

	/**
	 * No REST response ever contains the value.
	 */
	public function test_rest_output_never_contains_the_value(): void {
		$write = $this->rest_write(
			[
				'token' => self::SECRET,
				'vault' => self::SECRET,
			]
		);

		$this->assertSame( 200, $write->get_status() );
		$this->assertStringNotContainsString( self::SECRET, wp_json_encode( $write->get_data() ) );
		$this->assertStringNotContainsString( self::SECRET, wp_json_encode( $this->rest_read()->get_data() ) );

		$options = rest_get_server()->dispatch( new WP_REST_Request( 'OPTIONS', '/cassette-cmf/v1/settings/' . self::PAGE ) );
		$this->assertStringNotContainsString( self::SECRET, wp_json_encode( $options->get_data() ) );
	}

	/**
	 * Encryption round trip.
	 */
	public function test_encryption_round_trip(): void {
		$this->classic_save( 'vault', [ 'value' => self::SECRET ] );

		$stored = get_option( self::PAGE . '_vault' );

		$this->assertStringStartsWith( Secret_Field::ENCRYPTED_PREFIX, $stored );
		$this->assertStringNotContainsString( self::SECRET, $stored );
		$this->assertSame( self::SECRET, CassetteCMF::get_secret( 'vault', self::PAGE ) );
		$this->assertSame( '••••9876', CassetteCMF::get_field( 'vault', self::PAGE, 'settings' ) );

		// Keep does not re-encrypt.
		$this->classic_save( 'vault', [ 'value' => '' ] );
		$this->assertSame( $stored, get_option( self::PAGE . '_vault' ) );

		// Sanitizing the stored form is idempotent (double sanitize in update_option).
		$this->assertSame( $stored, $this->field( 'vault' )->sanitize( $stored ) );

		// The stored layout: marker + base64( version 1 + nonce + ciphertext ).
		$raw = base64_decode( substr( $stored, strlen( Secret_Field::ENCRYPTED_PREFIX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Inspecting the binary payload layout.
		$this->assertSame( 1, ord( $raw[0] ) );
		$this->assertSame( 1 + SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + strlen( self::SECRET ) + SODIUM_CRYPTO_SECRETBOX_MACBYTES, strlen( $raw ) );
	}

	/**
	 * Two encryptions of one value differ (random nonce).
	 */
	public function test_nonce_is_random(): void {
		$a = $this->field( 'vault' )->sanitize( 'same-value-123456' );
		$b = $this->field( 'vault' )->sanitize( 'same-value-123456' );

		$this->assertNotSame( $a, $b );
	}

	/**
	 * A plaintext value stays readable after switching to encrypt.
	 */
	public function test_plaintext_value_is_readable_by_an_encrypting_field(): void {
		update_option( self::PAGE . '_vault', 'legacy-plain-0000' );

		$this->assertSame( 'legacy-plain-0000', CassetteCMF::get_secret( 'vault', self::PAGE ) );
		$this->assertSame( '••••0000', CassetteCMF::get_field( 'vault', self::PAGE, 'settings' ) );
	}

	/**
	 * Changed salts: '' plus an admin notice.
	 */
	public function test_wrong_key_returns_empty_and_notices(): void {
		$this->classic_save( 'vault', [ 'value' => self::SECRET ] );
		$this->assertSame( self::SECRET, CassetteCMF::get_secret( 'vault', self::PAGE ) );

		$rotate = static function () {
			return 'a-completely-different-salt';
		};
		add_filter( 'salt', $rotate );

		$this->assertSame( '', CassetteCMF::get_secret( 'vault', self::PAGE ) );
		$this->assertSame( '', CassetteCMF::get_field( 'vault', self::PAGE, 'settings' ) );

		ob_start();
		Secret_Field::print_decrypt_notice();
		$notice = (string) ob_get_clean();
		$this->assertStringContainsString( 'enter it again', $notice );
		$this->assertStringContainsString( 'Vault key', $notice );

		remove_filter( 'salt', $rotate );
		$this->assertSame( self::SECRET, CassetteCMF::get_secret( 'vault', self::PAGE ) );
	}

	/**
	 * A tampered payload decrypts to ''.
	 */
	public function test_corrupt_payload_returns_empty(): void {
		update_option( self::PAGE . '_vault', Secret_Field::ENCRYPTED_PREFIX . 'bm90LWEtcmVhbC1wYXlsb2Fk' );

		$this->assertSame( '', CassetteCMF::get_secret( 'vault', self::PAGE ) );
	}

	/**
	 * get_secret() and get_field() for other fields.
	 */
	public function test_get_secret_is_limited_to_secret_fields(): void {
		update_option( self::OPTION, self::SECRET );

		$this->assertSame( self::SECRET, CassetteCMF::get_secret( 'token', self::PAGE ) );
		$this->assertSame( '••••9876', CassetteCMF::get_field( 'token', self::PAGE, 'settings' ) );
		$this->assertSame( '••••9876', CassetteCMF::get_settings_field( 'token', self::PAGE ) );
		$this->assertSame( '', CassetteCMF::get_secret( 'missing', self::PAGE ) );
		$this->assertSame( '', CassetteCMF::get_secret( 'token', 'other-page' ) );
	}

	/**
	 * A secret field can never be exposed for bindings.
	 */
	public function test_secret_is_never_public(): void {
		$this->manager->get_new_settings_handler()->add_fields(
			'bind-page',
			[
				[
					'name'     => 'k',
					'type'     => 'secret',
					'bindings' => true,
				],
			]
		);

		$this->assertFalse( $this->manager->is_field_public( 'k', 'bind-page', 'settings' ) );
	}

	/**
	 * Required rules run against the plaintext.
	 */
	public function test_required_secret(): void {
		$field = Field_Factory::create(
			[
				'name'     => 'req',
				'type'     => 'secret',
				'required' => true,
				'encrypt'  => true,
			]
		);

		$this->assertFalse( $field->validate( '' )['valid'] );
		$this->assertTrue( $field->validate( $field->sanitize( 'a-real-secret-value' ) )['valid'] );
	}
}
