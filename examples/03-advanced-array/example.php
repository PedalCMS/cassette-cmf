<?php

/**
 * Plugin Name: Cassette-CMF Advanced Example (Array)
 * Plugin URI: https://github.com/pedalcms/cassette-cmf
 * Description: Advanced example demonstrating ALL Cassette-CMF capabilities using PHP array configuration
 * Version: 1.0.0
 * Author: PedalCMS
 * License: GPL v2 or later
 *
 * @package CassetteCmfAdvancedArray
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

use Pedalcms\CassetteCmf\CassetteCmf;

/**
 * =============================================================================
 * ADVANCED ARRAY EXAMPLE
 * =============================================================================
 *
 * This comprehensive example demonstrates ALL Cassette-CMF capabilities:
 *
 * 1. Creating a new Custom Post Type with multiple metaboxes
 * 2. Creating a new Settings Page with tabs and groups
 * 3. Creating new Taxonomies with custom fields
 * 4. Adding fields to existing taxonomies (category)
 * 5. Adding fields to existing post types (post, page)
 * 6. Adding fields to existing settings pages (general)
 * 7. All 16 field types:
 *    - Basic: text, textarea, number, email, url, password, date, color
 *    - Choice: select, checkbox, radio
 *    - Rich: wysiwyg
 *    - Containers: tabs, metabox, group, repeater
 * 8. Before-save filters
 * 9. Field validation and sanitization
 * =============================================================================
 */
