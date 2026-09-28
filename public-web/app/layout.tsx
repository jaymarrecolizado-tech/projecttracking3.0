import type { Metadata } from 'next';
import { Figtree } from 'next/font/google';

import './globals.css';

/**
 * Figtree, per Plan_UI.md Part I §1 ("has character, not Inter"). The main app
 * already ships it; the public surface keeps the same face so the console and
 * the survey are visibly one product.
 */
const figtree = Figtree({
  subsets: ['latin'],
  display: 'swap',
  variable: '--font-figtree',
});

export const metadata: Metadata = {
  title: {
    default: 'Free WiFi Feedback',
    template: '%s — Free WiFi Feedback',
  },
  // A feedback form has nothing to index, and this page is reachable only from
  // a QR code printed at a site.
  robots: { index: false, follow: false },
  referrer: 'no-referrer',
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en" className={figtree.variable}>
      <body style={{ fontFamily: 'var(--font-figtree), system-ui, sans-serif' }}>{children}</body>
    </html>
  );
}
