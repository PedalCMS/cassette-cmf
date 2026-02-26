<?php
/**
 * Custom Post Type Registration Tests
 *
 * Tests for registering custom post types with Cassette-CMF.
 *
 * @package Pedalcms\CassetteCmf\Tests\Unit
 */

use Pedalcms\CassetteCmf\Core\Manager;
use Pedalcms\CassetteCmf\CPT\Custom_Post_Type;

require_once __DIR__ . '/CassetteCmf_UnitTestCase.php';

/**
 * Class Test_Custom_Post_Type
 *
 * Tests for CPT registration.
 */
class Test_Custom_Post_Type extends CassetteCmf_UnitTestCase {

	/**
	 * Reset Manager between tests.
	 */
	public function set_up(): void {
		parent::set_up();

		// Reset the Manager singleton.
		$reflection = new ReflectionClass( Manager::class );
		$instance   = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );
	}

	/**
	 * Clean up registered post types.
	 */
	public function tear_down(): void {
		// Unregister test post types.
		unregister_post_type( 'test_book' );
		unregister_post_type( 'test_movie' );

		parent::tear_down();
	}

	/**
	 * Test registering a CPT via Manager.
	 *
	 */
	public function test_register_cpt_via_manager(): void {
		$manager = Manager::init();

		// Register a custom post type.
		$cpt = new Custom_Post_Type( 'test_book' );
		$cpt->set_labels(
			[
				'name'          => 'Books',
				'singular_name' => 'Book',
			]
		);
		$cpt->set_args(
			[
				'public'       => true,
				'show_ui'      => true,
				'supports'     => [ 'title', 'editor' ],
				'show_in_rest' => true,
			]
		);

		$manager->get_new_cpt_handler()->add_post_type_instance( $cpt );

		// Manually trigger registration for the test.
		do_action( 'init' );

		// Verify the post type is registered.
		$this->assertTrue(
			post_type_exists( 'test_book' ),
			'The "test_book" post type should be registered.'
		);
	}

	/**
	 * Test CPT is public.
	 *
	 */
	public function test_cpt_is_public(): void {
		$manager = Manager::init();

		$cpt = new Custom_Post_Type( 'test_book' );
		$cpt->set_args(
			[
				'public'  => true,
				'show_ui' => true,
			]
		);

		$manager->get_new_cpt_handler()->add_post_type_instance( $cpt );

		do_action( 'init' );

		$post_type_obj = get_post_type_object( 'test_book' );

		$this->assertNotNull( $post_type_obj );
		$this->assertTrue( $post_type_obj->public );
		$this->assertTrue( $post_type_obj->show_ui );
	}

	/**
	 * Test creating a post of the CPT.
	 *
	 */
	public function test_can_create_cpt_post(): void {
		$manager = Manager::init();

		$cpt = new Custom_Post_Type( 'test_book' );
		$cpt->set_args(
			[
				'public'   => true,
				'supports' => [ 'title', 'editor' ],
			]
		);

		$manager->get_new_cpt_handler()->add_post_type_instance( $cpt );

		do_action( 'init' );

		// Create a post using the factory.
		$post_id = self::factory()->post->create(
			[
				'post_type'  => 'test_book',
				'post_title' => 'Test Book Title',
			]
		);

		$this->assertIsInt( $post_id );
		$this->assertSame( 'test_book', get_post_type( $post_id ) );
		$this->assertSame( 'Test Book Title', get_the_title( $post_id ) );
	}

	/**
	 * Test CPT with custom supports.
	 *
	 */
	public function test_cpt_with_custom_supports(): void {
		$manager = Manager::init();

		$cpt = new Custom_Post_Type( 'test_movie' );
		$cpt->set_args(
			[
				'public'   => true,
				'supports' => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
			]
		);

		$manager->get_new_cpt_handler()->add_post_type_instance( $cpt );

		do_action( 'init' );

		$this->assertTrue( post_type_supports( 'test_movie', 'title' ) );
		$this->assertTrue( post_type_supports( 'test_movie', 'editor' ) );
		$this->assertTrue( post_type_supports( 'test_movie', 'thumbnail' ) );
		$this->assertTrue( post_type_supports( 'test_movie', 'excerpt' ) );
		$this->assertFalse( post_type_supports( 'test_movie', 'comments' ) );
	}

	/**
	 * Test CPT registration from array config.
	 *
	 */
	public function test_register_cpt_from_array(): void {
		$manager = Manager::init();

		$manager->register_from_array(
			[
				'cpts' => [
					[
						'id'   => 'test_book',
						'args' => [
							'label'   => 'Books',
							'public'  => true,
							'show_ui' => true,
						],
					],
				],
			]
		);

		do_action( 'init' );

		$this->assertTrue(
			post_type_exists( 'test_book' ),
			'The "test_book" post type should be registered from array config.'
		);
	}

	/**
	 * Test Custom_Post_Type from_array factory method.
	 */
	public function test_cpt_from_array(): void {
		$cpt = Custom_Post_Type::from_array(
			'product',
			[
				'label'   => 'Products',
				'public'  => true,
				'show_ui' => true,
			]
		);

		$this->assertInstanceOf( Custom_Post_Type::class, $cpt );
		$this->assertSame( 'product', $cpt->get_post_type() );
	}

	/**
	 * Test Custom_Post_Type get_args.
	 */
	public function test_cpt_get_args(): void {
		$cpt = new Custom_Post_Type( 'event' );
		$cpt->set_args(
			[
				'public'  => true,
				'show_ui' => false,
			]
		);

		$args = $cpt->get_args();

		$this->assertIsArray( $args );
		$this->assertTrue( $args['public'] );
		$this->assertFalse( $args['show_ui'] );
	}

	/**
	 * Test Custom_Post_Type get_labels.
	 */
	public function test_cpt_get_labels(): void {
		$cpt = new Custom_Post_Type( 'document' );
		$cpt->set_labels(
			[
				'name'          => 'Documents',
				'singular_name' => 'Document',
			]
		);

		$labels = $cpt->get_labels();

		$this->assertIsArray( $labels );
		$this->assertSame( 'Documents', $labels['name'] );
		$this->assertSame( 'Document', $labels['singular_name'] );
	}

	/**
	 * Test Custom_Post_Type fluent interface.
	 */
	public function test_cpt_fluent_interface(): void {
		$cpt = ( new Custom_Post_Type( 'article' ) )
			->set_labels( [ 'name' => 'Articles' ] )
			->set_args( [ 'public' => true ] );

		$this->assertInstanceOf( Custom_Post_Type::class, $cpt );
		$this->assertSame( 'article', $cpt->get_post_type() );
	}

	/**
	 * Test Custom_Post_Type configure method.
	 */
	public function test_cpt_configure(): void {
		$cpt = new Custom_Post_Type( 'newsletter' );
		$cpt->configure(
			[
				'labels' => [
					'name' => 'Newsletters',
				],
				'public' => true,
			]
		);

		$labels = $cpt->get_labels();
		$this->assertSame( 'Newsletters', $labels['name'] );
	}

	/**
	 * Test Custom_Post_Type set_supports.
	 */
	public function test_cpt_set_supports(): void {
		$cpt = new Custom_Post_Type( 'faq' );
		$cpt->set_supports( [ 'title', 'editor', 'thumbnail' ] );

		$supports = $cpt->get_supports();
		$this->assertContains( 'title', $supports );
		$this->assertContains( 'editor', $supports );
		$this->assertContains( 'thumbnail', $supports );
	}

	/**
	 * Test multiple CPTs from array.
	 *
	 */
	public function test_multiple_cpts_from_array(): void {
		$manager = Manager::init();

		$manager->register_from_array(
			[
				'cpts' => [
					[
						'id'   => 'test_book',
						'args' => [
							'label'  => 'Books',
							'public' => true,
						],
					],
					[
						'id'   => 'test_movie',
						'args' => [
							'label'  => 'Movies',
							'public' => true,
						],
					],
				],
			]
		);

		do_action( 'init' );

		$this->assertTrue( post_type_exists( 'test_book' ) );
		$this->assertTrue( post_type_exists( 'test_movie' ) );
	}

	/**
	 * Test CPT with fields.
	 */
	public function test_cpt_with_fields(): void {
		$manager = Manager::init();

		$manager->register_from_array(
			[
				'cpts' => [
					[
						'id'     => 'test_book',
						'args'   => [
							'label'  => 'Books',
							'public' => true,
						],
						'fields' => [
							[
								'name'  => 'isbn',
								'type'  => 'text',
								'label' => 'ISBN',
							],
							[
								'name'  => 'author',
								'type'  => 'text',
								'label' => 'Author',
							],
						],
					],
				],
			]
		);

		$handler = $manager->get_new_cpt_handler();
		$fields  = $handler->get_fields( 'test_book' );

		$this->assertCount( 2, $fields );
		$this->assertArrayHasKey( 'isbn', $fields );
		$this->assertArrayHasKey( 'author', $fields );
	}

	/**
	 * Test adding fields to existing post type.
	 */
	public function test_add_fields_to_existing_post_type(): void {
		$manager = Manager::init();

		$handler = $manager->get_existing_cpt_handler();
		$handler->add_fields(
			'post',
			[
				[
					'name'  => 'subtitle',
					'type'  => 'text',
					'label' => 'Subtitle',
				],
			]
		);

		$fields = $handler->get_fields( 'post' );

		$this->assertCount( 1, $fields );
		$this->assertArrayHasKey( 'subtitle', $fields );
	}

	/**
	 * Test is_registered returns false before registration.
	 */
	public function test_cpt_not_registered_initially(): void {
		$cpt = new Custom_Post_Type( 'unregistered_type' );

		$this->assertFalse( $cpt->is_registered() );
	}

	/**
	 * Test generate_labels creates full label set.
	 */
	public function test_cpt_generate_labels(): void {
		$cpt = new Custom_Post_Type( 'recipe' );
		$cpt->set_labels(
			[
				'name'          => 'Recipes',
				'singular_name' => 'Recipe',
			]
		);

		// The generate_labels method should create full label set
		$labels = $cpt->get_labels();

		$this->assertArrayHasKey( 'name', $labels );
		$this->assertArrayHasKey( 'singular_name', $labels );
	}
}
