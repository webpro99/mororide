import { initPaymentSheet, initStripe, presentPaymentSheet } from '@stripe/stripe-react-native';

export type CardResult = { status: 'completed' | 'canceled' | 'failed'; message?: string };

/**
 * Present the native Stripe PaymentSheet for a PaymentIntent created by the
 * backend. Native only — the `.web.ts` sibling replaces this on web so the
 * Stripe native module never enters the web bundle.
 */
export async function payWithCardSheet(opts: {
  clientSecret: string;
  publishableKey: string;
  label?: string;
}): Promise<CardResult> {
  await initStripe({ publishableKey: opts.publishableKey });

  const init = await initPaymentSheet({
    paymentIntentClientSecret: opts.clientSecret,
    merchantDisplayName: 'MoroRide',
    allowsDelayedPaymentMethods: false,
  });
  if (init.error) {
    return { status: 'failed', message: init.error.message };
  }

  const result = await presentPaymentSheet();
  if (result.error) {
    const canceled = String(result.error.code) === 'Canceled';
    return { status: canceled ? 'canceled' : 'failed', message: result.error.message };
  }

  return { status: 'completed' };
}
