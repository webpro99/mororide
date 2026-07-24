import AsyncStorage from '@react-native-async-storage/async-storage';
import { Platform } from 'react-native';
import { API_BASE_URL, DEMO_ACCOUNTS, DemoRole } from './config';
import {
  ApiEnvelope,
  AppNotification,
  Catalog,
  ChatMessage,
  ConnectStatus,
  Dashboard,
  DriverDocument,
  DriverDocuments,
  DriverConversation,
  FareEstimate,
  DriverProfile,
  Order,
  OrderOffer,
  PaymentIntentResponse,
  Rating,
  RidePaymentIntent,
  RideConversation,
  Transaction,
  User,
  Wallet,
} from './types';

const TOKEN_KEY = 'mororide.mobile.token';
const ROLE_KEY = 'mororide.mobile.role';
const API_BASE_KEY = 'mororide.mobile.apibase';
// Docker Desktop on Windows can take longer to warm Laravel/Postgres after an
// idle period. Keep the UI responsive, but do not abort valid auth/order calls
// during that local warm-up.
const REQUEST_TIMEOUT_MS = 30000;

let cachedBase: string | null = null;

/** Resolve the API base URL: an in-app override wins over the build-time env. */
export async function getApiBaseUrl(): Promise<string> {
  if (cachedBase) return cachedBase;
  const override = await AsyncStorage.getItem(API_BASE_KEY);
  const resolved = override && override.trim() ? override.trim() : API_BASE_URL;
  cachedBase = resolved;
  return resolved;
}

/** Save an in-app backend URL (so an IP change never requires a rebuild). */
export async function setApiBaseUrl(url: string): Promise<string> {
  let value = url.trim().replace(/\/+$/, '');
  if (!/\/api$/i.test(value)) value += '/api';
  await AsyncStorage.setItem(API_BASE_KEY, value);
  cachedBase = value;
  return value;
}

/** Host of the resolved API base — used so realtime follows the same server. */
export function resolvedApiHost(): string | null {
  const base = cachedBase ?? API_BASE_URL;
  const match = base.match(/^[a-z]+:\/\/([^:/]+)/i);
  return match ? match[1] : null;
}

/** Resolved backend origin (base without the trailing /api). */
export function resolvedBackendBaseUrl(): string {
  const base = cachedBase ?? API_BASE_URL;
  return base.replace(/\/api\/?$/i, '');
}

/** fetch() with an abort timeout so an unreachable backend fails fast. */
async function fetchWithTimeout(url: string, init: RequestInit, timeout = REQUEST_TIMEOUT_MS): Promise<Response> {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeout);
  try {
    return await fetch(url, { ...init, signal: controller.signal });
  } catch (error) {
    if (error instanceof Error && error.name === 'AbortError') {
      throw new Error('The server did not respond. Check the backend URL and your connection.');
    }
    throw new Error('Could not reach the server. Check the backend URL and your connection.');
  } finally {
    clearTimeout(timer);
  }
}

type RequestOptions = {
  method?: 'GET' | 'POST' | 'PATCH' | 'DELETE';
  body?: Record<string, unknown>;
  token?: string | null;
  headers?: Record<string, string>;
};

type Paged<T> = {
  data: T[];
};

function asList<T>(value: T[] | Paged<T>): T[] {
  return Array.isArray(value) ? value : value.data ?? [];
}

async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const token = options.token ?? (await AsyncStorage.getItem(TOKEN_KEY));
  const base = await getApiBaseUrl();
  const response = await fetchWithTimeout(`${base}${path}`, {
    method: options.method ?? 'GET',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...options.headers,
    },
    body: options.body ? JSON.stringify(options.body) : undefined,
  });

  const payload = (await response.json()) as ApiEnvelope<T> | { message?: string };

  if (!response.ok) {
    throw new Error(payload.message ?? `Request failed with status ${response.status}`);
  }

  return (payload as ApiEnvelope<T>).data;
}

export async function restoreSession() {
  const [token, role] = await Promise.all([AsyncStorage.getItem(TOKEN_KEY), AsyncStorage.getItem(ROLE_KEY)]);
  if (!token || !role) return null;

  const user = await request<User>('/me', { token });
  return { token, role: role as DemoRole, user };
}

export async function loginAs(role: DemoRole) {
  const account = DEMO_ACCOUNTS[role];
  const data = await request<{ token: string; user: User }>('/login', {
    method: 'POST',
    token: null,
    body: account,
  });

  await AsyncStorage.multiSet([
    [TOKEN_KEY, data.token],
    [ROLE_KEY, role],
  ]);

  return { ...data, role };
}