function cassette_cmf_advanced_array_init() {
	$cmf = CassetteCmf::init();

	// =========================================================================
	// PART 1: NEW CUSTOM POST TYPE - Product
	// =========================================================================
	$cmf->register_from_array(
		[
			'cpts' => [
				[
					'id'     => 'product',
					'args'   => [
						'label'         => 'Products',
						'public'        => true,
						'has_archive'   => true,
						'show_in_rest'  => true,
						'supports'      => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
						'menu_icon'     => 'dashicons-cart',
						'menu_position' => 25,
					],
					'fields' => [
						// ---------------------------------------------------------
						// METABOX 1: Basic Information
						// ---------------------------------------------------------
						[
							'name'     => 'basic_info',
							'type'     => 'metabox',
							'label'    => 'Basic Information',
							'context'  => 'normal',
							'priority' => 'high',
							'fields'   => [
								[
									'name'        => 'sku',
									'type'        => 'text',
									'label'       => 'SKU',
									'description' => 'Stock Keeping Unit',
									'required'    => true,
								],
								[
									'name'  => 'price',
									'type'  => 'number',
									'label' => 'Price ($)',
									'min'   => 0,
									'step'  => 0.01,
								],
								[
									'name'  => 'sale_price',
									'type'  => 'number',
									'label' => 'Sale Price ($)',
									'min'   => 0,
									'step'  => 0.01,
								],
								[
									'name'    => 'stock_status',
									'type'    => 'select',
									'label'   => 'Stock Status',
									'options' => [
										'instock'    => 'In Stock',
										'outofstock' => 'Out of Stock',
										'backorder'  => 'On Backorder',
									],
									'default' => 'instock',
								],
								[
									'name'  => 'quantity',
									'type'  => 'number',
									'label' => 'Quantity in Stock',
									'min'   => 0,
								],
								[
									'name'         => 'product_image',
									'type'         => 'upload',
									'label'        => 'Product Image',
									'description'  => 'Main product image',
									'library_type' => 'image',
									'button_text'  => 'Select Image',
								],
							],
						],

						// ---------------------------------------------------------
						// METABOX 2: Product Details (with Tabs)
						// ---------------------------------------------------------
						[
							'name'     => 'product_details',
							'type'     => 'metabox',
							'label'    => 'Product Details',
							'context'  => 'normal',
							'priority' => 'default',
							'fields'   => [
								[
									'name'        => 'details_tabs',
									'type'        => 'tabs',
									'orientation' => 'horizontal',
									'tabs'        => [
										[
											'id'     => 'description_tab',
											'label'  => 'Description',
											'icon'   => 'dashicons-edit',
											'fields' => [
												[
													'name' => 'short_description',
													'type' => 'textarea',
													'label' => 'Short Description',
													'rows' => 3,
												],
												[
													'name' => 'full_description',
													'type' => 'wysiwyg',
													'label' => 'Full Description',
												],
											],
										],
										[
											'id'     => 'specs_tab',
											'label'  => 'Specifications',
											'icon'   => 'dashicons-list-view',
											'fields' => [
												[
													'name' => 'weight',
													'type' => 'text',
													'label' => 'Weight',
												],
												[
													'name' => 'dimensions',
													'type' => 'text',
													'label' => 'Dimensions (L x W x H)',
												],
												[
													'name' => 'material',
													'type' => 'text',
													'label' => 'Material',
												],
												[
													'name' => 'color_options',
													'type' => 'text',
													'label' => 'Available Colors',
												],
											],
										],
										[
											'id'     => 'shipping_tab',
											'label'  => 'Shipping',
											'icon'   => 'dashicons-car',
											'fields' => [
												[
													'name' => 'free_shipping',
													'type' => 'checkbox',
													'label' => 'Free Shipping',
													'description' => 'Enable free shipping for this product',
												],
												[
													'name' => 'shipping_class',
													'type' => 'select',
													'label' => 'Shipping Class',
													'options' => [
														'standard' => 'Standard',
														'express'  => 'Express',
														'freight'  => 'Freight',
													],
												],
												[
													'name' => 'handling_time',
													'type' => 'number',
													'label' => 'Handling Time (days)',
													'min'  => 0,
													'max'  => 30,
												],
											],
										],
									],
								],
							],
						],

						// ---------------------------------------------------------
						// METABOX 3: Categories & Tags (Side)
						// ---------------------------------------------------------
						[
							'name'     => 'categorization',
							'type'     => 'metabox',
							'label'    => 'Categorization',
							'context'  => 'side',
							'priority' => 'default',
							'fields'   => [
								[
									'name'     => 'product_category',
									'type'     => 'checkbox',
									'label'    => 'Categories',
									'multiple' => true,
									'options'  => [
										'electronics' => 'Electronics',
										'clothing'    => 'Clothing',
										'home'        => 'Home & Garden',
										'sports'      => 'Sports',
										'books'       => 'Books',
									],
								],
								[
									'name'    => 'brand',
									'type'    => 'select',
									'label'   => 'Brand',
									'options' => [
										''        => '-- Select Brand --',
										'apple'   => 'Apple',
										'samsung' => 'Samsung',
										'sony'    => 'Sony',
										'nike'    => 'Nike',
										'other'   => 'Other',
									],
								],
								[
									'name'        => 'featured',
									'type'        => 'checkbox',
									'label'       => 'Featured Product',
									'description' => 'Show on homepage',
								],
							],
						],

						// ---------------------------------------------------------
						// METABOX 4: Variations (Repeater)
						// ---------------------------------------------------------
						[
							'name'     => 'variations_box',
							'type'     => 'metabox',
							'label'    => 'Product Variations',
							'context'  => 'normal',
							'priority' => 'low',
							'fields'   => [
								[
									'name'         => 'variations',
									'type'         => 'repeater',
									'label'        => 'Variations',
									'button_label' => 'Add Variation',
									'fields'       => [
										[
											'name'  => 'variant_name',
											'type'  => 'text',
											'label' => 'Variation Name',
										],
										[
											'name'  => 'variant_sku',
											'type'  => 'text',
											'label' => 'Variation SKU',
										],
										[
											'name'  => 'variant_price',
											'type'  => 'number',
											'label' => 'Price',
											'min'   => 0,
											'step'  => 0.01,
										],
										[
											'name'  => 'variant_stock',
											'type'  => 'number',
											'label' => 'Stock',
											'min'   => 0,
										],
									],
								],
							],
						],
					],
				],
			],
		]
	);

	// =========================================================================
	// PART 2: NEW SETTINGS PAGE - Store Settings (as submenu under Products)
	// =========================================================================
	$cmf->register_from_array(
		[
			'settings_pages' => [
				[
					'id'          => 'store-settings',
					'page_title'  => 'Store Settings',
					'menu_title'  => 'Store',
					'capability'  => 'manage_options',
					'parent_slug' => 'edit.php?post_type=product',
					'fields'      => [
						// Tabs must be inside a metabox on settings pages
						[
							'name'     => 'store_settings_metabox',
							'type'     => 'metabox',
							'label'    => 'Store Configuration',
							'context'  => 'normal',
							'priority' => 'high',
							'fields'   => [
								// Settings with Vertical Tabs.
								[
									'name'        => 'store_tabs',
									'type'        => 'tabs',
									'orientation' => 'vertical',
									'tabs'        => [
										// Tab 1: General Settings.
										[
											'id'     => 'general_tab',
											'label'  => 'General',
											'icon'   => 'dashicons-admin-settings',
											'fields' => [
												// Custom HTML - welcome message
												[
													'name' => 'welcome_message',
													'type' => 'custom_html',
													'content' => '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;"><h3 style="margin: 0 0 10px 0; color: white;">🌟 Welcome to Store Settings</h3><p style="margin: 0;">Configure your store\'s general information, pricing, shipping, and appearance settings using the tabs on the left.</p></div>',
												],
												[
													'name' => 'store_name',
													'type' => 'text',
													'label' => 'Store Name',
													'placeholder' => 'My Awesome Store',
												],
												[
													'name' => 'store_tagline',
													'type' => 'text',
													'label' => 'Tagline',
												],
												[
													'name' => 'store_email',
													'type' => 'email',
													'label' => 'Store Email',
												],
												[
													'name' => 'store_url',
													'type' => 'url',
													'label' => 'Store URL',
												],
											],
										],
										// Tab 2: Currency & Pricing
										[
											'id'     => 'pricing_tab',
											'label'  => 'Pricing',
											'icon'   => 'dashicons-money-alt',
											'fields' => [
												[
													'name' => 'currency',
													'type' => 'select',
													'label' => 'Currency',
													'options' => [
														'USD' => 'US Dollar ($)',
														'EUR' => 'Euro (€)',
														'GBP' => 'British Pound (£)',
														'JPY' => 'Japanese Yen (¥)',
													],
													'default' => 'USD',
												],
												[
													'name' => 'currency_position',
													'type' => 'radio',
													'label' => 'Currency Position',
													'options' => [
														'before' => 'Before: $99.99',
														'after'  => 'After: 99.99$',
													],
													'default' => 'before',
												],
												[
													'name' => 'decimal_places',
													'type' => 'number',
													'label' => 'Decimal Places',
													'min'  => 0,
													'max'  => 4,
													'default' => 2,
												],
												[
													'name' => 'tax_enabled',
													'type' => 'checkbox',
													'label' => 'Enable Tax',
													'description' => 'Calculate tax on products',
												],
												[
													'name' => 'tax_rate',
													'type' => 'number',
													'label' => 'Tax Rate (%)',
													'min'  => 0,
													'max'  => 100,
													'step' => 0.01,
												],
											],
										],
										// Tab 3: Shipping Group
										[
											'id'     => 'shipping_tab',
											'label'  => 'Shipping',
											'icon'   => 'dashicons-car',
											'fields' => [
												[
													'name' => 'domestic_shipping',
													'type' => 'group',
													'label' => 'Domestic Shipping',
													'description' => 'Settings for domestic orders',
													'fields' => [
														[
															'name'  => 'domestic_flat_rate',
															'type'  => 'number',
															'label' => 'Flat Rate ($)',
															'min'   => 0,
															'step'  => 0.01,
														],
														[
															'name'  => 'domestic_free_threshold',
															'type'  => 'number',
															'label' => 'Free Shipping Threshold ($)',
															'min'   => 0,
														],
														[
															'name'  => 'domestic_handling_days',
															'type'  => 'number',
															'label' => 'Handling Days',
															'min'   => 0,
															'max'   => 14,
														],
													],
												],
												[
													'name' => 'international_shipping',
													'type' => 'group',
													'label' => 'International Shipping',
													'description' => 'Settings for international orders',
													'fields' => [
														[
															'name'        => 'intl_enabled',
															'type'        => 'checkbox',
															'label'       => 'Enable International Shipping',
														],
														[
															'name'  => 'intl_flat_rate',
															'type'  => 'number',
															'label' => 'Flat Rate ($)',
															'min'   => 0,
															'step'  => 0.01,
														],
														[
															'name'  => 'intl_handling_days',
															'type'  => 'number',
															'label' => 'Handling Days',
															'min'   => 0,
															'max'   => 30,
														],
													],
												],
											],
										],
										// Tab 4: Appearance
										[
											'id'     => 'appearance_tab',
											'label'  => 'Appearance',
											'icon'   => 'dashicons-admin-appearance',
											'fields' => [
												[
													'name' => 'primary_color',
													'type' => 'color',
													'label' => 'Primary Color',
													'default' => '#0073aa',
												],
												[
													'name' => 'secondary_color',
													'type' => 'color',
													'label' => 'Secondary Color',
													'default' => '#23282d',
												],
												[
													'name' => 'button_style',
													'type' => 'radio',
													'label' => 'Button Style',
													'options' => [
														'rounded' => 'Rounded',
														'square' => 'Square',
														'pill' => 'Pill',
													],
													'default' => 'rounded',
												],
												[
													'name' => 'custom_css',
													'type' => 'textarea',
													'label' => 'Custom CSS',
													'rows' => 6,
												],
											],
										],
										// Tab 5: Advanced (All Remaining Field Types)
										[
											'id'     => 'advanced_tab',
											'label'  => 'Advanced',
											'icon'   => 'dashicons-admin-tools',
											'fields' => [
												[
													'name' => 'api_key',
													'type' => 'password',
													'label' => 'API Key',
													'description' => 'Secret API key for integrations',
												],
												[
													'name' => 'webhook_url',
													'type' => 'url',
													'label' => 'Webhook URL',
												],
												[
													'name' => 'admin_email',
													'type' => 'email',
													'label' => 'Admin Email',
												],
												[
													'name' => 'launch_date',
													'type' => 'date',
													'label' => 'Store Launch Date',
												],
												[
													'name' => 'terms_conditions',
													'type' => 'wysiwyg',
													'label' => 'Terms & Conditions',
												],
											],
										],
									],
								],
							],
						],

						// ---------------------------------------------------------
						// METABOX 2: Quick Settings (No Tabs - Direct Fields)
						// ---------------------------------------------------------
						[
							'name'     => 'quick_settings',
							'type'     => 'metabox',
							'label'    => 'Quick Settings',
							'context'  => 'normal',
							'priority' => 'default',
							'fields'   => [
								[
									'name'        => 'maintenance_mode',
									'type'        => 'checkbox',
									'label'       => 'Maintenance Mode',
									'description' => 'Temporarily disable the store for maintenance',
								],
								[
									'name'        => 'store_phone',
									'type'        => 'text',
									'label'       => 'Store Phone Number',
									'placeholder' => '(555) 123-4567',
								],
								[
									'name'        => 'support_email',
									'type'        => 'email',
									'label'       => 'Support Email',
									'description' => 'Customer support contact email',
								],
								[
									'name'        => 'max_order_quantity',
									'type'        => 'number',
									'label'       => 'Max Order Quantity',
									'description' => 'Maximum items per order',
									'min'         => 1,
									'default'     => 99,
								],
								[
									'name'          => 'announcement_text',
									'type'          => 'textarea',
									'label'         => 'Announcement Banner',
									'description'   => 'Show announcement at top of store',
									'rows'          => 2,
									'placeholder'   => 'Free shipping on orders over $50!',
									'textarea_rows' => 20,
								],
							],
						],
					],
				],
			],
		]
	);

	// =========================================================================
	// PART 3: NEW TAXONOMY - Product Category
	// =========================================================================
	$cmf->register_from_array(
		[
			'taxonomies' => [
				[
					'id'          => 'product_category',
					'object_type' => [ 'product' ],
					'args'        => [
						'label'             => 'Product Categories',
						'hierarchical'      => true,
						'public'            => true,
						'show_in_rest'      => true,
						'show_admin_column' => true,
					],
					'fields'      => [
						// Color for category badge
						[
							'name'        => 'category_color',
							'type'        => 'color',
							'label'       => 'Category Color',
							'description' => 'Color used for category labels and badges',
							'default'     => '#0073aa',
						],
						// Icon class
						[
							'name'        => 'category_icon',
							'type'        => 'text',
							'label'       => 'Icon Class',
							'description' => 'Dashicons class (e.g., dashicons-cart)',
							'placeholder' => 'dashicons-category',
						],
						// Featured image URL
						[
							'name'        => 'category_image',
							'type'        => 'url',
							'label'       => 'Category Image URL',
							'description' => 'Image to display for this category',
						],
						// Display order
						[
							'name'    => 'display_order',
							'type'    => 'number',
							'label'   => 'Display Order',
							'min'     => 0,
							'default' => 0,
						],
						// Featured category
						[
							'name'        => 'is_featured',
							'type'        => 'checkbox',
							'label'       => 'Featured Category',
							'description' => 'Show this category prominently on the store',
						],
						// Commission rate for this category
						[
							'name'        => 'commission_rate',
							'type'        => 'number',
							'label'       => 'Commission Rate (%)',
							'description' => 'Commission percentage for products in this category',
							'min'         => 0,
							'max'         => 100,
							'step'        => 0.1,
						],
					],
				],
				// Non-hierarchical taxonomy (like tags)
				[
					'id'          => 'product_brand',
					'object_type' => [ 'product' ],
					'args'        => [
						'label'             => 'Brands',
						'hierarchical'      => false,
						'public'            => true,
						'show_in_rest'      => true,
						'show_admin_column' => true,
					],
					'fields'      => [
						// Brand website
						[
							'name'        => 'brand_website',
							'type'        => 'url',
							'label'       => 'Brand Website',
							'placeholder' => 'https://brand.com',
						],
						// Brand description
						[
							'name'  => 'brand_description',
							'type'  => 'textarea',
							'label' => 'Brand Description',
							'rows'  => 3,
						],
						// Country of origin
						[
							'name'    => 'brand_country',
							'type'    => 'select',
							'label'   => 'Country of Origin',
							'options' => [
								''   => '-- Select Country --',
								'us' => 'United States',
								'uk' => 'United Kingdom',
								'de' => 'Germany',
								'jp' => 'Japan',
								'cn' => 'China',
								'kr' => 'South Korea',
							],
						],
						// Verified brand
						[
							'name'        => 'is_verified',
							'type'        => 'checkbox',
							'label'       => 'Verified Brand',
							'description' => 'Mark as an officially verified brand partner',
						],
					],
				],
			],
		]
	);

	// =========================================================================
	// PART 4: ADD FIELDS TO EXISTING TAXONOMY (category)
	// =========================================================================
	$cmf->register_from_array(
		[
			'taxonomies' => [
				[
					'id'     => 'category', // Built-in taxonomy
					'fields' => [
						// Subtitle
						[
							'name'        => 'category_subtitle',
							'type'        => 'text',
							'label'       => 'Category Subtitle',
							'description' => 'Short subtitle displayed below the category name',
						],
						// Category color
						[
							'name'    => 'category_color',
							'type'    => 'color',
							'label'   => 'Category Color',
							'default' => '#333333',
						],
						// Featured
						[
							'name'        => 'is_featured',
							'type'        => 'checkbox',
							'label'       => 'Featured Category',
							'description' => 'Display this category on the homepage',
						],
					],
				],
			],
		]
	);

	// =========================================================================
	// PART 5: ADD FIELDS TO EXISTING POST TYPE (post)
	// =========================================================================
	$cmf->register_from_array(
		[
			'cpts' => [
				[
					'id'     => 'post', // Built-in post type
					'fields' => [
						[
							'name'     => 'post_options',
							'type'     => 'metabox',
							'label'    => 'Post Options',
							'context'  => 'side',
							'priority' => 'high',
							'fields'   => [
								[
									'name'        => 'sponsored',
									'type'        => 'checkbox',
									'label'       => 'Sponsored Post',
									'description' => 'Mark as sponsored content',
								],
								[
									'name'  => 'sponsor_name',
									'type'  => 'text',
									'label' => 'Sponsor Name',
								],
								[
									'name'  => 'sponsor_url',
									'type'  => 'url',
									'label' => 'Sponsor URL',
								],
							],
						],
						[
							'name'     => 'reading_time',
							'type'     => 'metabox',
							'label'    => 'Reading Info',
							'context'  => 'side',
							'priority' => 'low',
							'fields'   => [
								[
									'name'        => 'read_time',
									'type'        => 'number',
									'label'       => 'Reading Time (min)',
									'description' => 'Estimated reading time',
									'min'         => 1,
								],
								[
									'name'    => 'difficulty',
									'type'    => 'select',
									'label'   => 'Difficulty',
									'options' => [
										'beginner'     => 'Beginner',
										'intermediate' => 'Intermediate',
										'advanced'     => 'Advanced',
									],
								],
							],
						],
					],
				],
			],
		]
	);

	// =========================================================================
	// PART 6: ADD FIELDS TO EXISTING POST TYPE (page)
	// =========================================================================
	$cmf->register_from_array(
		[
			'cpts' => [
				[
					'id'     => 'page', // Built-in page type
					'fields' => [
						[
							'name'     => 'page_settings',
							'type'     => 'metabox',
							'label'    => 'Page Settings',
							'context'  => 'normal',
							'priority' => 'high',
							'fields'   => [
								[
									'name'        => 'hide_title',
									'type'        => 'checkbox',
									'label'       => 'Hide Page Title',
									'description' => 'Hide the title on the frontend',
								],
								[
									'name'    => 'sidebar_position',
									'type'    => 'radio',
									'label'   => 'Sidebar Position',
									'options' => [
										'none'  => 'No Sidebar',
										'left'  => 'Left',
										'right' => 'Right',
									],
									'default' => 'none',
								],
								[
									'name'    => 'page_layout',
									'type'    => 'select',
									'label'   => 'Page Layout',
									'options' => [
										'default'    => 'Default',
										'full-width' => 'Full Width',
										'boxed'      => 'Boxed',
										'landing'    => 'Landing Page',
									],
								],
								[
									'name'  => 'header_background',
									'type'  => 'color',
									'label' => 'Header Background Color',
								],
							],
						],
					],
				],
			],
		]
	);

	// =========================================================================
	// PART 7: ADD FIELDS TO EXISTING SETTINGS PAGE (general)
	// =========================================================================
	$cmf->register_from_array(
		[
			'settings_pages' => [
				[
					'id'     => 'general', // WordPress General Settings
					'parent' => 'options-general.php',
					'fields' => [
						[
							'name'        => 'social_links',
							'type'        => 'group',
							'label'       => 'Social Media Links',
							'description' => 'Add your social media profiles',
							'fields'      => [
								[
									'name'        => 'facebook_url',
									'type'        => 'url',
									'label'       => 'Facebook URL',
									'placeholder' => 'https://facebook.com/yourpage',
								],
								[
									'name'        => 'twitter_url',
									'type'        => 'url',
									'label'       => 'Twitter/X URL',
									'placeholder' => 'https://twitter.com/yourhandle',
								],
								[
									'name'        => 'instagram_url',
									'type'        => 'url',
									'label'       => 'Instagram URL',
									'placeholder' => 'https://instagram.com/yourprofile',
								],
								[
									'name'        => 'linkedin_url',
									'type'        => 'url',
									'label'       => 'LinkedIn URL',
									'placeholder' => 'https://linkedin.com/in/yourprofile',
								],
							],
						],
						[
							'name'    => 'site_logo_color',
							'type'    => 'color',
							'label'   => 'Brand Color',
							'default' => '#0073aa',
						],
					],
				],
			],
		]
	);
}
add_action( 'init', 'cassette_cmf_advanced_array_init' );

