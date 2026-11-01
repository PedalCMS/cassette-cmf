# React settings page

A settings page can be drawn by a `@wordpress/components` app instead of the classic form. It is **opt-in per page**: without `render_mode => 'react'` nothing changes, and the classic output stays byte-identical. Available since 0.2.0.

## Contents

- [Enabling](#enabling)
- [Requirements and fallback](#requirements-and-fallback)
- [What the page does](#what-the-page-does)
- [Field mapping](#field-mapping)
- [Classic-only fields](#classic-only-fields)
- [Conditional display](#conditional-display)
- [Page keys and hooks](#page-keys-and-hooks)
- [Building](#building)
- [Differences from the classic page](#differences-from-the-classic-page)

## Enabling

```php
CassetteCMF::register_from_array( [
    'settings_pages' => [
        [
            'id'          => 'my-settings',
            'page_title'  => 'My Settings',
            'description' => 'Shown under the title.',   // optional, React mode only
            'render_mode' => 'react',                    // 'classic' (default) or 'react'
            'fields'      => [ /* ... */ ],
        ],
    ],
] );
```

`react` implies `show_in_rest => true`: the app reads and writes through the [REST route](rest-api.md). Any `render_mode` other than `classic` or `react` raises `_doing_it_wrong()` and uses the classic renderer; it is not a validation error, so a typo never rejects a config. A page that sets its own `callback` keeps it.

See `examples/05-react-settings` for a page using every field type.

## Requirements and fallback

The app needs WordPress 6.7 or newer and the built files in `build/` (`settings-app.js`, `settings-app.asset.php`, `settings-app.css`, `settings-app-rtl.css`). If either is missing, or the `wp-components` script is not registered, the page is rendered by the classic renderer and an admin notice on that page says why. The library's declared WordPress minimum is not raised.

## What the page does

- Prints a root element and a `<noscript>` notice; the script and style are registered with the asset file's dependencies and version (handle `{asset_handle}-settings-app`, see `CassetteCMF::boot()`) and enqueued **only** on that page's screen. The classic scripts are not loaded there. `wp_enqueue_media()` runs for the upload field, translations come from `languages/` (`wp_set_script_translations()`, text domain `cassette-cmf`) and the style has an RTL twin (`'rtl' => 'replace'`).
- The current values and the field tree are passed in an inline object, `window.{js_global}SettingsApp` (default `CassetteCMFFieldsSettingsApp`).
- Layout: tabs are a `TabPanel`, groups a `Panel`, metaboxes a `Card`. Title and description are at the top.
- Save is primary and disabled while saving or when nothing changed. Only changed, writable fields are sent. Success shows a snackbar; failure shows an error `Notice` plus the per-field messages from `data.errors` / `data.details` / `data.params` next to the field, and focus moves to the first invalid field (switching tab if needed). Field help and errors are linked with `aria-describedby`.
- A `beforeunload` warning is active while there are unsaved changes.

## Field mapping

| Field type | Component |
|---|---|
| `text`, `email`, `url`, `password` | `TextControl` with the matching `type` |
| `number` | `TextControl type="number"` (the stable choice; `NumberControl` is experimental) |
| `date` | `TextControl type="date"` (stores `Y-m-d`, exactly like classic) |
| `textarea` | `TextareaControl` |
| `wysiwyg` | `TextareaControl` (the closest equivalent; no editor is initialised) |
| `select` | `SelectControl` (`multiple` when configured) |
| `radio` | `RadioControl` |
| `checkbox` | `CheckboxControl`; with `options`, a group of them |
| `color` | hex `TextControl` plus a `ColorPicker` in a `Dropdown` |
| `upload` | the `wp.media` modal plus a preview; stores the attachment ID like classic |
| `custom_html` | the configured HTML, sanitized with the same `wp_kses` rules as classic (`raw_html` honoured) |
| `secret` | password `TextControl` whose placeholder is the mask, plus Clear / Undo clear |
| `password` | password `TextControl`; empty means keep (never returned) |
| `group` | `Panel` / `PanelBody` |
| `metabox` | `Card` |
| `tabs` | `TabPanel` (horizontal or vertical) |
| `repeater` | rows of sub-fields with add, remove, move up and move down |

Labels, descriptions, `required`, `placeholder`, `readonly` / `disabled`, `min` / `max` / `step`, `maxlength` and select/radio options come from the field config. `prepend` / `append` adornments and the `pattern` attribute are not rendered; the server still validates.

## Classic-only fields

Fields with `show_in_rest => false` (and the children of a container with it) are not drawn. A notice lists their labels: "Some settings are only editable in classic mode". Switch the page back to `classic` to edit them.

## Conditional display

The rules of `conditional` are evaluated in the browser by a pure port of the server evaluator (`src/assets/settings-app/conditions.js`). Both implementations run the same cases in `tests/fixtures/conditions.json` (PHPUnit `Test_Condition_Fixtures`, Jest `conditions.test.js`). A hidden field keeps its value and is still saved, as in classic.

## Page keys and hooks

| Key / hook | Purpose |
|---|---|
| `render_mode` | `classic` (default) or `react` |
| `description` | intro text under the title (React mode) |
| filter `cassette_cmf_settings_app_build_path` | directory of the built app |
| filter `cassette_cmf_settings_app_build_url` | its URL |

## Building

```bash
npm ci
npm run build        # sass (classic CSS) + wp-scripts (build/settings-app.*)
npm run test:unit:js # Jest
npm run lint:js
```

`build/` is committed and ships in the release package. Playwright specs live in `tests/e2e` (they need `wp-env`, i.e. Docker).

## Differences from the classic page

- Saving is over REST: all or nothing, with the REST value shapes of [rest-api.md](rest-api.md) (it also honours the page capability, where the classic metabox save checks `manage_options`).
- `wysiwyg` is a plain textarea; `upload` is single-file.
- Nothing is submitted for unchanged fields, so values saved elsewhere meanwhile are not overwritten by this page.
