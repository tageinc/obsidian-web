import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
    plugins: [vue()],
    publicDir: false,
    base: '/build/',
    resolve: { alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) } },
    server: {
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
        cors: { origin: ['http://localhost:8080', 'http://127.0.0.1:8080'] },
    },
    build: {
        outDir: 'public/build',
        emptyOutDir: true,
        manifest: 'manifest.json',
        sourcemap: false,
        rolldownOptions: { input: 'resources/js/entries/app.js' },
    },
});
