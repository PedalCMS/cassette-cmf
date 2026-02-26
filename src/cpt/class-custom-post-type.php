<?php
/**
 * Custom_Post_Type class for Cassette-CMF
 *
 * Handles registration and configuration of WordPress custom post types.
 * Provides a clean API for defining CPT labels, arguments, and supports.
 *
 * @package Pedalcms\CassetteCmf
 * @since 1.0.0
 */

namespace Pedalcms\CassetteCmf\CPT;

/**
 * Custom_Post_Type class - Manages custom post type registration
 *
 * Provides a fluent interface for configuring and registering custom post types
 * with WordPress, including labels, arguments, and feature support.
 */
class Custom_Post_Type {

	/**
	 * Post type slug
	 *
	 * @var string
	 */
	private string $post_type;

	/**
	 * Post type arguments
	 *
	 * @var array<string, mixed>
	 */
	private array $args = [];

	/**
	 * Post type labels
	 *
	 * @var array<string, string>
	 */
	private array $labels = [];

	/**
	 * Post type supports
	 *
	 * @var array<string>
	 */
	private array $supports = [];

	/**
	 * Whether the post type has been registered
	 *
	 * @var bool
	 */
	private bool $registered = false;

	/**
	 * Constructor
	 *
	 * @param string               $post_type Post type slug.
	 * @param array<string, mixed> $config    Configuration array.
	 */
	public function __construct( string $post_type, array $config = [] ) {
		$this->post_type = $post_type;
		$this->configure( $config );
	}

	/**
	 * Configure the post type from an array
	 *
	 * @param array<string, mixed> $config Configuration array.
	 * @return self
	 */
	public function configure( array $config ): self {
		// Set labels if provided
		if ( isset( $config['labels'] ) && is_array( $config['labels'] ) ) {
			$this->labels = array_merge( $this->labels, $config['labels'] );
		}

		// Set supports if provided
		if ( isset( $config['supports'] ) && is_array( $config['supports'] ) ) {
			$this->supports = $config['supports'];
		}

		// Set arguments (everything except labels and supports)
		$args_config = array_diff_key( $config, array_flip( [ 'labels', 'supports' ] ) );
		$this->args  = array_merge( $this->args, $args_config );

		return $this;
	}

	/**
	 * Set post type labels
	 *
	 * @param array<string, string> $labels Labels array.
	 * @return self
	 */
	public function set_labels( array $labels ): self {
		$this->labels = array_merge( $this->labels, $labels );
		return $this;
	}

	/**
	 * Set a single label
	 *
	 * @param string $key   Label key.
	 * @param string $value Label value.
	 * @return self
	 */
	public function set_label( string $key, string $value ): self {
		$this->labels[ $key ] = $value;
		return $this;
	}

	/**
	 * Set post type arguments
	 *
	 * @param array<string, mixed> $args Arguments array.
	 * @return self
	 */
	public function set_args( array $args ): self {
		$this->args = array_merge( $this->args, $args );
		return $this;
	}

	/**
	 * Set a single argument
	 *
	 * @param string $key   Argument key.
	 * @param mixed  $value Argument value.
	 * @return self
	 */
	public function set_arg( string $key, $value ): self {
		$this->args[ $key ] = $value;
		return $this;
	}

	/**
	 * Set post type supports
	 *
	 * @param array<string> $supports Supports array.
	 * @return self
	 */
	public function set_supports( array $supports ): self {
		$this->supports = $supports;
		return $this;
	}

	/**
	 * Add a single support feature
	 *
	 * @param string $feature Feature to support.
	 * @return self
	 */
	public function add_support( string $feature ): self {
		if ( ! in_array( $feature, $this->supports, true ) ) {
			$this->supports[] = $feature;
		}
		return $this;
	}

	/**
	 * Remove a support feature
	 *
	 * @param string $feature Feature to remove.
	 * @return self
	 */
	public function remove_support( string $feature ): self {
		$this->supports = array_filter(
			$this->supports,
			function ( $support ) use ( $feature ) {
				return $support !== $feature;
			}
		);
		return $this;
	}

