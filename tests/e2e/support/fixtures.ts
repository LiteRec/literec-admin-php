import { test as base, expect } from '@playwright/test';

/**
 * Base test extended with an automatic page-error collector: any test that
 * triggers an uncaught browser exception fails during teardown. Suites import
 * `test` and `expect` from here instead of directly from @playwright/test so
 * the collector is always active.
 */
export const test = base.extend<{ pageErrors: string[] }>({
  pageErrors: [
    async ({ page }, use) => {
      const errors: string[] = [];
      page.on('pageerror', (error) => errors.push(error.message));
      await use(errors);
      expect(errors, `Uncaught page errors: ${errors.join('; ')}`).toEqual([]);
    },
    { auto: true },
  ],
});

export { expect } from '@playwright/test';
