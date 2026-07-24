export type User = {
  id: number;
  name: string;
  email: string;
  role: string;
  status?: string;
  driver_profile?: DriverProfile | null;
};

export type DriverProfile = {
  id?: number;
  user_id?: number;
  vehicle_name?: string | null;
  vehicle_plate?: string | null;
  vehicle_type?: string | null;
  approval_state?: string | null;
  online_status?: boolean;
  current_lat?: number | null;
  current_lng?: number | null;
};

export type ApiEnvelope<T> = {
  success: boolean;
  message: string;
  data: T;
};

export type Order = {
  id: number;
  status: string;
  city?: string | { id: number; name: string };
  source?: string;
  pickup_address: string;
  pickup_lat?: number | null;
  pickup_lng?: number | null;
  dropoff_address: string;
  dropoff_lat?: number | null;
  dropoff_lng?: number | null;
  distance_km: number;
  eta_min: number;
  pax: number;
  offered_fare: number;
  final_fare?: number | null;
  payment_method?: string;
  assigned_driver_id?: number | null;
  created_at?: string;
  driver?: User | null;
};

export type OrderOffer = {
  id: number;
  order_id: number;
  type: 'accept' | 'counter';
  amount?: number | null;
  message?: string | null;
  status: string;
  driver: {
    id: number;
    name: string;
    vehicle?: {
      name?: string | null;
      plate?: string | null;
      type?: string | null;
    } | null;
    vehicle_photos?: Array<{ id: number; type: string; url: string }>;
  };
  created_at?: string;
};

export type Rating = {
  id: number;
  order_id: number;
  score: number;
  comment?: string | null;
};

export type ChatMessage = {
  id: number;
  order_id: number;
  sender_id: number;
  sender_role: string;
  text?: string | null;
  image_url?: string | null;
  created_at?: string;
};

export type RideConversation = {
  order: Order;
  messages_count: number;
  last_message: ChatMessage | null;
};

export type DriverConversation = RideConversation;

export type DriverLocationEvent = {
  location_id: number;
  driver_id: number;
  city_id: number;
  lat: number;
  lng: number;
  reported_at: string;
  active_order_id?: number | null;
};

export type RidePaymentIntent = {
  id: number;
  provider: 'stripe';
  provider_intent_id: string;
  purpose: 'ride';
  order_id: number;
  amount: number;
  amount_minor: number;
  currency: string;
  status: string;
  client_secret: string;
  publishable_key: string;
};

export type PaymentIntentResponse = {
  id: number;
  provider: 'stripe';
  provider_intent_id: string;
  purpose: string;
  order_id?: number | null;
  amount: number;
  amount_minor: number;
  currency: string;
  status: string;
  client_secret: string | null;
  publishable_key: string | null;
  points?: number;
  point_price?: number;
};

export type PointsPurchaseConfig = {
  available: boolean;
  currency: string;
  point_price: number;
  points_per_currency_unit: number;
  minimum_payment: number;
  maximum_payment: number;
  minimum_points: number;
  maximum_points: number;
};

export type ConnectStatus = {
  provider: string;
  account_id?: string | null;
  country?: string | null;
  status: string;
  charges_enabled: boolean;
  payouts_enabled: boolean;
  details_submitted: boolean;
};

export type CatalogCity = {
  id: number;
  name: string;
};

export type CatalogVehicle = {
  key: 'sedan' | 'minivan' | 'suv' | 'minibus' | 'luxury';
  name: string;
  icon: string;
  multiplier: number;
};

export type CatalogDriver = {
  id: number;
  name: string;
  initials: string;
  vehicle_name?: string | null;
  vehicle_plate?: string | null;
  vehicle_type?: string | null;
  approval_state?: string | null;
  online_status: boolean;
  vehicle_photos?: Array<{ id: number; type: string; url: string }>;
};

export type Catalog = {
  cities: CatalogCity[];
  vehicles: CatalogVehicle[];
  drivers: CatalogDriver[];
  fare_config?: Record<string, unknown> | null;
};

export type AppNotification = {
  id: number;
  type: string;
  title: string;
  body: string;
  data?: Record<string, unknown> | null;
  read_at?: string | null;
  created_at?: string;
};

export type Transaction = {
  id: number;
  order_id?: number;
  fare?: number;
  fee?: number;
  net?: number;
  type?: string;
  source?: string;
  status?: string;
  created_at?: string;
};

export type Dashboard = {
  users: number;
  active_drivers: number;
  live_orders: number;
  completed_orders: number;
  gross_revenue: number;
  platform_fees: number;
};

export type Wallet = {
  points_balance?: number;
  wallet_balance?: number;
  free_rides_remaining?: number;
  currency?: string;
  ledger_entries?: WalletLedgerEntry[];
};

export type WalletLedgerEntry = {
  id: number;
  direction: string;
  entry_type: string;
  amount: number;
  points_delta: number;
  reason?: string | null;
  created_at?: string;
};

export type DriverDocument = {
  id: number;
  type: string;
  status: string;
  file_path?: string;
  original_name?: string | null;
  note?: string | null;
  created_at?: string;
  preview_url?: string | null;
};

export type DocChecklistItem = {
  type: string;
  status: string; // missing | pending | approved | rejected
  document?: DriverDocument | null;
};

export type DriverDocuments = {
  approval_state: string;
  checklist: DocChecklistItem[];
  documents: DriverDocument[];
  has_all_required: boolean;
};
