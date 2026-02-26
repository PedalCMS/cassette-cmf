# CASSETTE-CMF Test Suite

This directory contains the WordPress unit tests for CASSETTE-CMF, following the WordPress testing standards.

## Prerequisites

- PHP 8.1+
- Composer
- MySQL database (separate from your development database!)
- SVN (for downloading WordPress test files on Linux/macOS)

## Quick Start

### Option 1: GitHub Actions (Recommended for CI)

Tests run automatically on GitHub when you push to `main` or `develop` branches, or create a pull request.

### Option 2: Local Setup (Linux/macOS)

1. **Create a test database** (use a SEPARATE database from your development database!):
   ```bash
   mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS wordpress_test;"
   ```

2. **Install the WordPress test environment**:
   ```bash
   composer test-install
   ```
   
   Or with custom credentials:
   ```bash
   bash bin/install-wp-tests.sh wordpress_test db_user 'db_pass' localhost latest
   ```

3. **Run the tests**:
   ```bash
   composer test
   ```

### Option 3: Local Setup (Windows with WSL)

1. Install WSL (Windows Subsystem for Linux)
2. Install MySQL in WSL or use a local MySQL server
3. Follow the Linux instructions above in WSL

### Option 4: Local Setup (Windows with Docker)

1. Install Docker Desktop
2. Use `wp-env` for WordPress development:
   ```bash
   npm install -g @wordpress/env
   wp-env start
   wp-env run tests-cli bash bin/install-wp-tests.sh wordpress_test root password localhost latest
   wp-env run tests-cli vendor/bin/phpunit
   ```

## Test Structure

```
tests/
├── bootstrap.php           # Test bootstrap file
├── wp-tests-config.php     # WordPress test configuration (customize this!)
├── Data/                   # Test data files
└── Unit/                   # Unit tests
    ├── Test_Sample.php           # Sample/sanity tests
    ├── Test_Manager.php          # Manager class tests
    ├── Test_Field_Factory.php    # Field_Factory tests
    ├── Test_Field_Types.php      # Core field types tests
    ├── Test_Container_Fields.php # Container field tests
    ├── Test_Custom_Post_Type.php # CPT registration tests
    ├── Test_Settings_Page.php    # Settings page tests
    └── Test_Integration.php      # Integration tests
```

## Writing Tests

All test classes should:

1. Extend `WP_UnitTestCase`
2. Use the naming convention `Test_*` for the class name
3. Use the naming convention `test_*` for test methods
4. Use `set_up()` and `tear_down()` (snake_case) methods for setup/teardown

### Example Test

```php
<?php
class Test_My_Feature extends WP_UnitTestCase {

    public function set_up(): void {
        parent::set_up();
        // Setup code
    }

    public function tear_down(): void {
        // Cleanup code
        parent::tear_down();
    }

    public function test_my_feature_works(): void {
        $result = my_function();
        $this->assertTrue( $result );
    }
}
```

## Available Test Utilities

### Factory Methods

Create test data easily:

```php
// Create a post
$post_id = self::factory()->post->create();

// Create a user
$user_id = self::factory()->user->create(['role' => 'administrator']);

// Create a term
$term_id = self::factory()->term->create(['taxonomy' => 'category']);
```

### Assertions

All PHPUnit assertions plus WordPress-specific ones:

```php
$this->assertTrue( $condition );
$this->assertFalse( $condition );
$this->assertSame( $expected, $actual );
$this->assertEquals( $expected, $actual );
$this->assertStringContainsString( $needle, $haystack );
```

## Configuration

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `WP_TESTS_DIR` | Path to WordPress test library | Auto-detected |
| `WP_CORE_DIR` | Path to WordPress core | Auto-detected |
| `WP_TESTS_DB_NAME` | Test database name | `wordpress_test` |
| `WP_TESTS_DB_USER` | Database username | `root` |
| `WP_TESTS_DB_PASSWORD` | Database password | `root` |
| `WP_TESTS_DB_HOST` | Database host | `localhost` |

### wp-tests-config.php

Edit `tests/wp-tests-config.php` to customize your local database settings:

```php
define( 'DB_NAME', 'your_test_database' );
define( 'DB_USER', 'your_db_user' );
define( 'DB_PASSWORD', 'your_db_password' );
define( 'DB_HOST', 'localhost' );
```

## Troubleshooting

### "Could not find wordpress-tests-lib"

Run the install script:
```bash
composer test-install
```

### Database connection errors

1. Verify MySQL is running
2. Check credentials in `tests/wp-tests-config.php`
3. Ensure the test database exists

### "Table prefix already in use"

The test suite uses `wptests_` prefix. If you have conflicts, change the `$table_prefix` in `tests/wp-tests-config.php`.

## Continuous Integration

The GitHub Actions workflow (`.github/workflows/phpunit.yml`) runs tests on:
- PHP 8.1, 8.2, 8.3
- WordPress latest and 6.7

Tests run on every push to `main`/`develop` and on pull requests.
