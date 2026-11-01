# Changelog

All notable changes to Cassette-CMF will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Optional boolean field-config key `bindings` (strict `true` only). It is ignored by rendering, saving and validation; it lets companion libraries, such as a block-bindings source, expose only opted-in fields. Accepted in JSON configs and documented in `schema.json`.
- `Manager::get_registered_field()` / `CassetteCMF::get_registered_field()`: returns the config of a registered field for a post, term or settings-page context, or `null`.
- `Manager::is_field_public()` / `CassetteCMF::is_field_public()`: `true` only for a registered field with `bindings` set to `true` whose type is not `password` or `secret`.

- `CassetteCMF::boot( array $options = [] ): Manager` / `Manager::boot()`: optional coexistence options for sites that load two copies of the library. `asset_handle` (default `cassette-cmf`) names the CSS and JS handle in every handler; `js_global` (default `CassetteCMFFields`) names the `window` property `cassette-cmf.js` publishes (a non-default name is handed over through a one-shot inline script, the default adds none); `rest_namespace` (default `cassette-cmf/v1`) is stored for the REST controller. Invalid values raise `_doing_it_wrong()` and use the default; options apply on the first call only. New accessors `Manager::asset_handle()`, `js_global()`, `rest_namespace()`. Without a `boot()` call, all output is unchanged.
- Test: a Strauss-style prefixed copy of `src/` is generated at test time (`tests/Support/class-prefixed-copy.php`) and loaded next to the plain library (`Test_Coexistence`).
- Opt-in REST route for settings pages: set `show_in_rest => true` on a page config (also implied by a future `render_mode` of `react`) and `{namespace}/settings/{page_id}` serves it (`GET` values, `POST`/`PUT`/`PATCH` partial update, `OPTIONS` JSON schema). The namespace is the `rest_namespace` boot option (default `cassette-cmf/v1`). Capability is the page's own (default `manage_options`). A write runs the same pipeline as the classic form (`cassette_cmf_before_save_field*` filters, the field's `sanitize()` and `validate()`, `update_option()`), stores the same shape, and is all or nothing with per-field 400 errors (`data.params`, `data.details`). Pages without the key are untouched. `register_setting()` is deliberately not given `show_in_rest`, so options are not also exposed on `/wp/v2/settings` (that route would expose each option as a top-level key, require `manage_options`, and has no per-page error shape). See `docs/rest-api.md`.
- `Rest\Settings_Schema`: public static builder of a JSON schema from field config (types, enums from options, min/max/step, `email`/`uri`/`date`/`hex-color` formats, `required`, `readOnly`/`writeOnly`, repeater rows as arrays of objects, multi-checkbox as arrays), plus value conversion between the stored and the REST shape.
- Field-level `show_in_rest: false` (settings pages): the field is left out of the REST schema and values and is not writable over REST; it still renders and saves in the classic renderer. Separate from the `bindings` key.
- Settings page keys `schema_version` (int >= 1) and `upgrade` (`callable( array $old, int $from ): array`): on `admin_init` and before the first REST read or write, a page whose stored version (option `cassette_cmf_schema_version_{page_id}`) is behind is upgraded once under an expiring lock; the returned fields are sanitized and written, the version is bumped. Failures (exception or non-array) leave data and version untouched, are logged under `WP_DEBUG`, fire `cassette_cmf_schema_upgrade_failed` and show an admin notice. Existing data without a stored version counts as version 1; a new install just records the version. Pages without the keys write nothing.
- React settings page renderer: set `render_mode => 'react'` on a settings page (default `classic`, byte-identical output) to draw it with a `@wordpress/components` app (`TabPanel`, `Panel`, `Card`, snackbar and inline errors, dirty-state guard, RTL, translations) that saves through the REST route. All field types are mapped; conditional display is a JS port of the server rules checked against shared fixtures (`tests/fixtures/conditions.json`). If the build is missing or WordPress is older than 6.7 the classic renderer is used with an admin notice. Fields with `show_in_rest => false` stay classic-only. New page key `description`, filters `cassette_cmf_settings_app_build_path` / `_url`, `Settings_Page::get_render_mode()`, `Custom_HTML_Field::get_content_html()`, `Settings_Controller::get_values()`. The built app is in `build/` (`npm run build`). See `docs/react-settings.md` and `examples/05-react-settings`.
- New `secret` field type for API keys and tokens: never rendered or returned (only a mask such as `••••1234`), empty submission keeps the stored value, an explicit clear control empties it, optional `encrypt => true` (libsodium secretbox keyed from `wp_salt( 'auth' )`). `CassetteCMF::get_secret()` / `Manager::get_secret()` return the real value; `get_field()` returns the mask. `password` is unchanged.
- Filter `cassette_cmf_schema_upgrade_lock_ttl`, action `cassette_cmf_schema_upgrade_failed`.
- `schema.json` accepts the page keys `show_in_rest` and `schema_version`, the field key `show_in_rest` and the `secret` type.
- **`docs/STABILITY.md`**: the stability promise for 1.0 (storage shapes, option naming, config keys, sanitizer outputs, hooks, REST route and errors, boot options, the public PHP API, what is not frozen, the semver rule), linked from the README. `@api` tags added to the `CassetteCMF` facade, the frozen `Manager` methods, `Field_Interface`, `Container_Field_Interface`, `Abstract_Field` and `Field_Factory` (docblocks only; no signature changed). New `tests/Unit/Test_Stability_Contract.php` locks the sanitizer table and the option naming rule.
- Settings page config accepts the schema's key names `slug`, `icon` and `parent` as aliases of `menu_slug`, `icon_url` and `parent_slug`, in arrays and in JSON. When both spellings are given the code name wins and a `_doing_it_wrong()` notice is raised. Configs that use the code names are unchanged. The aliases only apply when a new page is created (a config that sets `page_title`, `menu_title`, `capability`, `menu_slug`, `callback`, `icon_url`, `position` or `parent_slug`); a config whose only page key is an alias, such as `{ "id": "general", "parent": "options-general.php", "fields": [...] }`, still adds its fields to the existing page. **Upgrade note:** a new page that already carried `icon`, `slug` or `parent` (ignored until now) now uses them, for example the menu icon.
- `schema.json` documents `taxonomies` (`id`, `args`, `object_type`, `fields`), which `register_from_array()` and `register_from_json()` already handled, and lists the code names `menu_slug`, `icon_url` and `parent_slug` next to their aliases.
- Docs: `docs/rest-api.md` and `docs/field-api.md` no longer describe the React renderer as upcoming.

