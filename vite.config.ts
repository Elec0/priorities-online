import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';

const appUrlPlugin = {
  name: 'app-url',
  configureServer(server: { httpServer: { once: (event: string, cb: () => void) => void } }) {
    server.httpServer?.once('listening', () => {
      console.log('\n  \x1b[1m\x1b[36mApp running at:\x1b[0m \x1b[4mhttp://localhost:8000/priorities/\x1b[0m\n');
    });
  },
};

export default defineConfig({
  plugins: [react(), appUrlPlugin],
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./tests/ui/setup.ts'],
    include: ['tests/ui/**/*.{test,spec}.{ts,tsx}'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'html'],
      include: ['priorities/assets/js/**/*.{ts,tsx}'],
      exclude: ['priorities/assets/js/dist/**'],
    },
  },
  build: {
    rollupOptions: {
      input: {
        index: resolve(__dirname, 'priorities/assets/js/index.tsx'),
        lobby: resolve(__dirname, 'priorities/assets/js/lobby.tsx'),
        game: resolve(__dirname, 'priorities/assets/js/game.tsx'),
      },
      output: {
        entryFileNames: '[name].js',
        chunkFileNames: '[name]-[hash].js',
        assetFileNames: '[name].[ext]',
        dir: 'priorities/assets/js/dist',
      },
    },
  },
  server: {
    proxy: {
      '/priorities/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
});
