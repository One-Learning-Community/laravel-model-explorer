import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import prismjs from 'vite-plugin-prismjs-plus'
import { resolve } from 'path'

export default defineConfig({
    plugins: [
        tailwindcss(),
        vue(),
        prismjs({
            languages: ['php'],
            plugins: ['line-numbers'],
            theme: 'tomorrow',
            css: true,
            manual: true,
        }),
    ],
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
