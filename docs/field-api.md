# Field API Documentation

Complete guide to creating, extending, and using fields in Cassette-CMF.

## Table of Contents

- [Overview](#overview)
- [Field Interface](#field-interface)
- [Creating Custom Fields](#creating-custom-fields)
- [Core Field Types Reference](#core-field-types-reference)
- [Field Configuration](#field-configuration)
- [Validation and Sanitization](#validation-and-sanitization)
- [Asset Enqueuing](#asset-enqueuing)
- [Using FieldFactory](#using-fieldfactory)
- [Integration with CPT and Settings](#integration-with-cpt-and-settings)
- [Advanced Examples](#advanced-examples)
- [Best Practices](#best-practices)

---

## Overview

The Cassette-CMF Field API provides a flexible, extensible system for creating form fields in WordPress. The API consists of:

- **FieldInterface**: Contract that all fields must implement
- **AbstractField**: Base class providing common functionality
- **FieldFactory**: Factory for creating fields from configuration
- **13 Core Field Types**: Ready-to-use field implementations

### Architecture

```
FieldInterface (contract)
    ↑
AbstractField (base implementation)
    ↑
Core Field Types (TextField, EmailField, etc.)
    ↑
Custom Field Types (your custom fields)
```

### Key Features

- ✅ **Type-safe**: All fields implement FieldInterface
- ✅ **Validation**: Built-in validation rules (required, min/max, pattern, email, url)
- ✅ **Sanitization**: Automatic data sanitization for security
- ✅ **Extensible**: Easy to create custom field types
- ✅ **Configuration-driven**: Create fields from arrays or JSON
- ✅ **Asset management**: Fields can enqueue their own CSS/JS
- ✅ **WordPress-compatible**: Uses WordPress functions when available

---

## Field Interface

All fields must implement the `FieldInterface`:

```php
namespace Pedalcms\CassetteCmf\Field;

interface FieldInterface {
    public function render($value = null): string;
    public function sanitize($value);
    public function validate($value): array;
    public function get_name(): string;
    public function get_label(): string;
    public function get_type(): string;
    public function get_schema(): array;
    public function get_config(string $key, $default_value = null);
    public function set_config(array $config): self;
    public function enqueue_assets(): void;
}
```

### Method Descriptions

#### `render($value = null): string`

Renders the HTML for the field.

**Parameters:**
- `$value` - Current field value

**Returns:** HTML string

**Example:**
```php
$html = $field->render('current value');
echo $html;
```

#### `sanitize($value)`

Sanitizes user input before saving.

**Parameters:**
- `$value` - Raw user input

**Returns:** Sanitized value

**Example:**
```php
$clean = $field->sanitize($_POST['field_name']);
```

#### `validate($value): array`

Validates user input against field rules.

**Parameters:**
- `$value` - Value to validate

**Returns:** Array with `valid` (bool) and `errors` (array)

**Example:**
```php
$result = $field->validate($input);
if (!$result['valid']) {
    foreach ($result['errors'] as $error) {
        echo "Error: $error\n";
    }
}
```

#### `get_name(): string`

Returns the field name/identifier.

#### `get_label(): string`

Returns the field display label.

#### `get_type(): string`

Returns the field type (text, email, etc.).

#### `get_schema(): array`

Returns JSON schema for the field (used for validation).

#### `get_config(string $key, $default_value = null)`

Gets a configuration value.

**Parameters:**
- `$key` - Configuration key
- `$default_value` - Default if key not found

#### `set_config(array $config): self`

Sets multiple configuration values (fluent interface).

#### `enqueue_assets(): void`

Enqueues CSS/JS assets for the field.

---

## Creating Custom Fields

### Step 1: Extend AbstractField

Create a class that extends `AbstractField`:

```php
<?php
namespace YourPlugin\Fields;

use Pedalcms\CassetteCmf\Field\AbstractField;

class SliderField extends AbstractField {
    
    public function render($value = null): string {
        $value = $value ?? $this->get_config('default', 50);
        $min   = $this->get_config('min', 0);
        $max   = $this->get_config('max', 100);
        $step  = $this->get_config('step', 1);
        
        $attrs = $this->get_attributes([
            'type'  => 'range',
            'min'   => $min,
            'max'   => $max,
            'step'  => $step,
            'value' => $value,
        ]);
        
        $output  = $this->render_label();
        $output .= "<input {$attrs} />";
        $output .= "<output>{$value}</output>";
        $output .= $this->render_description();
        
        return $this->render_wrapper($output);
    }
    
    public function sanitize($value) {
        $min = $this->get_config('min', 0);
        $max = $this->get_config('max', 100);
        
        $value = intval($value);
        return max($min, min($max, $value));
    }
}
```

### Step 2: Register Your Field Type

Register with FieldFactory:

```php
use Pedalcms\CassetteCmf\Field\FieldFactory;

FieldFactory::register_type('slider', SliderField::class);
```

Or use Manager:

```php
use Pedalcms\CassetteCmf\Core\Manager;

$manager = Manager::init();
$manager->register_field_type('slider', SliderField::class);
```

### Step 3: Use Your Field

Create instances using configuration:

```php
$field = FieldFactory::create([
    'name'    => 'volume',
    'type'    => 'slider',
    'label'   => 'Volume Level',
    'min'     => 0,
    'max'     => 100,
    'step'    => 5,
    'default' => 50,
]);

echo $field->render(75);
```

---

## Core Field Types Reference

### TextField

Single-line text input.

**Configuration:**
```php
[
    'name'        => 'username',
    'type'        => 'text',
    'label'       => 'Username',
    'placeholder' => 'Enter username',
    'maxlength'   => 50,
    'pattern'     => '[a-zA-Z0-9]+',
    'required'    => true,
]
```

**Special Options:**
- `placeholder` - Placeholder text
- `maxlength` - Maximum character length
- `pattern` - Regex pattern for validation
- `autocomplete` - Autocomplete attribute
- `readonly` - Make field read-only
- `disabled` - Disable the field

---

### TextareaField

Multi-line text input.

**Configuration:**
```php
[
    'name'        => 'bio',
    'type'        => 'textarea',
    'label'       => 'Biography',
    'rows'        => 5,
    'cols'        => 50,
    'maxlength'   => 500,
    'placeholder' => 'Tell us about yourself...',
]
```

**Special Options:**
- `rows` - Number of visible rows
- `cols` - Number of visible columns
- `maxlength` - Maximum character length

---

### SelectField

Dropdown select (single or multiple).

**Configuration:**
```php
[
    'name'     => 'country',
    'type'     => 'select',
    'label'    => 'Country',
    'options'  => [
        'us' => 'United States',
        'uk' => 'United Kingdom',
        'ca' => 'Canada',
    ],
    'multiple' => false,
    'default'  => 'us',
]
```

**Special Options:**
- `options` - Array of value => label pairs (required)
- `multiple` - Allow multiple selection

---

### CheckboxField

Single checkbox or multiple checkboxes.

**Single Checkbox:**
```php
[
    'name'  => 'agree_terms',
    'type'  => 'checkbox',
    'label' => 'I agree to the terms',
]
```

**Multiple Checkboxes:**
```php
[
    'name'    => 'interests',
    'type'    => 'checkbox',
    'label'   => 'Interests',
    'options' => [
        'sports' => 'Sports',
        'music'  => 'Music',
        'art'    => 'Art',
    ],
    'layout'  => 'stacked', // or 'inline'
]
```

**Special Options:**
- `options` - Array of checkboxes (for multiple)
- `layout` - 'inline' or 'stacked'

---

### RadioField

Radio button group.

**Configuration:**
```php
[
    'name'    => 'gender',
    'type'    => 'radio',
    'label'   => 'Gender',
    'options' => [
        'male'   => 'Male',
        'female' => 'Female',
        'other'  => 'Other',
    ],
    'layout'  => 'inline',
    'default' => 'male',
]
```

**Special Options:**
- `options` - Array of value => label pairs (required)
- `layout` - 'inline' or 'stacked'

---

### NumberField

Numeric input with validation.

**Configuration:**
```php
[
    'name'    => 'age',
    'type'    => 'number',
    'label'   => 'Age',
    'min'     => 0,
    'max'     => 120,
    'step'    => 1,
    'default' => 25,
]
```

**Special Options:**
- `min` - Minimum value
- `max` - Maximum value
- `step` - Increment step

---

### EmailField

Email input with validation.

**Configuration:**
```php
[
    'name'        => 'email',
    'type'        => 'email',
    'label'       => 'Email Address',
    'placeholder' => 'user@example.com',
    'required'    => true,
]
```

**Features:**
- Automatic email validation
- Uses WordPress `sanitize_email()` for sanitization

---

### URLField

URL input with validation.

**Configuration:**
```php
[
    'name'        => 'website',
    'type'        => 'url',
    'label'       => 'Website',
    'placeholder' => 'https://example.com',
]
```

**Features:**
- Automatic URL validation
- Uses WordPress `esc_url_raw()` for sanitization

---

### DateField

Date input with validation.

**Configuration:**
```php
[
    'name'    => 'birth_date',
    'type'    => 'date',
    'label'   => 'Date of Birth',
    'min'     => '1900-01-01',
    'max'     => '2025-12-31',
    'default' => '2000-01-01',
]
```

**Special Options:**
- `min` - Minimum date (YYYY-MM-DD)
- `max` - Maximum date (YYYY-MM-DD)

**Features:**
- Validates YYYY-MM-DD format
- Validates against real calendar dates

---

### PasswordField

Masked password input.

**Configuration:**
```php
[
    'name'        => 'password',
    'type'        => 'password',
    'label'       => 'Password',
    'placeholder' => 'Enter password',
    'required'    => true,
]
```

**Features:**
- Never pre-fills value (security)
- Preserves special characters during sanitization

---

### ColorField

Color picker with WordPress integration.

**Configuration:**
```php
[
    'name'    => 'theme_color',
    'type'    => 'color',
    'label'   => 'Theme Color',
    'default' => '#3498db',
]
```

**Features:**
- Integrates with WordPress color picker
- Validates hex color format
- Automatically enqueues `wp-color-picker` assets

---

### Custom_HTML_Field

Display custom HTML content (display-only, no stored value).

**Configuration:**
```php
[
    'name'        => 'info_banner',
    'type'        => 'custom_html',
    'label'       => 'Important Notice',
    'content'     => '<div class="notice notice-info"><p>This is an informational message.</p></div>',
    'description' => 'This is a display-only field.',
]
```

**Special Options:**
- `content` - The HTML content to display (required)
- `allowed_tags` - Custom array of allowed HTML tags for wp_kses sanitization
- `raw_html` - If true, outputs HTML without sanitization (use with caution, default: false)

**Features:**
- Display-only field (does not store any value)
- Sanitizes HTML by default using `wp_kses` with post-safe tags
- Supports SVG and iframe tags by default
- Use `raw_html => true` for unsanitized output (only for trusted content)

**Example with custom allowed tags:**
```php
[
    'name'         => 'custom_block',
    'type'         => 'custom_html',
    'content'      => '<custom-element>Content</custom-element>',
    'allowed_tags' => [
        'custom-element' => [
            'class' => true,
            'id'    => true,
        ],
    ],
]
```

**Use Cases:**
- Displaying informational messages or notices
- Adding custom UI elements between form fields
- Embedding videos or iframes
- Adding decorative content or branding
- Displaying instructions or help content

---

### Upload_Field

Media upload field using WordPress media library.

**Configuration:**
```php
[
    'name'         => 'featured_image',
    'type'         => 'upload',
    'label'        => 'Featured Image',
    'description'  => 'Select or upload an image',
    'button_text'  => 'Select Image',
    'remove_text'  => 'Remove',
    'library_type' => 'image',
    'preview'      => true,
]
```

**Special Options:**
- `button_text` - Text for the upload button (default: 'Select File')
- `remove_text` - Text for the remove button (default: 'Remove')
- `library_type` - Filter media library by type: 'image', 'video', 'audio', 'application'
- `multiple` - Allow multiple file selection (default: false)
- `preview` - Show preview for images (default: true)
- `allowed_types` - Array of allowed mime types for validation

**Features:**
- Integrates with WordPress media library
- Image preview for image uploads
- File name display for non-image files
- Stores attachment ID for easy retrieval
- Automatic media script enqueuing

**Example with video upload:**
```php
[
    'name'         => 'video_file',
    'type'         => 'upload',
    'label'        => 'Video File',
    'button_text'  => 'Select Video',
    'library_type' => 'video',
    'preview'      => false,
]
```

**Retrieving uploaded files:**
```php
use Pedalcms\CassetteCmf\CassetteCmf;

// Get attachment ID using Cassette-CMF
$attachment_id = CassetteCmf::get_field( 'featured_image', $post_id );

// Get attachment URL
$image_url = wp_get_attachment_url( $attachment_id );

// Get image with specific size
$image = wp_get_attachment_image( $attachment_id, 'medium' );
```

---

## Field Configuration

### Common Configuration Keys

All fields support these configuration options:

```php
[
    // Required
    'name'        => 'field_name',      // Field identifier
    'type'        => 'text',            // Field type
    
    // Display
    'label'       => 'Field Label',     // Display label
    'description' => 'Help text',       // Description/help text
    'placeholder' => 'Enter text...',   // Placeholder text
    
    // Behavior
    'default'     => 'default value',   // Default value
    'required'    => false,             // Is required?
    'disabled'    => false,             // Is disabled?
    'readonly'    => false,             // Is read-only?
    
    // Styling
    'class'       => 'custom-class',    // CSS class
    'wrapper_class' => 'wrapper-class', // Wrapper CSS class
    
    // Validation
    'validation'  => [
        'required' => true,
        'min'      => 5,
        'max'      => 100,
        'pattern'  => '/^[a-z]+$/',
        'email'    => true,
        'url'      => true,
    ],
]
```

### Field-Specific Configuration

Each field type may support additional options. See the [Core Field Types Reference](#core-field-types-reference) for details.

---

## Validation and Sanitization

### Built-in Validation Rules

AbstractField provides these validation rules:

#### Required
```php
$field->set_config([
    'validation' => ['required' => true],
]);
```

#### Minimum Length
```php
$field->set_config([
    'validation' => ['min' => 5],
]);
```

#### Maximum Length
```php
$field->set_config([
    'validation' => ['max' => 100],
]);
```

#### Pattern (Regex)
```php
$field->set_config([
    'validation' => ['pattern' => '/^[a-zA-Z0-9]+$/'],
]);
```

#### Email
```php
$field->set_config([
    'validation' => ['email' => true],
]);
```

#### URL
```php
$field->set_config([
    'validation' => ['url' => true],
]);
```

### Custom Validation

Override the `validate()` method:

```php
class PhoneField extends AbstractField {
    
    public function validate($value): array {
        // Call parent validation first
        $result = parent::validate($value);
        
        // Add custom validation
        if ($result['valid']) {
            if (!preg_match('/^\d{3}-\d{3}-\d{4}$/', $value)) {
                $result['valid'] = false;
                $result['errors'][] = 'Phone must be in format: 123-456-7890';
            }
        }
        
        return $result;
    }
}
```

### Custom Sanitization

Override the `sanitize()` method:

```php
class PhoneField extends AbstractField {
    
    public function sanitize($value) {
        // Remove all non-digit characters
        $digits = preg_replace('/\D/', '', $value);
        
        // Format as XXX-XXX-XXXX
        if (strlen($digits) === 10) {
            return substr($digits, 0, 3) . '-' . 
                   substr($digits, 3, 3) . '-' . 
                   substr($digits, 6, 4);
        }
        
        return $value;
    }
}
```

---

## Asset Enqueuing

Fields can enqueue their own CSS and JavaScript assets.

### Basic Asset Enqueuing

Override the `enqueue_assets()` method:

```php
class ColorPickerField extends AbstractField {
    
    public function enqueue_assets(): void {
        // Enqueue WordPress color picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        // Enqueue custom initialization script
        wp_enqueue_script(
            'my-color-picker-init',
            plugin_dir_url(__FILE__) . 'assets/color-picker.js',
            ['wp-color-picker', 'jquery'],
            '1.0.0',
            true
        );
    }
    
    public function render($value = null): string {
        $attrs = $this->get_attributes([
            'type'  => 'text',
            'class' => 'color-picker-field',
            'value' => $value ?? $this->get_config('default', '#000000'),
        ]);
        
        return $this->render_wrapper(
            $this->render_label() .
            "<input {$attrs} />" .
            $this->render_description()
        );
    }
}
```

### Context-Aware Loading

Assets are automatically loaded only on relevant admin screens. The Registrar manages this through the `admin_enqueue_scripts` hook.

### Common Assets

Use the `CassetteCmf_enqueue_common_assets` action for assets used by multiple fields:

```php
add_action('CassetteCmf_enqueue_common_assets', function() {
    wp_enqueue_style(
        'my-common-field-styles',
        plugin_dir_url(__FILE__) . 'assets/common.css',
        [],
        '1.0.0'
    );
});
```

---

## Using FieldFactory

### Creating Single Fields

```php
use Pedalcms\CassetteCmf\Field\FieldFactory;

$field = FieldFactory::create([
    'name'  => 'username',
    'type'  => 'text',
    'label' => 'Username',
]);
```

### Creating Multiple Fields

```php
$fields = FieldFactory::create_multiple([
    'first_name' => [
        'type'  => 'text',
        'label' => 'First Name',
    ],
    'last_name' => [
        'type'  => 'text',
        'label' => 'Last Name',
    ],
    'email' => [
        'type'  => 'email',
        'label' => 'Email Address',
    ],
]);

foreach ($fields as $name => $field) {
    echo $field->render();
}
```

### Registering Custom Field Types

```php
FieldFactory::register_type('slider', SliderField::class);
FieldFactory::register_type('wysiwyg', WysiwygField::class);
```

### Checking Available Types

```php
if (FieldFactory::has_type('slider')) {
    $field = FieldFactory::create([
        'name' => 'volume',
        'type' => 'slider',
    ]);
}

$types = FieldFactory::get_registered_types();
print_r($types);
```

---

## Integration with CPT and Settings

### Adding Fields to Custom Post Types

```php
use Pedalcms\CassetteCmf\Core\Manager;

$manager = Manager::init();

// Register CPT with fields using array configuration
$manager->register_from_array([
    'cpts' => [
        [
            'id'   => 'book',
            'args' => [
                'label'  => 'Books',
                'public' => true,
            ],
            'fields' => [
                [
                    'name'  => 'author_name',
                    'type'  => 'text',
                    'label' => 'Author Name',
                ],
                [
                    'name'  => 'isbn',
                    'type'  => 'text',
                    'label' => 'ISBN',
                ],
                [
                    'name'  => 'publish_date',
                    'type'  => 'date',
                    'label' => 'Publish Date',
                ],
            ],
        ],
    ],
]);

// Or add fields to an existing post type
$handler = $manager->get_existing_cpt_handler();
$handler->add_fields('post', [
    [
        'name'  => 'subtitle',
        'type'  => 'text',
        'label' => 'Subtitle',
    ],
]);
```

### Adding Fields to Settings Pages

```php
$manager = Manager::init();

// Register settings page with fields
$manager->register_from_array([
    'settings_pages' => [
        [
            'id'         => 'my_settings',
            'page_title' => 'My Settings',
            'menu_title' => 'My Settings',
            'capability' => 'manage_options',
            'fields' => [
                [
                    'name'  => 'site_name',
                    'type'  => 'text',
                    'label' => 'Site Name',
                ],
                [
                    'name'  => 'contact_email',
                    'type'  => 'email',
                    'label' => 'Contact Email',
                ],
                [
                    'name'  => 'theme_color',
                    'type'  => 'color',
                    'label' => 'Theme Color',
                ],
            ],
        ],
    ],
]);

// Or add fields to existing WordPress settings pages
$handler = $manager->get_existing_settings_handler();
$handler->add_fields('general', [
    [
        'name'  => 'custom_option',
        'type'  => 'text',
        'label' => 'Custom Option',
    ],
]);
```

---

## Advanced Examples

### Conditional Fields

Show/hide fields based on other field values:

```php
class ConditionalField extends AbstractField {
    
    public function render($value = null): string {
        $depends_on = $this->get_config('depends_on');
        $depends_value = $this->get_config('depends_value');
        
        $attrs = $this->get_attributes([
            'type'  => 'text',
            'value' => $value ?? $this->get_config('default', ''),
            'data-depends-on' => $depends_on,
            'data-depends-value' => $depends_value,
        ]);
        
        return $this->render_wrapper(
            $this->render_label() .
            "<input {$attrs} />" .
            $this->render_description(),
            ['data-conditional' => 'true']
        );
    }
    
    public function enqueue_assets(): void {
        wp_enqueue_script(
            'conditional-fields',
            plugin_dir_url(__FILE__) . 'assets/conditional-fields.js',
            ['jquery'],
            '1.0.0',
            true
        );
    }
}
```

### Repeater Fields

Create repeatable field groups:

```php
class RepeaterField extends AbstractField {
    
    public function render($value = null): string {
        $fields = $this->get_config('fields', []);
        $value = $value ?? [];
        
        $output = $this->render_label();
        $output .= '<div class="repeater-field">';
        
        foreach ($value as $index => $item) {
            $output .= $this->render_group($fields, $item, $index);
        }
        
        $output .= '<button type="button" class="add-row">Add Row</button>';
        $output .= '</div>';
        
        return $this->render_wrapper($output);
    }
    
    private function render_group(array $fields, array $data, int $index): string {
        $output = '<div class="repeater-row">';
        
        foreach ($fields as $field_config) {
            $field = FieldFactory::create($field_config);
            $name = $this->get_name() . "[{$index}][{$field->get_name()}]";
            $value = $data[$field->get_name()] ?? null;
            
            $output .= $field->render($value);
        }
        
        $output .= '<button type="button" class="remove-row">Remove</button>';
        $output .= '</div>';
        
        return $output;
    }
}
```

### WYSIWYG Editor Field

Integrate WordPress TinyMCE editor:

```php
class WysiwygField extends AbstractField {
    
    public function render($value = null): string {
        $value = $value ?? $this->get_config('default', '');
        $editor_id = $this->get_name() . '_editor';
        
        $settings = [
            'textarea_name' => $this->get_name(),
            'textarea_rows' => $this->get_config('rows', 10),
            'teeny' => $this->get_config('teeny', false),
            'media_buttons' => $this->get_config('media_buttons', true),
        ];
        
        ob_start();
        echo $this->render_label();
        wp_editor($value, $editor_id, $settings);
        echo $this->render_description();
        $output = ob_get_clean();
        
        return $this->render_wrapper($output);
    }
}
```

### File Upload Field

Handle file uploads:

```php
class FileField extends AbstractField {
    
    public function render($value = null): string {
        $accept = $this->get_config('accept', '*');
        $multiple = $this->get_config('multiple', false);
        
        $attrs = $this->get_attributes([
            'type' => 'file',
            'accept' => $accept,
            'multiple' => $multiple,
        ]);
        
        $output = $this->render_label();
        $output .= "<input {$attrs} />";
        
        if ($value) {
            $output .= "<div class='current-file'>";
            $output .= "Current: " . basename($value);
            $output .= "</div>";
        }
        
        $output .= $this->render_description();
        
        return $this->render_wrapper($output);
    }
    
    public function sanitize($value) {
        // Handle file upload
        if (isset($_FILES[$this->get_name()])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            
            $file = $_FILES[$this->get_name()];
            $upload = wp_handle_upload($file, ['test_form' => false]);
            
            if (isset($upload['url'])) {
                return $upload['url'];
            }
        }
        
        return $value;
    }
}
```

---

## Best Practices

### 1. Always Extend AbstractField

Don't implement FieldInterface directly. Extend AbstractField to get common functionality:

```php
// ✅ Good
class MyField extends AbstractField { }

// ❌ Bad
class MyField implements FieldInterface { }
```

### 2. Use Helper Methods

AbstractField provides many helper methods:

```php
// Render helpers
$this->render_label();
$this->render_description();
$this->render_wrapper($content, $attrs);
$this->get_attributes($attrs);

// Validation helpers
parent::validate($value); // Call parent first
$this->add_validation_rule('custom', $callback);

// Configuration helpers
$this->get_config('key', 'default');
$this->set_config(['key' => 'value']);
```

### 3. Validate Input

Always validate user input:

```php
public function validate($value): array {
    $result = parent::validate($value);
    
    if ($result['valid']) {
        // Your custom validation
        if (!$this->is_valid_format($value)) {
            $result['valid'] = false;
            $result['errors'][] = 'Invalid format';
        }
    }
    
    return $result;
}
```

### 4. Sanitize Output

Always sanitize values before rendering:

```php
public function render($value = null): string {
    $value = $this->sanitize($value ?? $this->get_config('default', ''));
    
    $attrs = $this->get_attributes([
        'value' => esc_attr($value),
    ]);
    
    return $this->render_wrapper("<input {$attrs} />");
}
```

### 5. Make Fields Reusable

Design fields to be configuration-driven:

```php
// ✅ Good - Configurable
$field = FieldFactory::create([
    'name' => 'rating',
    'type' => 'slider',
    'min' => 0,
    'max' => 5,
    'step' => 0.5,
]);

// ❌ Bad - Hardcoded
class RatingField extends AbstractField {
    private const MIN = 0;
    private const MAX = 5;
}
```

### 6. Register Fields Early

Register custom field types during plugin initialization:

```php
add_action('init', function() {
    FieldFactory::register_type('wysiwyg', WysiwygField::class);
    FieldFactory::register_type('repeater', RepeaterField::class);
}, 5); // Early priority
```

### 7. Enqueue Assets Conditionally

Only enqueue assets when needed:

```php
public function enqueue_assets(): void {
    // Only enqueue if not already enqueued
    if (!wp_script_is('my-field-script', 'enqueued')) {
        wp_enqueue_script('my-field-script', ...);
    }
}
```

### 8. Document Your Fields

Add PHPDoc comments to custom fields:

```php
/**
 * Slider Field
 * 
 * Renders an HTML5 range input with value output.
 * 
 * Configuration:
 * - min (int): Minimum value (default: 0)
 * - max (int): Maximum value (default: 100)
 * - step (int): Step increment (default: 1)
 * 
 * @package YourPlugin\Fields
 */
class SliderField extends AbstractField { }
```

### 9. Test Your Fields

Write tests for custom fields:

```php
public function test_slider_field_renders(): void {
    $field = new SliderField('volume', 'slider', [
        'min' => 0,
        'max' => 100,
    ]);
    
    $html = $field->render(50);
    
    $this->assertStringContainsString('type="range"', $html);
    $this->assertStringContainsString('value="50"', $html);
}
```

### 10. Handle Errors Gracefully

Provide helpful error messages:

```php
public function validate($value): array {
    $result = ['valid' => true, 'errors' => []];
    
    if (!$this->is_valid($value)) {
        $result['valid'] = false;
        $result['errors'][] = sprintf(
            'Value must be between %d and %d',
            $this->get_config('min'),
            $this->get_config('max')
        );
    }
    
    return $result;
}
```

---

## Additional Resources

- **[FieldFactory Documentation](../examples/field-factory-usage/README.md)** - Complete FieldFactory guide
- **[Field Asset Enqueuing](../examples/field-custom-assets/README.md)** - Custom assets guide
- **[Core Field Types Source](../src/Field/fields/)** - Source code for all core fields
- **[Field Tests](../tests/Field/)** - Test examples for reference

---

## Next Steps

1. **Create your first custom field** following the examples above
2. **Register it with FieldFactory** during plugin initialization
3. **Use it in CPTs or Settings Pages** via the Registrar
4. **Write tests** for your custom field
5. **Share your field** with the community!

For questions or issues, please visit the [GitHub repository](https://github.com/PedalCMS/cassette-cmf).
