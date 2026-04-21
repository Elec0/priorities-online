import { test, expect, type Page } from '@playwright/test';
import { createLobby, joinLobby } from './helpers';

/**
 * Sets up a full 3-player game and returns all three pages already on the
 * game page with the initial round visible.
 */
async function startThreePlayerGame(browser: Parameters<typeof createLobby>[0]) {
  const { context: ctx1, page: page1, code } = await createLobby(browser, 'Alice');
  const { context: ctx2, page: page2 } = await joinLobby(browser, 'Bob', code);
  const { context: ctx3, page: page3 } = await joinLobby(browser, 'Carol', code);

  await expect(page1.locator('.player-list .player-item')).toHaveCount(3);
  await page1.locator('.start-btn').click();

  const pages: Page[] = [page1, page2, page3];
  await Promise.all(pages.map(p => p.waitForURL(/\/priorities\/game\.php/)));
  // Wait for SSE to deliver game state so the round header appears.
  await Promise.all(pages.map(p => expect(p.locator('.round-info')).toBeVisible({ timeout: 15_000 })));

  return { pages, contexts: [ctx1, ctx2, ctx3] };
}

test.describe('Game flow', () => {
  test('game page shows round info and player list for all players', async ({ browser }) => {
    const { pages, contexts } = await startThreePlayerGame(browser);
    try {
      for (const p of pages) {
        await expect(p.locator('.round-info')).toContainText('Round 1');
        await expect(p.locator('.player-list .player-item')).toHaveCount(3);
      }
    } finally {
      await Promise.all(contexts.map(c => c.close()));
    }
  });

  test('non-target players see the "secretly ranking" message during ranking phase', async ({ browser }) => {
    const { pages, contexts } = await startThreePlayerGame(browser);
    try {
      // Exactly one page shows the ranking form; the others wait.
      const targetPages = await Promise.all(
        pages.map(p => p.locator('button.action-btn', { hasText: 'Submit My Ranking' }).isVisible()),
      );
      const targetCount = targetPages.filter(Boolean).length;
      expect(targetCount).toBe(1);

      const nonTargetCount = targetPages.filter(v => !v).length;
      expect(nonTargetCount).toBe(2);

      for (let i = 0; i < pages.length; i++) {
        if (!targetPages[i]) {
          await expect(pages[i].locator('.ranking-phase .phase-label')).toContainText('secretly ranking');
        }
      }
    } finally {
      await Promise.all(contexts.map(c => c.close()));
    }
  });

  test('completes a full round: ranking → guessing → revealed', async ({ browser }) => {
    const { pages, contexts } = await startThreePlayerGame(browser);
    try {
      // ── Ranking phase ────────────────────────────────────────────────────
      // Find the target (only page with the submit button).
      let targetPage: Page | undefined;
      for (const p of pages) {
        if (await p.locator('button.action-btn', { hasText: 'Submit My Ranking' }).isVisible()) {
          targetPage = p;
          break;
        }
      }
      expect(targetPage).toBeDefined();

      // Target submits the cards in their default (dealt) order.
      await targetPage!.locator('button.action-btn', { hasText: 'Submit My Ranking' }).click();

      // ── Guessing phase ───────────────────────────────────────────────────
      await Promise.all(
        pages.map(p => expect(p.locator('.guessing-phase')).toBeVisible({ timeout: 15_000 })),
      );

      // Target sees the "everyone is guessing" message, not a lock-in button.
      await expect(targetPage!.locator('.guessing-phase .phase-label')).toContainText('guessing your ranking');
      await expect(targetPage!.locator('button.lock-btn')).not.toBeVisible();

      // Find the final decider (non-target with the lock-in button).
      let finalDeciderPage: Page | undefined;
      for (const p of pages) {
        if (p !== targetPage && await p.locator('button.lock-btn').isVisible()) {
          finalDeciderPage = p;
          break;
        }
      }
      expect(finalDeciderPage).toBeDefined();

      await expect(finalDeciderPage!.locator('.fd-hint')).toBeVisible();

      // Final decider locks in the current group ranking.
      await finalDeciderPage!.locator('button.lock-btn').click();

      // ── Revealed phase ───────────────────────────────────────────────────
      await Promise.all(
        pages.map(p => expect(p.locator('.revealed-phase')).toBeVisible({ timeout: 15_000 })),
      );

      // Every player sees the score summary (e.g. "2/5 correct!").
      for (const p of pages) {
        await expect(p.locator('.revealed-phase .phase-label')).toContainText(/\d+\/\d+ correct/);
      }
    } finally {
      await Promise.all(contexts.map(c => c.close()));
    }
  });

  test('cards are randomized: target and final decider both accept initial order without changes, should not be 5/5 correct', async ({ browser }) => {
    const { pages, contexts } = await startThreePlayerGame(browser);
    try {
      // ── Ranking phase ────────────────────────────────────────────────────
      // Find the target (only page with the submit button).
      let targetPage: Page | undefined;
      for (const p of pages) {
        if (await p.locator('button.action-btn', { hasText: 'Submit My Ranking' }).isVisible()) {
          targetPage = p;
          break;
        }
      }
      expect(targetPage).toBeDefined();

      // Target submits WITHOUT changing the order (accepts initial random order).
      await targetPage!.locator('button.action-btn', { hasText: 'Submit My Ranking' }).click();

      // ── Guessing phase ───────────────────────────────────────────────────
      await Promise.all(
        pages.map(p => expect(p.locator('.guessing-phase')).toBeVisible({ timeout: 15_000 })),
      );

      // Find the final decider (non-target with the lock-in button).
      let finalDeciderPage: Page | undefined;
      for (const p of pages) {
        if (p !== targetPage && await p.locator('button.lock-btn').isVisible()) {
          finalDeciderPage = p;
          break;
        }
      }
      expect(finalDeciderPage).toBeDefined();

      // Final decider locks in WITHOUT changing the order (accepts initial random order).
      await finalDeciderPage!.locator('button.lock-btn').click();

      // ── Revealed phase ───────────────────────────────────────────────────
      await Promise.all(
        pages.map(p => expect(p.locator('.revealed-phase')).toBeVisible({ timeout: 15_000 })),
      );

      // Verify that the score is NOT 5/5 correct (meaning cards were actually randomized).
      for (const p of pages) {
        const scoreLabel = p.locator('.revealed-phase .phase-label');
        await expect(scoreLabel).toContainText(/\d+\/\d+ correct/);
        // Extract the score to verify it's not 5/5
        const text = await scoreLabel.textContent();
        expect(text).not.toContain('5/5');
      }
    } finally {
      await Promise.all(contexts.map(c => c.close()));
    }
  });
});
