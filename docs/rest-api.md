# REST API

Cassette-CMF can expose a settings page over REST. It is **opt-in per page**: a page without `show_in_rest => true` gets no route and nothing changes for it. Available since 0.2.0.

## Contents

- [Enabling](#enabling)
- [Route](#route)
- [Authentication](#authentication)
- [Reading](#reading)
- [Writing](#writing)
- [Errors](#errors)
- [Schema](#schema)
- [Value shapes](#value-shapes)
- [Secret fields](#secret-fields)
- [Differences from the classic form](#differences-from-the-classic-form)
- [Why not `/wp/v2/settings`](#why-not-wpv2settings)
- [Limits](#limits)
- [Examples](#examples)

## Enabling

```php
CassetteCMF::register_from_array( [
    'settings_pages' => [
        [
            'id'           => 'my-settings',
            'page_title'   => 'My Settings',
            'capability'   => 'manage_options',   // default
            'show_in_rest' => true,
            'fields'       => [ /* ... */ ],
        ],
    ],
] );
```

Register the page before `rest_api_init` fires (any time on or before `init` is fine). The route is registered only when at least one page opted in. Fields can opt out individually with `show_in_rest => false` (see [Field API](field-api.md#rest-visibility-show_in_rest)).

## Route

`{namespace}/settings/{page_id}`, where the namespace is the `rest_namespace` boot option (`CassetteCMF::boot( [ 'rest_namespace' => 'acme/v1' ] )`, default `cassette-cmf/v1`). For example `GET /wp-json/cassette-cmf/v1/settings/my-settings`.

| Method | Purpose |
|--------|---------|
| `GET` | Current values: `{ "values": { "field": value, ... } }` |
| `POST`, `PUT`, `PATCH` | Partial update. Body `{ "values": { "field": value, ... } }`. Returns the fresh values in the same shape as `GET`. |
| `OPTIONS` | The JSON schema (key `schema`), per WordPress REST conventions. |

There is one route per opted-in page, so an unknown page or a page that did not opt in is a plain `404 rest_no_route`.

`GET` returns values only; the schema is on `OPTIONS` (keeps `GET` small and cacheable).

## Authentication

- Permission: `current_user_can( <page capability> )`, default `manage_options`. Anonymous requests get **401**, logged-in users without the capability **403**.
- Cookie authentication needs the core REST nonce: send the `X-WP-Nonce` header with a `wp_rest` nonce (`wp_create_nonce( 'wp_rest' )`, which `wp.apiFetch` does for you when the nonce middleware is set up). Without it WordPress treats the request as anonymous and the answer is 401. Application passwords need no nonce.
- `OPTIONS` returns the field list only to users who pass the capability check; everyone else gets an empty object schema.

## Reading

```json
{ "values": { "site_name": "Acme", "retries": 3, "enabled": true, "tags": ["a", "c"], "api_key": "••••9876" } }
```

- Only fields that store a value and are exposed appear: containers (`group`, `tabs`, `metabox`) and `custom_html` do not, their children do, **flat** (see [Value shapes](#value-shapes)). Fields with `show_in_rest => false` and `password` fields never appear.
- An option that was never saved reads as the field's `default` config (or the empty form of its type).

## Writing

The update is **partial** and **all or nothing**:

- Only the submitted fields are touched.
- Every submitted field goes through the same steps as a classic form save: the `cassette_cmf_before_save_field` and `cassette_cmf_before_save_field_{name}` filters (a filter returning `null` skips the field), the field's `sanitize()`, then the field's `validate()` (skipped when the field's conditional rules are not met; the other fields' stored and submitted values are the condition context), then `update_option( '{page_id}_{field}', value )`.
- If **any** submitted field is rejected, **nothing is written** and the response is a 400 listing every problem.
- A key that is not an exposed, writable field (unknown, `show_in_rest => false`, `readonly`, `disabled`, a container) is rejected the same way.
- Required fields are checked when they are submitted; a partial update does not require fields it does not mention.

## Errors

A rejected write returns HTTP 400 with the usual WordPress shape:

```json
{
  "code": "rest_invalid_param",
  "message": "Invalid parameter(s): retries, mode",
  "data": {
    "status": 400,
    "params":  { "retries": "Retries must be at most 10.", "mode": "Mode contains an invalid option." },
    "details": {
      "retries": { "code": "rest_invalid_param", "message": "Retries must be at most 10.", "data": { "errors": ["Retries must be at most 10."] } },
      "mode":    { "code": "rest_invalid_param", "message": "Mode contains an invalid option.", "data": { "errors": ["Mode contains an invalid option."] } }
    },
    "errors": { "retries": ["Retries must be at most 10."], "mode": ["Mode contains an invalid option."] }
  }
}
```

`params` and `details` follow WordPress core; `errors` is the same information as a plain `{ field: [messages] }` map. Other statuses: 401/403 (permission), 404 (no route), 503 `cassette_cmf_upgrade_in_progress` (a [schema upgrade](field-api.md#schema-version-and-upgrade) holds its lock; retry).

## Schema

`OPTIONS` returns, under `schema`, a JSON schema (draft 4, like core) of the response and the write body, generated from the field config by `PedalCMS\CassetteCMF\Rest\Settings_Schema` (public static `collect_fields()`, `build()`, `field_schema()`, `to_rest_value()`, `from_rest_value()`):

| Field | Schema |
|-------|--------|
| `text`, `textarea`, `wysiwyg` | `string` (`maxLength` from `maxlength`, `pattern` from a text `pattern`) |
| `email` / `url` / `date` / `color` | `string` with `format` `email` / `uri` / `date` (+ `pattern`) / `hex-color` (+ `pattern`) |
| `number` | `["number","null"]`, `minimum`, `maximum`, `multipleOf` from `step` |
| `select`, `radio` | `string` with `enum` of the option keys (plus `""` unless required) |
| `select` + `multiple`, `checkbox` with options | `array` of `string`, `uniqueItems`, `items.enum` |
| `checkbox` without options | `boolean` |
| `upload` | `["integer","string"]` (attachment ID or URL) |
| `repeater` | `array` of `object` (one property per sub-field), `minItems` / `maxItems` from `min_rows` / `max_rows` |
| `password` | `string`, `writeOnly` |
| `secret` | see below |
| any field | `title`, `description`, `default`, `required: true`, `readOnly` for `readonly` / `disabled`, `minLength` / `maxLength` / `minimum` / `maximum` / `format` from the `validation` rules |

The schema describes the REST shape. Rules that only a field's own `validate()` can express are enforced there, not by the schema, and the schema is advisory for values the field's sanitizer normalises (see below). A custom field class can supply its own entry by implementing `get_rest_schema(): array`.

## Value shapes

Storage is unchanged from the classic renderer: **one option per field**, named `{page_id}_{field}` (or just `{field}` with `use_name_prefix => false`). Containers store nothing, so `group`, `tabs` and `metabox` children are flat in `values`.

| Field | Stored in the option | REST value |
|-------|---------------------|------------|
| text-like (`text`, `textarea`, `email`, `url`, `date`, `color`, `radio`, `wysiwyg`, `select`) | string | string |
| `number` | int, float or `''` | number or `null` (`null` / `""` write `''`) |
| `checkbox` (single) | `'1'` or `'0'` | boolean (also accepts `0`/`1`/`"0"`/`"1"` on write) |
| `checkbox` with options, `select` + `multiple` | array of the selected keys | array of strings |
| `upload` | attachment ID (int), URL string or `''` | int or string |
| `repeater` | list of row arrays (`[ [ sub_field => value ] ]`) | array of row objects; sub-fields use the shapes above |
| `password` | string | write-only |
| `secret` | string (plain, or ciphertext with `encrypt`) | mask on read |

## Secret fields

`secret` fields are never returned. Reads give the mask: `••••` plus the last four characters (only `••••` for secrets of four characters or fewer), or `""` when unset.

| Write value | Effect |
|-------------|--------|
| a new string | replaces the stored value (encrypted when the field has `encrypt`) |
| the unchanged mask (starts with `••••`), `null`, `""`, or the key omitted | keeps the stored value |
| `{ "clear": true }` | clears it (stores `""`) |

Any other shape (`{ "clear": false }`, numbers, other objects) is a 400. A new secret cannot itself start with four bullet characters. Only `CassetteCMF::get_secret( $field, $page_id )` returns the real value server-side. `password` fields over REST: never returned, an empty write keeps the stored value, a non-empty write replaces it (use `secret` when you need to clear).

## Differences from the classic form

The stored result for valid input is identical. Differences, all on invalid or unusual input:

- A classic save sanitizes and then validates and only reports validation errors; REST does the same (it validates the sanitized value, as the classic path does), but it additionally rejects wrong JSON types (`"abc"` for a number, an object for a string) and, for `select`, `radio` and multi-`checkbox`, an option that is not in the field's options. The classic form silently blanks an unknown choice.
- Sanitizers that normalise (`email`, `url`, `color`, `sanitize_text_field`, ...) behave as in the classic form: an invalid email is stored as `''` rather than rejected.
- `readonly` and `disabled` fields cannot be written over REST.
- `password`: an empty write keeps the stored value (the classic form overwrites it with `''`).
- `custom_html` stores nothing and is not exposed.

## Why not `/wp/v2/settings`

Cassette-CMF stores one option per field. Registering them with `show_in_rest` would put every field on `/wp/v2/settings` as a top-level key, would require `manage_options` regardless of the page's own capability, and has no per-page error shape or all-or-nothing writes. So `register_setting()` is deliberately **not** given `show_in_rest` for opted-in pages, and the dedicated route above is used.

## Limits

- Only pages created by Cassette-CMF (with `page_title` / `menu_title` / ... in the config) can opt in. Fields added to existing WordPress pages (Settings > General, ...) are not exposed.
- `render_mode => 'react'` draws the page with the [React renderer](react-settings.md) and implies `show_in_rest`.
- Register pages before `rest_api_init`; a page added later gets no route in that request.

## Examples

```bash
# Read (application password)
curl -u admin:APP_PASSWORD https://example.com/wp-json/cassette-cmf/v1/settings/my-settings

# Partial update
curl -u admin:APP_PASSWORD -X POST -H 'Content-Type: application/json' \
  -d '{"values":{"site_name":"Acme","retries":3,"api_key":"sk-live-123456789"}}' \
  https://example.com/wp-json/cassette-cmf/v1/settings/my-settings

# Clear a secret
curl -u admin:APP_PASSWORD -X PATCH -H 'Content-Type: application/json' \
  -d '{"values":{"api_key":{"clear":true}}}' \
  https://example.com/wp-json/cassette-cmf/v1/settings/my-settings
```

```js
// In an admin screen (apiFetch adds the nonce)
const { values } = await wp.apiFetch( { path: '/cassette-cmf/v1/settings/my-settings' } );

try {
    await wp.apiFetch( {
        path: '/cassette-cmf/v1/settings/my-settings',
        method: 'POST',
        data: { values: { retries: 99 } },
    } );
} catch ( error ) {
    // error.data.details.retries.message
}
```
