import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
    plugins: [vue()],
    build: {
        outDir: 'public',
        emptyOutDir: true,
        rollupOptions: {
            input: {
                app: resolve(__dirname, 'resources/js/app.js'),
            },
            output: {
                entryFileNames: '[name].js',
                chunkFileNames: '[name]-[hash].js',
                assetFileNames: '[name][extname]',
            },
        },
    },
})
