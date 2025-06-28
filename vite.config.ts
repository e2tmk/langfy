import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            buildDirectory: '../resources/dist',
            input: [
                'resources/css/app.css',
            ],
            refresh: true,
        }),
    ],
})
