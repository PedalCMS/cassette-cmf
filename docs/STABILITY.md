# Stability promise

From the **`1.0.0`** tag, everything on this page is frozen. A change to a frozen surface ships only in a **major** version, with a migration note in [CHANGELOG.md](../CHANGELOG.md). New field types, config keys, hooks and `@api` methods are additive and may arrive in any minor release; a bug fix that makes the library match this page is a patch release.

Cassette-CMF is used by existing projects, so "frozen" also means: data written by any release from `0.1.x` on is read back unchanged, and a project that upgrades without changing its config sees no difference in options, meta, hooks, handles or markup. The cases in sections 1 and 4 are executable in `tests/Unit` (the classic save contract in `Test_Rest_Settings`, the sanitizers in the per-field tests).

## Contents

1. [Option and meta storage](#1-option-and-meta-storage)
2. [Option naming rule](#2-option-naming-rule)
3. [Config keys](#3-config-keys)
4. [Sanitizer output per field type](#4-sanitizer-output-per-field-type)
5. [Hooks](#5-hooks)
6. [REST route, request, response and errors](#6-rest-route-request-response-and-errors)
7. [Boot options](#7-boot-options)
8. [Public PHP API](#8-public-php-api)
9. [What is not frozen](#9-what-is-not-frozen)
10. [Semver rule and the recommended 1.0.0 point](#10-semver-rule-and-the-recommended-100-point)

## 1. Option and meta storage

**One option per field.** Containers (`group`, `tabs`, `metabox`) store nothing; their children are stored **flat**, each in its own option. `custom_html` stores nothing meaningful (`sanitize()` returns `null`).

| Field type | Stored value |
|---|---|
| `text`, `textarea`, `email`, `url`, `date`, `color`, `radio`, `wysiwyg`, `password` | string |
| `number` | `int`, `float`, or `''` when empty |
| `checkbox` (single, no `options`) | `'1'` or `'0'` |
| `checkbox` with `options` | array of the selected option keys (original keys preserved) |
| `select` | string; array of strings with `multiple`; a value outside `options` is stored as `''` |
| `upload` | attachment ID (`int`), URL string, or `''` |
| `repeater` | list of row arrays, `[ [ sub_field => value ] ]`; rows are re-indexed and empty rows dropped; sub-fields are never stored separately |
| `secret` | string: the plain value, or with `encrypt => true` the string `cassette-cmf-secret:` + `base64( 0x01 . nonce . sodium_crypto_secretbox( value ) )`; `''` when cleared |
| `group`, `tabs`, `metabox` | nothing |

**Where.**

- Settings pages: `update_option( {option name}, value )`.
- Post types: `update_post_meta( $post_id, {field name}, value )`; taxonomies: `update_term_meta( $term_id, {field name}, value )`. The meta key is the field name verbatim (no prefix), for new and existing post types and taxonomies.
- Settings pages that declare `schema_version` also keep `cassette_cmf_schema_version_{page_id}` (int, autoload off), the lock `cassette_cmf_schema_lock_{page_id}` (value: expiry timestamp) while an upgrade runs, and `cassette_cmf_schema_error_{page_id}` after a failed upgrade (removed on success). Pages without `schema_version` write none of them.

**Reading back.** `CassetteCMF::get_field()` and friends return the stored value (or the default argument when nothing is stored); for a `secret` field they return the mask, and only `CassetteCMF::get_secret()` returns the real value.

## 2. Option naming rule

`{page_id}_{field_name}`, or just `{field_name}` when the field sets `use_name_prefix => false`. This is `Field_Interface::get_option_name( $page_id )`. The page ID is the `id` of the settings page config (also the default menu slug). Post and term meta use the bare field name.

## 3. Config keys

Top-level array / JSON config: `cpts`, `settings_pages`. Each entry has a required `id`.

**Settings page** (`settings_pages[]`): `id`, `page_title`, `menu_title`, `capability` (default `manage_options`), `menu_slug` (default `id`), `callback`, `icon_url`, `position`, `parent_slug`, `fields`, and the opt-in keys:

| Key | Meaning |
|---|---|
| `show_in_rest` (bool) | Opt in to the REST route (section 6). Default: no route. |
| `render_mode` (`'classic'` default, `'react'`) | `'react'` draws the page with the React app and implies `show_in_rest`. An unknown value raises `_doing_it_wrong()` and means `classic`. A page with its own `callback` keeps it. |
| `schema_version` (int >= 1) | Version of the stored data of the page. |
| `upgrade` (`callable( array $old, int $from ): array`) | Migrates stored data older than `schema_version`; PHP configs only. Semantics: runs once on `admin_init` and before the first REST read or write, under a lock (60 s, filter `cassette_cmf_schema_upgrade_lock_ttl`); existing values with no stored version count as version 1; a new install only records the version; only returned fields are written, each through the field's `sanitize()`; an exception or non-array return writes nothing, keeps the version, releases the lock and fires `cassette_cmf_schema_upgrade_failed`. |

**Post type** (`cpts[]`): `id`, `args` (passed to `register_post_type()`), `fields`.

**Field** (every field): `name` and `type` (required), `label`, `description`, `placeholder`, `default`, `required`, `disabled`, `readonly`, `class`, `wrapper_class`, `attributes`, `prepend`, `append`, `use_name_prefix`, `conditional`, `validation` (`required`, `min`, `max`, `pattern`, `email`, `url`), `sanitize_callback`, and the keys of the individual types as listed in `schema.json`. Opt-in keys:

| Key | Meaning |
|---|---|
| `bindings` (bool) | Strictly `true` marks the field as exposable to companion libraries (`CassetteCMF::is_field_public()`). Ignored by rendering, saving and validation. Never exposes `password` or `secret`. |
| `show_in_rest` (bool) | `false` leaves the field out of the REST schema and values, rejects it on REST writes, and keeps it classic-only in the React renderer. Any other value means "exposed". |
| `encrypt` (bool, `secret` only) | Store the value encrypted with `sodium_crypto_secretbox`, keyed from `wp_salt( 'auth' )`. Rotating the salt makes stored secrets read as unset. |

Field types: `text`, `textarea`, `select`, `checkbox`, `radio`, `number`, `email`, `url`, `date`, `password`, `color`, `tabs`, `metabox`, `repeater`, `wysiwyg`, `group`, `custom_html`, `upload`, `secret`. The set only grows in minor releases.

Behaviour of conditional fields is frozen too: a field whose `conditional` rules are not met is still sanitized and saved; only its `validate()` is skipped.

## 4. Sanitizer output per field type

| Type | `sanitize( $input )` returns |
|---|---|
| `text`, `date` (the base-class default) | `sanitize_text_field( $input )` for strings; non-strings are returned unchanged |
| `textarea` | `sanitize_textarea_field()` (newlines kept); with `allow_html`, `wp_kses_post()`; non-strings `''` |
| `select` | the value if it is a key of `options`, else `''`; with `multiple` and an array, each item the same way |
| `radio` | the value if it is a key of `options`, else `''` |
| `checkbox` (single) | `'1'` if the input is non-empty, else `'0'` |
| `checkbox` with `options` | `array_intersect( $input, array_keys( options ) )`; `[]` for a non-array |
| `number` | `''` for an empty input other than `0` / `'0'`; `float` if the input contains a `.`; else `int` |
| `email` | `sanitize_email()`; `''` for non-strings |
| `url` | `esc_url_raw()`; `''` for non-strings |
| `color` | `#rgb` / `#rrggbb` (a leading `#` is added); anything else the field's `default`, else `#000000` |
| `password` | `trim()`; `''` for non-strings (no other change, on purpose) |
| `secret` | keep / replace / clear: an array with a truthy `clear` returns `''`; an empty string, the unchanged mask or a non-string keeps the stored value; a new string replaces it (encrypted with `encrypt`); idempotent |
| `wysiwyg` | `wp_kses_post()`; `''` for non-strings |
| `upload` | `''` for empty; a numeric input returns its `int` ID; a valid URL `esc_url_raw()`; else `''` |
| `repeater` | list of rows; each sub-field sanitized by its own type; non-array rows dropped; `[]` for a non-array |
| `group`, `tabs`, `metabox` | `[]` |
| `custom_html` | `null` |

Every built-in sanitizer is idempotent (the classic metabox save sanitizes twice).

## 5. Hooks

Names, argument lists and meaning are frozen. `{id}` is the entity or page ID.

| Hook | Type | Arguments |
|---|---|---|
| `cassette_cmf_register_config` | filter | `array $config`: the whole config, once per `register_from_array()` |
| `cassette_cmf_cpt_config`, `cassette_cmf_taxonomy_config`, `cassette_cmf_settings_page_config` | filter | `array $config, string $id` |
| `cassette_cmf_cpt_config_{id}`, `cassette_cmf_taxonomy_config_{id}`, `cassette_cmf_settings_page_config_{id}` | filter | `array $config` |
| `cassette_cmf_fields` | filter | `array $fields, string $id, string $context` (`'cpt'`, `'taxonomy'`, `'settings_page'`) |
| `cassette_cmf_fields_{id}` | filter | `array $fields, string $context` |
| `cassette_cmf_before_save_field` | filter | `mixed $value, string $field_name, string $context`; return `null` to skip saving the field |
| `cassette_cmf_before_save_field_{field_name}` | filter | `mixed $value` |
| `cassette_cmf_enqueue_common_assets` | action | none; fires after the common CSS/JS are enqueued |
| `cassette_cmf_schema_upgrade_failed` | action | `string $page_id, int $from, int $to, \Throwable $error` |
| `cassette_cmf_schema_upgrade_lock_ttl` | filter | `int $seconds, string $page_id` (default 60) |
| `cassette_cmf_settings_app_build_path` | filter | `string $path` (React renderer build directory) |
| `cassette_cmf_settings_app_build_url` | filter | `string $url` |

The before-save filters run on the classic save paths, on the REST write path and in the schema upgrade, with the same arguments. On a settings page saved through the metabox path they run twice (once explicitly, once through `pre_update_option_{option}`), so a filter must be idempotent.

## 6. REST route, request, response and errors

Only for settings pages with `show_in_rest => true` (or `render_mode => 'react'`). One route per opted-in page; no route is registered when no page opts in.

- **Route**: `{namespace}/settings/{page_id}`, namespace = the `rest_namespace` boot option (default `cassette-cmf/v1`).
- **Methods**: `GET` returns `{ "values": { field: value } }`; `POST`, `PUT`, `PATCH` take `{ "values": { field: value } }` (partial) and return the fresh values in the same shape; `OPTIONS` returns the JSON schema under `schema`.
- **Permission**: `current_user_can( <page capability> )` (default `manage_options`); anonymous requests get `401`, users without the capability `403` with code `rest_forbidden`. An unknown or non-opted-in page is `404 rest_no_route`. `OPTIONS` returns the field list only to users who pass the capability check.
- **Values**: containers and `custom_html` do not appear; children appear flat; `show_in_rest => false` fields and `password` fields never appear; `secret` reads as the mask; a single checkbox is a boolean, an empty `number` is `null`; a never-saved option reads as the field's `default`.
- **Writes** are all or nothing. Each submitted field runs the before-save filters, `sanitize()`, then `validate()` (skipped when its conditional rules are not met), and is stored with `update_option()` exactly as the classic form would store it. A key that is not an exposed, writable field (unknown, `show_in_rest => false`, `readonly`, `disabled`, a container) is rejected.
- **Errors**: `400` with `{ "code": "rest_invalid_param", "message": "Invalid parameter(s): a, b", "data": { "status": 400, "params": { field: message }, "details": { field: { code, message, data: { errors: [...] } } }, "errors": { field: [messages] } } }`; `503` with code `cassette_cmf_upgrade_in_progress` while a schema upgrade holds its lock.
- **Secret writes**: a new string replaces; the unchanged mask, `null`, `""` or an omitted key keeps; `{ "clear": true }` clears; any other shape is a `400`.

`register_setting()` is deliberately not given `show_in_rest` for these pages, so nothing is exposed on `/wp/v2/settings`.

## 7. Boot options

`CassetteCMF::boot( array $options = [] ): Manager`, optional, once, before any other call:

| Option | Default | Rule |
|---|---|---|
| `asset_handle` | `cassette-cmf` | `^[a-z0-9_-]+$`; the script and style handle of the common assets |
| `js_global` | `CassetteCMFFields` | `^[A-Za-z_$][A-Za-z0-9_$]*$`; the name `cassette-cmf.js` publishes on `window` |
| `rest_namespace` | `cassette-cmf/v1` | `^[a-z0-9-]+/v[0-9]+$` |

An invalid value or an unknown key raises `_doing_it_wrong()` and uses the default. The first call wins; a later call with different options is flagged and ignored; an identical call is silent. Without `boot()` every identifier keeps its default and the output is byte-identical to a project that never heard of it.

## 8. Public PHP API

Only items tagged `@api` are frozen.

**`PedalCMS\CassetteCMF\CassetteCMF`** (static facade): `init(): Manager`, `boot( array $options = [] ): Manager`, `register_from_array( array $config ): Manager`, `register_from_json( string $json_path_or_string, bool $validate = true ): Manager`, `get_field( string $field_name, $context, string $context_type = 'post', $default_value = '' )`, `get_post_field( string $field_name, int $post_id, $default_value = '' )`, `get_term_field( string $field_name, int $term_id, $default_value = '' )`, `get_settings_field( string $field_name, string $settings_page_id, $default_value = '' )`, `get_secret( string $field_name, string $page_id ): string`, `get_registered_field( string $field_name, $context, string $context_type = 'post' ): ?array`, `is_field_public( string $field_name, $context, string $context_type = 'post' ): bool`, `register_field_type( string $type, string $class_name ): Manager`. `$context_type` is `'post'`, `'term'` or `'settings'` (a post ID, a term ID, a page ID); anything else resolves to `null` / `false`.

**`Core\Manager`**: the same methods as instance methods (`register_from_array`, `register_from_json`, `register_field_type`, `get_field`, `get_post_field`, `get_term_field`, `get_settings_field`, `get_secret`, `get_registered_field`, `is_field_public`), the static `init()`, `boot()`, `asset_handle()`, `js_global()`, `rest_namespace()`, the constants `DEFAULT_ASSET_HANDLE`, `DEFAULT_JS_GLOBAL`, `DEFAULT_REST_NAMESPACE`, and `get_options()`, `get_option()`, `set_option()`.

**Custom fields**: `Field\Field_Interface` (the methods `render`, `sanitize`, `validate`, `get_name`, `get_label`, `get_type`, `get_schema`, `get_config`, `set_config`, `enqueue_assets`, `get_option_name`, `uses_name_prefix`, `get_field_id`, `uses_label_wrapper`), `Field\Container_Field_Interface` (adds `get_nested_fields`, `is_container`), `Field\Abstract_Field` (constructor `( string $name, string $type, array $config = [] )`, overridable `get_defaults()`, abstract `render()`, the default `sanitize()` / `validate()`, `get_all_config()`), `Field\Field_Factory::register_type()`, `create()`, `create_multiple()`, `get_registered_types()`, `has_type()`. An optional `get_rest_schema(): array` on a field class supplies its REST schema entry, and `Rest\Settings_Schema` (`collect_fields()`, `build()`, `field_schema()`, `to_rest_value()`, `from_rest_value()`) is public API.

## 9. What is not frozen

- Every class, method and constant not named in section 8, even if `public` (the handlers, `Settings_Page`, `Settings_Upgrader`, `Settings_Controller`, `React_Renderer`, `Manager::reset()`, the handler getters).
- The HTML the classic renderer prints, its CSS class names, the `cassette-cmf.js` internals and the DOM ids, except where a section above documents them (the posted names `{name}[value]` / `{name}[clear]` of the `secret` field are frozen).
- The React app: its component tree, markup and the `window.{js_global}SettingsApp` config object.
- WordPress hooks the library attaches to (`admin_init` priority, `rest_api_init`, ...) and the exact translatable strings.
- `schema.json` may gain keys in a minor release; it is only tightened (rejecting a config that loads today) in a major release.
- The compiled `build/` files and the npm tooling.

## 10. Semver rule and the recommended 1.0.0 point

- **Major**: a change to anything in sections 1 to 8 (a storage shape, option name, config key meaning, sanitizer output, hook name or argument, route, error shape, boot option, `@api` signature).
- **Minor**: additive only (new field types, config keys, hooks, `@api` methods, optional arguments).
- **Patch**: bug fixes.

**Recommended `1.0.0` tag point**: the commit that contains this file, after `0.2.0` (PHP 7.4 floor, `bindings`, `get_registered_field()` / `is_field_public()`, boot options, REST route, schema versions, `secret`, React renderer) has been tagged and used by one real consumer. The Playwright specs for the React renderer have been run against a real editor (Chromium, WP 7.1.4), and the former `WP 7.1` test failures are resolved by a test-harness change. Intermediate tag: `0.2.0`, then `1.0.0`.
