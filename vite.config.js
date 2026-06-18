import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

// Watchtower ships a pre-compiled, self-contained SPA. The PHP controller
// serves dist/app.js and dist/app.css by exact name, so we disable hashing
// and force all CSS into a single file.
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
    },
  },
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    cssCodeSplit: false,
    // Keep the bundle small and predictable; no manifest needed.
    manifest: false,
    rollupOptions: {
      input: fileURLToPath(new URL('./resources/js/app.js', import.meta.url)),
      output: {
        entryFileNames: 'app.js',
        chunkFileNames: 'app.js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'app.css';
          }
          return 'assets/[name][extname]';
        },
      },
    },
  },
});
