# React Settings Example

One settings page (Settings > React Demo) with `render_mode => 'react'`, drawn by the `@wordpress/components` app and saved over REST.

It uses every field type (text, email, url, password, number, date, color, textarea, select, multi-select, radio, checkbox, multi-checkbox, upload, wysiwyg, custom_html, secret), tabs inside a metabox, a group, a repeater, a conditional field ("Pro note" shows when Mode is Pro) and a `show_in_rest => false` field (listed in the classic-only notice). Remove `render_mode` to see the same page in the classic renderer.

Requires `npm run build` output in `build/` (committed) and WordPress 6.7+; otherwise the page falls back to classic with a notice. See [docs/react-settings.md](../../docs/react-settings.md).
