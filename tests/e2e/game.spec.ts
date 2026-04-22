import { test, expect, type Page } from '@playwright/test';
import { createLobby, joinLobby } from './helpers';

function makeGameOverSsePayload(hostPlayerId: number) {
  return {
    state_version: 999,
    lobby_status: 'playing' as const,
    lobby_code: 'ABCDEF',
    game_id: 1,
    game_status: 'draw' as const,
    round: {
      id: 9,
      number: 9,
      status: 'revealed' as const,
      card_ids: [1, 2, 3],
      cards: [
        { id: 1, content: 'Pizza', category: 'food', emoji: '🍕', letter: 'P' },
        { id: 2, content: 'Rain', category: 'weather', emoji: '🌧️', letter: 'R' },
        { id: 3, content: 'Ice cream', category: 'food', emoji: '🍦', letter: 'I' },
      ],
      group_ranking: [2, 1, 3],
      target_ranking: [1, 2, 3],
      result: [
        { card_id: 1, correct: false },
        { card_id: 2, correct: false },
        { card_id: 3, correct: true },
      ],
      ranking_deadline: null,
    },
    target_player: { id: hostPlayerId, name: 'Alice', turn_order: 0, is_host: true, status: 'active' as const },
    final_decider: { id: hostPlayerId + 1, name: 'Bob', turn_order: 1, is_host: false, status: 'active' as const },
    players: [
      { id: hostPlayerId, name: 'Alice', turn_order: 0, is_host: true, status: 'active' as const },
      { id: hostPlayerId + 1, name: 'Bob', turn_order: 1, is_host: false, status: 'active' as const },
      { id: hostPlayerId + 2, name: 'Carol', turn_order: 2, is_host: false, status: 'active' as const },
    ],
    player_letters: { P: 1, R: 1, I: 1, O: 1, T: 0, E: 0, S: 0 },
    game_letters: { P: 1, R: 1, I: 1, O: 1, T: 1, E: 1, S: 1 },
    chat: [],
  };
}

function makeLobbyWaitingSsePayload(hostPlayerId: number) {
  return {
    state_version: 1000,
    lobby_status: 'waiting' as const,
    lobby_code: 'ABCDEF',
    game_id: null,
    players: [
      { id: hostPlayerId, name: 'Alice', turn_order: 0, is_host: true, status: 'active' as const },
      { id: hostPlayerId + 1, name: 'Bob', turn_order: 1, is_host: false, status: 'active' as const },
      { id: hostPlayerId + 2, name: 'Carol', turn_order: 2, is_host: false, status: 'active' as const },
    ],
    chat: [],
  };
}

async function mockRestartFlowState(page: Page, hostPlayerId: number) {
  const gameOverPayload = makeGameOverSsePayload(hostPlayerId);
  const waitingPayload = makeLobbyWaitingSsePayload(hostPlayerId);
  const streamRegex = /\/priorities\/api\/stream\.php\?/;
  const restartRegex = /\/priorities\/api\/restart_game\.php/;

  let inWaitingState = false;

  await page.route(streamRegex, async route => {
    const payload = inWaitingState ? waitingPayload : gameOverPayload;
    await route.fulfill({
      status: 200,
      contentType: 'text/event-stream',
      headers: {
        'Cache-Control': 'no-cache',
        Connection: 'keep-alive',
      },
      body: `data: ${JSON.stringify(payload)}\n\n`,
    });
  });

  await page.route(restartRegex, async route => {
    inWaitingState = true;
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ success: true }),
    });
  });
}

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

  test('host can restart a finished game with the same players', async ({ browser }) => {
    const { pages, contexts } = await startThreePlayerGame(browser);
    const [hostPage] = pages;

    try {
      const hostPlayerId = parseInt((await hostPage.locator('#root').getAttribute('data-player-id')) ?? '0', 10);
      expect(hostPlayerId).toBeGreaterThan(0);

      await mockRestartFlowState(hostPage, hostPlayerId);
      await hostPage.reload();

      await expect(hostPage.locator('.game-over-screen')).toBeVisible({ timeout: 15_000 });
      await expect(hostPage.getByRole('button', { name: 'Restart with Same People' })).toBeVisible();

      await hostPage.getByRole('button', { name: 'Restart with Same People' }).click();

      await expect(hostPage).toHaveURL(/\/priorities\/lobby\.php\?lobby_id=\d+/);
      await expect(hostPage.locator('.player-list .player-item')).toHaveCount(3);
      await expect(hostPage.locator('.player-list .player-item')).toContainText(['Alice', 'Bob', 'Carol']);
    } finally {
      await Promise.all(contexts.map(c => c.close()));
    }
  });

  test('restart API rejects host while game is still active', async ({ browser }) => {
    const { pages, contexts } = await startThreePlayerGame(browser);
    const [hostPage] = pages;

    try {
      const res = await hostPage.evaluate(async () => {
        const r = await fetch('api/restart_game.php', { method: 'POST' });
        const body = await r.json();
        return { ok: r.ok, status: r.status, body };
      });

      expect(res.ok).toBe(false);
      expect(res.status).toBe(400);
      expect(String(res.body?.error ?? '')).toContain('active');
    } finally {
      await Promise.all(contexts.map(c => c.close()));
    }
  });
});
