import Echo from 'laravel-echo';
import Pusher from 'pusher-js/react-native';
import { resolvedApiHost, resolvedBackendBaseUrl } from './api';
import {
  BACKEND_BASE_URL,
  REVERB_APP_KEY,
  REVERB_HOST,
  REVERB_PORT,
  REVERB_SCHEME,
} from './config';

type EchoClient = InstanceType<typeof Echo>;

export function createRealtimeClient(token: string): EchoClient {
  const forceTLS = REVERB_SCHEME === 'https';
  // Follow the in-app backend URL so realtime uses the same server as the API.
  const wsHost = resolvedApiHost() ?? REVERB_HOST;
  const authEndpoint = `${resolvedBackendBaseUrl() || BACKEND_BASE_URL}/broadcasting/auth`;

  return new Echo({
    broadcaster: 'reverb',
    // Echo 2.x expects `client` to be an already-created Pusher instance.
    // Supplying the class there lets subscriptions appear to work but crashes
    // during cleanup (`disconnect is not a function`). `Pusher` is the
    // constructor option Echo instantiates with the connection config below.
    Pusher,
    key: REVERB_APP_KEY,
    wsHost,
    wsPort: REVERB_PORT,
    wssPort: REVERB_PORT,
    forceTLS,
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
    authEndpoint,
    auth: {
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
      },
    },
  });
}
