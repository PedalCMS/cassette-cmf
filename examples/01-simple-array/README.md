# Simple Example - PHP Array Configuration

This is a minimal example demonstrating Cassette-CMF basics using PHP array configuration.

## What This Example Creates

### Custom Post Type: Book
A simple "Books" post type with the following fields:
- **Book Info Banner** (custom_html) - Informational display banner
- **ISBN** (text) - Book identifier
- **Author** (text, required) - Author name
- **Page Count** (number) - Number of pages
- **Publication Date** (date) - When published
- **In Stock** (checkbox) - Availability status
- **Synopsis** (textarea) - Book description
- **Book Cover** (upload) - Cover image upload

### Taxonomy: Book Genre
A hierarchical taxonomy for categorizing books with custom fields:
- **Genre Color** (color) - Color for genre badges and labels
- **Icon Class** (text) - Dashicons class for the genre
- **Featured Genre** (checkbox) - Display prominently on the site

### Settings Page: Library Settings
A top-level settings page with:
- **Library Name** (text) - Name of the library
- **Contact Email** (email) - Contact information
- **Website URL** (url) - Library website
- **Max Borrowing Days** (number) - Loan period
- **Enable Notifications** (checkbox) - Email reminders
- **Display Theme** (radio) - Light/Dark/Auto theme
- **Accent Color** (color) - UI accent color

## Usage

Cassette-CMF provides a universal static method to retrieve field values:

```php
use Pedalcms\CassetteCmf\CassetteCmf;

// Get book meta (post fields)
$author = CassetteCmf::get_field( 'author_name', $post_id );
$isbn   = CassetteCmf::get_field( 'isbn', $post_id );
$pages  = CassetteCmf::get_field( 'page_count', $post_id, 'post', 0 ); // With default

// Get taxonomy term meta
$genre_color = CassetteCmf::get_field( 'genre_color', $term_id, 'term' );
$is_featured = CassetteCmf::get_field( 'is_featured', $term_id, 'term' );

// Get genres for a book with their custom colors
$genres = get_the_terms( $post_id, 'book_genre' );
foreach ( $genres as $genre ) {
    $color = CassetteCmf::get_field( 'genre_color', $genre->term_id, 'term', '#000000' );
}

// Get settings
$library_name = CassetteCmf::get_field( 'library_name', 'library-settings', 'settings' );
$accent_color = CassetteCmf::get_field( 'accent_color', 'library-settings', 'settings', '#2271b1' );
```

### Method Signature

```php
CassetteCmf::get_field( $field_name, $context, $context_type = 'post', $default = '' )
```

| Parameter | Description |
|-----------|-------------|
| `$field_name` | The field name as defined in your config |
| `$context` | Post ID, term ID, or settings page ID |
| `$context_type` | `'post'` (default), `'term'`, or `'settings'` |
| `$default` | Default value if field is empty |

## Key Concepts Demonstrated

1. **CPT Registration** - Simple post type with labels, icons, supports
2. **Taxonomy Registration** - Hierarchical taxonomy with custom fields
3. **Settings Page** - Top-level menu with icon and position
4. **Common Field Types** - text, textarea, number, date, select, checkbox, radio, email, url, color, custom_html, upload
5. **Field Options** - required, placeholder, default, min/max, description
6. **Data Retrieval** - `CassetteCmf::get_field()` for all field types

## For Advanced Features

See `advanced-array` or `advanced-json` examples for:
- All 18 field types
- Tabs, Metaboxes, Groups, Repeaters
- Adding fields to existing post types (posts, pages)
- Adding fields to existing taxonomies (categories, tags)
- Adding fields to existing settings (General, Reading, etc.)
- Nested containers and complex layouts
- Before-save filters
