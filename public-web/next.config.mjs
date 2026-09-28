import path from 'node:path';
import { fileURLToPath } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));

/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
  // Standalone keeps the production image small and self-contained for a VPS
  // deploy behind an nginx split (Plan_UI.md Part II §10).
  output: 'standalone',
  poweredByHeader: false,
  // Pin the workspace root to this directory. Without it, Next infers the root
  // from the nearest lockfile and picks up the *parent* Laravel app's
  // postcss.config.js — an ESM/Tailwind config — which its loader cannot read,
  // and the build fails inside next/font. This app uses plain CSS on purpose,
  // so it must not inherit the console's build pipeline.
  outputFileTracingRoot: dir,
  async headers() {
    return [
      {
        source: '/:path*',
        headers: [
          { key: 'X-Content-Type-Options', value: 'nosniff' },
          { key: 'X-Frame-Options', value: 'SAMEORIGIN' },
          { key: 'Referrer-Policy', value: 'no-referrer' },
        ],
      },
    ];
  },
};

export default nextConfig;
