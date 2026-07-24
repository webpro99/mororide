import { FontAwesome5, Ionicons, MaterialCommunityIcons } from '@expo/vector-icons';
import { StatusBar } from 'expo-status-bar';
import { ReactNode, useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Animated,
  Easing,
  Image,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
  ViewStyle,
} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { SafeAreaProvider, SafeAreaView } from 'react-native-safe-area-context';
import {
  cancelRiderOrder,
  chooseRiderOffer,
  createRidePaymentIntent,
  createRiderOrder,
  estimateFare,
  getApiBaseUrl,
  setApiBaseUrl,
  getAccessToken,
  getCatalog,
  getNotifications,
  getOrderMessages,
  getPlatformMode,
  getRiderOffers,
  getRiderOrder,
  getRiderHistory,
  getRiderConversations,
  getRiderOrders,
  loginUser,
  logout,
  markNotificationRead,
  rateRiderOrder,
  registerUser,
  restoreSession,
  sendOrderMessage,
} from './src/api';
import { IncomingCallNotice, registerForPush, subscribeToIncomingCalls, unregisterPush } from './src/push';
import { payWithCardSheet } from './src/stripeCard';
import ConciergeApp from './src/ConciergeApp';
import DriverApp from './src/DriverApp';
import { NativeGoogleRouteMap } from './src/NativeGoogleRouteMap';
import { LocationPicker, PickedLocation } from './src/LocationPicker';
import { VoiceCallScreen } from './src/VoiceCallScreen';
import { IncomingCallPrompt } from './src/IncomingCallPrompt';
import { createRealtimeClient } from './src/realtime';
import { AppNotification, Catalog, CatalogCity, CatalogDriver, CatalogVehicle, ChatMessage, DriverLocationEvent, FareEstimate, Order, OrderOffer, PlatformMode, RideConversation } from './src/types';

type Screen = 'booking' | 'offers' | 'profile' | 'tracking' | 'chat' | 'call' | 'completed' | 'history' | 'messages';
type DemoCoord = { lat: number; lng: number };

const moroLogoMark = require('./assets/moro_logo_mark_transparent.png');
const DEMO_MAP_ZOOM = 14;
const DEMO_MAP_CENTER = { lat: 31.6338, lng: -7.9962 };
const CITY_CENTERS: Record<string, DemoCoord> = {
  agadir: { lat: 30.4278, lng: -9.5981 },
  casablanca: { lat: 33.5731, lng: -7.5898 },
  chefchaouen: { lat: 35.1688, lng: -5.2636 },
  essaouira: { lat: 31.5085, lng: -9.7595 },
  fez: { lat: 34.0181, lng: -5.0078 },
  marrakech: { lat: 31.6295, lng: -7.9811 },
  rabat: { lat: 34.0209, lng: -6.8416 },
  tangier: { lat: 35.7595, lng: -5.8340 },
};
const hasGoogleMapsKey = Boolean(process.env.EXPO_PUBLIC_GOOGLE_MAPS_API_KEY);

function driverFromOffer(offer: OrderOffer): CatalogDriver {
  const name = offer.driver.name;

  return {
    id: offer.driver.id,
    name,
    initials: name.slice(0, 2).toUpperCase(),
    vehicle_name: offer.driver.vehicle?.name,
    vehicle_plate: offer.driver.vehicle?.plate,
    vehicle_type: offer.driver.vehicle?.type,
    vehicle_photos: offer.driver.vehicle_photos ?? [],
    approval_state: 'approved',
    online_status: true,
  };
}

const APP_MODE_KEY = 'mororide.mobile.appmode';

type AppMode = 'rider' | 'driver' | 'concierge';

export default function App() {
  return (
    <SafeAreaProvider>
      <AppContent />
    </SafeAreaProvider>
  );
}

function AppContent() {
  const [mode, setMode] = useState<AppMode | null | undefined>(undefined);
  const [authenticated, setAuthenticated] = useState(false);
  const [signUp, setSignUp] = useState(false);
  const [signUpRole, setSignUpRole] = useState<AppMode>('rider');
  const [platformMode, setPlatformMode] = useState<PlatformMode | null>(null);
  const [freeBannerHidden, setFreeBannerHidden] = useState(false);

  useEffect(() => {
    Promise.all([AsyncStorage.getItem(APP_MODE_KEY), restoreSession().catch(() => null)]).then(([stored, session]) => {
      const storedMode = stored === 'rider' || stored === 'driver' || stored === 'concierge' ? stored : null;
      setAuthenticated(Boolean(storedMode && session?.user.role === storedMode));
      setMode(storedMode);
    });
  }, []);

  useEffect(() => {
    let active = true;
    const refresh = () => {
      getPlatformMode()
        .then((nextMode) => {
          if (!active) return;
          setPlatformMode(nextMode);
          if (nextMode.free_launch_enabled) setFreeBannerHidden(false);
        })
        .catch(() => undefined);
    };
    refresh();
    const timer = setInterval(refresh, 60000);
    return () => {
      active = false;
      clearInterval(timer);
    };
  }, []);

  async function pick(next: AppMode) {
    await AsyncStorage.setItem(APP_MODE_KEY, next);
    setSignUp(false);
    setAuthenticated(false);
    setMode(next);
  }

  async function finishAuth(next: AppMode) {
    await AsyncStorage.setItem(APP_MODE_KEY, next);
    setSignUp(false);
    setAuthenticated(true);
    setMode(next);
  }

  async function switchRole() {
    await unregisterPush();
    await logout();
    await AsyncStorage.removeItem(APP_MODE_KEY);
    setSignUp(false);
    setAuthenticated(false);
    setMode(null);
  }

  if (mode === undefined) {
    return (
      <SafeAreaView style={styles.page}>
        <View style={[styles.phone, { alignItems: 'center', justifyContent: 'center' }]}>
          <ActivityIndicator color={colors.gold} size="large" />
        </View>
      </SafeAreaView>
    );
  }

  if (mode === null) {
    return signUp
      ? <SignUpScreen initialRole={signUpRole} onDone={finishAuth} onCancel={() => setSignUp(false)} />
      : <RoleGate onPick={pick} onSignUp={() => { setSignUpRole('rider'); setSignUp(true); }} />;
  }
  if (signUp) return <SignUpScreen initialRole={signUpRole} onDone={finishAuth} onCancel={() => setSignUp(false)} />;
  if (!authenticated) {
    return <RoleAuthScreen
      role={mode}
      onAuthenticated={() => finishAuth(mode)}
      onSignUp={() => { setSignUpRole(mode); setSignUp(true); }}
      onBack={async () => { await AsyncStorage.removeItem(APP_MODE_KEY); setMode(null); }}
    />;
  }
  const freeLaunch = Boolean(platformMode?.free_launch_enabled);
  const roleApp = mode === 'driver'
    ? <DriverApp onSwitchRole={switchRole} freeLaunch={freeLaunch} />
    : mode === 'concierge'
      ? <ConciergeApp onSwitchRole={switchRole} freeLaunch={freeLaunch} />
      : <RiderApp onSwitchRole={switchRole} freeLaunch={freeLaunch} />;

  return (
    <View style={styles.appShell}>
      {roleApp}
      {platformMode?.free_launch_enabled && !freeBannerHidden ? (
        <FreeLaunchBanner mode={platformMode} onClose={() => setFreeBannerHidden(true)} />
      ) : null}
    </View>
  );
}

function FreeLaunchBanner({ mode, onClose }: { mode: PlatformMode; onClose: () => void }) {
  return (
    <View pointerEvents="box-none" style={styles.freeLaunchWrap}>
      <View style={styles.freeLaunchCard}>
        <View style={styles.freeLaunchStar}>
          <Ionicons name="sparkles" size={22} color={colors.navy} />
        </View>
        <View style={styles.freeLaunchCopy}>
          <View style={styles.freeLaunchTitleRow}>
            <Text style={styles.freeLaunchTitle}>{mode.title}</Text>
            <View style={styles.billingOffPill}>
              <Text style={styles.billingOffText}>Billing off</Text>
            </View>
          </View>
          <Text style={styles.freeLaunchBody}>{mode.body}</Text>
        </View>
        <Pressable accessibilityRole="button" accessibilityLabel="Dismiss free launch banner" onPress={onClose} style={styles.freeLaunchClose}>
          <Ionicons name="close" size={18} color={colors.white} />
        </Pressable>
      </View>
    </View>
  );
}

function RoleGate({ onPick, onSignUp }: { onPick: (mode: AppMode) => void; onSignUp: () => void }) {
  const [serverOpen, setServerOpen] = useState(false);

  return (
    <SafeAreaView style={styles.page}>
      <StatusBar style="dark" translucent={false} backgroundColor={colors.cream} />
      {serverOpen ? <ServerSettings onClose={() => setServerOpen(false)} /> : null}
      <View style={styles.phone}>
        <ScrollView contentContainerStyle={styles.gate} showsVerticalScrollIndicator={false}>
          <View style={styles.gateHero}>
            <View style={styles.gateLogoShell}>
              <Image source={moroLogoMark} style={styles.gateLogo} resizeMode="contain" />
            </View>
            <Text style={styles.gateEyebrow}>WELCOME TO MORORIDE</Text>
            <Text style={styles.gateBrand}>How are you travelling?</Text>
            <Text style={styles.gateSub}>Choose your space. You can switch roles at any time.</Text>
          </View>

          <Pressable style={styles.gateCard} onPress={() => onPick('rider')}>
            <Ionicons name="person-outline" size={26} color={colors.navy} />
            <View style={{ flex: 1 }}>
              <Text style={styles.gateCardTitle}>I'm a Rider</Text>
              <Text style={styles.gateCardText}>Book a ride, choose a driver, track & pay</Text>
            </View>
            <Ionicons name="chevron-forward" size={22} color={colors.muted} />
          </Pressable>

          <Pressable style={styles.gateCard} onPress={() => onPick('driver')}>
            <Ionicons name="car-sport-outline" size={26} color={colors.navy} />
            <View style={{ flex: 1 }}>
              <Text style={styles.gateCardTitle}>I'm a Driver</Text>
              <Text style={styles.gateCardText}>Go online, accept rides, get paid</Text>
            </View>
            <Ionicons name="chevron-forward" size={22} color={colors.muted} />
          </Pressable>

          <Pressable style={styles.gateCard} onPress={() => onPick('concierge')}>
            <MaterialCommunityIcons name="bell-outline" size={26} color={colors.navy} />
            <View style={{ flex: 1 }}>
              <Text style={styles.gateCardTitle}>I'm a Concierge</Text>
              <Text style={styles.gateCardText}>Dispatch rides for hotel & riad guests</Text>
            </View>
            <Ionicons name="chevron-forward" size={22} color={colors.muted} />
          </Pressable>

          <Pressable style={styles.gateSignUp} onPress={onSignUp}>
            <Ionicons name="person-add-outline" size={20} color={colors.rust} />
            <Text style={styles.gateSignUpText}>Create a new account</Text>
          </Pressable>

          <Pressable style={styles.gateServer} onPress={() => setServerOpen(true)}>
            <Ionicons name="server-outline" size={16} color={colors.muted} />
            <Text style={styles.gateServerText}>Server settings</Text>
          </Pressable>

          <Text style={styles.gateHint}>Secure rides · Verified drivers · Local support</Text>
        </ScrollView>
      </View>
    </SafeAreaView>
  );
}

function RoleAuthScreen({ role, onAuthenticated, onSignUp, onBack }: {
  role: AppMode;
  onAuthenticated: () => void;
  onSignUp: () => void;
  onBack: () => void;
}) {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const roleLabel = role.charAt(0).toUpperCase() + role.slice(1);

  async function signIn() {
    if (!email.trim() || !password) {
      setError('Enter your email and password.');
      return;
    }
    setLoading(true); setError(null);
    try {
      const session = await loginUser(email, password);
      if (session.user.role !== role) {
        await logout();
        throw new Error(`This account is registered as ${session.user.role}, not ${role}.`);
      }
      registerForPush();
      onAuthenticated();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not sign in');
    } finally { setLoading(false); }
  }

  return (
    <SafeAreaView style={styles.page}>
      <StatusBar style="dark" translucent={false} backgroundColor={colors.cream} />
      <View style={[styles.phone, styles.authShell]}>
      <ScrollView contentContainerStyle={styles.authPage} keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false}>
        <Pressable accessibilityRole="button" accessibilityLabel="Back" onPress={onBack} style={styles.authBack}>
          <Ionicons name="arrow-back" size={22} color={colors.white} />
        </Pressable>
        <Image source={moroLogoMark} style={styles.authLogo} resizeMode="contain" />
        <Text style={styles.authEyebrow}>MORORIDE · {roleLabel.toUpperCase()}</Text>
        <Text style={styles.authTitle}>Welcome back</Text>
        <Text style={styles.authSubtitle}>Sign in to continue to your {role} app.</Text>

        <View style={styles.authCard}>
          <SignUpField label="Email" value={email} onChange={setEmail} placeholder="you@example.com" keyboardType="email-address" />
          <SignUpField label="Password" value={password} onChange={setPassword} placeholder="Your password" secure />
          {error ? <Text style={styles.signupError}>{error}</Text> : null}
          <Pressable onPress={signIn} disabled={loading} style={[styles.signupSubmit, loading && { opacity: .6 }]}>
            {loading ? <ActivityIndicator color={colors.white} /> : <Text style={styles.signupSubmitText}>Sign in</Text>}
          </Pressable>
          <View style={styles.authDivider}><View style={styles.authDividerLine} /><Text style={styles.authDividerText}>NEW HERE?</Text><View style={styles.authDividerLine} /></View>
          <Pressable onPress={onSignUp} style={styles.authCreate}>
            <Ionicons name="person-add-outline" size={19} color={colors.rust} />
            <Text style={styles.authCreateText}>Create a {role} account</Text>
          </Pressable>
        </View>
      </ScrollView>
      </View>
    </SafeAreaView>
  );
}

function ServerSettings({ onClose }: { onClose: () => void }) {
  const [url, setUrl] = useState('');
  const [saved, setSaved] = useState<string | null>(null);

  useEffect(() => {
    getApiBaseUrl().then(setUrl).catch(() => undefined);
  }, []);

  async function save() {
    const value = await setApiBaseUrl(url);
    setUrl(value);
    setSaved(`Saved. Backend: ${value}`);
  }

  return (
    <View style={styles.serverOverlay}>
      <View style={styles.serverCard}>
        <Text style={styles.serverTitle}>Backend server</Text>
        <Text style={styles.serverHint}>
          The address of the MoroRide backend. Change this if the server IP or domain changes — no rebuild needed.
        </Text>
        <TextInput
          value={url}
          onChangeText={setUrl}
          autoCapitalize="none"
          keyboardType="url"
          placeholder="http://192.168.1.20:8000  or  https://mororide.com"
          placeholderTextColor={colors.faded}
          style={styles.serverInput}
        />
        <Text style={styles.serverNote}>Tip: enter the host (with http/https). "/api" is added automatically.</Text>
        {saved ? <Text style={styles.serverSaved}>{saved}</Text> : null}
        <View style={styles.serverButtons}>
          <Pressable style={styles.serverCancel} onPress={onClose}>
            <Text style={styles.serverCancelText}>Close</Text>
          </Pressable>
          <Pressable style={styles.serverSave} onPress={save}>
            <Text style={styles.serverSaveText}>Save</Text>
          </Pressable>
        </View>
      </View>
    </View>
  );
}

function SignUpScreen({ initialRole = 'rider', onDone, onCancel }: { initialRole?: AppMode; onDone: (mode: AppMode) => void; onCancel: () => void }) {
  const [role, setRole] = useState<AppMode>(initialRole);
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function submit() {
    if (!name.trim() || !email.trim() || password.length < 8) {
      setError('Enter your name, email, and a password of at least 8 characters.');
      return;
    }
    setLoading(true);
    setError(null);
    try {
      await registerUser({ name: name.trim(), email: email.trim(), password, role, phone: phone.trim() || undefined });
      registerForPush();
      await onDone(role);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not create your account');
    } finally {
      setLoading(false);
    }
  }

  return (
    <SafeAreaView style={styles.page}>
      <StatusBar style="dark" translucent={false} backgroundColor={colors.cream} />
      <View style={styles.phone}>
      <ScrollView
        contentContainerStyle={styles.signupPage}
        keyboardShouldPersistTaps="handled"
        showsVerticalScrollIndicator={false}
      >
        <View style={styles.signupTopbar}>
          <Pressable accessibilityRole="button" accessibilityLabel="Back" onPress={onCancel} style={styles.signupBack}>
            <Ionicons name="arrow-back" size={22} color={colors.navy} />
          </Pressable>
          <View style={styles.signupBrand}>
            <Image source={moroLogoMark} style={styles.signupLogo} resizeMode="contain" />
            <Text style={styles.signupBrandText}>MoroRide</Text>
          </View>
          <View style={styles.signupTopbarSpacer} />
        </View>

        <View style={styles.signupHero}>
          <Text style={styles.signupEyebrow}>LET'S GET STARTED</Text>
          <Text style={styles.signupTitle}>Create your account</Text>
          <Text style={styles.signupSubtitle}>One account, built around the way you travel.</Text>
        </View>

        <View style={styles.signupRoles}>
          {(['rider', 'driver', 'concierge'] as AppMode[]).map((option) => (
            <Pressable key={option} onPress={() => setRole(option)} style={[styles.signupRole, role === option && styles.signupRoleActive]}>
              <Text style={[styles.signupRoleText, role === option && styles.signupRoleTextActive]}>{option}</Text>
            </Pressable>
          ))}
        </View>

        <View style={styles.signupForm}>
          <SignUpField label="Full name" value={name} onChange={setName} placeholder="e.g. Amine B." />
          <SignUpField label="Email address" value={email} onChange={setEmail} placeholder="you@example.com" keyboardType="email-address" />
          <SignUpField label="Phone number (optional)" value={phone} onChange={setPhone} placeholder="+212 6..." keyboardType="phone-pad" />
          <SignUpField label="Password" value={password} onChange={setPassword} placeholder="At least 8 characters" secure />
        </View>

        {role === 'driver' ? (
          <Text style={styles.signupNote}>Drivers upload their 7 verification documents in the app after signing up. You can go online once an admin approves them.</Text>
        ) : null}
        {error ? <Text style={styles.signupError}>{error}</Text> : null}

        <Pressable onPress={submit} disabled={loading} style={[styles.signupSubmit, loading && { opacity: 0.6 }]}>
          {loading ? <ActivityIndicator color={colors.white} /> : <Text style={styles.signupSubmitText}>Create account</Text>}
        </Pressable>
        <Text style={styles.signupTerms}>By continuing, you agree to MoroRide's terms and privacy policy.</Text>
      </ScrollView>
      </View>
    </SafeAreaView>
  );
}

function SignUpField({ label, value, onChange, placeholder, secure, keyboardType }: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  placeholder?: string;
  secure?: boolean;
  keyboardType?: 'default' | 'email-address' | 'phone-pad';
}) {
  return (
    <View style={styles.signupField}>
      <Text style={styles.signupLabel}>{label}</Text>
      <TextInput
        value={value}
        onChangeText={onChange}
        placeholder={placeholder}
        placeholderTextColor={colors.faded}
        secureTextEntry={secure}
        autoCapitalize={keyboardType === 'email-address' ? 'none' : 'sentences'}
        keyboardType={keyboardType ?? 'default'}
        style={styles.signupInput}
      />
    </View>
  );
}

