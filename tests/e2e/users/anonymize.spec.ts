import { test, expect } from '../support/fixtures';
import { createHousehold } from '../support/households';

/**
 * LRA-212: the anonymize-member confirmation flow, driven end-to-end
 * against a per-run-created member (never an anchor — anonymization is
 * irreversible, so it must never touch a seeded fixture other tests
 * depend on).
 */

const RUN = `${Date.now()}`;

test.describe('anonymize member', () => {
  test('requires typing the exact name and acknowledging before it can be submitted, then scrubs the member', async ({
    page,
  }) => {
    const lastName = `Anon${RUN}`;
    await createHousehold(page, 'Wendy', lastName);

    const fullName = `Wendy ${lastName}`;
    await expect(page.getByTestId('member-header')).toContainText(fullName);

    await page.getByTestId('anonymize-open').click();
    const dialog = page.locator('#anonymize-member-modal');
    await expect(dialog).toBeVisible();
    await expect(dialog).toContainText(/cannot be undone/i);

    const submit = page.getByTestId('anonymize-submit');
    await expect(submit).toBeDisabled();

    // Wrong name: still disabled.
    await page.getByTestId('anonymize-confirmation').fill('Not The Right Name');
    await expect(submit).toBeDisabled();

    // Exact name but not acknowledged: still disabled.
    await page.getByTestId('anonymize-confirmation').fill(fullName);
    await expect(submit).toBeDisabled();

    // Exact name + acknowledged: enabled.
    await page.getByTestId('anonymize-acknowledge').check();
    await expect(submit).toBeEnabled();

    await submit.click();

    await expect(page.getByTestId('member-header')).toContainText('Anonymized Member');
    // Scoped to the header: the primary member's own household-roster row
    // carries the same badge testid, so an unscoped lookup is ambiguous
    // whenever (as here) the anonymized member is the household's only member.
    await expect(page.getByTestId('member-header').getByTestId('badge-anonymized')).toBeVisible();
    await expect(page.getByTestId('profile-email')).toHaveCount(0);
    await expect(page.getByTestId('profile-edit')).toHaveCount(0);
    await expect(page.getByTestId('anonymize-open')).toHaveCount(0);
  });
});