/**
 * =============================================================================
 * BEFORE-SAVE FILTERS
 * =============================================================================
 * Demonstrate modifying field values before they are saved
 */

// Ensure SKU is uppercase
add_filter(
	'CassetteCmf_before_save_field_sku',
	function ( $value ) {
		return strtoupper( $value );
	}
);

// Auto-calculate reading time based on content length.
add_filter(
	'CassetteCmf_before_save_field_read_time',
	function ( $value, $post_id ) {
		if ( empty( $value ) ) {
			$content    = get_post_field( 'post_content', $post_id );
			$word_count = str_word_count( wp_strip_all_tags( $content ) );
			$value      = max( 1, ceil( $word_count / 200 ) ); // 200 words per minute.
		}
		return $value;
	},
	10,
	2
);

/**
 * =============================================================================
 * RETRIEVING SAVED VALUES
 * =============================================================================
 *
 * Cassette-CMF provides a universal static method to retrieve field values:
 *
 * CassetteCmf::get_field( $field_name, $context, $context_type, $default )
 *
 * - $field_name:   The field name as defined in your config
 * - $context:      Post ID, term ID, or settings page ID
 * - $context_type: 'post' (default), 'term', or 'settings'
 * - $default:      Default value if field is empty
 *
 * You can also use the specific helper methods:
 *   CassetteCmf::get_post_field( 'field_name', $post_id )
 *   CassetteCmf::get_term_field( 'field_name', $term_id )
 *   CassetteCmf::get_settings_field( 'field_name', 'page-id' )
 */

