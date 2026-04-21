import { type Browser, type BrowserContext, type Page } from '@playwright/test';

/**
 * Creates a new browser context, navigates to the index page, and creates a
 * lobby as `name`. Returns the context, page, and 6-char lobby code.
 */
export async function createLobby(
  browser: Browser,
  name: string,
): Promise<{ context: BrowserContext; page: Page; code: string }> {
  const context = await browser.newContext();
  const page = await context.newPage();

  await page.goto('/priorities/');
  await page.getByLabel('Your name').fill(name);
  await page.locator('.lobby-form').getByRole('button', { name: 'Create Lobby' }).click();
  await page.waitForURL(/\/priorities\/lobby\.php\?lobby_id=\d+/);

  const codeText = await page.locator('.lobby-code strong').textContent();
  return { context, page, code: codeText!.trim() };
}

/**
 * Creates a new browser context and joins an existing lobby `code` as `name`.
 * Returns the context and page, already on the lobby page.
 */
export async function joinLobby(
  browser: Browser,
  name: string,
  code: string,
): Promise<{ context: BrowserContext; page: Page }> {
  const context = await browser.newContext();
  const page = await context.newPage();

  await page.goto('/priorities/');
  await page.locator('.tabs').getByRole('button', { name: 'Join Lobby' }).click();
  await page.getByLabel('Your name').fill(name);
  await page.getByPlaceholder('ABCDEF').fill(code);
  await page.locator('.lobby-form').getByRole('button', { name: 'Join Lobby' }).click();
  await page.waitForURL(/\/priorities\/lobby\.php\?lobby_id=\d+/);

  return { context, page };
}