### Changed
- Classic settings pages: saving the form (the page's metabox fields) now checks the page's own `capability` (default `manage_options`) instead of a hard-coded `manage_options`, as the rest of the page and the REST route already did. Pages that do not set a capability are unchanged. **Upgrade note:** on a page with a custom `capability`, users who hold that capability can now save the page's fields; users who do not hold it still cannot. If you relied on `manage_options` being required to save such a page, set `capability` to `manage_options`.
- Minimum PHP lowered from 8.2 to 7.4 (`composer.json` `require.php` is now `>=7.4`). No source change was needed.
- Requirements are now declared as PHP 7.4+ and WordPress 6.7+ (the oldest WordPress version CI tests). No runtime check was added.

### Dev
- Test harness: `CassetteCMF_UnitTestCase` now also tolerates the `_doing_it_wrong()` notices that WordPress 7.1 raises for repeated icon registration (`WP_Icon_Collections_Registry::register`, `WP_Icons_Registry::register`) when a test re-fires `init`. Those two functions only; no assertion changed and no library code touched. The suite is green on WordPress 6.7, 6.8 and 7.1.
- Tests for the settings page key aliases, taxonomies in config arrays and the capability checked when a classic settings page is saved.
- `bin/set-version.sh` also updates `package-lock.json` (top-level `version` and `packages[""].version`).
- Release workflow: accepts `-alpha.N`, `-beta.N` and `-rc.N` tags (they were rejected by the version check although the tag filter and the pre-release flag expected them), and fails when the committed `build/` differs from a fresh `npm run build`.
- Tests for the settings REST route (`Test_Rest_Settings`, with a schema snapshot in `tests/fixtures/`), the secret field (`Test_Secret_Field`) and schema upgrades (`Test_Settings_Upgrade`).
- Added PHPCompatibilityWP (`phpcompatibility/phpcompatibility-wp`) with `testVersion` `7.4-` to the PHPCS ruleset.
- CI: PHP 7.4 / WordPress 6.7 cell in the PHPUnit matrix, and a `php -l` job on PHP 7.4 for `src/`, `examples/` and `tests/`.

## [0.0.2] - 2025-01-01

### Added
- Initial release
- Custom Post Type registration with fields
- Custom Taxonomy registration with term meta fields
- Settings Pages (top-level and submenu)
- 18 field types: text, textarea, number, email, url, password, date, color, select, checkbox, radio, wysiwyg, tabs, metabox, group, repeater, custom_html, upload
- Container fields: tabs, metabox, group, repeater
- Extend existing post types, taxonomies, and settings pages
- Array-based configuration
- JSON file configuration with schema validation
- Before-save filters for field values
- Built-in validation and sanitization
- Context-aware asset management
- Full internationalization (i18n) support
- PHPStan static analysis (level 5)
- WordPress Coding Standards compliance
- Comprehensive test suite

[Unreleased]: https://github.com/PedalCMS/cassette-cmf/compare/v0.0.2...HEAD
[0.0.2]: https://github.com/PedalCMS/cassette-cmf/releases/tag/v0.0.2