export async function loginUser(email: string, password: string) {
  const data = await request<{ token: string; user: User }>('/login', {
    method: 'POST',
    token: null,
    body: { email: email.trim(), password },
  });
  const role = data.user.role as DemoRole;
  await AsyncStorage.multiSet([
    [TOKEN_KEY, data.token],
    [ROLE_KEY, role],
  ]);
  return { ...data, role };
}

export async function registerUser(payload: {
  name: string;
  email: string;
  password: string;
  role: 'rider' | 'driver' | 'concierge';
  phone?: string;
}) {
  const data = await request<{ token: string; user: User }>('/register', {
    method: 'POST',
    token: null,
    body: payload,
  });

  await AsyncStorage.multiSet([
    [TOKEN_KEY, data.token],
    [ROLE_KEY, payload.role],
  ]);

  return { ...data, role: payload.role };
}

export async function logout() {
  await AsyncStorage.multiRemove([TOKEN_KEY, ROLE_KEY]);
}

export function getAccessToken() {
  return AsyncStorage.getItem(TOKEN_KEY);
}

/** A short, transport-safe idempotency key for payment intents. */
export function idempotencyKey(prefix = 'moro'): string {
  return `${prefix}-${Date.now()}-${Math.random().toString(36).slice(2, 12)}`;
}

export function registerDeviceToken(token: string, platform = 'expo') {
  return request<{ id: number; platform: string }>('/device-tokens', {
    method: 'POST',
    body: { token, platform },
  });
}

export function unregisterDeviceToken(token: string) {
  return request<null>('/device-tokens', {
    method: 'DELETE',
    body: { token },
  });
}

export function getCatalog() {
  return request<Catalog>('/catalog', { token: null });
}

export function getNotifications() {
  return request<AppNotification[]>('/notifications');
}

export function markNotificationRead(notificationId: number) {
  return request<AppNotification>(`/notifications/${notificationId}/read`, { method: 'POST' });
}

export function createRiderOrder(payload?: {
  city_id?: number;
  pickup_address?: string;
  pickup_lat?: number;
  pickup_lng?: number;
  dropoff_address?: string;
  dropoff_lat?: number;
  dropoff_lng?: number;
  distance_km?: number;
  eta_min?: number;
  pax?: number;
  luggage?: number;
  offered_fare?: number;
  payment_method?: 'cash' | 'card';
  vehicle_type?: string;
}) {
  return request<Order>('/rider/orders', {
    method: 'POST',
    body: {
      city_id: payload?.city_id ?? 2,
      pickup_address: payload?.pickup_address ?? 'Jemaa El Fnaa, Marrakech',
      pickup_lat: payload?.pickup_lat,
      pickup_lng: payload?.pickup_lng,
      dropoff_address: payload?.dropoff_address ?? 'Essaouira Medina',
      dropoff_lat: payload?.dropoff_lat,
      dropoff_lng: payload?.dropoff_lng,
      distance_km: payload?.distance_km ?? 175,
      eta_min: payload?.eta_min ?? 160,
      pax: payload?.pax ?? 2,
      luggage: payload?.luggage ?? 0,
      offered_fare: payload?.offered_fare ?? 620,
      payment_method: payload?.payment_method ?? 'cash',
      vehicle_type: payload?.vehicle_type ?? 'sedan',
    },
  });
}

export function createConciergeOrder(payload: {
  city_id: number;
  pickup_address: string;
  dropoff_address: string;
  distance_km: number;
  eta_min: number;
  pax?: number;
  luggage?: number;
  offered_fare?: number;
  payment_method?: 'cash' | 'card';
  vehicle_type?: string;
  hotel_name?: string;
  guest_name?: string;
  note?: string;
}) {
  return request<Order>('/concierge/orders', {
    method: 'POST',
    body: {
      pax: 2,
      payment_method: 'cash',
      vehicle_type: 'sedan',
      ...payload,
    },
  });
}

export function getConciergeOrder(orderId: number) {
  return request<Order>(`/concierge/orders/${orderId}`);
}

export function getConciergeOffers(orderId: number) {
  return request<OrderOffer[]>(`/concierge/orders/${orderId}/offers`);
}

export function chooseConciergeOffer(orderId: number, offerId: number) {
  return request<Order>(`/concierge/orders/${orderId}/choose-driver`, {
    method: 'POST',
    body: { offer_id: offerId },
  });
}

