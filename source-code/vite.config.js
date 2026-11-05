import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/document-library.css',
                'resources/js/app.js',
                'resources/js/osas-notifications.js',
                'resources/js/document-library.js',
            ],
            refresh: true,
        }),
    ],
});
