# Cassette-CMF (WordPress Content Modeling Framework)

A powerful, flexible Composer library for building WordPress plugins with custom post types, taxonomies, settings pages, and dynamic form fields.

[![Version](https://img.shields.io/badge/version-0.0.2-blue.svg)](https://github.com/PedalCMS/cassette-cmf)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-purple.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green.svg)](LICENSE)
![Coverage](https://img.shields.io/endpoint?url=https://gist.githubusercontent.com/namithj/e9d56e393be0b5f10774a3432beaf815/raw/cassette-cmf-coverage.json)
[![PHPUnit Tests](https://github.com/PedalCMS/cassette-cmf/actions/workflows/phpunit.yml/badge.svg)](https://github.com/PedalCMS/cassette-cmf/actions/workflows/phpunit.yml)
[![Coding Standards](https://github.com/PedalCMS/cassette-cmf/actions/workflows/coding-standards.yml/badge.svg)](https://github.com/PedalCMS/cassette-cmf/actions/workflows/coding-standards.yml)
[![PHPStan](https://github.com/PedalCMS/cassette-cmf/actions/workflows/phpstan.yml/badge.svg)](https://github.com/PedalCMS/cassette-cmf/actions/workflows/phpstan.yml)

## Features

- **Custom Post Types** - Register CPTs with fields using array or JSON configuration
- **Custom Taxonomies** - Create taxonomies with custom term meta fields
- **Settings Pages** - Top-level and submenu pages with automatic form handling
- **18 Field Types** - Text, textarea, number, email, url, password, date, color, select, checkbox, radio, wysiwyg, upload, custom_html, tabs, metabox, group, repeater
- **Container Fields** - Organize fields with tabs, metaboxes, groups, and repeaters
- **Extend Existing** - Add fields to existing post types, taxonomies, and settings pages
- **Array Configuration** - Register everything from a single PHP array
- **JSON Configuration** - Load configurations from JSON files with schema validation
- **Before-Save Filters** - Modify or validate field values before saving
- **Validation & Sanitization** - Built-in security with customizable rules
- **Asset Management** - Context-aware CSS/JS enqueuing
- **i18n Ready** - Full internationalization support

## Requirements

- **PHP**: 8.2 or higher
- **WordPress**: 6.0 or higher
- **Composer**: For autoloading

## Installation

```bash
composer require pedalcms/cassette-cmf
```

## Quick Start

### Basic Example

```php
<?php
/**
 * Plugin Name: My Custom Plugin
 */

use Pedalcms\CassetteCmf\Core\Manager;

function my_plugin_init() {
    Manager::init()->register_from_array([
        // Custom Post Types
        'cpts' => [
            [
                'id'   => 'product',
                'args' => [
                    'label'       => 'Products',
                    'public'      => true,
                    'has_archive' => true,
                    'menu_icon'   => 'dashicons-cart',
                ],
                'fields' => [
                    [
                        'name'  => 'price',
                        'type'  => 'number',
                        'label' => 'Price ($)',
                        'min'   => 0,
                        'step'  => 0.01,
                    ],
                    [
                        'name'    => 'stock_status',
                        'type'    => 'select',
                        'label'   => 'Stock Status',
                        'options' => [
                            'in_stock'     => 'In Stock',
                            'out_of_stock' => 'Out of Stock',
                        ],
                    ],
                ],
            ],
        ],

        // Custom Taxonomies
        'taxonomies' => [
            [
                'id'          => 'product_category',
                'object_type' => ['product'],
                'args'        => [
                    'label'        => 'Categories',
                    'hierarchical' => true,
                ],
                'fields' => [
                    [
                        'name'    => 'category_color',
                        'type'    => 'color',
                        'label'   => 'Category Color',
                        'default' => '#0073aa',
                    ],
                    [
                        'name'  => 'category_icon',
                        'type'  => 'text',
                        'label' => 'Icon Class',
                    ],
                ],
            ],
        ],

        // Settings Pages
        'settings_pages' => [
            [
                'id'         => 'store-settings',
                'page_title' => 'Store Settings',
                'menu_title' => 'Store',
                'capability' => 'manage_options',
                'icon'       => 'dashicons-store',
                'fields'     => [
                    [
                        'name'  => 'store_name',
                        'type'  => 'text',
                        'label' => 'Store Name',
                    ],
                    [
                        'name'    => 'currency',
                        'type'    => 'select',
                        'label'   => 'Currency',
                        'options' => [
                            'USD' => 'US Dollar',
                            'EUR' => 'Euro',
                            'GBP' => 'British Pound',
                        ],
                    ],
                ],
            ],
        ],
    ]);
}
add_action( 'init', 'my_plugin_init' );
```

### Extending Existing Post Types and Taxonomies

```php
Manager::init()->register_from_array([
    // Add fields to existing post types
    'cpts' => [
        [
            'id'     => 'post',  // WordPress built-in
            'fields' => [
                [
                    'name'  => 'subtitle',
                    'type'  => 'text',
                    'label' => 'Subtitle',
                ],
            ],
        ],
    ],

    // Add fields to existing taxonomies
    'taxonomies' => [
        [
            'id'     => 'category',  // WordPress built-in
            'fields' => [
                [
                    'name'    => 'category_color',
                    'type'    => 'color',
                    'label'   => 'Category Color',
                    'default' => '#333333',
                ],
            ],
        ],
    ],
]);
```

### JSON Configuration

```php
// Load from JSON file
Manager::init()->register_from_json( __DIR__ . '/config.json' );
```

```json
{
  "cpts": [
    {
      "id": "event",
      "args": {
        "label": "Events",
        "public": true
      },
      "fields": [
        {
          "name": "event_date",
          "type": "date",
          "label": "Event Date"
        }
      ]
    }
  ],
  "taxonomies": [
    {
      "id": "event_type",
      "object_type": ["event"],
      "args": {
        "label": "Event Types",
        "hierarchical": true
      },
      "fields": [
        {
          "name": "type_color",
          "type": "color",
          "label": "Type Color"
        }
      ]
    }
  ]
}
```

## Field Types

### Basic Fields

| Type       | Description              | Key Options                       |
|------------|--------------------------|-----------------------------------|
| `text`     | Single-line text input   | placeholder, maxlength, pattern   |
| `textarea` | Multi-line text input    | rows, cols, maxlength             |
| `number`   | Numeric input            | min, max, step                    |
| `email`    | Email input              | Automatic validation              |
| `url`      | URL input                | Automatic validation              |
| `password` | Password input           | Masked input                      |
| `date`     | Date picker              | min, max                          |
| `color`    | Color picker             | WordPress color picker            |

### Choice Fields

| Type       | Description              | Key Options                       |
|------------|--------------------------|-----------------------------------|
| `select`   | Dropdown select          | options, multiple                 |
| `checkbox` | Checkbox input           | options (for multiple)            |
| `radio`    | Radio button group       | options                           |

### Rich Content

| Type          | Description              | Key Options                       |
|---------------|--------------------------|-----------------------------------|
| `wysiwyg`     | Visual editor            | TinyMCE with media buttons        |
| `upload`      | Media uploader           | button_text, library_type, preview |
| `custom_html` | Display custom HTML      | content, allowed_tags, raw_html   |

### Container Fields

| Type       | Description              | Key Options                       |
|------------|--------------------------|-----------------------------------|
| `tabs`     | Tabbed container         | orientation, tabs[]               |
| `metabox`  | Metabox container        | context, priority, fields[]       |
| `group`    | Field group              | label, description, fields[]      |
| `repeater` | Repeatable fields        | button_label, min, max, fields[]  |

## Retrieving Values

Cassette-CMF provides a universal static method to retrieve field values regardless of their storage location:

```php
use Pedalcms\CassetteCmf\CassetteCmf;

// Post meta (CPT fields)
$price = CassetteCmf::get_field( 'price', $post_id );
$price = CassetteCmf::get_field( 'price', $post_id, 'post', 0 );  // With default

// Term meta (taxonomy fields)
$color = CassetteCmf::get_field( 'category_color', $term_id, 'term' );
$color = CassetteCmf::get_field( 'category_color', $term_id, 'term', '#000000' );

// Settings (uses settings page ID as context)
$store_name = CassetteCmf::get_field( 'store_name', 'store-settings', 'settings' );
$currency = CassetteCmf::get_field( 'currency', 'store-settings', 'settings', 'USD' );
```

### Method Signature

```php
CassetteCmf::get_field( string $field_name, int|string $context, string $context_type = 'post', mixed $default = '' )
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `$field_name` | string | The field name as defined in your config |
| `$context` | int\|string | Post ID, term ID, or settings page ID |
| `$context_type` | string | `'post'` (default), `'term'`, or `'settings'` |
| `$default` | mixed | Default value if field is empty |

### Context-Specific Helper Methods

```php
use Pedalcms\CassetteCmf\CassetteCmf;

// Context-specific methods for convenience
$value = CassetteCmf::get_post_field( 'field_name', $post_id );
$value = CassetteCmf::get_term_field( 'field_name', $term_id );
$value = CassetteCmf::get_settings_field( 'field_name', 'page-id' );
```

### Legacy Approach (Still Works)

```php
// Post meta (CPT fields)
$price = get_post_meta( $post_id, 'price', true );

// Term meta (taxonomy fields)
$color = get_term_meta( $term_id, 'category_color', true );

// Settings (pattern: {page_id}_{field_name})
$store_name = get_option( 'store-settings_store_name' );
```

## Before-Save Filters

Modify or validate field values before saving:

```php
// Global filter for all fields
add_filter( 'CassetteCmf_before_save_field', function( $value, $field_name, $context ) {
    // Return modified value, or null to skip saving
    return $value;
}, 10, 3 );

// Field-specific filter
add_filter( 'CassetteCmf_before_save_field_price', function( $value ) {
    return abs( floatval( $value ) );  // Ensure positive number
} );
```

## Examples

| Example | Description |
|---------|-------------|
| [01-simple-array](examples/01-simple-array/) | Book CPT + Genre Taxonomy + Library Settings (PHP) |
| [02-simple-json](examples/02-simple-json/) | Event CPT + Event Taxonomies + Settings (JSON) |
| [03-advanced-array](examples/03-advanced-array/) | Full demo: CPTs, taxonomies, settings, containers, filters |
| [04-advanced-json](examples/04-advanced-json/) | Full demo with multi-file JSON configuration |

## Documentation

- [Field API Documentation](docs/field-api.md)
- [Examples](examples/)

## Testing

```bash
composer test
```

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Write tests for your changes
4. Ensure all tests pass (`composer test`)
5. Submit a pull request

## License

GPL-2.0-or-later. See [LICENSE](LICENSE) for details.

## Support

- [GitHub Issues](https://github.com/PedalCMS/cassette-cmf/issues)
- [Documentation](docs/)
- [Examples](examples/)