export function cancelConciergeOrder(orderId: number, reason = 'Cancelled by concierge') {
  return request<Order>(`/concierge/orders/${orderId}/cancel`, {
    method: 'POST',
    body: { reason },
  });
}

export function rateConciergeOrder(orderId: number, score: number, comment?: string) {
  return request<Rating>(`/concierge/orders/${orderId}/rating`, {
    method: 'POST',
    body: { score, ...(comment ? { comment } : {}) },
  });
}

// Driver points top-up. The backend requires an Idempotency-Key header and
// only credits points once its signed webhook confirms the payment.
export function createPointsTopupIntent(amount: number, key: string) {
  return request<PaymentIntentResponse>('/driver/payments/points/intent', {
    method: 'POST',
    body: { amount },
    headers: { 'Idempotency-Key': key },
  });
}

export function getPointsPurchaseConfig() {
  return request<import('./types').PointsPurchaseConfig>('/driver/payments/points/config');
}

export function getConnectStatus() {
  return request<ConnectStatus>('/driver/payments/connect/status');
}

export function createConnectOnboarding() {
  return request<{ account: ConnectStatus; onboarding_url: string; expires_at?: number | null }>(
    '/driver/payments/connect/onboarding',
    { method: 'POST' },
  );
}

export function getRiderOrders() {
  return request<Order[] | Paged<Order>>('/rider/orders').then(asList);
}

export function getRiderHistory() {
  return request<Order[] | Paged<Order>>('/rider/history').then(asList);
}

export function getRiderConversations() {
  return request<RideConversation[] | Paged<RideConversation>>('/rider/conversations').then(asList);
}

export function estimateFare(payload: { distance_km: number; eta_min: number; pax?: number; vehicle_type?: string }) {
  return request<FareEstimate>('/fares/estimate', { method: 'POST', body: payload });
}

export function getRiderOrder(orderId: number) {
  return request<Order>(`/rider/orders/${orderId}`);
}

export function getRiderOffers(orderId: number) {
  return request<OrderOffer[]>(`/rider/orders/${orderId}/offers`);
}

export function getVoiceCallToken(orderId: number, notify = true) {
  return request<{ server_url: string; participant_token: string; room_name: string; order_id: number }>(
    `/orders/${orderId}/voice-call/token`,
    { method: 'POST', body: { notify } },
  );
}

export function chooseRiderOffer(orderId: number, offerId: number) {
  return request<Order>(`/rider/orders/${orderId}/choose-driver`, {
    method: 'POST',
    body: { offer_id: offerId },
  });
}

export function cancelRiderOrder(orderId: number, reason = 'Cancelled by rider') {
  return request<Order>(`/rider/orders/${orderId}/cancel`, {
    method: 'POST',
    body: { reason },
  });
}

export function rateRiderOrder(orderId: number, score: number, comment?: string) {
  return request<Rating>(`/rider/orders/${orderId}/rating`, {
    method: 'POST',
    body: { score, ...(comment ? { comment } : {}) },
  });
}

export function getOrderMessages(orderId: number) {
  return request<{ order_id: number; messages: ChatMessage[] }>(`/orders/${orderId}/messages`)
    .then((data) => data.messages);
}

export function sendOrderMessage(orderId: number, text: string) {
  return request<ChatMessage>(`/orders/${orderId}/messages`, {
    method: 'POST',
    body: { text },
  });
}

export function createRidePaymentIntent(orderId: number) {
  return request<RidePaymentIntent>(`/payments/orders/${orderId}/intent`, { method: 'POST' });
}

// Multipart image attachment for the in-ride chat — cannot go through the JSON
// `request()` helper.
export async function sendOrderImage(
  orderId: number,
  file: { uri: string; name: string; mimeType?: string },
  text?: string,
): Promise<ChatMessage> {
  const token = await AsyncStorage.getItem(TOKEN_KEY);
  const form = new FormData();
  if (text && text.trim()) {
    form.append('text', text.trim());
  }

  if (Platform.OS === 'web') {
    const blob = await (await fetch(file.uri)).blob();
    form.append('image', blob, file.name);
  } else {
    form.append('image', { uri: file.uri, name: file.name, type: file.mimeType ?? 'image/jpeg' } as unknown as Blob);
  }

  const base = await getApiBaseUrl();
  const response = await fetch(`${base}/orders/${orderId}/messages/image`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      // No Content-Type — fetch sets the multipart boundary.
    },
    body: form,
  });

  const payload = (await response.json()) as ApiEnvelope<ChatMessage> | { message?: string; errors?: Record<string, string[]> };
  if (!response.ok) {
    const withErrors = payload as { message?: string; errors?: Record<string, string[]> };
    const detail = withErrors.errors ? Object.values(withErrors.errors).flat().join(' ') : undefined;
    throw new Error(detail ?? withErrors.message ?? 'Upload failed');
  }
  return (payload as ApiEnvelope<ChatMessage>).data;
}

