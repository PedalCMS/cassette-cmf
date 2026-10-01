# Advanced Example - JSON Configuration

This comprehensive example demonstrates **ALL** Cassette-CMF capabilities using external JSON configuration files.

## What This Example Demonstrates

### 1. New Custom Post Type: Property (Real Estate Listing)
A complete real estate property post type showing all field types and containers:

**Metabox: Listing Information**
- Listing ID (text, auto-generated)
- Price (number, required)
- Status (select: Active/Pending/Sold/Rented)
- Property Type (select)
- Listing Date (date)

**Metabox: Property Details (Horizontal Tabs)**
- Tab 1: Basic Info - bedrooms, bathrooms, sqft, lot size, year built, garage
- Tab 2: Features - amenities (multiple checkbox), flooring (radio), HOA fee shown only when HOA is enabled
- Tab 3: Description - short description (textarea), full description (wysiwyg)
- Tab 4: Media - virtual tour URL, video URL, floor plan URL

**Metabox: Location (with Groups)**
- Address Details group: street, city, state, zip, country
- Map Coordinates group: latitude, longitude

**Metabox: Agent Information (Sidebar)**
- Agent name, email, phone
- Featured listing checkbox

**Metabox: Open Houses (Repeater)**
- Repeatable entries with date, start/end times, notes

### 2. New Settings Page: Agency Settings
A comprehensive agency settings page demonstrating proper container field usage:

**Agency Settings Metabox** (wraps tabs - required on settings pages)
- **Vertical Tabs Container**:
  - **Company Info**: Agency name, tagline, email, phone, address, license
  - **Listings**: Per page count, default sort, show sold, favorites, compare
  - **Maps**: Google Maps API key, default coordinates, zoom, style
  - **Contact Form**: Recipient, subject, notifications (nested groups)
  - **Appearance**: Colors, card layout, custom CSS
  - **Advanced**: API settings, cache duration, terms (wysiwyg)

> **Note**: Tabs and other container fields (except Group and Metabox) must be wrapped in a Metabox on settings pages. Group fields can be used directly and render as WordPress Settings API sections.

### 3. Adding Fields to Existing Post Types

**Posts (built-in):**
- Article Settings (side): Featured, reading time, article type
- SEO Settings (normal): Meta title, description, focus keyword, noindex

**Pages (built-in):**
- Page Options (horizontal tabs):
  - Layout: Page layout (radio), sidebar, content width
  - Header: Hide title, header style, background color
  - Footer: Hide footer, footer style
- Call to Action (group): Enable, title, text, button, URL, color

### 4. Adding Fields to Existing Settings Pages

**General Settings:**
- Social Media Profiles (group): Facebook, Twitter, Instagram, YouTube, LinkedIn
- Brand Settings (group): Primary/secondary colors
- Google Analytics ID (text)

**Reading Settings:**
- Extended Reading Options (group): Show reading time, author bio, related posts, excerpt length

## File Structure

```
04-advanced-json/
├── example.php                    # PHP loader with before-save filters
├── README.md                      # This documentation
└── config/
    ├── cpt-property.json          # Property CPT definition
    ├── settings-agency.json       # Agency settings page
    ├── extend-posts.json          # Extensions for posts/pages
    └── extend-settings.json       # Extensions for general/reading settings
```

## All 18 Field Types Demonstrated

| Field Type | Location in Example |
|------------|---------------------|
| `text` | Listing ID, Agent Name, Street Address |
| `textarea` | Short Description, Custom CSS, Meta Description |
| `number` | Price, Bedrooms, Zoom Level, Excerpt Length |
| `email` | Agent Email, Notification Email |
| `url` | Virtual Tour, Social Profiles, CTA Button URL |
| `password` | Google Maps API Key, API Secret Key |
| `date` | Listing Date, Open House Date |
| `color` | Primary/Secondary Colors, Header Background |
| `select` | Property Status/Type, Country, Layout |
| `checkbox` | Featured, Amenities (multiple), Show Sold |
| `radio` | Flooring, Map Style, Page Layout |
| `wysiwyg` | Full Description, Terms & Conditions |
| `tabs` | Property Details, Agency Settings, Page Options |
| `metabox` | All CPT field containers |
| `group` | Location, Shipping, Social Profiles, CTA |
| `repeater` | Open House Schedule |

## Conditional Fields

