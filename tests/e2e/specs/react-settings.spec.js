/**
 * E2E: the React settings page of examples/05-react-settings.
 *
 * Run against a throwaway WordPress with `WP_BASE_URL` (see README.md).
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const PAGE = '/wp-admin/options-general.php?page=react-demo';

test.describe( 'React settings page', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( PAGE );
		await expect( page.getByRole( 'heading', { name: 'React Settings Demo' } ) ).toBeVisible();
	} );

	test( 'loads with a disabled Save button', async ( { page } ) => {
		await expect( page.getByRole( 'button', { name: 'Save Changes' } ) ).toBeDisabled();
		await expect(
			page
				.locator( '.components-notice' )
				.getByText( 'only editable in classic mode' )
		).toBeVisible();
	} );

	test( 'edits, saves and shows the saved values after a reload', async ( { page } ) => {
		await page.getByLabel( 'Site name' ).fill( 'E2E Site' );
		await page.getByLabel( 'Contact email' ).fill( 'e2e@example.com' );
		await page.getByLabel( 'Retries' ).fill( '7' );
		await page.getByLabel( 'Launch date' ).fill( '2027-01-15' );
		await page.getByLabel( 'Tagline' ).fill( 'Saved over REST' );

		await page.getByRole( 'tab', { name: 'Choices' } ).click();
		await page.getByLabel( 'Mode' ).selectOption( 'pro' );
		await page.getByLabel( 'Pro note' ).fill( 'visible in pro mode' );
		await page.getByRole( 'radio', { name: 'Narrow' } ).check();
		await page.getByRole( 'checkbox', { name: 'Enable cache' } ).check();
		await page.getByRole( 'checkbox', { name: 'SMS' } ).check();

		await page.getByRole( 'tab', { name: 'Content' } ).click();
		await page.getByLabel( 'API key' ).first().fill( 'sk-e2e-123456789' );

		await page.getByRole( 'tab', { name: 'Lists' } ).click();
		// The saved option survives between runs, so rows from an earlier run
		// may already exist: work on the row just added (always the last).
		await page.getByRole( 'button', { name: 'Add link' } ).click();
		await page.getByLabel( 'Title' ).last().fill( 'Docs' );

		await page.getByRole( 'button', { name: 'Save Changes' } ).click();
		await expect( page.getByText( 'Settings saved.' ).first() ).toBeVisible();

		await page.reload();
		await expect( page.getByLabel( 'Site name' ) ).toHaveValue( 'E2E Site' );
		await expect( page.getByLabel( 'Retries' ) ).toHaveValue( '7' );
		await page.getByRole( 'tab', { name: 'Choices' } ).click();
		await expect( page.getByLabel( 'Mode' ) ).toHaveValue( 'pro' );
		await expect( page.getByRole( 'radio', { name: 'Narrow' } ) ).toBeChecked();
		await page.getByRole( 'tab', { name: 'Content' } ).click();
		await expect( page.getByPlaceholder( '••••6789' ) ).toBeVisible();
		await page.getByRole( 'tab', { name: 'Lists' } ).click();
		await expect( page.getByLabel( 'Title' ).last() ).toHaveValue( 'Docs' );
	} );

	test( 'shows a validation error inline', async ( { page } ) => {
		await page.getByLabel( 'Retries' ).fill( '99' );
		await page.getByRole( 'button', { name: 'Save Changes' } ).click();

		await expect( page.locator( '.cassette-cmf-field__error' ).first() ).toBeVisible();
		await expect( page.getByLabel( 'Retries' ) ).toBeFocused();
	} );
} );
