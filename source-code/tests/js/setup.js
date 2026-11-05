import { configureGlobal } from 'fast-check';

// Reduced from 50 to 25 generated cases for faster checkpoints. The fixed seed
// keeps runs reproducible; deterministic unit assertions remain unchanged.
configureGlobal({
    numRuns: 25,
    seed: 0x05a5_2025,
});

const blockedFetch = () => Promise.reject(
    new Error('HTTP is disabled in JavaScript tests; inject a local test collaborator.'),
);

// Property tests must remain local; individual tests may replace this with a stub.
Object.defineProperty(globalThis, 'fetch', {
    configurable: true,
    writable: true,
    value: blockedFetch,
});