function RiderApp({ onSwitchRole, freeLaunch = false }: { onSwitchRole: () => void; freeLaunch?: boolean }) {
  const [screen, setScreen] = useState<Screen>('booking');
  const [callShouldNotify, setCallShouldNotify] = useState(true);
  const [menuOpen, setMenuOpen] = useState(false);
  const [notificationsOpen, setNotificationsOpen] = useState(false);
  const [notifications, setNotifications] = useState<AppNotification[]>([]);
  const [notificationsLoading, setNotificationsLoading] = useState(false);
  const [incomingCall, setIncomingCall] = useState<(IncomingCallNotice & { notificationId?: number }) | null>(null);
  const [catalog, setCatalog] = useState<Catalog | null>(null);
  const [catalogLoading, setCatalogLoading] = useState(true);
  const [selectedCityId, setSelectedCityId] = useState<number | null>(null);
  const [selectedVehicleKey, setSelectedVehicleKey] = useState<CatalogVehicle['key']>('sedan');
  const [passengers, setPassengers] = useState(2);
  const [luggage, setLuggage] = useState(2);
  const [payment, setPayment] = useState<'cash' | 'card'>('cash');
  const [price, setPrice] = useState('120');
  const [suggestedFare, setSuggestedFare] = useState<FareEstimate | null>(null);
  const [priceEdited, setPriceEdited] = useState(false);
  const [pickup, setPickup] = useState<PickedLocation | null>(null);
  const [dropoff, setDropoff] = useState<PickedLocation | null>(null);
  const [locationPicker, setLocationPicker] = useState<'pickup' | 'dropoff' | null>(null);
  const [rating, setRating] = useState(0);
  const [loading, setLoading] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);
  const [currentOrder, setCurrentOrder] = useState<Order | null>(null);
  const [selectedDriver, setSelectedDriver] = useState<CatalogDriver | null>(null);
  const [offers, setOffers] = useState<OrderOffer[]>([]);
  const [selectedOffer, setSelectedOffer] = useState<OrderOffer | null>(null);
  const [offersLoading, setOffersLoading] = useState(false);
  const [driverCoord, setDriverCoord] = useState<DemoCoord | null>(null);
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [chatLoading, setChatLoading] = useState(false);
  const [rideHistory, setRideHistory] = useState<Order[]>([]);
  const [conversations, setConversations] = useState<RideConversation[]>([]);
  const [archiveLoading, setArchiveLoading] = useState(false);
  const [messageBadge, setMessageBadge] = useState(0);
  const [chatBackScreen, setChatBackScreen] = useState<Screen>('tracking');
  const announcedAssignmentRef = useRef<number | null>(null);
  const lastNotificationIdRef = useRef<number | null>(null);

  function shouldShowIncomingCall(orderId: number) {
    return screen !== 'call' && (!currentOrder?.id || currentOrder.id === orderId);
  }

  function queueIncomingCall(call: IncomingCallNotice & { notificationId?: number }) {
    if (!shouldShowIncomingCall(call.orderId)) return;
    setIncomingCall((current) => current?.orderId === call.orderId && current.notificationId === call.notificationId ? current : call);
  }

  useEffect(() => {
    let mounted = true;

    registerForPush();

    getCatalog()
      .then((data) => {
        if (!mounted) return;
        setCatalog(data);
        setSelectedCityId((current) => current ?? data.cities.find((city) => city.name === 'Marrakech')?.id ?? data.cities[0]?.id ?? null);
        setSelectedVehicleKey((current) => data.vehicles.some((vehicle) => vehicle.key === current) ? current : data.vehicles[0]?.key ?? 'sedan');
      })
      .catch((error) => {
        if (mounted) setNotice(error instanceof Error ? error.message : 'Could not load catalog');
      })
      .finally(() => {
        if (mounted) setCatalogLoading(false);
      });

    return () => {
      mounted = false;
    };
  }, []);

  useEffect(() => {
    if (freeLaunch && payment === 'card') setPayment('cash');
  }, [freeLaunch, payment]);

  useEffect(() => {
    return subscribeToIncomingCalls(queueIncomingCall);
  }, [screen, currentOrder?.id]);

  // Restore an open request after an app reload or a temporary transport
  // failure. The backend may have committed the order even if realtime failed
  // while the HTTP response was being completed.
  useEffect(() => {
    let active = true;
    getRiderOrders().then(async (orders) => {
      const open = orders.find((item) => ['searching', 'offered', 'assigned', 'arrived', 'in_progress'].includes(item.status));
      if (!active || !open) return;
      setCurrentOrder(open);
      const nextOffers = await getRiderOffers(open.id).catch(() => []);
      if (!active) return;
      setOffers(nextOffers);
      setScreen(open.assigned_driver_id ? 'tracking' : 'offers');
      if (open.assigned_driver_id) {
        const accepted = nextOffers.find((item) => item.driver.id === open.assigned_driver_id);
        if (accepted) {
          setSelectedOffer(accepted);
          setSelectedDriver(driverFromOffer(accepted));
        }
      }
    }).catch(() => undefined);
    return () => { active = false; };
  }, []);

  useEffect(() => {
    if (!pickup || !dropoff) {
      setSuggestedFare(null);
      return undefined;
    }

    const tripDistance = Math.round(haversineKm(pickup, dropoff) * 10) / 10;
    if (tripDistance < 0.1) {
      setSuggestedFare(null);
      return undefined;
    }

    let active = true;
    const etaMin = Math.max(1, Math.round((tripDistance / 45) * 60));

    estimateFare({
      distance_km: tripDistance,
      eta_min: etaMin,
      pax: passengers,
      vehicle_type: selectedVehicleKey,
    })
      .then((fare) => {
        if (!active) return;
        setSuggestedFare(fare);
        if (!priceEdited) setPrice(String(Math.round(fare.suggested_fare)));
      })
      .catch(() => {
        if (active) setSuggestedFare(null);
      });

    return () => { active = false; };
  }, [pickup, dropoff, passengers, selectedVehicleKey, priceEdited]);

  useEffect(() => {
    if (!currentOrder) return undefined;

    let active = true;
    let realtime: ReturnType<typeof createRealtimeClient> | null = null;
    const orderId = currentOrder.id;

    getAccessToken()
      .then((token) => {
        if (!active || !token) return;

        realtime = createRealtimeClient(token);
        realtime.private(`order.${orderId}`)
          .listen('.order.offer.submitted', () => {
            syncRiderOrder(orderId, active).catch(() => undefined);
          })
          .listen('.order.status.changed', () => {
            syncRiderOrder(orderId, active).catch(() => undefined);
          })
          .listen('.order.assigned', () => {
            syncRiderOrder(orderId, active).catch(() => undefined);
          });

        realtime.private(`chat.${orderId}`)
          .listen('.chat.message.sent', (event: {
            message_id: number;
            order_id: number;
            sender_id: number;
            sender_role: string;
            text?: string | null;
            image_url?: string | null;
            sent_at: string;
          }) => {
            if (!active) return;
            setMessages((current) => current.some((message) => message.id === event.message_id)
              ? current
              : [...current, {
                id: event.message_id,
                order_id: event.order_id,
                sender_id: event.sender_id,
                sender_role: event.sender_role,
                text: event.text,
                image_url: event.image_url,
                created_at: event.sent_at,
              }]);
          });

        if (currentOrder.assigned_driver_id) {
          realtime.private(`driver.${currentOrder.assigned_driver_id}.location`)
            .listen('.driver.location.updated', (event: DriverLocationEvent) => {
              if (active && (!event.active_order_id || event.active_order_id === orderId)) {
                setDriverCoord({ lat: event.lat, lng: event.lng });
              }
            });
        }
      })
      .catch(() => undefined);

    return () => {
      active = false;
      realtime?.disconnect();
    };
  }, [currentOrder?.id, currentOrder?.assigned_driver_id]);

  useEffect(() => {
    if (!currentOrder || currentOrder.assigned_driver_id || !['searching', 'offered'].includes(currentOrder.status)) return undefined;
    const orderId = currentOrder.id;
    const poll = setInterval(() => { syncRiderOrder(orderId, true).catch(() => undefined); }, 3000);
    return () => clearInterval(poll);
  }, [currentOrder?.id, currentOrder?.assigned_driver_id, currentOrder?.status]);

  useEffect(() => {
    if (screen !== 'chat' || !currentOrder?.id) return undefined;
    const orderId = currentOrder.id;
    let active = true;
    const refreshChat = () => {
      getOrderMessages(orderId).then((nextMessages) => {
        if (active) setMessages(nextMessages);
      }).catch((error) => {
        if (active) setNotice(error instanceof Error ? error.message : 'Could not refresh chat');
      });
    };
    const poll = setInterval(refreshChat, 3000);
    return () => { active = false; clearInterval(poll); };
  }, [screen, currentOrder?.id]);

  useEffect(() => {
    if (screen !== 'history' && screen !== 'messages') return undefined;
    let active = true;
    setArchiveLoading(true);
    const request = screen === 'history' ? getRiderHistory() : getRiderConversations();
    request.then((data) => {
      if (!active) return;
      if (screen === 'history') setRideHistory(data as Order[]);
      else setConversations(data as RideConversation[]);
    }).catch((error) => {
      if (active) setNotice(error instanceof Error ? error.message : 'Could not load your archive');
    }).finally(() => { if (active) setArchiveLoading(false); });
    return () => { active = false; };
  }, [screen]);

  useEffect(() => {
    let active = true;
    const refresh = async () => {
      try {
        const all = await getNotifications();
        if (!active) return;
        setNotifications(all);
        const incoming = all.find((item) => {
          const orderId = Number(item.data?.order_id);
          return item.type === 'incoming_voice_call' && !item.read_at && Number.isFinite(orderId) && shouldShowIncomingCall(orderId);
        });
        if (incoming) {
          queueIncomingCall({
            orderId: Number(incoming.data?.order_id),
            title: incoming.title,
            body: incoming.body,
            callerId: Number(incoming.data?.caller_id) || null,
            notificationId: incoming.id,
          });
        }
        const unread = all.filter((item) => item.type === 'chat_message' && !item.read_at);
        const latest = all.find((item) => item.type === 'chat_message');
        if (latest && lastNotificationIdRef.current !== null && latest.id > lastNotificationIdRef.current) {
          setNotice(`${latest.title}: ${latest.body}`);
        }
        if (latest) lastNotificationIdRef.current = Math.max(lastNotificationIdRef.current ?? 0, latest.id);

        if (screen === 'messages' || screen === 'chat') {
          await Promise.all(unread.map((item) => markNotificationRead(item.id).catch(() => null)));
          if (active) setMessageBadge(0);
        } else {
          setMessageBadge(unread.length);
        }
      } catch { /* best-effort polling fallback */ }
    };
    refresh();
    const id = setInterval(refresh, 4000);
    return () => { active = false; clearInterval(id); };
  }, [screen]);

  async function syncRiderOrder(orderId: number, active: boolean) {
    const [nextOrder, nextOffers] = await Promise.all([getRiderOrder(orderId), getRiderOffers(orderId)]);
    if (!active) return;
    setCurrentOrder(nextOrder);
    setOffers(nextOffers);

    if (!nextOrder.assigned_driver_id) return;
    const acceptedOffer = nextOffers.find((offer) => offer.driver.id === nextOrder.assigned_driver_id);
    if (acceptedOffer) {
      setSelectedOffer(acceptedOffer);
      setSelectedDriver(driverFromOffer(acceptedOffer));
    } else if (nextOrder.driver) {
      const name = nextOrder.driver.name || 'Assigned driver';
      setSelectedDriver({
        id: nextOrder.driver.id,
        name,
        initials: name.split(' ').filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase(),
        approval_state: 'approved',
        online_status: true,
      });
    }

    if (announcedAssignmentRef.current !== nextOrder.assigned_driver_id) {
      announcedAssignmentRef.current = nextOrder.assigned_driver_id;
      const driverName = acceptedOffer?.driver.name ?? nextOrder.driver?.name ?? 'Your driver';
      setNotice(`${driverName} accepted your ride. Chat is now open.`);
    }
    setScreen('tracking');
  }

  async function findDriver() {
    setNotice(null);

    if (!pickup || !dropoff) {
      setNotice('Choose both pickup and drop-off on the map.');
      return;
    }
    const tripDistance = haversineKm(pickup, dropoff);
    if (tripDistance < 0.1) {
      setNotice('Pickup and drop-off must be different locations.');
      return;
    }
    if (!Number(price)) {
      setNotice('Enter your offered price or ask admin to activate fare pricing.');
      return;
    }

    setLoading(true);

    try {
      if (!(await getAccessToken())) throw new Error('Sign in as a rider before booking.');
      const order = await createRiderOrder({
        city_id: selectedCityId ?? undefined,
        pickup_address: pickup.address,
        pickup_lat: pickup.lat,
        pickup_lng: pickup.lng,
        dropoff_address: dropoff.address,
        dropoff_lat: dropoff.lat,
        dropoff_lng: dropoff.lng,
        distance_km: Math.round(tripDistance * 10) / 10,
        eta_min: Math.max(1, Math.round((tripDistance / 45) * 60)),
        pax: passengers,
        luggage,
        offered_fare: Number(price) || undefined,
        payment_method: payment,
        vehicle_type: selectedVehicleKey,
      });
      setCurrentOrder(order);
      announcedAssignmentRef.current = null;
      setOffers([]);
      setSelectedOffer(null);
      await refreshOffers(order.id);
      setScreen('offers');
    } catch (error) {
      // Recover a request that was committed server-side before a transient
      // realtime/HTTP failure interrupted the response.
      const recovered = await getRiderOrders()
        .then((orders) => orders.find((item) =>
          ['searching', 'offered'].includes(item.status)
          && item.pickup_address === pickup.address
          && item.dropoff_address === dropoff.address))
        .catch(() => undefined);
      if (recovered) {
        setCurrentOrder(recovered);
        setOffers(await getRiderOffers(recovered.id).catch(() => []));
        setNotice(null);
        setScreen('offers');
      } else {
        setNotice(error instanceof Error ? error.message : 'Could not create ride request');
      }
    } finally {
      setLoading(false);
    }
  }

  async function refreshOffers(orderId = currentOrder?.id) {
    if (!orderId) return;

    setOffersLoading(true);
    try {
      const [nextOffers, nextOrder] = await Promise.all([
        getRiderOffers(orderId),
        getRiderOrder(orderId),
      ]);
      setOffers(nextOffers);
      setCurrentOrder(nextOrder);
    } catch (error) {
      setNotice(error instanceof Error ? error.message : 'Could not refresh driver offers');
    } finally {
      setOffersLoading(false);
    }
  }

  async function chooseOffer(offer: OrderOffer) {
    if (!currentOrder) return;

    setLoading(true);
    setNotice(null);
    try {
      const order = offer.status === 'accepted' || currentOrder.assigned_driver_id === offer.driver.id
        ? await getRiderOrder(currentOrder.id)
        : await chooseRiderOffer(currentOrder.id, offer.id);
      setCurrentOrder(order);
      setSelectedOffer(offer);
      setSelectedDriver(driverFromOffer(offer));
      setNotice(`Offer accepted. ${offer.driver.name} is your driver and chat is now open.`);
      setScreen('tracking');
    } catch (error) {
      setNotice(error instanceof Error ? error.message : 'Could not choose this driver');
    } finally {
      setLoading(false);
    }
  }

  async function cancelCurrentOrder() {
    if (!currentOrder) return;

    setLoading(true);
    setNotice(null);
    try {
      await cancelRiderOrder(currentOrder.id);
      // A cancelled request starts a completely fresh booking. Do not leave
      // stale pins, fare, offers, chat, or passenger choices on the form.
      setPickup(null);
      setDropoff(null);
      setLocationPicker(null);
      setCurrentOrder(null);
      setOffers([]);
      setSelectedOffer(null);
      setSelectedDriver(null);
      setDriverCoord(null);
      setMessages([]);
      setPassengers(1);
      setLuggage(0);
      setPrice('');
      setSuggestedFare(null);
      setPriceEdited(false);
      setPayment('cash');
      setRating(0);
      announcedAssignmentRef.current = null;
      setScreen('booking');
    } catch (error) {
      setNotice(error instanceof Error ? error.message : 'Could not cancel this request');
    } finally {
      setLoading(false);
    }
  }

  async function submitRating(comment?: string) {
    if (!currentOrder || rating < 1) {
      setNotice('Choose a rating from 1 to 5 stars.');
      return;
    }

    setLoading(true);
    setNotice(null);
    try {
      await rateRiderOrder(currentOrder.id, rating, comment);
      setRating(0);
      setCurrentOrder(null);
      setScreen('booking');
    } catch (error) {
      setNotice(error instanceof Error ? error.message : 'Could not submit rating');
    } finally {
      setLoading(false);
    }
  }

  async function openChat() {
    if (!currentOrder) return;

    setChatBackScreen('tracking');
    setChatLoading(true);
    setNotice(null);
    try {
      setMessages(await getOrderMessages(currentOrder.id));
      setScreen('chat');
    } catch (error) {
      setNotice(error instanceof Error ? error.message : 'Could not load ride chat');
    } finally {
      setChatLoading(false);
    }
  }

  async function openArchivedChat(order: Order, returnTo: 'history' | 'messages') {
    setCurrentOrder(order);
    setChatBackScreen(returnTo);
    setChatLoading(true);
    setNotice(null);
    try {
      setMessages(await getOrderMessages(order.id));
      setScreen('chat');
    } catch (error) {
      setNotice(error instanceof Error ? error.message : 'Could not load ride chat');
    } finally {
      setChatLoading(false);
    }
  }

  async function payWithCard() {
    if (!currentOrder) return;

    setLoading(true);
    setNotice(null);
    try {
      const intent = await createRidePaymentIntent(currentOrder.id);
      if (!intent.client_secret || !intent.publishable_key) {
        setNotice('Card payment is unavailable — Stripe is not configured yet.');
        return;
      }

      const result = await payWithCardSheet({
        clientSecret: intent.client_secret,
        publishableKey: intent.publishable_key,
        label: `Ride #${currentOrder.id}`,
      });

      if (result.status === 'completed') {
        setNotice('Payment received. Your driver can now complete the ride.');
        const fresh = await getRiderOrder(currentOrder.id).catch(() => null);
        if (fresh) setCurrentOrder(fresh);
      } else if (result.status === 'canceled') {
        setNotice('Payment canceled.');
      } else {
        setNotice(result.message ?? 'Card payment failed.');
      }
    } catch (error) {
      setNotice(error instanceof Error ? error.message : 'Could not start card payment');
    } finally {
      setLoading(false);
    }
  }

  async function sendChatMessage(text: string) {
    if (!currentOrder || !text.trim()) return;

    const cleanText = text.trim();
    const temporaryId = -Date.now();
    const optimisticMessage: ChatMessage = {
      id: temporaryId,
      order_id: currentOrder.id,
      sender_id: 0,
      sender_role: 'rider',
      text: cleanText,
      created_at: new Date().toISOString(),
    };
    setMessages((current) => [...current, optimisticMessage]);
    setChatLoading(true);
    setNotice(null);
    try {
      const message = await sendOrderMessage(currentOrder.id, cleanText);
      setMessages((current) => current.map((item) => item.id === temporaryId ? message : item));
    } catch (error) {
      setMessages((current) => current.filter((item) => item.id !== temporaryId));
      setNotice(error instanceof Error ? error.message : 'Could not send message');
    } finally {
      setChatLoading(false);
    }
  }

  async function openNotifications() {
    setNotificationsOpen(true);
    setNotificationsLoading(true);

    try {
      setNotifications(await getNotifications());
    } catch (error) {
      setNotice(error instanceof Error ? error.message : 'Could not load notifications');
    } finally {
      setNotificationsLoading(false);
    }
  }

  async function answerIncomingCall() {
    if (!incomingCall) return;
    const orderId = incomingCall.orderId;
    const notificationId = incomingCall.notificationId;
    setIncomingCall(null);
    if (notificationId) {
      markNotificationRead(notificationId).catch(() => null);
    }
    if (currentOrder?.id !== orderId) {
      const order = await getRiderOrder(orderId).catch(() => null);
      if (order) setCurrentOrder(order);
    }
    setCallShouldNotify(false);
    setScreen('call');
  }

  function declineIncomingCall() {
    if (incomingCall?.notificationId) {
      markNotificationRead(incomingCall.notificationId).catch(() => null);
      setNotifications((current) => current.map((item) => item.id === incomingCall.notificationId ? { ...item, read_at: item.read_at ?? new Date().toISOString() } : item));
    }
    setIncomingCall(null);
  }

  const content = useMemo(() => {
    if (screen === 'offers') return <OffersScreen offers={offers} order={currentOrder} loading={offersLoading || loading} onBack={() => setScreen('booking')} onProfile={(offer) => {
      setSelectedOffer(offer);
      setSelectedDriver(driverFromOffer(offer));
      setScreen('profile');
    }} onChoose={chooseOffer} onRefresh={() => refreshOffers()} onCancel={cancelCurrentOrder} notice={notice} />;
    if (screen === 'profile') return <ProfileScreen driver={selectedDriver} onBack={() => setScreen('offers')} onChoose={() => selectedOffer && chooseOffer(selectedOffer)} />;
    if (screen === 'tracking') return <TrackingScreen order={currentOrder} driver={selectedDriver} driverCoord={driverCoord} notice={notice} onBack={() => setScreen('offers')} onChat={openChat} onCall={() => { setCallShouldNotify(true); setScreen('call'); }} onPay={payWithCard} onComplete={() => setScreen('completed')} />;
    if (screen === 'chat') return <ChatScreen messages={messages} loading={chatLoading} notice={notice} onBack={() => setScreen(chatBackScreen)} onSend={sendChatMessage} />;
    if (screen === 'call' && currentOrder) return <VoiceCallScreen orderId={currentOrder.id} peerLabel={selectedDriver?.name ?? currentOrder.driver?.name ?? 'Your driver'} notify={callShouldNotify} onEnd={() => setScreen('tracking')} />;
    if (screen === 'completed') return <CompletedScreen order={currentOrder} driver={selectedDriver} rating={rating} setRating={setRating} loading={loading} notice={notice} onDone={submitRating} />;
    if (screen === 'history') return <RiderHistoryScreen orders={rideHistory} loading={archiveLoading} notice={notice} onBack={() => setScreen('booking')} onOpenChat={(order) => openArchivedChat(order, 'history')} />;
    if (screen === 'messages') return <RiderMessagesScreen conversations={conversations} loading={archiveLoading} notice={notice} onBack={() => setScreen('booking')} onOpenChat={(order) => openArchivedChat(order, 'messages')} />;

    return (
      <BookingScreen
        cities={catalog?.cities ?? []}
        vehicles={catalog?.vehicles ?? []}
        selectedCityId={selectedCityId}
        setSelectedCityId={setSelectedCityId}
        selectedVehicleKey={selectedVehicleKey}
        setSelectedVehicleKey={setSelectedVehicleKey}
        passengers={passengers}
        setPassengers={setPassengers}
        luggage={luggage}
        setLuggage={setLuggage}
        payment={payment}
        setPayment={setPayment}
        freeLaunch={freeLaunch}
        price={price}
        setPrice={(value) => {
          setPriceEdited(true);
          setPrice(value);
        }}
        suggestedFare={suggestedFare}
        onUseSuggestedFare={() => {
          if (!suggestedFare) return;
          setPrice(String(Math.round(suggestedFare.suggested_fare)));
          setPriceEdited(false);
        }}
        pickupCoord={pickup}
        dropoffCoord={dropoff}
        onPickPickup={() => setLocationPicker('pickup')}
        onPickDropoff={() => setLocationPicker('dropoff')}
        loading={loading}
        catalogLoading={catalogLoading}
        notice={notice}
        onOpenMenu={() => setMenuOpen(true)}
        onOpenNotifications={openNotifications}
        onFindDriver={findDriver}
      />
    );
  }, [screen, catalog, currentOrder, selectedDriver, selectedOffer, offers, offersLoading, driverCoord, messages, chatLoading, selectedCityId, selectedVehicleKey, passengers, luggage, payment, freeLaunch, price, suggestedFare, pickup, dropoff, loading, catalogLoading, notice, rating, rideHistory, conversations, archiveLoading, chatBackScreen]);

  function navigate(screenName: Screen) {
    setScreen(screenName);
    setMenuOpen(false);
  }

  return (
    <SafeAreaView style={styles.page}>
      <StatusBar style="dark" translucent={false} backgroundColor={colors.cream} />
      <View style={styles.phone}>
        {content}
        {menuOpen ? (
          <AppMenu
            activeScreen={screen}
            messageBadge={messageBadge}
            onClose={() => setMenuOpen(false)}
            onNavigate={navigate}
            onFindDriver={() => {
              setMenuOpen(false);
              findDriver();
            }}
            onSwitchRole={onSwitchRole}
          />
        ) : null}
        {notificationsOpen ? (
          <NotificationPanel
            notifications={notifications}
            loading={notificationsLoading}
            onClose={() => setNotificationsOpen(false)}
            onOpenCall={(orderId) => {
              if (currentOrder?.id !== orderId) return;
              setNotificationsOpen(false);
              setCallShouldNotify(false);
              setScreen('call');
            }}
          />
        ) : null}
        {incomingCall ? (
          <IncomingCallPrompt
            title={incomingCall.title}
            body={incomingCall.body}
            peerLabel={selectedDriver?.name ?? currentOrder?.driver?.name ?? 'Your driver'}
            onAnswer={answerIncomingCall}
            onDecline={declineIncomingCall}
          />
        ) : null}
        <LocationPicker
          visible={locationPicker !== null}
          label={locationPicker === 'dropoff' ? 'Drop-off' : 'Pickup'}
          center={CITY_CENTERS[(catalog?.cities.find((city) => city.id === selectedCityId)?.name ?? '').toLocaleLowerCase()] ?? DEMO_MAP_CENTER}
          initial={locationPicker === 'dropoff' ? dropoff : pickup}
          onCancel={() => setLocationPicker(null)}
          onConfirm={(location) => {
            if (locationPicker === 'pickup') setPickup(location);
            if (locationPicker === 'dropoff') setDropoff(location);
            setLocationPicker(null);
            setNotice(null);
          }}
        />
      </View>
    </SafeAreaView>
  );
}