/**
 * Get product field value
 *
 * @param int    $post_id       Post ID.
 * @param string $field         Field name.
 * @param mixed  $default_value Default value.
 * @return mixed
 */
function get_product_field( $post_id, $field, $default_value = '' ) {
	return CassetteCmf::get_field( $field, $post_id, 'post', $default_value );
}

/**
 * Get store setting
 *
 * @param string $field         Field name.
 * @param mixed  $default_value Default value.
 * @return mixed
 */
function get_store_setting( $field, $default_value = '' ) {
	return CassetteCmf::get_field( $field, 'store-settings', 'settings', $default_value );
}

/**
 * Get post option (added to built-in posts)
 *
 * @param int    $post_id       Post ID.
 * @param string $field         Field name.
 * @param mixed  $default_value Default value.
 * @return mixed
 */
function get_post_option( $post_id, $field, $default_value = '' ) {
	return CassetteCmf::get_field( $field, $post_id, 'post', $default_value );
}

/**
 * Get page setting (added to built-in pages)
 *
 * @param int    $post_id       Post ID.
 * @param string $field         Field name.
 * @param mixed  $default_value Default value.
 * @return mixed
 */
function get_page_setting( $post_id, $field, $default_value = '' ) {
	return CassetteCmf::get_field( $field, $post_id, 'post', $default_value );
}

