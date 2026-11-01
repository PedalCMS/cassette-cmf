# End-to-end tests

Playwright specs for the React settings page (`render_mode => 'react'`), run with
`examples/05-react-settings` loaded by `fixture-plugin/`.

With Docker and `@wordpress/env`:

```bash
npm run build
npx wp-env start
npm run test:e2e
```

Without Docker, against any throwaway WordPress (the specs were run this way):
install WordPress with an `admin` / `password` user, copy this repository into
`wp-content/plugins/` (it must be reachable as `cassette-cmf`), activate it and
`tests/e2e/fixture-plugin/` as its own plugin, then:

```bash
npm run build
WP_BASE_URL=http://localhost:8889 npx wp-scripts test-playwright --reporter=list
```

`@wordpress/scripts` uses `WP_BASE_URL` (and `WP_USERNAME` / `WP_PASSWORD`,
default `admin` / `password`) when `@wordpress/env` is not installed or running.
Failure screenshots and traces land in `artifacts/test-results/`.

**Result of the first real run** (Chromium, WordPress 7.1.4 on SQLite served by
`wp server`): **3 of 3 specs pass**, also with `--repeat-each=3`. Two spec fixes
were needed and no product bug was found: `getByText( 'only editable in classic
mode' )` matched both the notice and the `a11y-speak` live region (now scoped to
`.components-notice`), and the save test assumed a pristine saved option (the
saved option survives between runs, so it now works on the last repeater row).
The 400 line in the console during the validation spec is the expected response
to the out-of-range value. The component logic is also covered by Jest
(`npm run test:unit:js`).
