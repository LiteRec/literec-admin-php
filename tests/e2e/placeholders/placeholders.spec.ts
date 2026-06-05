import fs from 'node:fs';
import path from 'node:path';
import yaml from 'js-yaml';
import { test, expect } from '../support/fixtures';
import { ANON_STATE, expectRedirectToLogin } from '../support/auth';

// Lower bound that catches a broken parse without coupling to the exact count
// (which legitimately shrinks as placeholders graduate to real features).
const MIN_PLACEHOLDER_ROUTES = 20;

/**
 * S11 (LRA-173): placeholder routes smoke. Every "coming soon" destination maps
 * to the shared PlaceholderController. The route list is parsed from the source
 * of truth (config/routes/placeholders.yaml) so the suite tracks stubs being
 * added or retired automatically.
 */
interface PlaceholderRoute {
  path: string;
  sectionTitle: string;
}

interface PlaceholderDefinition {
  path?: string;
  defaults?: { sectionTitle?: string };
}

function placeholderRoutes(): PlaceholderRoute[] {
  const source = fs.readFileSync(
    path.join(process.cwd(), 'config/routes/placeholders.yaml'),
    'utf8',
  );
  // JSON_SCHEMA parses only standard JSON-compatible types — appropriate for a
  // Symfony routing file and free of any custom-tag surprises.
  const document: unknown = yaml.load(source, { schema: yaml.JSON_SCHEMA });
  if (typeof document !== 'object' || document === null) {
    throw new Error('config/routes/placeholders.yaml did not parse to an object');
  }

  const routes: PlaceholderRoute[] = [];
  for (const value of Object.values(document)) {
    const definition = value as PlaceholderDefinition;
    if (typeof definition?.path === 'string' && typeof definition.defaults?.sectionTitle === 'string') {
      routes.push({ path: definition.path, sectionTitle: definition.defaults.sectionTitle });
    }
  }
  if (routes.length === 0) {
    throw new Error('No placeholder routes parsed from config/routes/placeholders.yaml');
  }
  return routes;
}

const ROUTES = placeholderRoutes();
const [FIRST_ROUTE] = ROUTES;

test.describe('placeholder routes', () => {
  test('the placeholder route list parsed from source is substantial', () => {
    expect(ROUTES.length).toBeGreaterThan(MIN_PLACEHOLDER_ROUTES);
  });

  for (const route of ROUTES) {
    test(`${route.path} renders the coming-soon stub`, async ({ page }) => {
      const response = await page.goto(route.path);

      expect(response?.status()).toBe(200);
      await expect(page.getByText('Coming soon').first()).toBeVisible();
      await expect(page.getByRole('main')).toContainText(route.sectionTitle);
      await expect(page.getByRole('navigation', { name: 'Main navigation' })).toBeVisible();
    });
  }
});

test.describe('placeholder access control', () => {
  test.use({ storageState: ANON_STATE });

  test('a placeholder route redirects anonymous users to login', async ({ page }) => {
    await expectRedirectToLogin(page, FIRST_ROUTE.path);
  });
});