function BookingScreen(props: {
  cities: CatalogCity[];
  vehicles: CatalogVehicle[];
  selectedCityId: number | null;
  setSelectedCityId: (cityId: number) => void;
  selectedVehicleKey: CatalogVehicle['key'];
  setSelectedVehicleKey: (vehicle: CatalogVehicle['key']) => void;
  passengers: number;
  setPassengers: (value: number) => void;
  luggage: number;
  setLuggage: (value: number) => void;
  payment: 'cash' | 'card';
  setPayment: (value: 'cash' | 'card') => void;
  freeLaunch: boolean;
  price: string;
  setPrice: (value: string) => void;
  suggestedFare: FareEstimate | null;
  onUseSuggestedFare: () => void;
  pickupCoord: PickedLocation | null;
  dropoffCoord: PickedLocation | null;
  onPickPickup: () => void;
  onPickDropoff: () => void;
  loading: boolean;
  catalogLoading: boolean;
  notice: string | null;
  onOpenMenu: () => void;
  onOpenNotifications: () => void;
  onFindDriver: () => void;
}) {
  const routeDistanceKm = props.pickupCoord && props.dropoffCoord
    ? Math.round(haversineKm(props.pickupCoord, props.dropoffCoord) * 10) / 10
    : null;
  const routeEtaMin = routeDistanceKm ? Math.max(1, Math.round((routeDistanceKm / 45) * 60)) : null;
  const displayedFare = props.suggestedFare ? Math.round(props.suggestedFare.suggested_fare) : Number(props.price || 0);
  const fareMeta = props.suggestedFare
    ? `Admin rate · ${props.suggestedFare.distance_km} km · ${props.suggestedFare.eta_min} min · ${props.suggestedFare.vehicle_type}`
    : (routeDistanceKm ? `${routeDistanceKm} km route · ${routeEtaMin} min estimated` : 'Choose pickup and drop-off to calculate');

  return (
    <View style={styles.screen}>
      <DarkHeader
        onNotifications={props.onOpenNotifications}
        left={
          <Pressable accessibilityRole="button" accessibilityLabel="Open menu" onPress={props.onOpenMenu} style={styles.menuButton}>
            <Ionicons name="menu" size={30} color={colors.white} />
          </Pressable>
        }
      />
      <ScrollView style={styles.sheet} contentContainerStyle={styles.bookingContent} showsVerticalScrollIndicator={false}>
        {props.notice ? <Text style={styles.noticeText}>{props.notice}</Text> : null}

        <Text style={styles.fieldTitle}>City</Text>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.cityStrip}>
          {props.cities.map((city) => (
            <Pressable key={city.id} onPress={() => props.setSelectedCityId(city.id)} style={[styles.cityChip, props.selectedCityId === city.id && styles.cityChipActive]}>
              <Text style={[styles.cityChipText, props.selectedCityId === city.id && styles.cityChipTextActive]}>{city.name}</Text>
            </Pressable>
          ))}
        </ScrollView>

        <BookingRoutePickerMap
          pickup={props.pickupCoord}
          dropoff={props.dropoffCoord}
          onPickPickup={props.onPickPickup}
          onPickDropoff={props.onPickDropoff}
        />

        {routeDistanceKm ? (
          <Card style={styles.tripMetricsCard}>
            <View style={styles.tripMetric}>
              <View style={styles.tripMetricIcon}>
                <Ionicons name="navigate-outline" size={21} color={colors.navy} />
              </View>
              <View>
                <Text style={styles.tripMetricLabel}>Trip distance</Text>
                <Text style={styles.tripMetricValue}>{routeDistanceKm} km</Text>
              </View>
            </View>
            <View style={styles.tripMetricDivider} />
            <View style={styles.tripMetric}>
              <View style={styles.tripMetricIcon}>
                <Ionicons name="time-outline" size={21} color={colors.navy} />
              </View>
              <View>
                <Text style={styles.tripMetricLabel}>Estimated time</Text>
                <Text style={styles.tripMetricValue}>{routeEtaMin} min</Text>
              </View>
            </View>
          </Card>
        ) : null}

        <View style={styles.twoCols}>
          <Stepper label="Passengers" value={props.passengers} icon="person-outline" onMinus={() => props.setPassengers(Math.max(1, props.passengers - 1))} onPlus={() => props.setPassengers(props.passengers + 1)} />
          <Stepper label="Luggage" value={props.luggage} icon="briefcase-outline" onMinus={() => props.setLuggage(Math.max(0, props.luggage - 1))} onPlus={() => props.setLuggage(props.luggage + 1)} />
        </View>

        <Text style={styles.fieldTitle}>Vehicle type</Text>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.vehicleStrip}>
          {props.vehicles.map((vehicle) => (
            <Pressable
              key={vehicle.key}
              onPress={() => props.setSelectedVehicleKey(vehicle.key)}
              style={[styles.vehicleCard, props.selectedVehicleKey === vehicle.key && styles.vehicleCardActive]}
            >
              {props.selectedVehicleKey === vehicle.key ? (
                <View style={styles.vehicleCheck}>
                  <Ionicons name="checkmark" size={19} color={colors.white} />
                </View>
              ) : null}
              <FontAwesome5 name={vehicle.icon as never} size={27} color={props.selectedVehicleKey === vehicle.key ? colors.white : colors.navy} />
              <Text style={[styles.vehicleLabel, props.selectedVehicleKey === vehicle.key && styles.vehicleLabelActive]}>{vehicle.name}</Text>
            </Pressable>
          ))}
        </ScrollView>

        <Card style={styles.fareCard}>
          <View style={styles.fareHeader}>
            <View>
              <Text style={styles.fareEyebrow}>Suggested fare</Text>
              <View style={styles.fareLine}>
                <Text style={styles.fareNumber}>{displayedFare || '-'}</Text>
                <Text style={styles.currency}>MAD</Text>
              </View>
              <Text style={styles.fareMeta}>{fareMeta}</Text>
            </View>
            <View style={styles.fareShield}>
              <MaterialCommunityIcons name="shield-check-outline" size={34} color={colors.rust} />
            </View>
          </View>
          <View style={styles.fareBenefits}>
            {['Fair price for this route', 'Verified drivers', 'Secure & safe'].map((text) => (
              <View key={text} style={styles.benefitRow}>
                <View style={styles.benefitCheck}>
                  <Ionicons name="checkmark" size={12} color={colors.white} />
                </View>
                <Text style={styles.benefitText}>{text}</Text>
              </View>
            ))}
          </View>
          {props.suggestedFare ? (
            <Pressable style={styles.useSuggestedButton} onPress={props.onUseSuggestedFare}>
              <Ionicons name="sparkles-outline" size={17} color={colors.navy} />
              <Text style={styles.useSuggestedText}>Use admin suggested price</Text>
            </Pressable>
          ) : null}
        </Card>

        <Card style={styles.priceInputCard}>
          <View style={styles.priceInputHead}>
            <View style={styles.roundIcon}>
              <Ionicons name="pricetag-outline" size={22} color={colors.navy} />
            </View>
            <View>
              <Text style={styles.priceInputTitle}>Name your price</Text>
              <Text style={styles.priceInputHint}>Drivers can accept or counter your offer</Text>
            </View>
          </View>
          <View style={styles.priceInputRow}>
            <TextInput
              value={props.price}
              onChangeText={props.setPrice}
              keyboardType="numeric"
              placeholder="e.g. 110"
              placeholderTextColor={colors.faded}
              style={styles.priceInput}
            />
            <Text style={styles.madLabel}>MAD</Text>
          </View>
        </Card>

        <Text style={styles.fieldTitle}>Payment method</Text>
        <View style={styles.paymentBox}>
          <PaymentChoice active={props.payment === 'cash'} title="Cash" subtitle={props.freeLaunch ? 'Card disabled during free launch' : 'Pay the driver'} icon="cash" onPress={() => props.setPayment('cash')} />
          {!props.freeLaunch ? (
            <PaymentChoice active={props.payment === 'card'} title="Card" subtitle="Pay securely" icon="card-outline" onPress={() => props.setPayment('card')} />
          ) : null}
        </View>
        {props.freeLaunch ? <Text style={styles.freeModeHint}>Billing off: card payments are disabled during the free launch period.</Text> : null}

        <PrimaryButton label="Find Driver" loading={props.loading} onPress={props.onFindDriver} />
        <SafetyText text="Your safety is our priority" />
      </ScrollView>
    </View>
  );
}

function OffersScreen({
  offers,
  order,
  loading,
  onBack,
  onProfile,
  onChoose,
  onRefresh,
  onCancel,
  notice,
}: {
  offers: OrderOffer[];
  order: Order | null;
  loading: boolean;
  onBack: () => void;
  onProfile: (offer: OrderOffer) => void;
  onChoose: (offer: OrderOffer) => void;
  onRefresh: () => void;
  onCancel: () => void;
  notice: string | null;
}) {
  return (
    <View style={styles.lightScreen}>
      <LightHeader title="Driver Offers" onBack={onBack} right={<Ionicons name="notifications-outline" size={28} color={colors.navy} />} />
      <ScrollView contentContainerStyle={styles.lightContent} showsVerticalScrollIndicator={false}>
        <Card style={styles.offerRoute}>
          <View style={styles.routePins}>
            <Pin color={colors.navy} />
            <View style={styles.dottedLine} />
            <Pin color={colors.rust} />
          </View>
          <View style={{ flex: 1, gap: 18 }}>
            <View>
              <Text style={styles.orangeLabel}>From</Text>
              <Text style={styles.routeValue}>{order?.pickup_address ?? 'Pickup address'}</Text>
            </View>
            <View>
              <Text style={styles.orangeLabel}>To</Text>
              <Text style={styles.routeValue}>{order?.dropoff_address ?? 'Dropoff address'}</Text>
            </View>
          </View>
          <MaterialCommunityIcons name="mosque" size={72} color={colors.rustLight} />
        </Card>

        <Card style={styles.broadcastCard}>
          <MaterialCommunityIcons name="broadcast" size={38} color={colors.rust} />
          <View style={{ flex: 1 }}>
            <Text style={styles.broadcastTitle}>
              {offers.length > 0 ? `${offers.length} driver offer${offers.length === 1 ? '' : 's'} ready` : 'Finding nearby drivers'}
            </Text>
            <Text style={styles.subtle}>{offers.length > 0 ? 'Compare the price and vehicle, then choose your driver.' : 'Offers appear here automatically as drivers respond.'}</Text>
          </View>
          {loading ? <ActivityIndicator color={colors.rust} /> : <View style={styles.liveDot} />}
        </Card>
        {notice ? <Text style={styles.noticeText}>{notice}</Text> : null}

        {offers.length === 0 ? (
          <Card style={styles.emptyDriversCard}>
            <View style={styles.emptyOfferIcon}><Ionicons name="car-sport-outline" size={28} color={colors.rust} /></View>
            <Text style={styles.broadcastTitle}>{loading ? 'Checking for offers…' : 'Waiting for the first offer'}</Text>
            <Text style={styles.emptyOfferText}>You can leave this screen open. We refresh automatically when a nearby driver responds.</Text>
            {loading ? <ActivityIndicator color={colors.rust} /> : null}
          </Card>
        ) : null}

        {offers.map((offer) => (
          <DriverOffer key={offer.id} offer={offer} order={order} onProfile={onProfile} onChoose={onChoose} />
        ))}
        <View style={styles.offerFooterActions}>
          <Pressable onPress={onRefresh} disabled={loading} style={styles.refreshOffersButton}>
            {loading ? <ActivityIndicator size="small" color={colors.navy} /> : <Ionicons name="refresh" size={19} color={colors.navy} />}
            <Text style={styles.refreshOffersText}>{loading ? 'Refreshing…' : 'Refresh offers'}</Text>
          </Pressable>
          <Pressable onPress={onCancel} disabled={loading} style={styles.cancelRideButton}>
            <Ionicons name="close-circle-outline" size={19} color="#b84747" />
            <Text style={styles.cancelRideText}>Cancel request</Text>
          </Pressable>
        </View>
        <SafetyText text="Your safety is our priority" />
      </ScrollView>
    </View>
  );
}

function ProfileScreen({ driver, onBack, onChoose }: { driver: CatalogDriver | null; onBack: () => void; onChoose: () => void }) {
  const name = driver?.name ?? 'Driver profile';
  const initials = driver?.initials || name.slice(0, 2).toUpperCase();
  const vehicle = driver?.vehicle_name ?? driver?.vehicle_type ?? 'Vehicle pending';
  const plate = driver?.vehicle_plate ?? 'Plate pending';
  const vehicleType = driver?.vehicle_type ?? 'vehicle';

  return (
    <View style={styles.screen}>
      <ProfileHeader onBack={onBack} />
      <ScrollView style={styles.profileSheet} contentContainerStyle={styles.profileContent} showsVerticalScrollIndicator={false}>
        <Card style={styles.profileHeroCard}>
          <View style={styles.profileHeroMain}>
            <Avatar initials={initials} size={78} />
            <View style={styles.profileHeroCopy}>
              <Text numberOfLines={2} ellipsizeMode="middle" style={styles.profileHeroName}>{name}</Text>
              <View style={styles.profileStatusRow}>
                <View style={styles.verifiedPill}><Ionicons name="shield-checkmark" size={14} color={colors.green} /><Text style={styles.verifiedPillText}>Verified</Text></View>
                <View style={[styles.onlinePill, !driver?.online_status && styles.offlinePill]}><View style={[styles.profileOnlineDot, !driver?.online_status && { backgroundColor: colors.faded }]} /><Text style={styles.onlinePillText}>{driver?.online_status ? 'Online' : 'Offline'}</Text></View>
              </View>
            </View>
          </View>
          <View style={styles.profileTrustLine}>
            <Ionicons name="lock-closed-outline" size={16} color={colors.muted} />
            <Text style={styles.profileTrustText}>Identity and required documents reviewed by MoroRide</Text>
          </View>
        </Card>

        <View style={styles.profileSectionHead}><Text style={styles.profileSectionTitle}>Vehicle details</Text><Text style={styles.profileSectionMeta}>Live profile</Text></View>
        {driver?.vehicle_photos?.length ? (
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.profilePhotoGallery}>
            {driver.vehicle_photos.map((photo) => (
              <View key={photo.id} style={styles.profilePhotoCard}>
                <Image source={{ uri: photo.url }} style={styles.profilePhotoImage} resizeMode="cover" />
                <View style={styles.profilePhotoLabel}><Text style={styles.profilePhotoLabelText}>{photo.type === 'vehicle_in' ? 'Interior' : 'Exterior'}</Text></View>
              </View>
            ))}
          </ScrollView>
        ) : null}
        <Card style={styles.profileVehicleCard}>
          <View style={styles.profileVehicleVisual}><FontAwesome5 name="car-side" size={48} color={colors.navy} /></View>
          <View style={{ flex: 1 }}>
            <Text numberOfLines={1} style={styles.profileVehicleName}>{vehicle}</Text>
            <Text style={styles.profileVehicleType}>{vehicleType}</Text>
            <View style={styles.plateBox}><Text style={styles.plateBoxLabel}>PLATE</Text><Text numberOfLines={1} style={styles.plateBoxValue}>{plate}</Text></View>
          </View>
        </Card>

        <Card style={styles.profileRecordCard}>
          <View style={styles.profileRecordItem}><Text style={styles.profileRecordLabel}>Driver ID</Text><Text style={styles.profileRecordValue}>{driver ? `#${driver.id}` : '—'}</Text></View>
          <View style={styles.profileRecordDivider} />
          <View style={styles.profileRecordItem}><Text style={styles.profileRecordLabel}>Approval</Text><Text style={[styles.profileRecordValue, { color: colors.green }]}>{driver?.approval_state ?? '—'}</Text></View>
          <View style={styles.profileRecordDivider} />
          <View style={styles.profileRecordItem}><Text style={styles.profileRecordLabel}>Availability</Text><Text style={styles.profileRecordValue}>{driver?.online_status ? 'Online' : 'Offline'}</Text></View>
        </Card>

        <PrimaryButton label="Choose this Driver" onPress={onChoose} />
        <SafetyText text="Your safety is our priority" />
      </ScrollView>
    </View>
  );
}