This advanced JSON example includes conditional field visibility using the shared `conditional` property. In `config/cpt-property.json`, the `hoa_fee` field is displayed only when `has_hoa` is checked:

```json
{
    "name": "hoa_fee",
    "type": "number",
    "label": "Monthly HOA Fee ($)",
    "conditional": {
        "rules": [
            {
                "field": "has_hoa",
                "operator": "==",
                "value": "1"
            }
        ]
    }
}
```

Conditional visibility is not supported inside repeater sub-fields. Keep repeater rows unconditional or split the dependent inputs outside the repeater.

## Loading Multiple JSON Files

```php
$manager = Manager::init();

$config_files = [
    __DIR__ . '/config/cpt-property.json',
    __DIR__ . '/config/settings-agency.json',
    __DIR__ . '/config/extend-posts.json',
    __DIR__ . '/config/extend-settings.json',
];

foreach ( $config_files as $file ) {
    if ( file_exists( $file ) ) {
        $manager->register_from_json( $file );
    }
}
```

## Before-Save Filters (PHP Only)

JSON cannot define callbacks, so use PHP filters for preprocessing:

```php
// Format phone numbers
add_filter( 'CassetteCMF_before_save_field_agent_phone', function( $value ) {
    $numbers = preg_replace( '/[^0-9]/', '', $value );
    if ( strlen( $numbers ) === 10 ) {
        return sprintf( '(%s) %s-%s',
            substr( $numbers, 0, 3 ),
            substr( $numbers, 3, 3 ),
            substr( $numbers, 6 )
        );
    }
    return $value;
});

// Auto-generate listing ID
add_filter( 'CassetteCMF_before_save_field_listing_id', function( $value, $post_id ) {
    if ( empty( $value ) ) {
        return 'PROP-' . str_pad( $post_id, 6, '0', STR_PAD_LEFT );
    }
    return strtoupper( $value );
}, 10, 2 );
```

## Retrieving Values

Cassette-CMF provides a universal static method to retrieve field values:

```php
use PedalCMS\CassetteCMF\CassetteCMF;

// Property CPT fields
$price       = CassetteCMF::get_field( 'property_price', $property_id, 'post', 0 );
$amenities   = CassetteCMF::get_field( 'amenities', $property_id ); // returns array
$open_houses = CassetteCMF::get_field( 'open_house_schedule', $property_id ); // returns array

// Agency settings
$api_key       = CassetteCMF::get_field( 'map_api_key', 'agency-settings', 'settings' );
$primary_color = CassetteCMF::get_field( 'primary_color', 'agency-settings', 'settings', '#2c3e50' );

// Extended post fields
$is_featured = CassetteCMF::get_field( 'is_featured', $post_id );
$meta_title  = CassetteCMF::get_field( 'meta_title', $post_id );

// Extended page fields
$page_layout = CassetteCMF::get_field( 'page_layout', $page_id );
$cta_enabled = CassetteCMF::get_field( 'cta_enabled', $page_id );

// Extended general settings
$facebook  = CassetteCMF::get_field( 'social_facebook', 'general', 'settings' );
$analytics = CassetteCMF::get_field( 'analytics_id', 'general', 'settings' );

// Extended reading settings
$excerpt_length = CassetteCMF::get_field( 'excerpt_length', 'reading', 'settings', 55 );
```

## JSON vs Array Comparison

| Feature | JSON | Array (PHP) |
|---------|------|-------------|
| External file editing | ✅ Easy | ❌ Requires PHP |
| Schema validation | ✅ Built-in | ❌ None |
| Before-save callbacks | ❌ Not possible | ✅ Inline closures |
| Dynamic defaults | ❌ Not possible | ✅ PHP expressions |
| Multiple config files | ✅ Easy organization | ⚠️ Manual merging |
| Version control | ✅ Clean diffs | ⚠️ PHP noise |
| CI/CD integration | ✅ Excellent | ⚠️ Limited |
| Non-developer editing | ✅ Safe | ⚠️ Risk of errors |

## Best Practices for JSON Configuration

1. **Organize by concern** - Separate files for CPTs, settings, extensions
2. **Use descriptive names** - `cpt-property.json` not `config1.json`
3. **Keep PHP for callbacks** - Before-save filters, dynamic logic
4. **Validate before deployment** - Use JSON schema validation
5. **Document field names** - Comments not allowed in JSON, use README
6. **Use consistent naming** - `snake_case` for field names
