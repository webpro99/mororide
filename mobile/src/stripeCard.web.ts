export type CardResult = { status: 'completed' | 'canceled' | 'failed'; message?: string };

/**
 * Web fallback — the native Stripe PaymentSheet is not available in the browser.
 * Metro resolves this file for the web platform so `@stripe/stripe-react-native`
 * never enters the web bundle.
 */
export async function payWithCardSheet(_opts: {
  clientSecret: string;
  publishableKey: string;
  label?: string;
}): Promise<CardResult> {
  return { status: 'failed', message: 'Card payment is only available in the mobile app.' };
}