export function getConciergeOrders() {
  return request<Order[] | Paged<Order>>('/concierge/orders').then(asList);
}

export function driverOnline(payload?: { city_id?: number; current_lat?: number; current_lng?: number }) {
  const body =
    payload?.city_id != null && payload.current_lat != null && payload.current_lng != null
      ? { city_id: payload.city_id, current_lat: payload.current_lat, current_lng: payload.current_lng }
      : undefined;
  return request<DriverProfile>('/driver/online', { method: 'POST', body });
}

export function driverOffline() {
  return request<DriverProfile>('/driver/offline', { method: 'POST' });
}

export function updateDriverLocation(payload: { city_id: number; lat: number; lng: number }) {
  return request<{ location_id: number; lat: number; lng: number }>('/driver/location', {
    method: 'POST',
    body: {
      city_id: payload.city_id,
      lat: payload.lat,
      lng: payload.lng,
      reported_at: new Date().toISOString(),
    },
  });
}

export function getDriverOrders() {
  return request<Order[] | Paged<Order>>('/driver/orders').then(asList);
}

export function getDriverCurrentOrder() {
  return request<Order | null>('/driver/current-order');
}

export function getDriverHistory() {
  return request<Order[] | Paged<Order>>('/driver/history').then(asList);
}

export function getDriverConversations() {
  return request<DriverConversation[] | Paged<DriverConversation>>('/driver/conversations').then(asList);
}

export function getDriverOrder(orderId: number) {
  return request<Order>(`/driver/orders/${orderId}`);
}

export function acceptOrder(orderId: number) {
  return request<Order>(`/driver/orders/${orderId}/accept`, { method: 'POST' });
}

export function counterOrder(orderId: number, amount: number, message?: string) {
  return request<OrderOffer>(`/driver/orders/${orderId}/counter`, {
    method: 'POST',
    body: { amount, ...(message ? { message } : {}) },
  });
}

export function declineOrder(orderId: number, message?: string) {
  return request<OrderOffer>(`/driver/orders/${orderId}/decline`, {
    method: 'POST',
    body: message ? { message } : {},
  });
}

export function markOrder(orderId: number, action: 'arrived' | 'start' | 'complete') {
  return request<Order>(`/driver/orders/${orderId}/${action}`, { method: 'POST' });
}

export function getWallet() {
  return request<Wallet>('/driver/wallet');
}

export function getDriverDocuments() {
  return request<DriverDocuments>('/driver/documents');
}

// Multipart upload — cannot go through the JSON `request()` helper.
export async function uploadDriverDocument(
  type: string,
  file: { uri: string; name: string; mimeType?: string },
): Promise<DriverDocument> {
  const token = await AsyncStorage.getItem(TOKEN_KEY);
  const form = new FormData();
  form.append('type', type);

  if (Platform.OS === 'web') {
    const blob = await (await fetch(file.uri)).blob();
    form.append('file', blob, file.name);
  } else {
    // React Native file part
    form.append('file', { uri: file.uri, name: file.name, type: file.mimeType ?? 'image/jpeg' } as unknown as Blob);
  }

  const base = await getApiBaseUrl();
  const response = await fetch(`${base}/driver/documents`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      // Intentionally NO Content-Type — fetch sets the multipart boundary.
    },
    body: form,
  });

  const payload = (await response.json()) as ApiEnvelope<DriverDocument> | { message?: string; errors?: Record<string, string[]> };
  if (!response.ok) {
    const withErrors = payload as { message?: string; errors?: Record<string, string[]> };
    const detail = withErrors.errors ? Object.values(withErrors.errors).flat().join(' ') : undefined;
    throw new Error(detail ?? withErrors.message ?? 'Upload failed');
  }
  return (payload as ApiEnvelope<DriverDocument>).data;
}

export function getAdminDashboard() {
  return request<Dashboard>('/admin/dashboard');
}

export function getAdminOrders() {
  return request<Order[] | Paged<Order>>('/admin/orders').then(asList);
}

export function getAdminTransactions() {
  return request<Transaction[] | Paged<Transaction>>('/admin/transactions').then(asList);
}