function TrackingScreen({ order, driver, driverCoord, notice, onBack, onChat, onCall, onPay, onComplete }: { order: Order | null; driver: CatalogDriver | null; driverCoord: DemoCoord | null; notice: string | null; onBack: () => void; onChat: () => void; onCall: () => void; onPay: () => void; onComplete: () => void }) {
  const driverName = driver?.name ?? 'Assigned driver';
  const vehicle = driver?.vehicle_name ?? driver?.vehicle_type ?? 'Vehicle pending';
  const plate = driver?.vehicle_plate ?? 'Plate pending';
  const fare = order?.final_fare ?? order?.offered_fare;
  const pickup = order?.pickup_address ?? 'Jemaa El Fnaa, Marrakech';
  const dropoff = order?.dropoff_address ?? 'Essaouira Medina';
  const eta = order?.eta_min ?? 28;
  const distance = order?.distance_km ?? 9.4;
  const routeProgress = 42;
  const demoPickup = order?.pickup_lat != null && order.pickup_lng != null
    ? { lat: order.pickup_lat, lng: order.pickup_lng }
    : { lat: 31.6258, lng: -7.9891 };
  const demoDropoff = order?.dropoff_lat != null && order.dropoff_lng != null
    ? { lat: order.dropoff_lat, lng: order.dropoff_lng }
    : { lat: 31.6417, lng: -8.0033 };
  const demoCar = { lat: 31.6325, lng: -7.9953 };

  return (
    <View style={styles.lightScreen}>
      <LightHeader title="Live Ride Tracking" subtitle="Enjoy your journey with MoroRide" onBack={onBack} right={<ShareButton />} />
      <ScrollView contentContainerStyle={styles.trackingContent} showsVerticalScrollIndicator={false}>
        {notice ? (
          <View style={styles.driverAcceptedBanner}>
            <View style={styles.driverAcceptedIcon}><Ionicons name="checkmark" size={21} color={colors.white} /></View>
            <View style={{ flex: 1 }}><Text style={styles.driverAcceptedTitle}>Driver accepted</Text><Text style={styles.driverAcceptedText}>{notice}</Text></View>
            <Pressable accessibilityRole="button" onPress={onChat} style={styles.chatNowButton}><Ionicons name="chatbubble-ellipses" size={17} color={colors.white} /><Text style={styles.chatNowText}>Chat</Text></Pressable>
          </View>
        ) : null}
        <View style={styles.mapCard}>
          <RouteMap
            pickupCoord={demoPickup}
            dropoffCoord={demoDropoff}
            carCoord={driverCoord ?? demoCar}
            pickup={pickup}
            dropoff={dropoff}
            eta={eta}
            distance={distance}
          />
        </View>

        <Card style={styles.timelineCard}>
          {[
            ['Assigned', 'Done', true],
            ['Arriving', `${eta} min`, true],
            ['Progress', `${routeProgress}%`, true],
            ['Payment', order?.payment_method ?? 'cash', false],
            ['Fare', fare ? `${fare} MAD` : '-', false],
          ].map(([label, time, active], index) => (
            <View key={label as string} style={styles.timelineItem}>
              <View style={[styles.timelineIcon, active && styles.timelineIconActive]}>
                <Ionicons name={index === 0 ? 'checkmark' : index === 1 ? 'car-sport-outline' : index === 4 ? 'flag-outline' : 'person-outline'} size={20} color={active ? colors.white : colors.faded} />
              </View>
              <Text style={[styles.timelineLabel, active && styles.timelineLabelActive]}>{label}</Text>
              <Text style={styles.timelineTime}>{time}</Text>
            </View>
          ))}
        </Card>

        <Card style={styles.driverTracking}>
          <Avatar initials={driver?.initials ?? 'DR'} size={86} />
          <View style={{ flex: 1 }}>
            <View style={styles.nameRow}>
              <Text style={styles.profileName}>{driverName}</Text>
              <Text style={styles.ratingPill}>{driver?.online_status ? 'Online' : 'Approved'}</Text>
            </View>
            <Text style={styles.profileMeta}>{vehicle}</Text>
            <Text style={styles.plate}>{plate}</Text>
          </View>
          <View style={styles.etaBox}>
            <Text style={styles.subtle}>ETA</Text>
            <Text style={styles.etaText}>{order?.eta_min ?? '-'} min</Text>
            <Text style={styles.subtle}>Ride price</Text>
            <Text style={styles.orangePrice}>{fare ? `${fare} MAD` : '-'}</Text>
          </View>
        </Card>

        <View style={styles.actionGrid}>
          <MiniAction icon="chatbox-outline" label="Chat with driver" onPress={onChat} />
          <MiniAction icon="call-outline" label="Call driver" onPress={onCall} />
          <MiniAction icon="shield-checkmark-outline" label="Safety & Support" />
        </View>
        {order?.payment_method === 'card' ? (
          <Pressable onPress={onPay} style={styles.payCardButton}>
            <Ionicons name="card-outline" size={20} color={colors.white} />
            <Text style={styles.payCardText}>Pay by card ({fare ? `${fare} MAD` : '—'})</Text>
          </Pressable>
        ) : null}
        <PrimaryButton label="Complete ride" onPress={onComplete} />
      </ScrollView>
    </View>
  );
}

function RiderHistoryScreen({ orders, loading, notice, onBack, onOpenChat }: {
  orders: Order[]; loading: boolean; notice: string | null; onBack: () => void; onOpenChat: (order: Order) => void;
}) {
  return (
    <View style={styles.lightScreen}>
      <LightHeader title="Ride history" subtitle="Your completed and cancelled rides" onBack={onBack} right={<Ionicons name="time-outline" size={27} color={colors.navy} />} />
      <ScrollView contentContainerStyle={styles.archiveContent} showsVerticalScrollIndicator={false}>
        {loading ? <ActivityIndicator color={colors.rust} size="large" /> : null}
        {!loading && orders.length === 0 ? <ArchiveEmpty icon="time-outline" title="No ride history yet" body="Your finished rides will appear here." /> : null}
        {orders.map((order) => (
          <Card key={order.id} style={styles.riderArchiveCard}>
            <View style={styles.archiveRowBetween}>
              <View><Text style={styles.archiveRideTitle}>Ride #{order.id}</Text><Text style={styles.archiveDate}>{formatArchiveDate(order.created_at)}</Text></View>
              <ArchiveStatus status={order.status} />
            </View>
            <ArchiveRoute from={order.pickup_address} to={order.dropoff_address} />
            <View style={styles.archiveRowBetween}>
              <Text style={styles.archiveMeta}>{Number(order.distance_km || 0)} km · {order.payment_method ?? 'cash'}</Text>
              <Text style={styles.archiveFare}>{Number(order.final_fare ?? order.offered_fare ?? 0)} MAD</Text>
            </View>
            {order.assigned_driver_id ? <Pressable style={styles.archiveChatButton} onPress={() => onOpenChat(order)}><Ionicons name="chatbubble-ellipses-outline" size={18} color={colors.navy} /><Text style={styles.archiveChatText}>Open ride chat</Text></Pressable> : null}
          </Card>
        ))}
        {notice ? <Text style={styles.noticeText}>{notice}</Text> : null}
      </ScrollView>
    </View>
  );
}

function RiderMessagesScreen({ conversations, loading, notice, onBack, onOpenChat }: {
  conversations: RideConversation[]; loading: boolean; notice: string | null; onBack: () => void; onOpenChat: (order: Order) => void;
}) {
  return (
    <View style={styles.lightScreen}>
      <LightHeader title="Messages" subtitle="All your ride conversations" onBack={onBack} right={<Ionicons name="chatbubbles-outline" size={27} color={colors.navy} />} />
      <ScrollView contentContainerStyle={styles.archiveContent} showsVerticalScrollIndicator={false}>
        {loading ? <ActivityIndicator color={colors.rust} size="large" /> : null}
        {!loading && conversations.length === 0 ? <ArchiveEmpty icon="chatbubbles-outline" title="No messages yet" body="Chats with your drivers will appear here." /> : null}
        {conversations.map(({ order, messages_count, last_message }) => (
          <Pressable key={order.id} onPress={() => onOpenChat(order)}>
            <Card style={styles.riderConversationCard}>
              <View style={styles.archiveMessageIcon}><Ionicons name="chatbubble" size={20} color={colors.white} /></View>
              <View style={{ flex: 1 }}>
                <View style={styles.archiveRowBetween}><Text style={styles.archiveRideTitle}>Ride #{order.id}</Text><Text style={styles.archiveDate}>{formatArchiveDate(last_message?.created_at)}</Text></View>
                <Text style={styles.archiveRoutePreview} numberOfLines={1}>{order.pickup_address} → {order.dropoff_address}</Text>
                <Text style={styles.archiveMessagePreview} numberOfLines={1}>{last_message?.text || (last_message?.image_url ? 'Photo' : 'Open conversation')}</Text>
              </View>
              <View style={styles.archiveMessageCount}><Text style={styles.archiveMessageCountText}>{messages_count}</Text></View>
              <Ionicons name="chevron-forward" size={18} color={colors.muted} />
            </Card>
          </Pressable>
        ))}
        {notice ? <Text style={styles.noticeText}>{notice}</Text> : null}
      </ScrollView>
    </View>
  );
}

function ArchiveRoute({ from, to }: { from: string; to: string }) {
  return <View style={styles.archiveRoute}><View style={styles.archiveRouteLine}><View style={[styles.archiveDot, { backgroundColor: colors.green }]} /><View style={styles.archiveBar} /><View style={[styles.archiveDot, { backgroundColor: colors.rust }]} /></View><View style={{ flex: 1 }}><Text style={styles.archiveRouteText} numberOfLines={1}>{from}</Text><Text style={[styles.archiveRouteText, { marginTop: 13 }]} numberOfLines={1}>{to}</Text></View></View>;
}

function ArchiveStatus({ status }: { status: string }) {
  const completed = status === 'completed';
  return <View style={[styles.archiveStatus, { backgroundColor: completed ? colors.greenSoft : '#fbe9e4' }]}><Text style={[styles.archiveStatusText, { color: completed ? colors.green : colors.rust }]}>{status.replace('_', ' ').toUpperCase()}</Text></View>;
}

function ArchiveEmpty({ icon, title, body }: { icon: string; title: string; body: string }) {
  return <Card style={styles.archiveEmpty}><Ionicons name={icon as never} size={40} color={colors.faded} /><Text style={styles.archiveRideTitle}>{title}</Text><Text style={styles.subtle}>{body}</Text></Card>;
}

function formatArchiveDate(value?: string): string {
  if (!value) return '';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
}

function ChatScreen({ messages, loading, notice, onBack, onSend }: {
  messages: ChatMessage[];
  loading: boolean;
  notice: string | null;
  onBack: () => void;
  onSend: (text: string) => void;
}) {
  const [draft, setDraft] = useState('');

  function submit() {
    const text = draft.trim();
    if (!text) return;
    onSend(text);
    setDraft('');
  }

  return (
    <View style={styles.lightScreen}>
      <LightHeader title="Ride chat" subtitle="Private to ride participants" onBack={onBack} right={<Ionicons name="shield-checkmark-outline" size={27} color={colors.navy} />} />
      <ScrollView contentContainerStyle={styles.chatContent} showsVerticalScrollIndicator={false}>
        {messages.length === 0 && !loading ? (
          <Card style={styles.emptyDriversCard}>
            <Text style={styles.broadcastTitle}>No messages yet.</Text>
            <Text style={styles.subtle}>Send a message to your assigned driver.</Text>
          </Card>
        ) : null}
        {messages.map((message) => (
          <View key={message.id} style={[styles.chatBubble, message.sender_role === 'rider' && styles.chatBubbleMine]}>
            <Text style={styles.chatRole}>{message.sender_role}</Text>
            <Text style={styles.chatText}>{message.text ?? 'Attachment'}</Text>
          </View>
        ))}
        {loading ? <ActivityIndicator color={colors.rust} /> : null}
        {notice ? <Text style={styles.noticeText}>{notice}</Text> : null}
      </ScrollView>
      <View style={styles.chatComposer}>
        <TextInput
          value={draft}
          onChangeText={setDraft}
          onSubmitEditing={submit}
          placeholder="Message your driver..."
          placeholderTextColor={colors.faded}
          style={styles.chatInput}
        />
        <Pressable onPress={submit} disabled={loading || !draft.trim()} style={styles.chatSend}>
          <Ionicons name="send" size={22} color={colors.white} />
        </Pressable>
      </View>
    </View>
  );
}

function CompletedScreen({
  order,
  driver,
  rating,
  setRating,
  loading,
  notice,
  onDone,
}: {
  order: Order | null;
  driver: CatalogDriver | null;
  rating: number;
  setRating: (value: number) => void;
  loading: boolean;
  notice: string | null;
  onDone: (comment?: string) => void;
}) {
  const [comment, setComment] = useState('');
  const driverName = driver?.name ?? 'Your driver';
  const fare = order?.final_fare ?? order?.offered_fare ?? Number.NaN;
  const paymentMethod = order?.payment_method ? order.payment_method[0].toUpperCase() + order.payment_method.slice(1) : 'Payment';

  return (
    <View style={styles.screen}>
      <DarkHeader left={<Ionicons name="arrow-back" size={28} color={colors.white} />} />
      <ScrollView style={styles.sheet} contentContainerStyle={styles.completedContent} showsVerticalScrollIndicator={false}>
        <View style={styles.completedHero}>
          <View style={styles.successCircle}>
            <Ionicons name="checkmark" size={42} color={colors.white} />
          </View>
          <View style={{ flex: 1 }}>
            <Text style={styles.completedTitle}>Ride completed</Text>
            <Text style={styles.completedSub}>Thank you for riding with MoroRide.</Text>
          </View>
        </View>

        <Card style={styles.totalFareCard}>
          <View>
            <Text style={styles.smallTitle}>Total fare</Text>
            <View style={styles.fareLine}>
              <Text style={styles.fareNumber}>{Number.isFinite(fare) ? fare : '-'}</Text>
              <Text style={styles.currency}>MAD</Text>
            </View>
            <Text style={styles.subtle}>Paid to driver</Text>
          </View>
          <View style={styles.fareIllustration}><MaterialCommunityIcons name="mosque" size={52} color={colors.rust} /></View>
        </Card>

        <Card style={styles.paidCard}>
          <View style={styles.cashIcon}>
            <Ionicons name="cash-outline" size={30} color={colors.white} />
          </View>
          <View style={{ flex: 1 }}>
            <Text style={styles.subtle}>Payment method</Text>
            <Text style={styles.routeValue}>{paymentMethod}</Text>
          </View>
          <View style={styles.paidPill}>
            <Ionicons name="checkmark-circle" size={22} color={colors.green} />
            <Text style={styles.paidText}>Paid</Text>
          </View>
        </Card>

        <Card style={styles.completedDriver}>
          <Avatar initials={driver?.initials ?? 'DR'} size={62} />
          <View style={{ flex: 1 }}>
            <Text style={styles.subtle}>Your driver</Text>
            <Text style={styles.profileName}>{driverName}</Text>
            <Text style={styles.profileMeta}>{driver?.vehicle_name ?? driver?.vehicle_type ?? 'Vehicle profile'}</Text>
          </View>
          <View style={styles.callBox}>
            <Ionicons name="call" size={28} color={colors.navy} />
          </View>
        </Card>

        <Card style={styles.ratingCard}>
          <Text style={styles.fieldTitle}>How was your ride?</Text>
          <Text style={styles.subtle}>Rate your experience with {driverName}</Text>
          <View style={styles.starsRow}>
            {[1, 2, 3, 4, 5].map((item) => (
              <Pressable key={item} onPress={() => setRating(item)}>
                <Ionicons name={rating >= item ? 'star' : 'star-outline'} size={34} color={colors.rust} />
              </Pressable>
            ))}
          </View>
          <Text style={styles.inputLabel}>Tell us more (optional)</Text>
          <TextInput value={comment} onChangeText={setComment} style={styles.reviewInput} placeholder="Share your experience..." placeholderTextColor={colors.faded} multiline />
        </Card>
        {notice ? <Text style={styles.noticeText}>{notice}</Text> : null}
        <PrimaryButton label="Submit Rating" loading={loading} onPress={() => onDone(comment.trim() || undefined)} />
        <SafetyText text="Your feedback helps us improve" />
      </ScrollView>
    </View>
  );
}

function DarkHeader({ left, onNotifications }: { left: ReactNode; onNotifications?: () => void }) {
  return (
    <View style={styles.darkHeader}>
      <View style={styles.headerNav}>
        <View style={styles.headerIcon}>{left}</View>
        <View style={styles.logoRow}>
          <MoroMark />
          <View>
            <Text style={styles.logoText}>MoroRide</Text>
            <Text style={styles.logoTag}>Your journey, our hospitality</Text>
          </View>
        </View>
        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Open notifications"
          onPress={onNotifications}
          style={({ pressed }) => [styles.notificationButton, pressed && styles.pressed]}
        >
          <Ionicons name="notifications-outline" size={27} color={colors.white} />
          <View style={styles.notificationDot} />
        </Pressable>
      </View>
    </View>
  );
}

function MoroMark() {
  return (
    <Image source={moroLogoMark} style={styles.moroMarkImage} resizeMode="contain" />
  );
}

function AppMenu({
  activeScreen,
  messageBadge,
  onClose,
  onNavigate,
  onFindDriver,
  onSwitchRole,
}: {
  activeScreen: Screen;
  messageBadge: number;
  onClose: () => void;
  onNavigate: (screen: Screen) => void;
  onFindDriver: () => void;
  onSwitchRole: () => void;
}) {
  const progress = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    Animated.timing(progress, {
      toValue: 1,
      duration: 520,
      easing: Easing.out(Easing.cubic),
      useNativeDriver: true,
    }).start();
  }, [progress]);

  function closeAfter(callback: () => void) {
    Animated.timing(progress, {
      toValue: 0,
      duration: 340,
      easing: Easing.in(Easing.cubic),
      useNativeDriver: true,
    }).start(({ finished }) => {
      if (finished) callback();
    });
  }

  const panelTranslate = progress.interpolate({
    inputRange: [0, 1],
    outputRange: [-360, 0],
  });
  const backdropOpacity = progress.interpolate({
    inputRange: [0, 1],
    outputRange: [0, 1],
  });

  const items: { label: string; icon: string; screen: Screen }[] = [
    { label: 'Book ride', icon: 'map-outline', screen: 'booking' },
    { label: 'Ride history', icon: 'time-outline', screen: 'history' },
    { label: 'Messages', icon: 'chatbubbles-outline', screen: 'messages' },
    { label: 'Driver offers', icon: 'car-sport-outline', screen: 'offers' },
    { label: 'Driver profile', icon: 'person-circle-outline', screen: 'profile' },
    { label: 'Live tracking', icon: 'navigate-outline', screen: 'tracking' },
    { label: 'Completed ride', icon: 'checkmark-circle-outline', screen: 'completed' },
  ];

  return (
    <View style={styles.menuLayer}>
      <Animated.View style={[styles.menuBackdrop, { opacity: backdropOpacity }]}>
        <Pressable style={styles.menuBackdropPressable} onPress={() => closeAfter(onClose)} />
      </Animated.View>
      <Animated.View style={[styles.menuPanel, { transform: [{ translateX: panelTranslate }] }]}>
        <View style={styles.menuHead}>
          <View>
            <Text style={styles.menuEyebrow}>MoroRide</Text>
            <Text style={styles.menuTitle}>Ride control</Text>
          </View>
          <Pressable accessibilityRole="button" accessibilityLabel="Close" onPress={() => closeAfter(onClose)} style={styles.menuClose}>
            <Ionicons name="close" size={24} color={colors.navy} />
          </Pressable>
        </View>

        <View style={styles.menuItems}>
          {items.map((item) => (
            <Pressable
              key={item.screen}
              onPress={() => closeAfter(() => onNavigate(item.screen))}
              style={[styles.menuItem, activeScreen === item.screen && styles.menuItemActive]}
            >
              <Ionicons name={item.icon as never} size={22} color={activeScreen === item.screen ? colors.white : colors.navy} />
              <Text style={[styles.menuItemText, activeScreen === item.screen && styles.menuItemTextActive]}>{item.label}</Text>
              {item.screen === 'messages' && messageBadge > 0 ? <View style={styles.menuMessageBadge}><Text style={styles.menuMessageBadgeText}>{messageBadge > 9 ? '9+' : messageBadge}</Text></View> : null}
              <Ionicons name="chevron-forward" size={18} color={activeScreen === item.screen ? colors.white : colors.muted} />
            </Pressable>
          ))}
        </View>

        <Pressable onPress={() => closeAfter(onFindDriver)} style={styles.menuPrimary}>
          <Text style={styles.menuPrimaryText}>Create order + show offers</Text>
          <Ionicons name="arrow-forward" size={22} color={colors.white} />
        </Pressable>
        <Pressable onPress={() => closeAfter(onSwitchRole)} style={styles.menuSwitch}>
          <Ionicons name="log-out-outline" size={20} color={colors.navy} />
          <Text style={styles.menuSwitchText}>Log out</Text>
        </Pressable>
        <Text style={styles.menuHint}>Move between the available screens.</Text>
      </Animated.View>
    </View>
  );
}

