import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';

export default defineConfig({
  plugins: [react()],
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
