# Cassette-CMF (WordPress Content Modeling Framework)

A powerful, flexible Composer library for building WordPress plugins with custom post types, taxonomies, settings pages, and dynamic form fields.

[![Version](https://img.shields.io/badge/version-0.2.0-blue.svg)](https://github.com/PedalCMS/cassette-cmf)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-6.7%2B-blue.svg)](https://wordpress.org)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green.svg)](LICENSE)
![Coverage](https://img.shields.io/endpoint?url=https://gist.githubusercontent.com/namithj/e9d56e393be0b5f10774a3432beaf815/raw/cassette-cmf-coverage.json)
[![PHPUnit Tests](https://github.com/PedalCMS/cassette-cmf/actions/workflows/phpunit.yml/badge.svg)](https://github.com/PedalCMS/cassette-cmf/actions/workflows/phpunit.yml)
[![Coding Standards](https://github.com/PedalCMS/cassette-cmf/actions/workflows/coding-standards.yml/badge.svg)](https://github.com/PedalCMS/cassette-cmf/actions/workflows/coding-standards.yml)
[![PHPStan](https://github.com/PedalCMS/cassette-cmf/actions/workflows/phpstan.yml/badge.svg)](https://github.com/PedalCMS/cassette-cmf/actions/workflows/phpstan.yml)

## Features

- **Custom Post Types** - Register CPTs with fields using array or JSON configuration
- **Custom Taxonomies** - Create taxonomies with custom term meta fields
- **Settings Pages** - Top-level and submenu pages with automatic form handling
- **19 Field Types** - Text, textarea, number, email, url, password, secret, date, color, select, checkbox, radio, wysiwyg, upload, custom_html, tabs, metabox, group, repeater
- **Conditional Fields** - Show and hide fields with declarative `conditional` rules
- **Container Fields** - Organize fields with tabs, metaboxes, groups, and repeaters
- **Extend Existing** - Add fields to existing post types, taxonomies, and settings pages
- **Array Configuration** - Register everything from a single PHP array
- **JSON Configuration** - Load configurations from JSON files with schema validation
- **Before-Save Filters** - Modify or validate field values before saving
- **Validation & Sanitization** - Built-in security with customizable rules
- **Asset Management** - Context-aware CSS/JS enqueuing
- **i18n Ready** - Full internationalization support

## Requirements

- **PHP**: 7.4 or higher
- **WordPress**: 6.7 or higher
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

use PedalCMS\CassetteCMF\Core\Manager;

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

### Conditional Fields

Conditional visibility works in both PHP arrays and JSON configs. Hidden fields keep their submitted value, but they skip validation while their condition is false.

```php
[
        'name'        => 'ticket_price',
        'type'        => 'number',
        'label'       => 'Ticket Price',
        'conditional' => [
                'rules' => [
                        [
                                'field'    => 'is_free',
                                'operator' => '==',
                                'value'    => '0',
                        ],
                ],
        ],
]
```

```json
{
    "name": "ticket_price",
    "type": "number",
    "conditional": {
        "rules": [
            {
                "field": "is_free",
                "operator": "==",
                "value": "0"
            }
        ]
    }
}
```

Supported operators: `==`, `!=`, `>`, `>=`, `<`, `<=`, `in`, `not_in`, `empty`, `not_empty`. Use `relation: "OR"` when a field should appear if any rule matches. Conditional fields are supported for regular fields and container contents such as groups, tabs, and metaboxes, but not for repeater sub-fields.

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
| `secret`   | Write-only secret        | Mask on read, encrypt             |
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
use PedalCMS\CassetteCMF\CassetteCMF;

// Post meta (CPT fields)
$price = CassetteCMF::get_field( 'price', $post_id );
$price = CassetteCMF::get_field( 'price', $post_id, 'post', 0 );  // With default

// Term meta (taxonomy fields)
$color = CassetteCMF::get_field( 'category_color', $term_id, 'term' );
$color = CassetteCMF::get_field( 'category_color', $term_id, 'term', '#000000' );

// Settings (uses settings page ID as context)
$store_name = CassetteCMF::get_field( 'store_name', 'store-settings', 'settings' );
$currency = CassetteCMF::get_field( 'currency', 'store-settings', 'settings', 'USD' );
```

### Method Signature

```php
CassetteCMF::get_field( string $field_name, int|string $context, string $context_type = 'post', mixed $default = '' )
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `$field_name` | string | The field name as defined in your config |
| `$context` | int\|string | Post ID, term ID, or settings page ID |
| `$context_type` | string | `'post'` (default), `'term'`, or `'settings'` |
| `$default` | mixed | Default value if field is empty |

### Context-Specific Helper Methods

```php
use PedalCMS\CassetteCMF\CassetteCMF;

// Context-specific methods for convenience
$value = CassetteCMF::get_post_field( 'field_name', $post_id );
$value = CassetteCMF::get_term_field( 'field_name', $term_id );
$value = CassetteCMF::get_settings_field( 'field_name', 'page-id' );
```

### Field Lookup and Visibility Helpers

Two read-only helpers describe which fields Cassette-CMF has registered. They exist so companion libraries (for example a block-bindings source) can expose only the fields an author has explicitly opted in with the optional `bindings` field-config key. See [Field API: Exposing fields to companion libraries](docs/field-api.md#exposing-fields-to-companion-libraries-bindings).

```php
// Config array (including 'type' and 'name'), or null if no such field is registered.
$config = CassetteCMF::get_registered_field( 'price', $post_id );

// true only if the field is registered, has 'bindings' => true, and is not a password/secret field.
$public = CassetteCMF::is_field_public( 'price', $post_id );
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

## Settings REST API and schema versions

A settings page can opt in to a REST route with `show_in_rest => true`. Pages that do not opt in get no route and no change. The route is `{namespace}/settings/{page_id}` (default namespace `cassette-cmf/v1`, see `CassetteCMF::boot()`), protected by the page's capability, and writes exactly what the classic form writes. See [REST API](docs/rest-api.md). Set `render_mode => 'react'` to edit the page with a React app instead of the classic form (see [React settings page](docs/react-settings.md)).

```php
'settings_pages' => [
    [
        'id'           => 'my-settings',
        'page_title'   => 'My Settings',
        'show_in_rest' => true,
        'fields'       => [ /* ... */ ],
    ],
],
```

A page can also declare `schema_version` (int) and `upgrade` (`callable( array $old, int $from ): array`) to migrate stored data once, safely. For API keys and tokens use the `secret` field type: it is never printed or returned, only a mask (`••••1234`), and `CassetteCMF::get_secret( $field, $page_id )` returns the real value. See [Field API](docs/field-api.md#secret-field).

## Before-Save Filters

Modify or validate field values before saving:

```php
// Global filter for all fields
add_filter( 'CassetteCMF_before_save_field', function( $value, $field_name, $context ) {
    // Return modified value, or null to skip saving
    return $value;
}, 10, 3 );

// Field-specific filter
add_filter( 'CassetteCMF_before_save_field_price', function( $value ) {
    return abs( floatval( $value ) );  // Ensure positive number
} );
```

## Running alongside another copy

Two plugins on one site may each ship their own copy of Cassette-CMF, plain or namespace-prefixed (for example with Strauss). The two copies already keep separate state, since each has its own classes and singleton. What they would share is the identifiers they hand to WordPress and the browser. Calling `boot()` is optional and additive: without it nothing changes, and every identifier keeps the value it has always had.

Call it once, before any other Cassette-CMF call:

```php
use PedalCMS\CassetteCMF\CassetteCMF;

CassetteCMF::boot( [
    'asset_handle'   => 'acme-cmf',         // CSS and JS handle (default 'cassette-cmf')
    'js_global'      => 'AcmeCMFFields',    // window.<name> (default 'CassetteCMFFields')
    'rest_namespace' => 'acme-cmf/v1',      // REST namespace (default 'cassette-cmf/v1')
] );
```

| Option | Default | What it changes |
|--------|---------|-----------------|
| `asset_handle` | `cassette-cmf` | The handle of the common stylesheet and script, in every handler. `/^[a-z0-9_-]+$/`. Two copies on one handle would let WordPress enqueue only the first copy's files. |
| `js_global` | `CassetteCMFFields` | The `window` property `cassette-cmf.js` publishes its API under. `/^[A-Za-z_$][A-Za-z0-9_$]*$/`. With the default, no extra inline script is printed. |
| `rest_namespace` | `cassette-cmf/v1` | Stored now; used by the REST controller for opted-in settings pages. `/^[a-z0-9-]+\/v[0-9]+$/`. |

Invalid values raise `_doing_it_wrong()` and fall back to the default. Like `Manager::init()`, the options apply on the first call only; a later call with different options raises `_doing_it_wrong()` and is ignored. `CassetteCMF::boot()` returns the `Manager`, so `CassetteCMF::boot( [...] )->register_from_array( $config )` works.

**Hooks are shared on purpose.** The `cassette_cmf_*` actions and filters (`cassette_cmf_register_config`, `cassette_cmf_before_save_field`, `cassette_cmf_enqueue_common_assets`, ...) are not prefixed. A callback on them is a statement about "all Cassette-CMF copies", and that is the documented contract. If you need to treat one copy differently, check which settings page, post type or taxonomy ID the callback received. The same goes for the CSS classes (`.cassette-cmf-*`), which the stylesheet of every copy styles alike.

Other identifiers are safe to share: the text domain, nonces (scoped to the settings page ID, so only a page ID registered by two copies can clash, and it would clash in WordPress too), and option and meta keys (derived from your own page and field IDs). On a screen where **both** copies add fields, each copy's script is loaded under its own handle and each initialises the fields on the page. Keep one copy per screen where you can, or boot both copies with the same `asset_handle` (the first one enqueued wins).

## Examples

| Example | Description |
|---------|-------------|
| [01-simple-array](examples/01-simple-array/) | Book CPT + Genre Taxonomy + Library Settings (PHP) |
| [02-simple-json](examples/02-simple-json/) | Event CPT + Event Taxonomies + Settings (JSON) |
| [03-advanced-array](examples/03-advanced-array/) | Full demo: CPTs, taxonomies, settings, containers, filters |
| [04-advanced-json](examples/04-advanced-json/) | Full demo with multi-file JSON configuration |

## Documentation

- [Field API Documentation](docs/field-api.md)
- [REST API](docs/rest-api.md)
- [React settings page](docs/react-settings.md)
- [Stability promise](docs/STABILITY.md)
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