	/**
	 * Generate default labels based on post type name
	 *
	 * @param string $singular Singular name.
	 * @param string $plural   Plural name.
	 * @return self
	 */
	public function generate_labels( string $singular, string $plural ): self {
		$this->labels = array_merge(
			[
				'name'                  => $plural,
				'singular_name'         => $singular,
				'menu_name'             => $plural,
				'name_admin_bar'        => $singular,
				'archives'              => sprintf( '%s Archives', $singular ),
				'attributes'            => sprintf( '%s Attributes', $singular ),
				'parent_item_colon'     => sprintf( 'Parent %s:', $singular ),
				'all_items'             => sprintf( 'All %s', $plural ),
				'add_new_item'          => sprintf( 'Add New %s', $singular ),
				'add_new'               => 'Add New',
				'new_item'              => sprintf( 'New %s', $singular ),
				'edit_item'             => sprintf( 'Edit %s', $singular ),
				'update_item'           => sprintf( 'Update %s', $singular ),
				'view_item'             => sprintf( 'View %s', $singular ),
				'view_items'            => sprintf( 'View %s', $plural ),
				'search_items'          => sprintf( 'Search %s', $plural ),
				'not_found'             => 'Not found',
				'not_found_in_trash'    => 'Not found in Trash',
				'featured_image'        => 'Featured Image',
				'set_featured_image'    => 'Set featured image',
				'remove_featured_image' => 'Remove featured image',
				'use_featured_image'    => 'Use as featured image',
				'insert_into_item'      => sprintf( 'Insert into %s', strtolower( $singular ) ),
				'uploaded_to_this_item' => sprintf( 'Uploaded to this %s', strtolower( $singular ) ),
				'items_list'            => sprintf( '%s list', $plural ),
				'items_list_navigation' => sprintf( '%s list navigation', $plural ),
				'filter_items_list'     => sprintf( 'Filter %s list', strtolower( $plural ) ),
			],
			$this->labels
		);

		return $this;
	}

	/**
	 * Set default arguments for a typical custom post type
	 *
	 * @return self
	 */
	public function set_defaults(): self {
		$defaults = [
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => [ 'slug' => $this->post_type ],
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => null,
			'show_in_rest'       => true,
		];

		$this->args = array_merge( $defaults, $this->args );

		// Set default supports if none specified
		if ( empty( $this->supports ) ) {
			$this->supports = [ 'title', 'editor', 'thumbnail' ];
		}

		return $this;
	}

	/**
	 * Register the post type with WordPress
	 *
	 * @return bool True if registration was successful, false otherwise.
	 */
	public function register(): bool {
		if ( $this->registered ) {
			return true;
		}

		// Check if WordPress functions are available
		if ( ! function_exists( 'register_post_type' ) ) {
			return false;
		}

		// Prepare final arguments
		$final_args = $this->args;

		// Add labels to arguments
		if ( ! empty( $this->labels ) ) {
			$final_args['labels'] = $this->labels;
		}

		// Add supports to arguments
		if ( ! empty( $this->supports ) ) {
			$final_args['supports'] = $this->supports;
		}

		// Register the post type
		$result = register_post_type( $this->post_type, $final_args );

		if ( ! is_wp_error( $result ) ) {
			$this->registered = true;
			return true;
		}

		return false;
	}

	/**
	 * Get the post type slug
	 *
	 * @return string
	 */
	public function get_post_type(): string {
		return $this->post_type;
	}

	/**
	 * Get the post type arguments
	 *
	 * @return array<string, mixed>
	 */
	public function get_args(): array {
		return $this->args;
	}

	/**
	 * Get the post type labels
	 *
	 * @return array<string, string>
	 */
	public function get_labels(): array {
		return $this->labels;
	}

	/**
	 * Get the post type supports
	 *
	 * @return array<string>
	 */
	public function get_supports(): array {
		return $this->supports;
	}

	/**
	 * Check if the post type has been registered
	 *
	 * @return bool
	 */
	public function is_registered(): bool {
		return $this->registered;
	}

	/**
	 * Create a CustomPostType instance from array configuration
	 *
	 * @param string               $post_type Post type slug.
	 * @param array<string, mixed> $config    Configuration array.
	 * @return self
	 */
	public static function from_array( string $post_type, array $config ): self {
		$instance = new self( $post_type, $config );

		// If no explicit labels are set, try to generate them from config
		if ( empty( $instance->labels ) ) {
			$singular = $config['singular'] ?? ucfirst( str_replace( [ '_', '-' ], ' ', $post_type ) );
			$plural   = $config['plural'] ?? $singular . 's';
			$instance->generate_labels( $singular, $plural );
		}

		// Set defaults if not explicitly configured
		$instance->set_defaults();

		return $instance;
	}
}
