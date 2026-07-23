import { Platform } from 'react-native';

export const API_BASE_URL =
  process.env.EXPO_PUBLIC_API_BASE_URL ??
  (Platform.OS === 'android' ? 'http://10.0.2.2:8000/api' : 'http://127.0.0.1:8000/api');

export const BACKEND_BASE_URL = API_BASE_URL.replace(/\/api\/?$/, '');
export const REVERB_APP_KEY = process.env.EXPO_PUBLIC_REVERB_APP_KEY ?? 'mororide-local-key';
export const REVERB_HOST =
  process.env.EXPO_PUBLIC_REVERB_HOST ??
  (Platform.OS === 'android' ? '10.0.2.2' : '127.0.0.1');
export const REVERB_PORT = Number(process.env.EXPO_PUBLIC_REVERB_PORT ?? 8080);
export const REVERB_SCHEME = process.env.EXPO_PUBLIC_REVERB_SCHEME ?? 'http';

export const DEMO_ACCOUNTS = {
  rider: { email: 'rider@mororide.test', password: 'password' },
  concierge: { email: 'concierge@mororide.test', password: 'password' },
  driver: { email: 'driver@mororide.test', password: 'password' },
  admin: { email: 'admin@mororide.test', password: 'password' },
} as const;

export type DemoRole = keyof typeof DEMO_ACCOUNTS;
