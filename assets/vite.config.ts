import { defineConfig } from 'vite';

export default defineConfig({
  root: '.',
  build: {
    outDir: '../public/ui',
    emptyOutDir: false,
    rollupOptions: {
      input: 'src/main.tsx',
      output: {
        entryFileNames: 'react-dashboard.js',
        chunkFileNames: '[name].js',
        assetFileNames: '[name][extname]',
      },
    },
  },
});
