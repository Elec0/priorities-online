import { defineConfig, devices } from '@playwright/test';

/**
 * E2E tests run against the Docker Compose stack on localhost:8000.
 * Start the stack before running: docker compose up -d
 */
export default defineConfig({
  testDir: './tests/e2e',

  // Tests share a real database — run sequentially to avoid interference.
  fullyParallel: false,
  workers: 1,
  retries: 0,

  reporter: 'list',

  use: {
    baseURL: 'http://localhost:8000',
    // Capture traces on first retry to aid debugging.
    trace: 'on-first-retry',
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