function NotificationPanel({
  notifications,
  loading,
  onClose,
  onOpenCall,
}: {
  notifications: AppNotification[];
  loading: boolean;
  onClose: () => void;
  onOpenCall: (orderId: number) => void;
}) {
  const progress = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    Animated.timing(progress, {
      toValue: 1,
      duration: 420,
      easing: Easing.out(Easing.cubic),
      useNativeDriver: true,
    }).start();
  }, [progress]);

  function closeAnimated() {
    Animated.timing(progress, {
      toValue: 0,
      duration: 260,
      easing: Easing.in(Easing.cubic),
      useNativeDriver: true,
    }).start(({ finished }) => {
      if (finished) onClose();
    });
  }

  const translateY = progress.interpolate({
    inputRange: [0, 1],
    outputRange: [-180, 0],
  });
  const opacity = progress.interpolate({
    inputRange: [0, 1],
    outputRange: [0, 1],
  });

  return (
    <View style={styles.notificationLayer}>
      <Animated.View style={[styles.notificationBackdrop, { opacity }]}>
        <Pressable style={styles.menuBackdropPressable} onPress={closeAnimated} />
      </Animated.View>
      <Animated.View style={[styles.notificationPanel, { opacity, transform: [{ translateY }] }]}>
        <View style={styles.notificationHead}>
          <View>
            <Text style={styles.menuEyebrow}>MoroRide</Text>
            <Text style={styles.notificationTitle}>Notifications</Text>
          </View>
          <Pressable accessibilityRole="button" accessibilityLabel="Close notifications" onPress={closeAnimated} style={styles.menuClose}>
            <Ionicons name="close" size={24} color={colors.navy} />
          </Pressable>
        </View>

        {loading ? (
          <View style={styles.notificationLoading}>
            <ActivityIndicator color={colors.rust} />
            <Text style={styles.subtle}>Loading notifications...</Text>
          </View>
        ) : null}

        {!loading && notifications.length === 0 ? (
          <View style={styles.notificationEmpty}>
            <Ionicons name="notifications-off-outline" size={26} color={colors.muted} />
            <Text style={styles.subtle}>No notifications right now.</Text>
          </View>
        ) : null}

        {!loading ? (
          <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={styles.notificationList}>
            {notifications.map((notification) => {
              const orderId = Number(notification.data?.order_id);
              const incomingCall = notification.type === 'incoming_voice_call' && Number.isFinite(orderId);
              const tone = notificationTone(notification.type);
              return (
              <Pressable
                key={notification.id}
                disabled={!incomingCall}
                onPress={() => incomingCall && onOpenCall(orderId)}
                style={[styles.notificationCard, !notification.read_at && styles.notificationCardUnread, { backgroundColor: tone.bg, borderColor: tone.border }]}
              >
                <View style={[styles.notificationAccent, { backgroundColor: incomingCall ? colors.green : tone.fg }]} />
                <View style={[styles.notificationIcon, { backgroundColor: incomingCall ? colors.greenSoft : tone.soft }]}>
                  <Ionicons name={(incomingCall ? 'call' : notificationIcon(notification.type)) as never} size={19} color={incomingCall ? colors.green : tone.fg} />
                </View>
                <View style={{ flex: 1 }}>
                  <View style={styles.notificationTopLine}>
                    <Text style={[styles.notificationPill, { color: tone.fg }]}>{incomingCall ? 'Incoming call' : tone.label}</Text>
                    {!notification.read_at ? <View style={[styles.notificationUnreadDot, { backgroundColor: tone.fg }]} /> : null}
                  </View>
                  <Text style={styles.notificationCardTitle}>{notification.title}</Text>
                  <Text style={styles.notificationCardBody}>{notification.body}</Text>
                  <Text style={[styles.notificationType, { color: tone.fg }]}>{notification.type.replace(/_/g, ' ')}</Text>
                  {incomingCall ? <Text style={styles.notificationType}>Tap to answer</Text> : null}
                </View>
              </Pressable>
            )})}
          </ScrollView>
        ) : null}
      </Animated.View>
    </View>
  );
}

function notificationIcon(type: string) {
  if (type.includes('call')) return 'call-outline';
  if (type.includes('approved')) return 'shield-checkmark-outline';
  if (type.includes('rejected') || type.includes('failed') || type.includes('cancelled')) return 'alert-circle-outline';
  if (type.includes('document') || type.includes('verification')) return 'document-text-outline';
  if (type.includes('chat') || type.includes('message')) return 'chatbubble-ellipses-outline';
  if (type.includes('driver')) return 'shield-checkmark-outline';
  if (type.includes('points')) return 'wallet-outline';
  if (type.includes('payment')) return 'card-outline';
  if (type.includes('completed')) return 'checkmark-circle-outline';
  return 'car-sport-outline';
}

function notificationTone(type: string) {
  if (type.includes('approved') || type.includes('completed') || type.includes('succeeded')) {
    return { bg: colors.card, border: colors.line, soft: colors.greenSoft, fg: colors.green, label: 'Approved' };
  }
  if (type.includes('rejected') || type.includes('failed') || type.includes('cancelled')) {
    return { bg: colors.card, border: colors.line, soft: '#fff1ef', fg: colors.rustDark, label: 'Attention' };
  }
  if (type.includes('document') || type.includes('verification') || type.includes('driver')) {
    return { bg: colors.card, border: colors.line, soft: '#fff8ef', fg: colors.rust, label: 'Verification' };
  }
  if (type.includes('chat') || type.includes('message') || type.includes('call')) {
    return { bg: colors.card, border: colors.line, soft: '#eef6fb', fg: colors.navy, label: 'Message' };
  }
  return { bg: colors.card, border: colors.line, soft: '#f6f2ec', fg: colors.rust, label: 'Activity' };
}

function ProfileHeader({ onBack }: { onBack: () => void }) {
  return (
    <View style={styles.profileHeader}>
      <View style={styles.profileNav}>
        <Pressable onPress={onBack}>
          <Ionicons name="chevron-back" size={34} color={colors.white} />
        </Pressable>
        <Text style={styles.profileHeaderTitle}>Driver Profile</Text>
        <MaterialCommunityIcons name="shield-check-outline" size={34} color={colors.white} />
      </View>
    </View>
  );
}

function LightHeader({ title, subtitle, onBack, right }: { title: string; subtitle?: string; onBack: () => void; right: ReactNode }) {
  return (
    <View style={styles.lightHeader}>
      <View style={styles.lightNav}>
        <Pressable onPress={onBack} style={styles.lightIconButton}>
          <Ionicons name="chevron-back" size={28} color={colors.navy} />
        </Pressable>
        <View style={{ alignItems: 'center', flex: 1 }}>
          <Text style={styles.lightTitle}>{title}</Text>
          {subtitle ? <Text style={styles.orangeLabel}>{subtitle}</Text> : null}
        </View>
        {right}
      </View>
    </View>
  );
}

function Card({ children, style }: { children: ReactNode; style?: object }) {
  return <View style={[styles.card, style]}>{children}</View>;
}

function BookingRoutePickerMap({ pickup, dropoff, onPickPickup, onPickDropoff }: {
  pickup: PickedLocation | null;
  dropoff: PickedLocation | null;
  onPickPickup: () => void;
  onPickDropoff: () => void;
}) {
  const ready = Boolean(pickup && dropoff);
  const from = pickup ?? DEMO_MAP_CENTER;
  const to = dropoff ?? pickup ?? DEMO_MAP_CENTER;
  const center = { lat: (from.lat + to.lat) / 2, lng: (from.lng + to.lng) / 2 };

  return (
    <View style={styles.bookingMapCard}>
      {ready ? (
        <View pointerEvents="none" style={StyleSheet.absoluteFillObject}>
          <NativeGoogleRouteMap
            pickupCoord={from}
            dropoffCoord={to}
            carCoord={center}
            center={center}
            pickup="Pickup"
            dropoff="Drop-off"
            showCar={false}
          />
        </View>
      ) : (
        <View style={styles.bookingMapEmpty}>
          <View style={styles.bookingMapEmptyIcon}><Ionicons name="map-outline" size={34} color={colors.rust} /></View>
          <Text style={styles.bookingMapEmptyTitle}>Choose your route on the map</Text>
          <Text style={styles.bookingMapEmptyHint}>{pickup ? 'Now choose the drop-off point' : 'Start with your pickup point'}</Text>
        </View>
      )}
      <View style={styles.bookingMapActions}>
        <Pressable accessibilityRole="button" accessibilityLabel="Choose Pickup on map" onPress={onPickPickup} style={[styles.mapPointButton, pickup && styles.mapPointButtonDone]}>
          <View style={[styles.mapPointDot, { backgroundColor: colors.navy }]}><Ionicons name={pickup ? 'checkmark' : 'location'} size={16} color={colors.white} /></View>
          <View style={styles.mapPointCopy}>
            <Text style={styles.mapPointButtonText}>PICKUP</Text>
            <Text numberOfLines={1} style={styles.mapPointAddress}>{pickup?.address ?? 'Use my location or choose on map'}</Text>
          </View>
          <Ionicons name="chevron-forward" size={17} color={colors.navy} />
        </Pressable>
        <Pressable accessibilityRole="button" accessibilityLabel="Choose Drop-off on map" onPress={onPickDropoff} style={[styles.mapPointButton, dropoff && styles.mapPointButtonDone]}>
          <View style={[styles.mapPointDot, { backgroundColor: colors.rust }]}><Ionicons name={dropoff ? 'checkmark' : 'flag'} size={16} color={colors.white} /></View>
          <View style={styles.mapPointCopy}>
            <Text style={styles.mapPointButtonText}>DROP-OFF</Text>
            <Text numberOfLines={1} style={styles.mapPointAddress}>{dropoff?.address ?? 'Choose your destination'}</Text>
          </View>
          <Ionicons name="chevron-forward" size={17} color={colors.navy} />
        </Pressable>
      </View>
    </View>
  );
}

function Pin({ color }: { color: string }) {
  return (
    <View style={[styles.pin, { backgroundColor: color }]}>
      <Ionicons name="location" size={18} color={colors.white} />
    </View>
  );
}

function Stepper({ label, value, icon, onMinus, onPlus }: { label: string; value: number; icon: string; onMinus: () => void; onPlus: () => void }) {
  return (
    <Card style={styles.stepper}>
      <View style={styles.stepperHeader}>
        <View style={styles.roundIcon}>
          <Ionicons name={icon as never} size={21} color={colors.navy} />
        </View>
        <Text numberOfLines={1} style={styles.inputLabel}>{label}</Text>
      </View>
      <View style={styles.stepperControls}>
        <RoundButton icon="remove" onPress={onMinus} />
        <Text style={styles.stepperValue}>{value}</Text>
        <RoundButton icon="add" onPress={onPlus} />
      </View>
    </Card>
  );
}

function RoundButton({ icon, onPress }: { icon: string; onPress: () => void }) {
  return (
    <Pressable onPress={onPress} style={styles.roundButton}>
      <Ionicons name={icon as never} size={22} color={colors.navy} />
    </Pressable>
  );
}

function PaymentChoice({ active, title, subtitle, icon, onPress }: { active: boolean; title: string; subtitle: string; icon: string; onPress: () => void }) {
  return (
    <Pressable onPress={onPress} style={[styles.paymentChoice, active && styles.paymentChoiceActive]}>
      <View style={[styles.paymentIcon, active && styles.paymentIconActive]}>
        <Ionicons name={icon as never} size={29} color={active ? colors.white : colors.navy} />
      </View>
      <View>
        <Text style={[styles.paymentTitle, active && styles.paymentTitleActive]}>{title}</Text>
        <Text style={[styles.paymentSub, active && styles.paymentSubActive]}>{subtitle}</Text>
      </View>
    </Pressable>
  );
}

function PrimaryButton({ label, onPress, loading }: { label: string; onPress: () => void; loading?: boolean }) {
  return (
    <Pressable onPress={onPress} disabled={loading} style={({ pressed }) => [styles.primaryButton, pressed && styles.pressed]}>
      {loading ? <ActivityIndicator color={colors.white} /> : <Text style={styles.primaryText}>{label}</Text>}
      {!loading ? <Ionicons name="arrow-forward" size={33} color={colors.white} /> : null}
    </Pressable>
  );
}

function DriverOffer({ offer, order, onProfile, onChoose }: { offer: OrderOffer; order: Order | null; onProfile: (offer: OrderOffer) => void; onChoose: (offer: OrderOffer) => void }) {
  const driver = driverFromOffer(offer);
  const vehicle = driver.vehicle_name ?? driver.vehicle_type ?? 'Vehicle pending';
  const price = offer.amount ? `${offer.amount} MAD` : (order?.offered_fare ? `${order.offered_fare} MAD` : 'Waiting fare');

  return (
    <Card style={styles.driverOffer}>
      <View style={styles.offerTop}>
        <Avatar initials={driver.initials} size={64} />
        <View style={styles.offerDriverCopy}>
          <Text numberOfLines={2} ellipsizeMode="middle" style={styles.offerDriverName}>{driver.name}</Text>
          <View style={styles.offerDriverMetaRow}>
            <Text style={[styles.driverStatus, driver.online_status && styles.driverStatusOnline]}>{driver.online_status ? 'Online' : 'Verified'}</Text>
            <Text numberOfLines={1} style={styles.offerVehicleText}>{vehicle}</Text>
          </View>
        </View>
        <View style={styles.offerVerifiedIcon}><Ionicons name="shield-checkmark" size={22} color={colors.green} /></View>
      </View>

      <View style={styles.offerFacts}>
        <View style={styles.offerFactPrimary}>
          <View style={styles.offerFactIcon}><Ionicons name="pricetag-outline" size={18} color={colors.rust} /></View>
          <View style={{ flex: 1 }}><Text style={styles.offerFactLabel}>{offer.type === 'counter' ? 'COUNTER OFFER' : 'RIDE PRICE'}</Text><Text style={styles.offerPrice}>{price}</Text></View>
        </View>
        <View style={styles.offerFactSecondary}>
          <View style={styles.offerFactIcon}><Ionicons name="time-outline" size={18} color={colors.rust} /></View>
          <View><Text style={styles.offerFactLabel}>ARRIVES IN</Text><Text style={styles.offerEta}>{order ? `${order.eta_min} min` : '—'}</Text></View>
        </View>
      </View>
      <Text style={styles.offerMessage}>{offer.message || (offer.type === 'counter' ? 'The driver proposed a new fare for your trip.' : 'The driver accepted your requested fare.')}</Text>

      <View style={styles.offerButtons}>
        <Pressable onPress={() => onProfile(offer)} style={styles.outlineButton}>
          <Text style={styles.outlineText}>View Profile</Text>
        </Pressable>
        <Pressable onPress={() => onChoose(offer)} style={styles.chooseButton}>
          <Text style={styles.chooseText}>{offer.status === 'accepted' ? 'Track Driver' : 'Choose Driver'}</Text>
        </Pressable>
      </View>
    </Card>
  );
}

function Fact({ label, value }: { label: string; value: string }) {
  return (
    <View style={styles.fact}>
      <Text style={styles.subtle}>{label}</Text>
      <Text style={styles.factValue}>{value}</Text>
    </View>
  );
}

function Avatar({ initials, size }: { initials: string; size: number }) {
  return (
    <View style={[styles.avatar, { height: size, width: size, borderRadius: size / 2 }]}>
      <Text style={[styles.avatarText, { fontSize: size * 0.28 }]}>{initials}</Text>
      <View style={styles.avatarBadge}>
        <Ionicons name="checkmark" size={17} color={colors.white} />
      </View>
    </View>
  );
}

function ProfileStat({ icon, label, value }: { icon: string; label: string; value: string }) {
  return (
    <View style={styles.profileStat}>
      <Ionicons name={icon as never} size={29} color={colors.navy} />
      <Text style={styles.statLabel}>{label}</Text>
      <Text style={styles.statValue}>{value}</Text>
    </View>
  );
}

function RealMapTiles({ pickup, dropoff, car }: { pickup: DemoCoord; dropoff: DemoCoord; car: DemoCoord }) {
  const centerX = Math.floor(lngToTile(DEMO_MAP_CENTER.lng, DEMO_MAP_ZOOM));
  const centerY = Math.floor(latToTile(DEMO_MAP_CENTER.lat, DEMO_MAP_ZOOM));
  const tiles = [-1, 0, 1].flatMap((dy) =>
    [-1, 0, 1].map((dx) => ({
      key: `${dx}-${dy}`,
      x: centerX + dx,
      y: centerY + dy,
    }))
  );

  return (
    <View style={styles.realMapLayer}>
      <View style={styles.tileGrid}>
        {tiles.map((tile) => (
          <Image
            key={tile.key}
            source={{ uri: `https://tile.openstreetmap.org/${DEMO_MAP_ZOOM}/${tile.x}/${tile.y}.png` }}
            style={styles.mapTile}
          />
        ))}
      </View>
      <View style={styles.mapTint} />
      <View style={[styles.mapPulse, mapPointStyle(car)]} />
    </View>
  );
}

function RouteMap({
  pickupCoord,
  dropoffCoord,
  carCoord,
  pickup,
  dropoff,
  eta,
  distance,
}: {
  pickupCoord: DemoCoord;
  dropoffCoord: DemoCoord;
  carCoord: DemoCoord;
  pickup: string;
  dropoff: string;
  eta: number;
  distance: number;
}) {
  // Real Google map on both native (react-native-maps) and web (Embed iframe).
  // react-native-maps uses the native Google Maps SDK on Android and does not
  // need the browser Embed key. The old check incorrectly showed the slow
  // nine-tile fallback in Expo whenever the web key was absent.
  if (Platform.OS !== 'web' || hasGoogleMapsKey) {
    return (
      <>
        <NativeGoogleRouteMap
          pickupCoord={pickupCoord}
          dropoffCoord={dropoffCoord}
          carCoord={carCoord}
          center={carCoord ?? DEMO_MAP_CENTER}
          pickup={pickup}
          dropoff={dropoff}
        />
        <RouteMapOverlay pickup={pickup} dropoff={dropoff} eta={eta} distance={distance} />
      </>
    );
  }

  return (
    <>
      <RealMapTiles pickup={pickupCoord} dropoff={dropoffCoord} car={carCoord} />
      <View style={styles.routePathMuted} />
      <View style={styles.routePathDone} />
      <View style={[styles.mapPin, styles.pickupPin, mapPointStyle(pickupCoord)]}>
        <Ionicons name="location" size={16} color={colors.white} />
      </View>
      <View style={[styles.mapPin, styles.dropoffPin, mapPointStyle(dropoffCoord)]}>
        <Ionicons name="flag" size={16} color={colors.white} />
      </View>
      <View style={[styles.carMarker, mapPointStyle(carCoord)]}>
        <FontAwesome5 name="car-side" size={22} color={colors.white} />
      </View>
      <RouteMapOverlay pickup={pickup} dropoff={dropoff} eta={eta} distance={distance} />
    </>
  );
}

function RouteMapOverlay({ pickup, dropoff, eta, distance }: { pickup: string; dropoff: string; eta: number; distance: number }) {
  return (
    <>
      <View style={styles.mapInfoCard}>
        <Text style={styles.mapInfoLabel}>Arriving in</Text>
        <Text style={styles.mapInfoValue}>{eta} min</Text>
        <Text style={styles.mapInfoSub}>{distance} km route</Text>
      </View>
      <View style={styles.mapBottomSheet}>
        <View style={styles.routeStop}>
          <View style={[styles.routeDot, { backgroundColor: colors.navy }]} />
          <View style={{ flex: 1 }}>
            <Text style={styles.mapInfoLabel}>Pickup</Text>
            <Text numberOfLines={1} style={styles.routeStopText}>{pickup}</Text>
          </View>
        </View>
        <View style={styles.routeStopDivider} />
        <View style={styles.routeStop}>
          <View style={[styles.routeDot, { backgroundColor: colors.rust }]} />
          <View style={{ flex: 1 }}>
            <Text style={styles.mapInfoLabel}>Dropoff</Text>
            <Text numberOfLines={1} style={styles.routeStopText}>{dropoff}</Text>
          </View>
        </View>
      </View>
    </>
  );
}

function lngToTile(lng: number, zoom: number) {
  return ((lng + 180) / 360) * 2 ** zoom;
}

