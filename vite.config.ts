import laravel from 'laravel-vite-plugin';
import tailwindcss from "@tailwindcss/vite";
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/app.ts',
                'resources/js/home.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