/**
 * Get general setting (added to WordPress General Settings)
 *
 * @param string $field         Field name.
 * @param mixed  $default_value Default value.
 * @return mixed
 */
function get_general_option( $field, $default_value = '' ) {
	return CassetteCmf::get_field( $field, 'general', 'settings', $default_value );
}

/**
 * =============================================================================
 * EXAMPLE: DISPLAY PRODUCT DETAILS
 * =============================================================================
 */
add_filter(
	'the_content',
	function ( $content ) {
		if ( ! is_singular( 'product' ) ) {
			return $content;
		}

		$post_id = get_the_ID();

		// Get values
		$sku        = get_product_field( $post_id, 'sku' );
		$price      = get_product_field( $post_id, 'price' );
		$sale_price = get_product_field( $post_id, 'sale_price' );
		$stock      = get_product_field( $post_id, 'stock_status' );
		$featured   = get_product_field( $post_id, 'featured' );
		$variations = get_product_field( $post_id, 'variations' );

		// Format currency
		$currency = get_store_setting( 'currency', 'USD' );
		$symbols  = [
			'USD' => '$',
			'EUR' => '€',
			'GBP' => '£',
			'JPY' => '¥',
		];
		$symbol   = $symbols[ $currency ] ?? '$';

		$output = '<div class="product-info">';

		if ( $featured ) {
			$output .= '<span class="featured-badge">★ Featured</span>';
		}

		if ( $sku ) {
			$output .= '<p><strong>SKU:</strong> ' . esc_html( $sku ) . '</p>';
		}

		if ( $sale_price && $sale_price < $price ) {
			$output .= '<p class="price">';
			$output .= '<del>' . esc_html( $symbol . number_format( $price, 2 ) ) . '</del> ';
			$output .= '<ins>' . esc_html( $symbol . number_format( $sale_price, 2 ) ) . '</ins>';
			$output .= '</p>';
		} elseif ( $price ) {
			$output .= '<p class="price">' . esc_html( $symbol . number_format( $price, 2 ) ) . '</p>';
		}

		$stock_labels = [
			'instock'    => 'In Stock',
			'outofstock' => 'Out of Stock',
			'backorder'  => 'Available on Backorder',
		];
		if ( $stock ) {
			$output .= '<p class="stock-status ' . esc_attr( $stock ) . '">';
			$output .= esc_html( $stock_labels[ $stock ] ?? $stock );
			$output .= '</p>';
		}

		// Display variations if present
		if ( ! empty( $variations ) && is_array( $variations ) ) {
			$output .= '<div class="variations"><h4>Available Variations:</h4><ul>';
			foreach ( $variations as $var ) {
				$output .= '<li>';
				$output .= esc_html( $var['variant_name'] ?? 'Variant' );
				if ( ! empty( $var['variant_price'] ) ) {
					$output .= ' - ' . esc_html( $symbol . number_format( $var['variant_price'], 2 ) );
				}
				$output .= '</li>';
			}
			$output .= '</ul></div>';
		}

		$output .= '</div>';

		return $output . $content;
	}
);