function haversineKm(from: DemoCoord, to: DemoCoord) {
  const earthRadiusKm = 6371;
  const radians = (degrees: number) => (degrees * Math.PI) / 180;
  const dLat = radians(to.lat - from.lat);
  const dLng = radians(to.lng - from.lng);
  const lat1 = radians(from.lat);
  const lat2 = radians(to.lat);
  const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;
  return earthRadiusKm * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function latToTile(lat: number, zoom: number) {
  const latRad = (lat * Math.PI) / 180;
  return ((1 - Math.log(Math.tan(latRad) + 1 / Math.cos(latRad)) / Math.PI) / 2) * 2 ** zoom;
}

function mapPointStyle(coord: DemoCoord): ViewStyle {
  const centerX = Math.floor(lngToTile(DEMO_MAP_CENTER.lng, DEMO_MAP_ZOOM));
  const centerY = Math.floor(latToTile(DEMO_MAP_CENTER.lat, DEMO_MAP_ZOOM));
  const tileX = lngToTile(coord.lng, DEMO_MAP_ZOOM);
  const tileY = latToTile(coord.lat, DEMO_MAP_ZOOM);

  return {
    left: `${((tileX - (centerX - 1)) / 3) * 100}%`,
    top: `${((tileY - (centerY - 1)) / 3) * 100}%`,
  } as ViewStyle;
}

function ShareButton() {
  return (
    <View style={styles.shareButton}>
      <Ionicons name="share-social-outline" size={23} color={colors.navy} />
      <Text style={styles.shareText}>Share</Text>
    </View>
  );
}

function MiniAction({ icon, label, onPress }: { icon: string; label: string; onPress?: () => void }) {
  return (
    <Pressable onPress={onPress} disabled={!onPress} style={styles.miniAction}>
      <Ionicons name={icon as never} size={34} color={colors.navy} />
      <Text style={styles.miniActionText}>{label}</Text>
    </Pressable>
  );
}

function SafetyText({ text }: { text: string }) {
  return (
    <View style={styles.safetyText}>
      <MaterialCommunityIcons name="shield-check-outline" size={22} color={colors.muted} />
      <Text style={styles.safetyLabel}>{text}</Text>
    </View>
  );
}

const colors = {
  navy: '#082b4c',
  navy2: '#061f38',
  white: '#ffffff',
  cream: '#fcfaf7',
  sand: '#f7f4ef',
  card: '#ffffff',
  line: '#e6e0d8',
  muted: '#66788a',
  faded: '#9ba7b4',
  rust: '#c66b43',
  rustDark: '#a94f2f',
  rustLight: '#dca486',
  gold: '#d49a55',
  green: '#2f7d57',
  greenSoft: '#e8f5ee',
};

const shadow = {
  shadowColor: '#09223d',
  shadowOpacity: 0.08,
  shadowRadius: 16,
  shadowOffset: { width: 0, height: 8 },
  elevation: 2,
};

const styles = StyleSheet.create({
  appShell: {
    backgroundColor: '#f5f1ec',
    flex: 1,
    position: 'relative',
  },
  page: {
    alignItems: Platform.OS === 'web' ? 'center' : 'stretch',
    backgroundColor: '#f5f1ec',
    flex: 1,
  },
  phone: {
    backgroundColor: colors.cream,
    flex: 1,
    overflow: Platform.OS === 'web' ? 'hidden' : 'visible',
    position: 'relative',
    width: '100%',
    ...(Platform.OS === 'web'
      ? {
          borderColor: '#d7dce2',
          borderRadius: 32,
          borderWidth: 1,
          marginVertical: 16,
          maxHeight: 920,
          maxWidth: 430,
          boxShadow: '0 24px 80px rgba(8, 26, 45, .18)' as never,
        }
      : null),
  },
  freeLaunchWrap: {
    alignItems: 'center',
    elevation: 40,
    left: 0,
    paddingHorizontal: 14,
    position: 'absolute',
    right: 0,
    top: Platform.OS === 'web' ? 28 : 54,
    zIndex: 200,
  },
  freeLaunchCard: {
    alignItems: 'flex-start',
    backgroundColor: '#151a21',
    borderColor: 'rgba(255,255,255,.08)',
    borderRadius: 24,
    borderWidth: 1,
    flexDirection: 'row',
    gap: 14,
    maxWidth: 430,
    paddingBottom: 18,
    paddingLeft: 16,
    paddingRight: 12,
    paddingTop: 18,
    width: '100%',
    ...shadow,
    elevation: 40,
  },
  freeLaunchStar: {
    alignItems: 'center',
    backgroundColor: colors.gold,
    borderRadius: 18,
    height: 38,
    justifyContent: 'center',
    marginTop: 2,
    width: 38,
  },
  freeLaunchCopy: {
    flex: 1,
    gap: 8,
  },
  freeLaunchTitleRow: {
    alignItems: 'flex-start',
    flexDirection: 'row',
    gap: 10,
  },
  freeLaunchTitle: {
    color: '#fffaf0',
    flex: 1,
    fontSize: 18,
    fontWeight: '900',
    lineHeight: 25,
  },
  billingOffPill: {
    backgroundColor: '#dff5f1',
    borderRadius: 999,
    paddingHorizontal: 11,
    paddingVertical: 7,
  },
  billingOffText: {
    color: '#12756b',
    fontSize: 12,
    fontWeight: '900',
  },
  freeLaunchBody: {
    color: 'rgba(255,250,240,.72)',
    fontSize: 14,
    fontWeight: '600',
    lineHeight: 20,
  },
  freeLaunchClose: {
    alignItems: 'center',
    backgroundColor: 'rgba(255,255,255,.12)',
    borderRadius: 999,
    height: 30,
    justifyContent: 'center',
    marginLeft: -4,
    width: 30,
  },
  screen: {
    backgroundColor: colors.navy,
    flex: 1,
  },
  lightScreen: {
    backgroundColor: colors.cream,
    flex: 1,
  },
  darkHeader: {
    backgroundColor: colors.navy,
    minHeight: 92,
    paddingHorizontal: 18,
    paddingVertical: 18,
  },
  profileHeader: {
    backgroundColor: colors.navy,
    minHeight: 92,
    paddingHorizontal: 18,
    paddingVertical: 18,
  },
  statusRow: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 28,
  },
  timeText: {
    color: colors.white,
    fontSize: 19,
    fontWeight: '800',
  },
  lightTime: {
    color: colors.navy,
    fontSize: 19,
    fontWeight: '800',
  },
  notch: {
    backgroundColor: '#05080c',
    borderRadius: 999,
    height: 32,
    width: 118,
  },
  signalRow: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 5,
  },
  headerNav: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  headerIcon: {
    width: 46,
  },
  menuButton: {
    alignItems: 'center',
    borderColor: 'rgba(255,255,255,.18)',
    borderRadius: 14,
    borderWidth: 1,
    height: 46,
    justifyContent: 'center',
    width: 46,
  },
  logoRow: {
    alignItems: 'center',
    flexDirection: 'row',
    flexShrink: 1,
    gap: 9,
  },
  moroMarkImage: {
    height: 52,
    width: 52,
  },
  logoText: {
    color: colors.white,
    fontFamily: Platform.OS === 'ios' ? 'Georgia' : undefined,
    fontSize: 31,
    fontWeight: '700',
    lineHeight: 35,
  },
  logoTag: {
    color: colors.rustLight,
    fontSize: 13,
    fontWeight: '700',
  },
  notificationButton: {
    alignItems: 'center',
    borderColor: 'rgba(255,255,255,.18)',
    borderRadius: 14,
    borderWidth: 1,
    height: 46,
    justifyContent: 'center',
    position: 'relative',
    width: 46,
  },
  notificationDot: {
    backgroundColor: colors.rust,
    borderColor: colors.navy,
    borderRadius: 999,
    borderWidth: 2,
    height: 11,
    position: 'absolute',
    right: 11,
    top: 10,
    width: 11,
  },
  notificationLayer: {
    ...StyleSheet.absoluteFillObject,
    zIndex: 60,
  },
  notificationBackdrop: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(5, 18, 32, .34)',
  },
  notificationPanel: {
    backgroundColor: '#fffefa',
    borderColor: colors.line,
    borderRadius: 28,
    borderWidth: 1,
    maxHeight: '72%',
    padding: 14,
    paddingTop: 14,
    position: 'absolute',
    left: 10,
    right: 10,
    top: 82,
    ...shadow,
    elevation: 18,
  },
  notificationHead: {
    alignItems: 'center',
    backgroundColor: '#f6f0e8',
    borderRadius: 22,
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 12,
    padding: 12,
  },
  notificationTitle: {
    color: colors.navy,
    fontSize: 25,
    fontWeight: '900',
    marginTop: 3,
  },
  notificationLoading: {
    alignItems: 'center',
    gap: 10,
    paddingVertical: 26,
  },
  notificationEmpty: {
    alignItems: 'center',
    gap: 8,
    paddingVertical: 24,
  },
  notificationList: {
    gap: 9,
    paddingBottom: 6,
  },
  notificationCard: {
    alignItems: 'flex-start',
    backgroundColor: colors.card,
    borderColor: '#f0ebe4',
    borderRadius: 20,
    borderWidth: 1,
    flexDirection: 'row',
    gap: 12,
    overflow: 'hidden',
    padding: 13,
    paddingLeft: 15,
    position: 'relative',
    shadowColor: '#09223d',
    shadowOpacity: 0.05,
    shadowRadius: 10,
    shadowOffset: { width: 0, height: 4 },
    elevation: 1,
  },
  notificationCardUnread: {
    backgroundColor: '#fffdf8',
    borderColor: '#ead9bf',
    shadowColor: '#7a431f',
    shadowOpacity: 0.07,
    shadowRadius: 14,
    shadowOffset: { width: 0, height: 7 },
    elevation: 3,
  },
  notificationAccent: {
    bottom: 0,
    left: 0,
    position: 'absolute',
    top: 0,
    width: 4,
  },
  notificationIcon: {
    alignItems: 'center',
    backgroundColor: colors.navy,
    borderRadius: 16,
    height: 44,
    justifyContent: 'center',
    width: 44,
  },
  notificationCardTitle: {
    color: colors.navy,
    fontSize: 16,
    fontWeight: '900',
  },
  notificationPill: {
    fontSize: 9,
    fontWeight: '900',
    letterSpacing: 1,
    textTransform: 'uppercase',
  },
  notificationTopLine: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 7,
    marginBottom: 5,
  },
  notificationUnreadDot: {
    borderRadius: 4,
    height: 8,
    width: 8,
  },
  notificationCardBody: {
    color: colors.muted,
    fontSize: 13,
    fontWeight: '600',
    lineHeight: 19,
    marginTop: 4,
  },
  notificationType: {
    color: colors.rust,
    fontSize: 12,
    fontWeight: '900',
    marginTop: 8,
    textTransform: 'capitalize',
  },
  menuLayer: {
    ...StyleSheet.absoluteFillObject,
    zIndex: 50,
  },
  menuBackdrop: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(5, 18, 32, .48)',
  },
  menuBackdropPressable: {
    flex: 1,
  },
  menuPanel: {
    backgroundColor: colors.cream,
    borderRightColor: colors.line,
    borderRightWidth: 1,
    bottom: 0,
    left: 0,
    padding: 18,
    paddingTop: 26,
    position: 'absolute',
    top: 0,
    width: '82%',
    maxWidth: 340,
    ...shadow,
  },
  menuHead: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 18,
  },
  menuEyebrow: {
    color: colors.rust,
    fontSize: 12,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  menuTitle: {
    color: colors.navy,
    fontSize: 27,
    fontWeight: '900',
    marginTop: 3,
  },
  menuClose: {
    alignItems: 'center',
    backgroundColor: colors.card,
    borderColor: colors.line,
    borderRadius: 12,
    borderWidth: 1,
    height: 44,
    justifyContent: 'center',
    width: 44,
  },
  menuItems: {
    gap: 9,
  },
  menuItem: {
    alignItems: 'center',
    backgroundColor: colors.card,
    borderColor: colors.line,
    borderRadius: 14,
    borderWidth: 1,
    flexDirection: 'row',
    gap: 12,
    minHeight: 54,
    paddingHorizontal: 14,
  },
  menuItemActive: {
    backgroundColor: colors.navy,
    borderColor: colors.navy,
  },
  menuItemText: {
    color: colors.navy,
    flex: 1,
    fontSize: 15,
    fontWeight: '800',
  },
  menuItemTextActive: {
    color: colors.white,
  },
  menuMessageBadge: {
    alignItems: 'center',
    backgroundColor: colors.rust,
    borderColor: colors.white,
    borderRadius: 10,
    borderWidth: 1.5,
    justifyContent: 'center',
    minHeight: 20,
    minWidth: 20,
    paddingHorizontal: 5,
  },
  menuMessageBadgeText: {
    color: colors.white,
    fontSize: 9,
    fontWeight: '900',
  },
  menuPrimary: {
    alignItems: 'center',
    backgroundColor: colors.rust,
    borderRadius: 14,
    flexDirection: 'row',
    gap: 10,
    justifyContent: 'center',
    marginTop: 18,
    minHeight: 56,
    paddingHorizontal: 14,
  },
  menuPrimaryText: {
    color: colors.white,
    fontSize: 15,
    fontWeight: '900',
  },
  menuHint: {
    color: colors.muted,
    fontSize: 13,
    fontWeight: '600',
    lineHeight: 19,
    marginTop: 12,
  },
  menuSwitch: {
    alignItems: 'center',
    backgroundColor: colors.cream,
    borderColor: colors.line,
    borderRadius: 14,
    borderWidth: 1,
    flexDirection: 'row',
    gap: 10,
    justifyContent: 'center',
    marginTop: 10,
    minHeight: 48,
  },
  menuSwitchText: {
    color: colors.navy,
    fontSize: 14,
    fontWeight: '800',
  },
  gate: {
    alignItems: 'stretch',
    flexGrow: 1,
    gap: 12,
    justifyContent: 'center',
    paddingBottom: 28,
    paddingHorizontal: 20,
    paddingTop: 24,
  },
  gateHero: { alignItems: 'center', marginBottom: 6 },
  gateLogoShell: { alignItems: 'center', backgroundColor: colors.white, borderRadius: 24, height: 82, justifyContent: 'center', marginBottom: 16, width: 82, ...shadow },
  gateEyebrow: { color: colors.rust, fontSize: 11, fontWeight: '900', letterSpacing: 1.4, marginBottom: 6 },
  authPage: {
    alignItems: 'center',
    backgroundColor: colors.navy,
    paddingHorizontal: 24,
    flexGrow: 1,
    paddingBottom: 32,
    paddingTop: 20,
  },
  authShell: { backgroundColor: colors.navy },
  authBack: {
    alignItems: 'center',
    alignSelf: 'flex-start',
    borderColor: 'rgba(255,255,255,.18)',
    borderRadius: 13,
    borderWidth: 1,
    height: 44,
    justifyContent: 'center',
    width: 44,
  },
  authLogo: { height: 66, marginTop: 18, width: 66 },
  authEyebrow: { color: colors.gold, fontSize: 11, fontWeight: '900', letterSpacing: 1.6, marginTop: 12 },
  authTitle: { color: colors.white, fontSize: 29, fontWeight: '900', marginTop: 5 },
  authSubtitle: { color: 'rgba(255,255,255,.68)', fontSize: 14, marginTop: 5 },
  authCard: {
    backgroundColor: colors.cream,
    borderRadius: 24,
    marginTop: 24,
    padding: 20,
    width: '100%',
  },
  authDivider: { alignItems: 'center', flexDirection: 'row', gap: 10, marginVertical: 18 },
  authDividerLine: { backgroundColor: colors.line, flex: 1, height: 1 },
  authDividerText: { color: colors.faded, fontSize: 10, fontWeight: '900', letterSpacing: 1 },
  authCreate: { alignItems: 'center', borderColor: colors.rust, borderRadius: 14, borderWidth: 1, flexDirection: 'row', gap: 8, justifyContent: 'center', minHeight: 50 },
  authCreateText: { color: colors.rust, fontSize: 15, fontWeight: '800' },
  gateLogo: { height: 58, width: 58 },
  gateBrand: {
    color: colors.navy,
    fontSize: 28,
    fontWeight: '900',
    letterSpacing: -0.5,
    textAlign: 'center',
  },
  gateSub: {
    color: colors.muted,
    fontSize: 14,
    lineHeight: 20,
    marginBottom: 10,
    textAlign: 'center',
  },
  gateCard: {
    alignItems: 'center',
    backgroundColor: colors.white,
    borderColor: colors.line,
    borderRadius: 20,
    borderWidth: 1,
    flexDirection: 'row',
    gap: 14,
    minHeight: 82,
    paddingHorizontal: 16,
    paddingVertical: 14,
    width: '100%',
    ...shadow,
  },
  gateCardTitle: {
    color: colors.navy,
    fontSize: 16,
    fontWeight: '800',
  },
  gateCardText: {
    color: colors.muted,
    fontSize: 12.5,
    marginTop: 2,
  },
  gateHint: {
    color: colors.muted,
    fontSize: 12,
    marginTop: 10,
  },
  payCardButton: {
    alignItems: 'center',
    backgroundColor: colors.rust,
    borderRadius: 14,
    flexDirection: 'row',
    gap: 8,
    justifyContent: 'center',
    marginBottom: 12,
    paddingVertical: 15,
  },
  payCardText: {
    color: colors.white,
    fontSize: 15,
    fontWeight: '800',
  },
  gateSignUp: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 8,
    justifyContent: 'center',
    marginTop: 6,
    paddingVertical: 8,
  },
  gateServer: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 6,
    justifyContent: 'center',
    paddingVertical: 4,
  },
  gateServerText: {
    color: colors.muted,
    fontSize: 13,
    fontWeight: '700',
  },
  serverOverlay: {
    alignItems: 'center',
    backgroundColor: 'rgba(8,26,44,0.55)',
    bottom: 0,
    justifyContent: 'center',
    left: 0,
    padding: 22,
    position: 'absolute',
    right: 0,
    top: 0,
    zIndex: 50,
  },
  serverCard: {
    backgroundColor: colors.white,
    borderRadius: 18,
    padding: 20,
    width: '100%',
  },
  serverTitle: {
    color: colors.navy,
    fontSize: 18,
    fontWeight: '900',
    marginBottom: 6,
  },
  serverHint: {
    color: colors.muted,
    fontSize: 13,
    lineHeight: 18,
    marginBottom: 14,
  },
  serverInput: {
    backgroundColor: colors.cream,
    borderColor: colors.line,
    borderRadius: 12,
    borderWidth: 1,
    color: colors.navy,
    fontSize: 15,
    paddingHorizontal: 12,
    paddingVertical: 12,
  },
  serverNote: {
    color: colors.faded,
    fontSize: 12,
    marginTop: 8,
  },
  serverSaved: {
    color: colors.green,
    fontSize: 13,
    fontWeight: '700',
    marginTop: 10,
  },
  serverButtons: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 18,
  },
  serverCancel: {
    alignItems: 'center',
    borderColor: colors.line,
    borderRadius: 12,
    borderWidth: 1,
    flex: 1,
    paddingVertical: 13,
  },
  serverCancelText: {
    color: colors.navy,
    fontSize: 15,
    fontWeight: '800',
  },
  serverSave: {
    alignItems: 'center',
    backgroundColor: colors.navy,
    borderRadius: 12,
    flex: 1,
    paddingVertical: 13,
  },
  serverSaveText: {
    color: colors.white,
    fontSize: 15,
    fontWeight: '800',
  },
  gateSignUpText: {
    color: colors.rust,
    fontSize: 14,
    fontWeight: '800',
  },
  signupRoles: {
    flexDirection: 'row',
    gap: 7,
    marginBottom: 18,
    width: '100%',
  },
  signupRole: {
    alignItems: 'center',
    backgroundColor: colors.white,
    borderColor: colors.line,
    borderRadius: 999,
    borderWidth: 1,
    flex: 1,
    justifyContent: 'center',
    minHeight: 48,
    paddingHorizontal: 7,
  },
  signupRoleActive: {
    backgroundColor: colors.navy,
    borderColor: colors.navy,
  },
  signupRoleText: {
    color: colors.navy,
    fontSize: 12,
    fontWeight: '800',
    textAlign: 'center',
    textTransform: 'capitalize',
  },
  signupRoleTextActive: {
    color: colors.white,
  },
  signupField: {
    width: '100%',
  },
  signupLabel: {
    color: colors.navy,
    fontSize: 12,
    fontWeight: '800',
    marginBottom: 7,
  },
  signupInput: {
    backgroundColor: colors.white,
    borderColor: colors.line,
    borderRadius: 15,
    borderWidth: 1,
    color: colors.navy,
    fontSize: 16,
    minHeight: 54,
    paddingHorizontal: 16,
  },
  signupNote: {
    color: colors.muted,
    fontSize: 12.5,
    lineHeight: 18,
    marginTop: 12,
  },
  signupError: {
    color: colors.rust,
    fontSize: 13,
    fontWeight: '700',
    marginTop: 12,
  },
  signupSubmit: {
    alignItems: 'center',
    backgroundColor: colors.navy,
    borderRadius: 16,
    marginTop: 18,
    justifyContent: 'center',
    minHeight: 56,
    width: '100%',
  },
  signupSubmitText: {
    color: colors.white,
    fontSize: 16,
    fontWeight: '800',
  },
  signupCancel: {
    marginTop: 14,
    paddingVertical: 6,
  },
  signupCancelText: {
    color: colors.muted,
    fontSize: 14,
    fontWeight: '700',
  },
  signupPage: {
    backgroundColor: colors.cream,
    flexGrow: 1,
    paddingBottom: 34,
    paddingHorizontal: 18,
    paddingTop: 12,
  },
  signupTopbar: { alignItems: 'center', flexDirection: 'row', justifyContent: 'space-between', minHeight: 52 },
  signupBack: { alignItems: 'center', backgroundColor: colors.white, borderColor: colors.line, borderRadius: 14, borderWidth: 1, height: 44, justifyContent: 'center', width: 44 },
  signupBrand: { alignItems: 'center', flexDirection: 'row', gap: 7 },
  signupLogo: { height: 34, width: 34 },
  signupBrandText: { color: colors.navy, fontSize: 17, fontWeight: '900' },
  signupTopbarSpacer: { width: 44 },
  signupHero: { alignItems: 'center', marginBottom: 20, marginTop: 20 },
  signupEyebrow: { color: colors.rust, fontSize: 11, fontWeight: '900', letterSpacing: 1.5, marginBottom: 7 },
  signupTitle: { color: colors.navy, fontSize: 30, fontWeight: '900', letterSpacing: -0.8, lineHeight: 36, textAlign: 'center' },
  signupSubtitle: { color: colors.muted, fontSize: 14, lineHeight: 20, marginTop: 7, textAlign: 'center' },
  signupForm: { backgroundColor: colors.white, borderColor: colors.line, borderRadius: 22, borderWidth: 1, gap: 16, padding: 16, ...shadow },
  signupTerms: { color: colors.faded, fontSize: 11, lineHeight: 16, marginTop: 14, paddingHorizontal: 16, textAlign: 'center' },
  sheet: {
    backgroundColor: colors.cream,
    borderTopLeftRadius: 28,
    borderTopRightRadius: 28,
    flex: 1,
    marginTop: -2,
  },
  bookingContent: {
    padding: 16,
    paddingBottom: 34,
  },
  card: {
    backgroundColor: colors.card,
    borderColor: colors.line,
    borderRadius: 20,
    borderWidth: 1,
    ...shadow,
  },
  routeCard: {
    paddingHorizontal: 18,
    paddingVertical: 16,
  },
  bookingMapCard: {
    backgroundColor: '#e8ede8',
    borderColor: colors.line,
    borderRadius: 18,
    borderWidth: 1,
    height: 360,
    marginBottom: 14,
    overflow: 'hidden',
    position: 'relative',
  },
  bookingMapEmpty: {
    alignItems: 'center',
    flex: 1,
    justifyContent: 'center',
    paddingBottom: 76,
  },
  bookingMapEmptyIcon: {
    alignItems: 'center',
    backgroundColor: colors.white,
    borderRadius: 24,
    height: 64,
    justifyContent: 'center',
    marginBottom: 10,
    width: 64,
    ...shadow,
  },
  bookingMapEmptyTitle: { color: colors.navy, fontSize: 16, fontWeight: '900' },
  bookingMapEmptyHint: { color: colors.muted, fontSize: 12, marginTop: 4 },
  bookingMapActions: {
    bottom: 10,
    flexDirection: 'column',
    gap: 8,
    left: 10,
    position: 'absolute',
    right: 10,
    zIndex: 5,
  },
  mapPointButton: {
    alignItems: 'center',
    backgroundColor: 'rgba(255,255,255,.96)',
    borderColor: colors.line,
    borderRadius: 14,
    borderWidth: 1,
    flexDirection: 'row',
    gap: 7,
    minHeight: 58,
    paddingHorizontal: 12,
    ...shadow,
  },
  mapPointButtonDone: { borderColor: colors.green },
  mapPointDot: { alignItems: 'center', borderRadius: 15, height: 30, justifyContent: 'center', width: 30 },
  mapPointCopy: { flex: 1 },
  mapPointButtonText: { color: colors.navy, fontSize: 10, fontWeight: '900', letterSpacing: 1 },
  mapPointAddress: { color: colors.muted, fontSize: 12, fontWeight: '700', marginTop: 3 },
  tripMetricsCard: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 14,
    marginBottom: 4,
    marginTop: 0,
    padding: 14,
  },
  tripMetric: {
    alignItems: 'center',
    flex: 1,
    flexDirection: 'row',
    gap: 10,
  },
  tripMetricIcon: {
    alignItems: 'center',
    backgroundColor: '#eef6fb',
    borderRadius: 14,
    height: 42,
    justifyContent: 'center',
    width: 42,
  },
  tripMetricLabel: {
    color: colors.muted,
    fontSize: 12,
    fontWeight: '800',
  },
  tripMetricValue: {
    color: colors.navy,
    fontSize: 19,
    fontWeight: '900',
    marginTop: 2,
  },
  tripMetricDivider: {
    backgroundColor: colors.line,
    height: 46,
    width: 1,
  },
  routeRow: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 14,
  },
  pin: {
    alignItems: 'center',
    borderRadius: 999,
    height: 42,
    justifyContent: 'center',
    width: 42,
  },
  orangeLabel: {
    color: colors.rust,
    fontSize: 14,
    fontWeight: '700',
  },
  routeValue: {
    color: colors.navy,
    fontSize: 20,
    fontWeight: '500',
    marginTop: 4,
  },
  routePickerValue: {
    color: colors.navy,
    fontSize: 16,
    fontWeight: '500',
    lineHeight: 20,
    marginTop: 3,
    minHeight: 40,
  },
  routePickerPlaceholder: {
    color: colors.faded,
    fontSize: 18,
  },
  cityStrip: {
    gap: 8,
    paddingBottom: 14,
  },
  cityChip: {
    backgroundColor: colors.card,
    borderColor: colors.line,
    borderRadius: 999,
    borderWidth: 1,
    paddingHorizontal: 16,
    paddingVertical: 9,
  },
  cityChipActive: {
    backgroundColor: colors.navy,
    borderColor: colors.navy,
  },
  cityChipText: {
    color: colors.navy,
    fontSize: 14,
    fontWeight: '700',
  },
  cityChipTextActive: {
    color: colors.white,
  },
  locateButton: {
    alignItems: 'center',
    borderColor: colors.line,
    borderRadius: 12,
    borderWidth: 1,
    height: 46,
    justifyContent: 'center',
    width: 46,
  },
  routeDivider: {
    backgroundColor: colors.line,
    height: 1,
    marginLeft: 56,
    marginVertical: 14,
  },
  twoCols: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 14,
  },
  stepper: {
    alignItems: 'stretch',
    flex: 1,
    gap: 14,
    minHeight: 118,
    padding: 14,
  },
  stepperHeader: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 9,
    minWidth: 0,
  },
  stepperControls: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  roundIcon: {
    alignItems: 'center',
    backgroundColor: '#f0f2f5',
    borderRadius: 999,
    height: 38,
    justifyContent: 'center',
    width: 38,
  },
  inputLabel: {
    color: colors.navy,
    flex: 1,
    fontSize: 14,
    fontWeight: '800',
  },
  stepperValue: {
    color: colors.navy,
    fontSize: 28,
    fontWeight: '800',
    minWidth: 28,
    textAlign: 'center',
  },
  roundButton: {
    alignItems: 'center',
    borderColor: colors.line,
    borderRadius: 999,
    borderWidth: 1,
    height: 38,
    justifyContent: 'center',
    width: 38,
  },
  fieldTitle: {
    color: colors.navy,
    fontSize: 20,
    fontWeight: '700',
    marginBottom: 11,
    marginTop: 18,
  },
  vehicleStrip: {
    gap: 10,
    paddingRight: 18,
  },
  vehicleCard: {
    alignItems: 'center',
    backgroundColor: colors.card,
    borderColor: colors.line,
    borderRadius: 14,
    borderWidth: 1,
    height: 92,
    justifyContent: 'center',
    overflow: 'hidden',
    position: 'relative',
    width: 94,
  },
  vehicleCardActive: {
    backgroundColor: colors.navy,
    borderColor: colors.navy,
  },
  vehicleCheck: {
    alignItems: 'center',
    backgroundColor: colors.rust,
    borderColor: 'rgba(255,255,255,.9)',
    borderRadius: 999,
    borderWidth: 1,
    height: 24,
    justifyContent: 'center',
    position: 'absolute',
    right: 7,
    top: 7,
    width: 24,
    zIndex: 2,
  },
  vehicleLabel: {
    color: colors.navy,
    fontSize: 14,
    fontWeight: '800',
    marginTop: 9,
  },
  vehicleLabelActive: {
    color: colors.white,
  },
  fareCard: {
    gap: 14,
    marginTop: 18,
    padding: 18,
  },
  fareHeader: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  fareEyebrow: {
    color: colors.rust,
    fontSize: 13,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  fareMeta: {
    color: colors.muted,
    fontSize: 13,
    fontWeight: '700',
    lineHeight: 18,
    marginTop: 2,
  },
  fareShield: {
    alignItems: 'center',
    backgroundColor: '#fff3ec',
    borderColor: '#f0d3c2',
    borderRadius: 16,
    borderWidth: 1,
    height: 58,
    justifyContent: 'center',
    width: 58,
  },
  smallTitle: {
    color: colors.navy,
    fontSize: 17,
    fontWeight: '600',
  },
  fareLine: {
    alignItems: 'flex-end',
    flexDirection: 'row',
    gap: 7,
  },
  fareNumber: {
    color: colors.navy,
    fontSize: 48,
    fontWeight: '700',
  },
  currency: {
    color: colors.navy,
    fontSize: 18,
    fontWeight: '600',
    marginBottom: 9,
  },
  subtle: {
    color: colors.muted,
    fontSize: 14,
    lineHeight: 21,
  },
  fareBenefits: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  benefitRow: {
    alignItems: 'center',
    backgroundColor: '#f7f1eb',
    borderColor: colors.line,
    borderRadius: 999,
    borderWidth: 1,
    flexDirection: 'row',
    gap: 6,
    paddingHorizontal: 9,
    paddingVertical: 7,
  },
  benefitCheck: {
    alignItems: 'center',
    backgroundColor: colors.gold,
    borderRadius: 999,
    height: 18,
    justifyContent: 'center',
    width: 18,
  },
  benefitText: {
    color: colors.navy,
    fontSize: 12,
    fontWeight: '800',
  },
  useSuggestedButton: {
    alignItems: 'center',
    alignSelf: 'flex-start',
    backgroundColor: '#f7f1eb',
    borderColor: colors.line,
    borderRadius: 999,
    borderWidth: 1,
    flexDirection: 'row',
    gap: 8,
    paddingHorizontal: 12,
    paddingVertical: 9,
  },
  useSuggestedText: {
    color: colors.navy,
    fontSize: 13,
    fontWeight: '900',
  },
  priceInputCard: {
    gap: 12,
    marginTop: 14,
    padding: 15,
  },
  priceInputHead: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 12,
  },
  priceInputTitle: {
    color: colors.navy,
    fontSize: 17,
    fontWeight: '900',
  },
  priceInputHint: {
    color: colors.muted,
    fontSize: 12,
    fontWeight: '600',
    lineHeight: 17,
    marginTop: 2,
  },
  priceInputRow: {
    alignItems: 'center',
    backgroundColor: '#fbf7f2',
    borderColor: colors.line,
    borderRadius: 12,
    borderWidth: 1,
    flexDirection: 'row',
    paddingHorizontal: 12,
  },
  priceInput: {
    color: colors.navy,
    flex: 1,
    fontSize: 24,
    fontWeight: '700',
    minHeight: 54,
    paddingVertical: 8,
  },
  madLabel: {
    color: colors.navy,
    fontSize: 14,
    fontWeight: '900',
  },
  paymentBox: {
    borderColor: colors.line,
    borderRadius: 16,
    borderWidth: 1,
    flexDirection: 'row',
    marginBottom: 16,
    overflow: 'hidden',
  },
  paymentChoice: {
    alignItems: 'center',
    flex: 1,
    flexDirection: 'row',
    gap: 12,
    padding: 14,
  },
  paymentChoiceActive: {
    backgroundColor: colors.navy,
  },
  freeModeHint: {
    color: colors.green,
    fontSize: 12,
    fontWeight: '800',
    marginBottom: 14,
    marginTop: -6,
  },
  paymentIcon: {
    alignItems: 'center',
    backgroundColor: '#eef2f6',
    borderRadius: 999,
    height: 46,
    justifyContent: 'center',
    width: 46,
  },
  paymentIconActive: {
    backgroundColor: colors.navy2,
  },
  paymentTitle: {
    color: colors.navy,
    fontSize: 18,
    fontWeight: '700',
  },
  paymentTitleActive: {
    color: colors.white,
  },
  paymentSub: {
    color: colors.muted,
    fontSize: 13,
  },
  paymentSubActive: {
    color: '#dbe7f2',
  },
  primaryButton: {
    alignItems: 'center',
    backgroundColor: colors.rust,
    borderRadius: 14,
    flexDirection: 'row',
    justifyContent: 'center',
    minHeight: 74,
    paddingHorizontal: 24,
  },
  primaryText: {
    color: colors.white,
    flex: 1,
    fontSize: 27,
    fontWeight: '600',
    textAlign: 'center',
  },
  pressed: {
    opacity: 0.88,
    transform: [{ scale: 0.99 }],
  },
  safetyText: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 8,
    justifyContent: 'center',
    paddingTop: 16,
  },
  safetyLabel: {
    color: colors.muted,
    fontSize: 15,
    fontWeight: '500',
  },
  lightHeader: {
    backgroundColor: colors.cream,
    paddingHorizontal: 18,
    paddingTop: 14,
  },
  lightStatus: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 24,
  },
  lightNav: {
    alignItems: 'center',
    flexDirection: 'row',
    marginBottom: 16,
  },
  lightIconButton: {
    alignItems: 'center',
    height: 50,
    justifyContent: 'center',
    width: 50,
  },
  lightTitle: {
    color: colors.navy,
    fontSize: 25,
    fontWeight: '700',
  },
  lightContent: {
    padding: 16,
    paddingBottom: 26,
  },
  offerRoute: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 16,
    padding: 18,
  },
  routePins: {
    alignItems: 'center',
  },
  dottedLine: {
    borderColor: colors.rust,
    borderStyle: 'dashed',
    borderWidth: 1,
    height: 42,
  },
  mutedInline: {
    color: colors.muted,
  },
  broadcastCard: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 14,
    marginTop: 14,
    padding: 16,
  },
  liveDot: { backgroundColor: colors.green, borderColor: colors.white, borderRadius: 8, borderWidth: 3, height: 16, width: 16 },
  broadcastTitle: {
    color: colors.navy,
    fontSize: 18,
    fontWeight: '600',
  },
  noticeText: {
    color: colors.rust,
    fontSize: 13,
    marginTop: 10,
  },
  loadingInline: {
    color: colors.muted,
    fontSize: 14,
    fontWeight: '700',
    paddingHorizontal: 8,
    paddingVertical: 12,
  },
  emptyDriversCard: {
    alignItems: 'center',
    gap: 8,
    marginTop: 16,
    paddingHorizontal: 22,
    paddingVertical: 26,
  },
  emptyOfferIcon: { alignItems: 'center', backgroundColor: colors.rustLight, borderRadius: 22, height: 54, justifyContent: 'center', marginBottom: 2, width: 54 },
  emptyOfferText: { color: colors.muted, fontSize: 13, lineHeight: 19, textAlign: 'center' },
  offerFooterActions: { flexDirection: 'row', gap: 10, marginTop: 16 },
  refreshOffersButton: { alignItems: 'center', backgroundColor: colors.white, borderColor: colors.line, borderRadius: 15, borderWidth: 1, flex: 1, flexDirection: 'row', gap: 7, justifyContent: 'center', minHeight: 52, ...shadow },
  refreshOffersText: { color: colors.navy, fontSize: 13, fontWeight: '900' },
  cancelRideButton: { alignItems: 'center', backgroundColor: '#fff5f3', borderColor: '#efcbc5', borderRadius: 15, borderWidth: 1, flex: 1, flexDirection: 'row', gap: 7, justifyContent: 'center', minHeight: 52 },
  cancelRideText: { color: '#b84747', fontSize: 13, fontWeight: '900' },
  driverOffer: {
    marginTop: 16,
    padding: 16,
  },
  offerTop: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 13,
  },
  offerDriverCopy: { flex: 1, minWidth: 0 },
  offerDriverName: { color: colors.navy, fontSize: 19, fontWeight: '900', lineHeight: 23 },
  offerDriverMetaRow: { alignItems: 'center', flexDirection: 'row', gap: 8, marginTop: 7 },
  offerVehicleText: { color: colors.muted, flex: 1, fontSize: 13, fontWeight: '700', textTransform: 'capitalize' },
  offerVerifiedIcon: { alignItems: 'center', backgroundColor: colors.greenSoft, borderRadius: 14, height: 42, justifyContent: 'center', width: 42 },
  avatar: {
    alignItems: 'center',
    backgroundColor: '#d9c8b8',
    borderColor: colors.white,
    borderWidth: 4,
    justifyContent: 'center',
    position: 'relative',
  },
  avatarText: {
    color: colors.navy,
    fontWeight: '900',
  },
  avatarBadge: {
    alignItems: 'center',
    backgroundColor: '#42b86d',
    borderColor: colors.white,
    borderRadius: 999,
    borderWidth: 2,
    bottom: 3,
    height: 28,
    justifyContent: 'center',
    position: 'absolute',
    right: -3,
    width: 28,
  },
  nameRow: {
    alignItems: 'center',
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  driverName: {
    color: colors.navy,
    fontSize: 25,
    fontWeight: '700',
  },
  starText: {
    color: colors.navy,
    fontSize: 17,
    fontWeight: '600',
  },
  driverStatus: {
    backgroundColor: '#fff4ed',
    borderColor: colors.line,
    borderRadius: 999,
    borderWidth: 1,
    color: colors.rust,
    fontSize: 12,
    fontWeight: '900',
    paddingHorizontal: 9,
    paddingVertical: 5,
  },
  driverStatusOnline: {
    backgroundColor: colors.greenSoft,
    color: colors.green,
  },
  profileMeta: {
    color: colors.muted,
    fontSize: 15,
    marginTop: 6,
  },
  carThumb: {
    alignItems: 'center',
    backgroundColor: '#fbf9f6',
    borderColor: colors.line,
    borderRadius: 13,
    borderWidth: 1,
    height: 76,
    justifyContent: 'center',
    width: 102,
  },
  offerFacts: {
    flexDirection: 'row',
    gap: 9,
    marginTop: 16,
  },
  offerFactPrimary: { alignItems: 'center', backgroundColor: '#fbf7f1', borderColor: colors.line, borderRadius: 15, borderWidth: 1, flex: 1.2, flexDirection: 'row', gap: 9, minHeight: 76, padding: 11 },
  offerFactSecondary: { alignItems: 'center', backgroundColor: '#fbf7f1', borderColor: colors.line, borderRadius: 15, borderWidth: 1, flex: .8, flexDirection: 'row', gap: 8, minHeight: 76, padding: 11 },
  offerFactIcon: { alignItems: 'center', backgroundColor: colors.white, borderRadius: 11, height: 36, justifyContent: 'center', width: 36 },
  offerFactLabel: { color: colors.muted, fontSize: 9, fontWeight: '900', letterSpacing: .7 },
  offerPrice: { color: colors.navy, fontSize: 18, fontWeight: '900', marginTop: 5 },
  offerEta: { color: colors.navy, fontSize: 18, fontWeight: '900', marginTop: 5 },
  offerMessage: { color: colors.muted, fontSize: 12, lineHeight: 18, marginTop: 11 },
  fact: {
    borderRightColor: colors.line,
    borderRightWidth: 1,
    flex: 1,
    padding: 12,
  },
  factValue: {
    color: colors.navy,
    fontSize: 23,
    fontWeight: '700',
    marginTop: 5,
  },
  verifiedFact: {
    alignItems: 'center',
    flex: 1,
    flexDirection: 'row',
    gap: 8,
    justifyContent: 'center',
    padding: 12,
  },
  verifiedFactText: {
    color: colors.green,
    fontSize: 15,
    fontWeight: '600',
  },
  offerButtons: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 12,
  },
  outlineButton: {
    alignItems: 'center',
    borderColor: colors.navy,
    borderRadius: 9,
    borderWidth: 1,
    flex: 1,
    padding: 14,
  },
  outlineText: {
    color: colors.navy,
    fontSize: 17,
    fontWeight: '600',
  },
  chooseButton: {
    alignItems: 'center',
    backgroundColor: colors.rust,
    borderRadius: 9,
    flex: 1,
    padding: 14,
  },
  chooseText: {
    color: colors.white,
    fontSize: 17,
    fontWeight: '600',
  },
  profileNav: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  profileHeaderTitle: {
    color: colors.white,
    fontSize: 27,
    fontWeight: '700',
  },
  profileSheet: {
    backgroundColor: colors.cream,
    borderTopLeftRadius: 28,
    borderTopRightRadius: 28,
    flex: 1,
  },
  profileContent: {
    gap: 14,
    padding: 16,
    paddingBottom: 42,
  },
  profileHeroCard: { padding: 16 },
  profileHeroMain: { alignItems: 'center', flexDirection: 'row', gap: 14 },
  profileHeroCopy: { flex: 1, minWidth: 0 },
  profileHeroName: { color: colors.navy, fontSize: 21, fontWeight: '900', lineHeight: 25 },
  profileStatusRow: { alignItems: 'center', flexDirection: 'row', flexWrap: 'wrap', gap: 7, marginTop: 9 },
  verifiedPill: { alignItems: 'center', backgroundColor: colors.greenSoft, borderRadius: 999, flexDirection: 'row', gap: 4, paddingHorizontal: 9, paddingVertical: 5 },
  verifiedPillText: { color: colors.green, fontSize: 11, fontWeight: '900' },
  onlinePill: { alignItems: 'center', backgroundColor: '#eef8f2', borderRadius: 999, flexDirection: 'row', gap: 5, paddingHorizontal: 9, paddingVertical: 5 },
  offlinePill: { backgroundColor: '#f0f1f2' },
  profileOnlineDot: { backgroundColor: colors.green, borderRadius: 4, height: 7, width: 7 },
  onlinePillText: { color: colors.navy, fontSize: 11, fontWeight: '800' },
  profileTrustLine: { alignItems: 'center', borderTopColor: colors.line, borderTopWidth: 1, flexDirection: 'row', gap: 7, marginTop: 15, paddingTop: 13 },
  profileTrustText: { color: colors.muted, flex: 1, fontSize: 11, lineHeight: 16 },
  profileSectionHead: { alignItems: 'center', flexDirection: 'row', justifyContent: 'space-between', marginTop: 2 },
  profileSectionTitle: { color: colors.navy, fontSize: 17, fontWeight: '900' },
  profileSectionMeta: { color: colors.rust, fontSize: 10, fontWeight: '900', letterSpacing: .7, textTransform: 'uppercase' },
  profileVehicleCard: { alignItems: 'center', flexDirection: 'row', gap: 15, padding: 14 },
  profilePhotoGallery: { gap: 10, paddingRight: 4 },
  profilePhotoCard: { backgroundColor: '#ebe3da', borderRadius: 17, height: 170, overflow: 'hidden', position: 'relative', width: 270 },
  profilePhotoImage: { height: '100%', width: '100%' },
  profilePhotoLabel: { backgroundColor: 'rgba(8,36,67,.82)', borderRadius: 999, bottom: 10, left: 10, paddingHorizontal: 10, paddingVertical: 6, position: 'absolute' },
  profilePhotoLabelText: { color: colors.white, fontSize: 10, fontWeight: '900', letterSpacing: .6, textTransform: 'uppercase' },
  profileVehicleVisual: { alignItems: 'center', backgroundColor: '#ebe3da', borderRadius: 17, height: 92, justifyContent: 'center', width: 108 },
  profileVehicleName: { color: colors.navy, fontSize: 18, fontWeight: '900', textTransform: 'capitalize' },
  profileVehicleType: { color: colors.muted, fontSize: 12, marginTop: 3, textTransform: 'capitalize' },
  plateBox: { alignSelf: 'flex-start', backgroundColor: '#f8f6f2', borderColor: colors.line, borderRadius: 9, borderWidth: 1, marginTop: 9, paddingHorizontal: 9, paddingVertical: 5 },
  plateBoxLabel: { color: colors.faded, fontSize: 8, fontWeight: '900', letterSpacing: 1 },
  plateBoxValue: { color: colors.navy, fontSize: 11, fontWeight: '800', marginTop: 2, maxWidth: 130 },
  profileRecordCard: { flexDirection: 'row', paddingHorizontal: 8, paddingVertical: 14 },
  profileRecordItem: { alignItems: 'center', flex: 1, gap: 5 },
  profileRecordDivider: { backgroundColor: colors.line, width: 1 },
  profileRecordLabel: { color: colors.muted, fontSize: 10, fontWeight: '700' },
  profileRecordValue: { color: colors.navy, fontSize: 12, fontWeight: '900', textTransform: 'capitalize' },
  profileTop: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 12,
  },
  profileName: {
    color: colors.navy,
    fontSize: 27,
    fontWeight: '700',
  },
  topRated: {
    alignItems: 'center',
    backgroundColor: '#fff1e9',
    borderRadius: 8,
    flexDirection: 'row',
    gap: 4,
    paddingHorizontal: 8,
    paddingVertical: 6,
  },
  topRatedText: {
    color: colors.rust,
    fontSize: 12,
    fontWeight: '700',
  },
  languageRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 7,
    marginTop: 10,
  },
  languagePill: {
    backgroundColor: '#f8f4ef',
    borderColor: colors.line,
    borderRadius: 8,
    borderWidth: 1,
    color: colors.navy,
    fontSize: 13,
    paddingHorizontal: 11,
    paddingVertical: 6,
  },
  verifiedBox: {
    alignItems: 'center',
    backgroundColor: '#f6fbf8',
    borderColor: colors.line,
    borderRadius: 13,
    borderWidth: 1,
    padding: 10,
    width: 100,
  },
  verifiedTitle: {
    color: colors.green,
    fontSize: 12,
    fontWeight: '700',
    textAlign: 'center',
  },
  verifiedText: {
    color: colors.navy,
    fontSize: 11,
    lineHeight: 15,
    textAlign: 'center',
  },
  statsGrid: {
    flexDirection: 'row',
    marginTop: 16,
    paddingVertical: 14,
  },
  profileStat: {
    alignItems: 'center',
    borderRightColor: colors.line,
    borderRightWidth: 1,
    flex: 1,
    gap: 5,
  },
  statLabel: {
    color: colors.navy,
    fontSize: 13,
    textAlign: 'center',
  },
  statValue: {
    color: colors.navy,
    fontSize: 13,
    fontWeight: '700',
    textAlign: 'center',
  },
  vehiclePhotos: {
    gap: 10,
  },
  vehiclePhoto: {
    alignItems: 'center',
    backgroundColor: '#dfd5ca',
    borderRadius: 10,
    height: 98,
    justifyContent: 'center',
    overflow: 'hidden',
    position: 'relative',
    width: 128,
  },
  photoCount: {
    backgroundColor: 'rgba(0,0,0,.55)',
    borderRadius: 999,
    color: colors.white,
    left: 12,
    paddingHorizontal: 10,
    paddingVertical: 5,
    position: 'absolute',
    top: 12,
  },
  carTitle: {
    color: colors.navy,
    fontSize: 22,
    fontWeight: '700',
    marginTop: 12,
  },
  yearPill: {
    color: colors.muted,
    fontSize: 15,
  },
  vehicleSpecs: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
    marginTop: 10,
  },
  specText: {
    color: colors.navy,
    fontSize: 13,
  },
  aboutBlock: {
    backgroundColor: colors.sand,
    marginHorizontal: -14,
    marginTop: 18,
    padding: 14,
  },
  aboutText: {
    color: colors.navy,
    fontSize: 15,
    lineHeight: 22,
  },
  reviewsHeader: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  viewAll: {
    color: colors.rust,
    fontSize: 15,
    fontWeight: '700',
    marginTop: 18,
  },
  reviewStrip: {
    gap: 10,
    paddingBottom: 12,
  },
  reviewCard: {
    padding: 12,
    width: 190,
  },
  reviewName: {
    color: colors.navy,
    fontSize: 16,
    fontWeight: '700',
  },
  reviewStars: {
    color: colors.rust,
    letterSpacing: 1,
    marginVertical: 8,
  },
  reviewText: {
    color: colors.navy,
    fontSize: 13,
    lineHeight: 19,
  },
  trackingContent: {
    padding: 14,
    paddingBottom: 26,
  },
  driverAcceptedBanner: {
    alignItems: 'center',
    backgroundColor: colors.greenSoft,
    borderColor: colors.green,
    borderRadius: 16,
    borderWidth: 1,
    flexDirection: 'row',
    gap: 10,
    marginBottom: 12,
    padding: 12,
  },
  driverAcceptedIcon: { alignItems: 'center', backgroundColor: colors.green, borderRadius: 18, height: 36, justifyContent: 'center', width: 36 },
  driverAcceptedTitle: { color: colors.navy, fontSize: 14, fontWeight: '900' },
  driverAcceptedText: { color: colors.muted, fontSize: 11.5, marginTop: 2 },
  chatNowButton: { alignItems: 'center', backgroundColor: colors.navy, borderRadius: 12, flexDirection: 'row', gap: 5, paddingHorizontal: 11, paddingVertical: 10 },
  chatNowText: { color: colors.white, fontSize: 12, fontWeight: '800' },
  shareButton: {
    alignItems: 'center',
    borderColor: colors.line,
    borderRadius: 13,
    borderWidth: 1,
    flexDirection: 'row',
    gap: 8,
    paddingHorizontal: 12,
    paddingVertical: 12,
  },
  shareText: {
    color: colors.navy,
    fontSize: 15,
    fontWeight: '600',
  },
  mapCard: {
    backgroundColor: '#eef2ed',
    borderColor: colors.line,
    borderRadius: 18,
    borderWidth: 1,
    height: 430,
    overflow: 'hidden',
    position: 'relative',
  },
  nativeMap: {
    ...StyleSheet.absoluteFillObject,
  },
  nativeCarMarker: {
    alignItems: 'center',
    backgroundColor: colors.navy,
    borderColor: colors.white,
    borderRadius: 999,
    borderWidth: 3,
    height: 42,
    justifyContent: 'center',
    width: 42,
  },
  realMapLayer: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: '#e7ece7',
  },
  tileGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    height: 768,
    left: '50%',
    opacity: 0.92,
    position: 'absolute',
    top: '50%',
    transform: [{ translateX: -384 }, { translateY: -384 }],
    width: 768,
  },
  mapTile: {
    height: 256,
    width: 256,
  },
  mapTint: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(255, 253, 249, .08)',
  },
  mapPulse: {
    backgroundColor: 'rgba(200, 95, 55, .18)',
    borderColor: 'rgba(200, 95, 55, .34)',
    borderRadius: 999,
    borderWidth: 1,
    height: 84,
    marginLeft: -42,
    marginTop: -42,
    position: 'absolute',
    width: 84,
  },
  routePathMuted: {
    backgroundColor: '#bbcad0',
    borderRadius: 999,
    height: 10,
    left: 92,
    position: 'absolute',
    top: 126,
    transform: [{ rotate: '43deg' }],
    width: 245,
  },
  routePathDone: {
    backgroundColor: colors.rust,
    borderRadius: 999,
    height: 10,
    left: 94,
    position: 'absolute',
    top: 126,
    transform: [{ rotate: '43deg' }],
    width: 112,
  },
  mapPin: {
    alignItems: 'center',
    borderColor: colors.white,
    borderRadius: 999,
    borderWidth: 3,
    height: 36,
    justifyContent: 'center',
    position: 'absolute',
    width: 36,
    ...shadow,
  },
  pickupPin: {
    backgroundColor: colors.navy,
    left: 72,
    top: 92,
  },
  dropoffPin: {
    backgroundColor: colors.rust,
    right: 72,
    top: 264,
  },
  carMarker: {
    alignItems: 'center',
    backgroundColor: colors.navy,
    borderColor: colors.white,
    borderRadius: 999,
    borderWidth: 4,
    height: 54,
    justifyContent: 'center',
    left: '50%',
    position: 'absolute',
    top: 194,
    transform: [{ translateX: -27 }, { rotate: '43deg' }],
    width: 54,
    ...shadow,
  },
  mapInfoCard: {
    backgroundColor: colors.navy,
    borderRadius: 16,
    left: 16,
    padding: 13,
    position: 'absolute',
    top: 16,
    width: 126,
    ...shadow,
  },
  mapInfoLabel: {
    color: colors.rust,
    fontSize: 12,
    fontWeight: '900',
    textTransform: 'uppercase',
  },
  mapInfoValue: {
    color: colors.white,
    fontSize: 27,
    fontWeight: '900',
    marginTop: 2,
  },
  mapInfoSub: {
    color: '#dbe7f2',
    fontSize: 12,
    fontWeight: '700',
    marginTop: 3,
  },
  mapBottomSheet: {
    backgroundColor: colors.card,
    borderColor: colors.line,
    borderRadius: 18,
    borderWidth: 1,
    bottom: 14,
    left: 14,
    padding: 14,
    position: 'absolute',
    right: 14,
    ...shadow,
  },
  routeStop: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 10,
  },
  routeStopDivider: {
    backgroundColor: colors.line,
    height: 1,
    marginLeft: 13,
    marginVertical: 10,
  },
  routeDot: {
    borderRadius: 999,
    height: 12,
    width: 12,
  },
  routeStopText: {
    color: colors.navy,
    fontSize: 15,
    fontWeight: '800',
    marginTop: 2,
  },
  timelineCard: {
    flexDirection: 'row',
    marginTop: 14,
    padding: 14,
  },
  timelineItem: {
    alignItems: 'center',
    flex: 1,
    gap: 6,
  },
  timelineIcon: {
    alignItems: 'center',
    backgroundColor: '#eef0f2',
    borderRadius: 999,
    height: 45,
    justifyContent: 'center',
    width: 45,
  },
  timelineIconActive: {
    backgroundColor: colors.rust,
  },
  timelineLabel: {
    color: colors.navy,
    fontSize: 12,
    lineHeight: 16,
    textAlign: 'center',
  },
  timelineLabelActive: {
    color: colors.rust,
    fontWeight: '700',
  },
  timelineTime: {
    color: colors.muted,
    fontSize: 13,
  },
  driverTracking: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 14,
    marginTop: 14,
    padding: 14,
  },
  ratingPill: {
    backgroundColor: '#fff4ed',
    borderColor: colors.line,
    borderRadius: 8,
    borderWidth: 1,
    color: colors.rust,
    fontWeight: '800',
    paddingHorizontal: 8,
    paddingVertical: 4,
  },
  plate: {
    alignSelf: 'flex-start',
    borderColor: colors.line,
    borderRadius: 8,
    borderWidth: 1,
    color: colors.navy,
    fontSize: 16,
    fontWeight: '700',
    marginTop: 10,
    paddingHorizontal: 10,
    paddingVertical: 4,
  },
  etaBox: {
    borderLeftColor: colors.line,
    borderLeftWidth: 1,
    paddingLeft: 16,
  },
  etaText: {
    color: colors.navy,
    fontSize: 24,
    fontWeight: '700',
    marginBottom: 14,
  },
  orangePrice: {
    color: colors.rust,
    fontSize: 22,
    fontWeight: '700',
  },
  actionGrid: {
    flexDirection: 'row',
    gap: 10,
    marginVertical: 14,
  },
  miniAction: {
    alignItems: 'center',
    backgroundColor: colors.card,
    borderColor: colors.line,
    borderRadius: 16,
    borderWidth: 1,
    flex: 1,
    gap: 8,
    justifyContent: 'center',
    minHeight: 96,
    padding: 10,
  },
  miniActionText: {
    color: colors.navy,
    fontSize: 13,
    fontWeight: '600',
    textAlign: 'center',
  },
  archiveContent: {
    gap: 12,
    padding: 16,
    paddingBottom: 36,
  },
  riderArchiveCard: {
    gap: 13,
    padding: 16,
  },
  archiveRowBetween: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
  },
  archiveRideTitle: {
    color: colors.navy,
    fontSize: 16,
    fontWeight: '900',
  },
  archiveDate: {
    color: colors.muted,
    fontSize: 11,
    marginTop: 3,
  },
  archiveStatus: {
    borderRadius: 999,
    paddingHorizontal: 9,
    paddingVertical: 5,
  },
  archiveStatusText: {
    fontSize: 9,
    fontWeight: '900',
    letterSpacing: .5,
  },
  archiveRoute: {
    flexDirection: 'row',
    gap: 11,
  },
  archiveRouteLine: {
    alignItems: 'center',
    paddingTop: 4,
  },
  archiveDot: {
    borderRadius: 5,
    height: 10,
    width: 10,
  },
  archiveBar: {
    backgroundColor: colors.line,
    flex: 1,
    marginVertical: 3,
    width: 2,
  },
  archiveRouteText: {
    color: colors.navy,
    fontSize: 13,
    fontWeight: '600',
  },
  archiveMeta: {
    color: colors.muted,
    fontSize: 12,
  },
  archiveFare: {
    color: colors.rust,
    fontSize: 16,
    fontWeight: '900',
  },
  archiveChatButton: {
    alignItems: 'center',
    backgroundColor: colors.sand,
    borderColor: colors.line,
    borderRadius: 14,
    borderWidth: 1,
    flexDirection: 'row',
    gap: 8,
    justifyContent: 'center',
    padding: 12,
  },
  archiveChatText: {
    color: colors.navy,
    fontSize: 14,
    fontWeight: '800',
  },
  riderConversationCard: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 10,
    padding: 13,
  },
  archiveMessageIcon: {
    alignItems: 'center',
    backgroundColor: colors.navy,
    borderRadius: 20,
    height: 42,
    justifyContent: 'center',
    width: 42,
  },
  archiveRoutePreview: {
    color: colors.navy,
    fontSize: 11.5,
    fontWeight: '700',
    marginTop: 3,
  },
  archiveMessagePreview: {
    color: colors.muted,
    fontSize: 12,
    marginTop: 4,
  },
  archiveMessageCount: {
    alignItems: 'center',
    backgroundColor: colors.rust,
    borderRadius: 10,
    justifyContent: 'center',
    minHeight: 20,
    minWidth: 20,
    paddingHorizontal: 5,
  },
  archiveMessageCountText: {
    color: colors.white,
    fontSize: 10,
    fontWeight: '900',
  },
  archiveEmpty: {
    alignItems: 'center',
    gap: 8,
    paddingVertical: 42,
  },
  chatContent: {
    flexGrow: 1,
    gap: 10,
    padding: 16,
  },
  chatBubble: {
    alignSelf: 'flex-start',
    backgroundColor: colors.card,
    borderColor: colors.line,
    borderRadius: 16,
    borderWidth: 1,
    maxWidth: '82%',
    paddingHorizontal: 14,
    paddingVertical: 10,
  },
  chatBubbleMine: {
    alignSelf: 'flex-end',
    backgroundColor: colors.greenSoft,
    borderColor: '#b8d8cb',
  },
  chatRole: {
    color: colors.rust,
    fontSize: 11,
    fontWeight: '800',
    textTransform: 'uppercase',
  },
  chatText: {
    color: colors.navy,
    fontSize: 16,
    lineHeight: 22,
    marginTop: 3,
  },
  chatComposer: {
    alignItems: 'center',
    backgroundColor: colors.card,
    borderTopColor: colors.line,
    borderTopWidth: 1,
    flexDirection: 'row',
    gap: 10,
    paddingHorizontal: 14,
    paddingTop: 12,
    paddingBottom: 30,
  },
  chatInput: {
    backgroundColor: colors.white,
    borderColor: colors.line,
    borderRadius: 22,
    borderWidth: 1,
    color: colors.navy,
    flex: 1,
    minHeight: 44,
    paddingHorizontal: 16,
  },
  chatSend: {
    alignItems: 'center',
    backgroundColor: colors.rust,
    borderRadius: 22,
    height: 44,
    justifyContent: 'center',
    width: 44,
  },
  completedContent: {
    padding: 16,
    paddingBottom: 48,
  },
  completedHero: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 14,
    paddingBottom: 18,
    paddingHorizontal: 4,
    paddingTop: 8,
  },
  successCircle: {
    alignItems: 'center',
    backgroundColor: colors.green,
    borderColor: '#abd0c3',
    borderRadius: 999,
    borderWidth: 6,
    height: 68,
    justifyContent: 'center',
    width: 68,
  },
  completedTitle: {
    color: colors.navy,
    fontSize: 24,
    fontWeight: '900',
  },
  completedSub: {
    color: colors.muted,
    fontSize: 14,
    lineHeight: 19,
    marginTop: 4,
  },
  dividerFlower: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 14,
    marginVertical: 22,
  },
  smallLine: {
    backgroundColor: colors.line,
    height: 1,
    width: 80,
  },
  totalFareCard: {
    alignItems: 'center',
    flexDirection: 'row',
    justifyContent: 'space-between',
    padding: 18,
    width: '100%',
  },
  fareIllustration: { alignItems: 'center', backgroundColor: colors.rustLight, borderRadius: 18, height: 70, justifyContent: 'center', width: 70 },
  paidCard: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 14,
    marginTop: 14,
    padding: 14,
    width: '100%',
  },
  cashIcon: {
    alignItems: 'center',
    backgroundColor: colors.navy,
    borderRadius: 10,
    height: 52,
    justifyContent: 'center',
    width: 52,
  },
  paidPill: {
    alignItems: 'center',
    backgroundColor: colors.greenSoft,
    borderRadius: 999,
    flexDirection: 'row',
    gap: 6,
    paddingHorizontal: 14,
    paddingVertical: 8,
  },
  paidText: {
    color: colors.green,
    fontSize: 17,
    fontWeight: '700',
  },
  completedDriver: {
    alignItems: 'center',
    flexDirection: 'row',
    gap: 14,
    marginTop: 14,
    padding: 14,
    width: '100%',
  },
  callBox: {
    alignItems: 'center',
    borderColor: colors.line,
    borderRadius: 15,
    borderWidth: 1,
    height: 58,
    justifyContent: 'center',
    width: 58,
  },
  ratingCard: {
    marginTop: 14,
    padding: 14,
    width: '100%',
  },
  starsRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginVertical: 22,
  },
  reviewInput: {
    borderColor: colors.line,
    borderRadius: 12,
    borderWidth: 1,
    color: colors.navy,
    fontSize: 16,
    minHeight: 76,
    padding: 12,
    textAlignVertical: 'top',
  },
});
