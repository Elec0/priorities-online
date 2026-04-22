import { test, expect } from '@playwright/test';
import { createLobby, joinLobby } from './helpers';

test.describe('Lobby', () => {
  test('host sees lobby code and their own name in the player list', async ({ browser }) => {
    const { context, page } = await createLobby(browser, 'Alice');
    try {
      await expect(page.locator('.lobby-code strong')).toHaveText(/^[A-Z0-9]{6}$/);
      await expect(page.locator('.player-list .player-item')).toHaveCount(1);
      await expect(page.locator('.player-list .player-item').first()).toContainText('Alice');
    } finally {
      await context.close();
    }
  });

  test('start button is disabled with fewer than 3 players; non-host sees waiting message', async ({ browser }) => {
    const { context: ctx1, page: page1, code } = await createLobby(browser, 'Alice');
    const { context: ctx2, page: page2 } = await joinLobby(browser, 'Bob', code);
    try {
      // Alice's view updates via SSE when Bob joins.
      await expect(page1.locator('.player-list .player-item')).toHaveCount(2);
      await expect(page1.locator('.start-btn')).toBeDisabled();

      // Bob is not the host and should see the waiting message.
      await expect(page2.locator('.waiting-msg')).toBeVisible();
    } finally {
      await ctx1.close();
      await ctx2.close();
    }
  });

  test('host can kick a non-host player', async ({ browser }) => {
    const { context: ctx1, page: page1, code } = await createLobby(browser, 'Alice');
    const { context: ctx2 } = await joinLobby(browser, 'Bob', code);
    try {
      await expect(page1.locator('.player-list .player-item')).toHaveCount(2);

      await page1.locator('.kick-btn').first().click();

      await expect(page1.locator('.player-list .player-item')).toHaveCount(1);
    } finally {
      await ctx1.close();
      await ctx2.close();
    }
  });

  test('host can start the game once 3 players are present', async ({ browser }) => {
    const { context: ctx1, page: page1, code } = await createLobby(browser, 'Alice');
    const { context: ctx2, page: page2 } = await joinLobby(browser, 'Bob', code);
    const { context: ctx3, page: page3 } = await joinLobby(browser, 'Carol', code);
    try {
      // Wait for Alice's lobby to reflect all 3 players.
      await expect(page1.locator('.player-list .player-item')).toHaveCount(3);
      await expect(page1.locator('.start-btn')).toBeEnabled();

      await page1.locator('.start-btn').click();

      // All three players should be redirected to game.php.
      await Promise.all([
        page1.waitForURL(/\/priorities\/game\.php/),
        page2.waitForURL(/\/priorities\/game\.php/),
        page3.waitForURL(/\/priorities\/game\.php/),
      ]);

      for (const p of [page1, page2, page3]) {
        await expect(p.locator('.game-page')).toBeVisible({ timeout: 10_000 });
      }
    } finally {
      await ctx1.close();
      await ctx2.close();
      await ctx3.close();
    }
  });

  test('kicked player is redirected to home and host can still start with remaining 3 players', async ({ browser }) => {
    const { context: hostCtx, page: hostPage, code } = await createLobby(browser, 'Alice');
    const { context: bobCtx, page: bobPage } = await joinLobby(browser, 'Bob', code);
    const { context: carolCtx, page: carolPage } = await joinLobby(browser, 'Carol', code);
    const { context: daveCtx, page: davePage } = await joinLobby(browser, 'Dave', code);

    try {
      // Wait for host to see all 4 players, then kick Bob.
      await expect(hostPage.locator('.player-list .player-item')).toHaveCount(4);
      await hostPage.locator('.player-item', { hasText: 'Bob' }).locator('.kick-btn').click();

      // Host should now see 3 players and still be allowed to start.
      await expect(hostPage.locator('.player-list .player-item')).toHaveCount(3);
      await expect(hostPage.locator('.start-btn')).toBeEnabled();

      // Bob should be redirected out of the lobby.
      await bobPage.waitForURL(/\/priorities\/$/);

      await hostPage.locator('.start-btn').click();

      // Remaining active players should enter the game.
      await Promise.all([
        hostPage.waitForURL(/\/priorities\/game\.php/),
        carolPage.waitForURL(/\/priorities\/game\.php/),
        davePage.waitForURL(/\/priorities\/game\.php/),
      ]);

      for (const p of [hostPage, carolPage, davePage]) {
        await expect(p.locator('.game-page')).toBeVisible({ timeout: 10_000 });
      }
    } finally {
      await hostCtx.close();
      await bobCtx.close();
      await carolCtx.close();
      await daveCtx.close();
    }
  });
});
