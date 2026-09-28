/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
  // Standalone keeps the production image small and self-contained for a VPS
  // deploy behind an nginx split (Plan_UI.md Part II §10).
  output: 'standalone',
  poweredByHeader: false,
  // Headers that belong to the edge, not the app. The public surface is
  // server-rendered, so the CSP is strict — there is no inline script to allow.
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
