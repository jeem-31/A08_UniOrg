import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        environment: 'jsdom',
        include: [
            'resources/js/**/*.test.js',
            'tests/js/**/*.test.js',
        ],
        setupFiles: ['tests/js/setup.js'],
        clearMocks: true,
        restoreMocks: true,
    },
});
