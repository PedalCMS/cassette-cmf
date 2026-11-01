# BC baseline dump (cassette-cmf)

Backward-compatibility gate. Boots WordPress with this repository's own
`examples/*/example.php` (never the parent `cassette-cmf-example.php`) on default
settings and writes one deterministic JSON file describing everything an existing
consumer can observe:

- `block_types`, `block_bindings_sources`, `rendered_blocks`, `editor_payload` (empty here: CMF registers no blocks)
- `rest_routes` (namespaces, plus detail for non-core namespaces)
- `scripts` / `styles` per firing context (`enqueue_block_editor_assets`, `enqueue_block_assets`, `wp_enqueue_scripts`, `admin_enqueue_scripts` for each CMF settings page, existing settings page and post type edit screen)
- `settings`: every CMF settings page (config, menu slug, capability, hook suffix, registered settings, rendered classic settings HTML)
- `options`: option names per field, registered settings
- `public_api`: reflection over every class/interface/trait/function in `src/`
- `hooks`: action and filter names found in `src/`, plus `notices` (`_doing_it_wrong` / deprecations raised)

Paths, URLs, nonces and asset versions are replaced by `{REPO}`, `{WP}`, `{HOME}`, `{NONCE}`, `{VER}`; callables are `Class::method` / `function` / `{closure}`.

## Run

```bash
cd cassette-cmf
BC_DUMP_OUT=/path/to/cmf-after.json WP_CORE_DIR=/path/to/wordpress \
  vendor/bin/phpunit -c tests/bc/phpunit-bc.xml
diff cmf-before.json cmf-after.json
```

Without `BC_DUMP_OUT` the test still builds the dump but writes nothing. The suite
is not part of the default `phpunit.xml.dist` run.

For cassette-cmf the dump is a BC gate: after the work, the diff against the
"before" dump must be empty apart from additive, justified entries.
