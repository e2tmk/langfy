import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { viteStaticCopy } from 'vite-plugin-static-copy';

export default defineConfig({
    plugins: [
        laravel({
            buildDirectory: '../resources/dist',
            input: [
                'resources/css/app.css',
            ],
            refresh: true,
        }),
        tailwindcss(),
        viteStaticCopy({
            targets: [
                {
                    src: 'vendor/tallstackui/tallstackui/dist/tallstackui-*.js',
                    dest: 'assets',
                    rename: 'tallstackui.js'
                },
                {
                    src: 'vendor/tallstackui/tallstackui/dist/tippy-*.css',
                    dest: 'assets',
                    rename: 'tallstackui.css'
                }
            ]
        })
    ],
    build: {
        rollupOptions: {
            output: {
                entryFileNames: 'assets/[name].js',
                chunkFileNames: 'assets/[name].js',
                assetFileNames: 'assets/[name].[ext]'
            }
        }
    }
})
