import { test, expect } from '@playwright/test';
import { createLobby } from './helpers';

test.describe('Index page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/priorities/');
  });

  test('renders the Priorities logo with Create Lobby tab active', async ({ page }) => {
    await expect(page.locator('h1.logo')).toHaveText('Priorities');
    await expect(page.locator('.tab-btn.active')).toHaveText('Create Lobby');
    await expect(page.getByLabel('Your name')).toBeVisible();
  });

  test('switches to Join Lobby tab and shows code input', async ({ page }) => {
    await page.locator('.tabs').getByRole('button', { name: 'Join Lobby' }).click();

    await expect(page.locator('.tab-btn.active')).toHaveText('Join Lobby');
    await expect(page.getByPlaceholder('ABCDEF')).toBeVisible();
  });

  test('shows an error when joining with a non-existent code', async ({ page }) => {
    await page.locator('.tabs').getByRole('button', { name: 'Join Lobby' }).click();
    await page.getByLabel('Your name').fill('Tester');
    await page.getByPlaceholder('ABCDEF').fill('ZZZZZZ');
    await page.locator('.lobby-form').getByRole('button', { name: 'Join Lobby' }).click();

    await expect(page.locator('.error-msg')).toBeVisible();
  });

  test('creating a lobby navigates to the lobby page', async ({ browser }) => {
    const { context, page } = await createLobby(browser, 'Alice');
    try {
      await expect(page.locator('h1')).toContainText('Lobby');
      await expect(page.locator('.lobby-code strong')).toHaveText(/^[A-Z0-9]{6}$/);
    } finally {
      await context.close();
    }
  });
});
