import { Ionicons } from '@expo/vector-icons';
import { StatusBar } from 'expo-status-bar';
import * as ImagePicker from 'expo-image-picker';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Image,
  KeyboardAvoidingView,
  Linking,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import {
  acceptOrder,
  counterOrder,
  createConnectOnboarding,
  createPointsTopupIntent,
  declineOrder,
  driverOffline,
  driverOnline,
  getCatalog,
  getAccessToken,
  getDriverDocuments,
  getDriverCurrentOrder,
  getDriverConversations,
  getDriverHistory,
  getDriverOrders,
  getNotifications,
  getPointsPurchaseConfig,
  getOrderMessages,
  getWallet,
  idempotencyKey,
  markOrder,
  markNotificationRead,
  restoreSession,
  sendOrderMessage,
  updateDriverLocation,
  uploadDriverDocument,
} from './api';
import { watchCoord } from './location';
import { createRealtimeClient } from './realtime';
import { IncomingCallNotice, registerForPush, subscribeToIncomingCalls, unregisterPush } from './push';
import { payWithCardSheet } from './stripeCard';
import { VoiceCallScreen } from './VoiceCallScreen';
import { IncomingCallPrompt } from './IncomingCallPrompt';
import { AppNotification, CatalogCity, ChatMessage, DocChecklistItem, DriverConversation, DriverDocuments, Order, PointsPurchaseConfig, Wallet } from './types';
import { colors, radius, shadow } from './theme';

const CITY_COORDS: Record<string, { lat: number; lng: number }> = {
  Casablanca: { lat: 33.5731, lng: -7.5898 }, Marrakech: { lat: 31.6295, lng: -7.9811 },
  Rabat: { lat: 34.0209, lng: -6.8416 }, Fez: { lat: 34.0181, lng: -5.0078 },
  Tangier: { lat: 35.7595, lng: -5.834 }, Agadir: { lat: 30.4278, lng: -9.5981 },
  Essaouira: { lat: 31.5085, lng: -9.7595 }, Chefchaouen: { lat: 35.1688, lng: -5.2636 },
};
const DOC_LABELS: Record<string, string> = {
  profile: 'Profile photo', vehicle_out: 'Vehicle · exterior', vehicle_in: 'Vehicle · interior',
  id_front: 'ID card · front', id_back: 'ID card · back', license: 'Driving license', tourism_agreement: 'Tourism agreement',
};
const DOC_ICONS: Record<string, string> = {
  profile: 'person-circle-outline', vehicle_out: 'car-outline', vehicle_in: 'car-sport-outline',
  id_front: 'card-outline', id_back: 'card-outline', license: 'document-text-outline', tourism_agreement: 'ribbon-outline',
};

type Tab = 'drive' | 'rides' | 'messages' | 'verify' | 'wallet' | 'profile';
const TABS: { id: Tab; label: string; icon: string }[] = [
  { id: 'drive', label: 'Drive', icon: 'car-sport' },
  { id: 'rides', label: 'Rides', icon: 'time' },
  { id: 'messages', label: 'Messages', icon: 'chatbubbles' },
  { id: 'verify', label: 'Verify', icon: 'shield-checkmark' },
  { id: 'wallet', label: 'Wallet', icon: 'wallet' },
  { id: 'profile', label: 'Profile', icon: 'person' },
];

export default function DriverApp({ onSwitchRole, freeLaunch = false }: { onSwitchRole: () => void; freeLaunch?: boolean }) {
  const [phase, setPhase] = useState<'loading' | 'ready'>('loading');
  const [tab, setTab] = useState<Tab>('drive');
  const [menuOpen, setMenuOpen] = useState(false);
  const [notificationsOpen, setNotificationsOpen] = useState(false);
  const [notifications, setNotifications] = useState<AppNotification[]>([]);
  const [notificationBadge, setNotificationBadge] = useState(0);
  const [incomingCall, setIncomingCall] = useState<(IncomingCallNotice & { notificationId?: number }) | null>(null);
  const [driver, setDriver] = useState<{ name: string; email: string; approvalState: string }>({ name: 'Driver', email: '', approvalState: 'incomplete' });
  const [cities, setCities] = useState<CatalogCity[]>([]);
  const [cityId, setCityId] = useState<number | null>(null);
  const [online, setOnline] = useState(false);
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);
  const [queue, setQueue] = useState<Order[]>([]);
  const [activeOrder, setActiveOrder] = useState<Order | null>(null);
  const [wallet, setWallet] = useState<Wallet | null>(null);
  const [earnings, setEarnings] = useState<Order | null>(null);
  const [chatOrderId, setChatOrderId] = useState<number | null>(null);
  const [callOrderId, setCallOrderId] = useState<number | null>(null);
  const [callShouldNotify, setCallShouldNotify] = useState(true);
  const [history, setHistory] = useState<Order[]>([]);
  const [conversations, setConversations] = useState<DriverConversation[]>([]);
  const [archiveLoading, setArchiveLoading] = useState(false);
  const [messageBadge, setMessageBadge] = useState(0);
  const [docs, setDocs] = useState<DriverDocuments | null>(null);
  const [uploading, setUploading] = useState<string | null>(null);
  const [topUpBusy, setTopUpBusy] = useState(false);
  const [topUpStatus, setTopUpStatus] = useState<string | null>(null);
  const [pointsConfig, setPointsConfig] = useState<PointsPurchaseConfig | null>(null);
  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const lastNotificationIdRef = useRef<number | null>(null);

  useEffect(() => {
    if (freeLaunch && tab === 'wallet') setTab('drive');
  }, [freeLaunch, tab]);

  function shouldShowIncomingCall(orderId: number) {
    return callOrderId !== orderId && (!activeOrder?.id || activeOrder.id === orderId);
  }

  function queueIncomingCall(call: IncomingCallNotice & { notificationId?: number }) {
    if (!shouldShowIncomingCall(call.orderId)) return;
    setIncomingCall((current) => current?.orderId === call.orderId && current.notificationId === call.notificationId ? current : call);
  }

  const flash = useCallback((m: string) => {
    setNotice(m);
    setTimeout(() => setNotice((c) => (c === m ? null : c)), 4000);
  }, []);
  const refreshWallet = useCallback(async () => { try { setWallet(await getWallet()); } catch { /* best effort */ } }, []);
  const refreshPointsConfig = useCallback(async () => { try { setPointsConfig(await getPointsPurchaseConfig()); } catch { /* best effort */ } }, []);
  const loadDocs = useCallback(async () => {
    try {
      const fresh = await getDriverDocuments();
      setDocs(fresh);
      setDriver((current) => ({ ...current, approvalState: fresh.approval_state || current.approvalState }));
    } catch { /* best effort */ }
  }, []);
  const loadHistory = useCallback(async () => {
    setArchiveLoading(true);
    try { setHistory(await getDriverHistory()); }
    catch (e) { flash(e instanceof Error ? e.message : 'Could not load ride history'); }
    finally { setArchiveLoading(false); }
  }, [flash]);
  const loadConversations = useCallback(async () => {
    setArchiveLoading(true);
    try { setConversations(await getDriverConversations()); }
    catch (e) { flash(e instanceof Error ? e.message : 'Could not load messages'); }
    finally { setArchiveLoading(false); }
  }, [flash]);

  useEffect(() => {
    let on = true;
    (async () => {
      try {
        const session = (await restoreSession().catch(() => null)) ?? null;
        if (session?.role !== 'driver') throw new Error('Sign in with a driver account to continue.');
        const user = session.user;
        if (!on) return;
        registerForPush();
        const profile = user.driver_profile;
        setDriver({ name: user.name || 'Driver', email: user.email || '', approvalState: profile?.approval_state ?? 'incomplete' });
        setOnline(Boolean(profile?.online_status));
        const catalog = await getCatalog();
        if (!on) return;
        setCities(catalog.cities);
        const nearest = profile?.current_lat != null && profile.current_lng != null
          ? catalog.cities.reduce<CatalogCity | null>((best, city) => {
              const coord = CITY_COORDS[city.name];
              const bestCoord = best ? CITY_COORDS[best.name] : null;
              if (!coord) return best;
              const distance = (coord.lat - profile.current_lat!) ** 2 + (coord.lng - profile.current_lng!) ** 2;
              const bestDistance = bestCoord ? (bestCoord.lat - profile.current_lat!) ** 2 + (bestCoord.lng - profile.current_lng!) ** 2 : Infinity;
              return distance < bestDistance ? city : best;
            }, null)
          : null;
        setCityId(nearest?.id ?? catalog.cities.find((c) => c.name === 'Marrakech')?.id ?? catalog.cities[0]?.id ?? null);
        await Promise.all([
          refreshWallet(),
          loadDocs(),
          refreshPointsConfig(),
        ]);
      } catch (e) {
        if (on) flash(e instanceof Error ? e.message : 'Failed to start');
      } finally {
        if (on) setPhase('ready');
      }
    })();
    return () => { on = false; };
  }, [flash, refreshWallet, refreshPointsConfig, loadDocs]);

  useEffect(() => {
    if (phase !== 'ready') return undefined;
    return subscribeToIncomingCalls(queueIncomingCall);
  }, [phase, activeOrder?.id, callOrderId]);

  const loadQueue = useCallback(async () => {
    try { setQueue(await getDriverOrders()); }
    catch (e) { setQueue([]); if (e instanceof Error && !e.message.toLowerCase().includes('online')) flash(e.message); }
  }, [flash]);

  const loadCurrentOrder = useCallback(async (): Promise<Order | null> => {
    try {
      const fresh = await getDriverCurrentOrder();
      setActiveOrder((previous) => {
        if (fresh && previous?.id !== fresh.id) flash(`Ride #${fresh.id} is now your current ride`);
        return fresh;
      });
      return fresh;
    } catch { return null; }
  }, [flash]);

  useEffect(() => {
    if (pollRef.current) clearInterval(pollRef.current);
    if (phase !== 'ready' || tab !== 'drive') return;
    const refreshDrive = async () => {
      const current = await loadCurrentOrder();
      if (!current && online) await loadQueue();
    };
    refreshDrive();
    pollRef.current = setInterval(refreshDrive, 3000);
    return () => { if (pollRef.current) clearInterval(pollRef.current); };
  }, [phase, tab, online, loadQueue, loadCurrentOrder]);

  useEffect(() => {
    if (phase !== 'ready') return;
    if (tab === 'rides') loadHistory();
    if (tab === 'messages') loadConversations();
    if (tab === 'wallet') Promise.all([refreshWallet(), refreshPointsConfig()]);
  }, [phase, tab, loadHistory, loadConversations, refreshWallet, refreshPointsConfig]);

  useEffect(() => {
    if (phase !== 'ready') return undefined;
    let active = true;
    const refresh = async () => {
      try {
        const all = await getNotifications();
        if (!active) return;
        setNotifications(all);
        setNotificationBadge(all.filter((item) => !item.read_at).length);
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
        if (all.some((item) => item.type === 'driver_approved' && !item.read_at)) {
          await loadDocs();
        }
        const unread = all.filter((item) => item.type === 'chat_message' && !item.read_at);
        const latest = all.find((item) => item.type === 'chat_message');
        if (latest && lastNotificationIdRef.current !== null && latest.id > lastNotificationIdRef.current) {
          flash(`${latest.title}: ${latest.body}`);
        }
        if (latest) lastNotificationIdRef.current = Math.max(lastNotificationIdRef.current ?? 0, latest.id);

        if (tab === 'messages' || chatOrderId !== null) {
          await Promise.all(unread.map((item) => markNotificationRead(item.id).catch(() => null)));
          if (active) setMessageBadge(0);
        } else {
          setMessageBadge(unread.length);
        }
      } catch { /* polling fallback is best effort */ }
    };
    refresh();
    const id = setInterval(refresh, 4000);
    return () => { active = false; clearInterval(id); };
  }, [phase, tab, chatOrderId, flash, loadDocs]);

  // Publish real device GPS to the backend while online (foreground). The
  // watcher throttles to ~8s / 25m; failures are ignored best-effort.
  useEffect(() => {
    if (!online || !cityId) return undefined;
    let stop = () => undefined as void;
    let cancelled = false;
    watchCoord((coord) => {
      updateDriverLocation({ city_id: cityId, lat: coord.lat, lng: coord.lng }).catch(() => undefined);
    }).then((unsub) => { if (cancelled) unsub(); else stop = unsub; });
    return () => { cancelled = true; stop(); };
  }, [online, cityId]);

  async function buyPoints(amount: number) {
    if (!amount || amount <= 0) { flash('Enter a top-up amount'); return; }
    setTopUpBusy(true); setTopUpStatus(null);
    try {
      const intent = await createPointsTopupIntent(amount, idempotencyKey('points'));
      if (!intent.client_secret || !intent.publishable_key) {
        setTopUpStatus('Card payment is unavailable — Stripe is not configured yet.');
        return;
      }

      const result = await payWithCardSheet({
        clientSecret: intent.client_secret,
        publishableKey: intent.publishable_key,
        label: `${intent.points ?? Math.round(amount * (pointsConfig?.points_per_currency_unit ?? 1))} MoroRide points`,
      });

      if (result.status === 'completed') {
        setTopUpStatus(`Payment received — ${intent.points ?? 'your'} points appear once Stripe confirms.`);
        // Stripe confirms the top-up asynchronously through the backend
        // webhook. Refresh more than once so a slow webhook does not leave the
        // old balance on screen until the driver manually reloads the wallet.
        void (async () => {
          for (let attempt = 0; attempt < 8; attempt += 1) {
            await new Promise((resolve) => setTimeout(resolve, attempt === 0 ? 800 : 2000));
            await refreshWallet();
          }
        })();
      } else if (result.status === 'canceled') {
        setTopUpStatus('Payment canceled.');
      } else {
        setTopUpStatus(result.message ?? 'Card payment failed.');
      }
    } catch (e) {
      setTopUpStatus(e instanceof Error ? e.message : 'Could not start top-up');
    } finally { setTopUpBusy(false); }
  }

  async function setupPayouts() {
    try {
      const res = await createConnectOnboarding();
      if (res.onboarding_url) { Linking.openURL(res.onboarding_url); }
    } catch (e) {
      flash(e instanceof Error ? e.message : 'Payouts are not available yet');
    }
  }

  async function goOnline() {
    if (!cityId) return;
    setBusy(true); setNotice(null);
    try {
      const city = cities.find((c) => c.id === cityId);
      const coord = (city && CITY_COORDS[city.name]) || { lat: 31.6295, lng: -7.9811 };
      await driverOnline({ city_id: cityId, current_lat: coord.lat, current_lng: coord.lng });
      setOnline(true); await loadQueue();
    } catch (e) { flash(e instanceof Error ? e.message : 'Could not go online'); }
    finally { setBusy(false); }
  }
  async function selectCity(nextCityId: number) {
    setCityId(nextCityId);
    if (!online) return;

    setBusy(true); setNotice(null);
    try {
      const city = cities.find((item) => item.id === nextCityId);
      const coord = (city && CITY_COORDS[city.name]) || { lat: 31.6295, lng: -7.9811 };
      await driverOnline({ city_id: nextCityId, current_lat: coord.lat, current_lng: coord.lng });
      await loadQueue();
      flash(`Now receiving requests in ${city?.name ?? 'this city'}`);
    } catch (e) { flash(e instanceof Error ? e.message : 'Could not change city'); }
    finally { setBusy(false); }
  }
  async function goOffline() {
    setBusy(true);
    try { await driverOffline(); setOnline(false); setQueue([]); }
    catch (e) { flash(e instanceof Error ? e.message : 'Could not go offline'); }
    finally { setBusy(false); }
  }
  async function onAccept(order: Order) {
    setBusy(true); setNotice(null);
    try { const a = await acceptOrder(order.id); setActiveOrder(a); setQueue((q) => q.filter((o) => o.id !== order.id)); }
    catch (e) { flash(e instanceof Error ? e.message : 'Could not accept'); }
    finally { setBusy(false); }
  }
  async function onCounter(order: Order, amount: number) {
    setBusy(true);
    try { await counterOrder(order.id, amount); flash(`Counter of ${amount} MAD sent`); await loadQueue(); }
    catch (e) { flash(e instanceof Error ? e.message : 'Could not send counter'); }
    finally { setBusy(false); }
  }
  async function onDecline(order: Order) {
    setBusy(true);
    try { await declineOrder(order.id); setQueue((q) => q.filter((o) => o.id !== order.id)); }
    catch (e) { flash(e instanceof Error ? e.message : 'Could not decline'); }
    finally { setBusy(false); }
  }
  async function advanceRide() {
    if (!activeOrder) return;
    const next = activeOrder.status === 'assigned' ? 'arrived' : activeOrder.status === 'arrived' ? 'start' : 'complete';
    setBusy(true); setNotice(null);
    try {
      const updated = await markOrder(activeOrder.id, next as 'arrived' | 'start' | 'complete');
      if (updated.status === 'completed') { setEarnings(updated); setActiveOrder(null); await refreshWallet(); }
      else setActiveOrder(updated);
    } catch (e) { flash(e instanceof Error ? e.message : 'Action failed'); }
    finally { setBusy(false); }
  }

  async function pickAndUpload(type: string) {
    try {
      const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
      if (!perm.granted) { flash('Photo permission is required'); return; }
      const result = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ImagePicker.MediaTypeOptions.Images, quality: 0.7 });
      if (result.canceled || !result.assets?.length) return;
      const asset = result.assets[0];
      setUploading(type);
      await uploadDriverDocument(type, { uri: asset.uri, name: asset.fileName ?? `${type}.jpg`, mimeType: asset.mimeType ?? 'image/jpeg' });
      await loadDocs();
      flash(`${DOC_LABELS[type] ?? type} uploaded`);
    } catch (e) { flash(e instanceof Error ? e.message : 'Upload failed'); }
    finally { setUploading(null); }
  }

  async function answerIncomingCall() {
    if (!incomingCall) return;
    const orderId = incomingCall.orderId;
    const notificationId = incomingCall.notificationId;
    setIncomingCall(null);
    if (notificationId) {
      markNotificationRead(notificationId).catch(() => null);
    }
    if (activeOrder?.id !== orderId) {
      await loadCurrentOrder();
    }
    setCallShouldNotify(false);
    setCallOrderId(orderId);
  }

  function declineIncomingCall() {
    if (incomingCall?.notificationId) {
      markNotificationRead(incomingCall.notificationId).catch(() => null);
      setNotifications((current) => current.map((item) => item.id === incomingCall.notificationId ? { ...item, read_at: item.read_at ?? new Date().toISOString() } : item));
      setNotificationBadge((count) => Math.max(0, count - 1));
    }
    setIncomingCall(null);
  }

  if (phase === 'loading') {
    return (
      <SafeAreaView style={styles.page}>
        <View style={[styles.phone, styles.body, styles.center]}>
          <ActivityIndicator color={colors.gold} size="large" />
          <Text style={styles.dim}>Starting driver app…</Text>
        </View>
      </SafeAreaView>
    );
  }

  const headerTitle = tab === 'drive' ? 'Drive' : tab === 'rides' ? 'Ride history' : tab === 'messages' ? 'Messages' : tab === 'verify' ? 'Verification' : tab === 'wallet' ? 'Wallet & points' : 'Profile';

  return (
    <SafeAreaView style={styles.page}>
      <View style={styles.phone}>
      <StatusBar style="dark" translucent={false} backgroundColor={colors.sand} />
      {/* Header */}
      <View style={styles.header}>
        <Pressable accessibilityRole="button" accessibilityLabel="Open navigation menu" onPress={() => setMenuOpen(true)} style={styles.menuButton}>
          <Ionicons name="menu" size={25} color={colors.white} />
        </Pressable>
        <View style={styles.headerIdentity}>
          <View>
            <Text style={styles.eyebrow}>MORORIDE DRIVER</Text>
            <Text style={styles.title}>{tab === 'drive' ? `Hi, ${driver.name.split(' ')[0]}` : headerTitle}</Text>
          </View>
        </View>
        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Open notifications"
          onPress={async () => {
            setNotificationsOpen(true);
            const unread = notifications.filter((item) => !item.read_at);
            await Promise.all(unread.map((item) => markNotificationRead(item.id).catch(() => null)));
            setNotifications((current) => current.map((item) => ({ ...item, read_at: item.read_at ?? new Date().toISOString() })));
            setNotificationBadge(0);
          }}
          style={styles.headerBell}
        >
          <Ionicons name="notifications-outline" size={22} color={colors.white} />
          {notificationBadge > 0 ? <View style={styles.headerBellBadge}><Text style={styles.headerBellBadgeText}>{notificationBadge > 9 ? '9+' : notificationBadge}</Text></View> : null}
        </Pressable>
      </View>

      <ScrollView style={styles.scrollView} contentContainerStyle={styles.scroll} showsVerticalScrollIndicator={false}>
        {notice ? <View style={styles.notice}><Ionicons name="information-circle" size={18} color={colors.rust} /><Text style={styles.noticeText}>{notice}</Text></View> : null}

        {tab === 'drive' && (
          <DriveTab
            cities={cities} cityId={cityId} setCityId={selectCity} online={online} busy={busy}
            queue={queue} activeOrder={activeOrder} earnings={earnings} wallet={wallet}
            freeLaunch={freeLaunch}
            onGoOnline={goOnline} onGoOffline={goOffline} onAccept={onAccept} onCounter={onCounter}
            onDecline={onDecline} onAdvance={advanceRide} onChat={() => activeOrder && setChatOrderId(activeOrder.id)}
            onCall={() => {
              if (!activeOrder) return;
              setCallShouldNotify(true);
              setCallOrderId(activeOrder.id);
            }}
            onClearEarnings={() => setEarnings(null)} onRefresh={loadQueue}
            approvalState={driver.approvalState} onOpenVerification={() => setTab('verify')}
          />
        )}
        {tab === 'rides' && <RideHistoryTab orders={history} loading={archiveLoading} onRefresh={loadHistory} onOpenChat={setChatOrderId} />}
        {tab === 'messages' && <MessagesTab conversations={conversations} loading={archiveLoading} onRefresh={loadConversations} onOpenChat={setChatOrderId} />}
        {tab === 'verify' && <VerifyTab docs={docs} uploading={uploading} onUpload={pickAndUpload} onRefresh={loadDocs} />}
        {tab === 'wallet' && !freeLaunch && <WalletTab wallet={wallet} pointsConfig={pointsConfig} onRefresh={refreshWallet} onBuyPoints={buyPoints} onSetupPayouts={setupPayouts} topUpBusy={topUpBusy} topUpStatus={topUpStatus} />}
        {tab === 'profile' && <ProfileTab driver={driver} online={online} docs={docs} uploading={uploading} onUpload={pickAndUpload} onSwitchRole={onSwitchRole} />}

        <View style={{ height: 12 }} />
      </ScrollView>

      {menuOpen ? (
        <View style={styles.menuLayer}>
          <Pressable accessibilityRole="button" accessibilityLabel="Close navigation menu" onPress={() => setMenuOpen(false)} style={styles.menuBackdrop} />
          <View style={styles.menuPanel}>
            <View style={styles.menuHead}>
              <View style={styles.menuAvatar}><Text style={styles.menuAvatarText}>{driver.name.charAt(0).toUpperCase()}</Text></View>
              <View style={{ flex: 1 }}>
                <Text style={styles.menuName}>{driver.name}</Text>
                <Text style={styles.menuEmail}>{driver.email}</Text>
              </View>
              <Pressable onPress={() => setMenuOpen(false)} style={styles.menuClose}>
                <Ionicons name="close" size={22} color={colors.navy} />
              </Pressable>
            </View>
            <Text style={styles.menuSectionLabel}>DRIVER MENU</Text>
            <View style={styles.menuItems}>
              {TABS.filter((item) => !(freeLaunch && item.id === 'wallet')).map((item) => {
                const active = item.id === tab;
                const needsVerification = item.id === 'verify' && docs && !docs.has_all_required;
                const count = item.id === 'messages' ? messageBadge : 0;
                return (
                  <Pressable
                    key={item.id}
                    onPress={() => { setTab(item.id); setMenuOpen(false); }}
                    style={[styles.menuItem, active && styles.menuItemActive]}
                  >
                    <View style={[styles.menuItemIcon, active && styles.menuItemIconActive]}>
                      <Ionicons name={(active ? item.icon : `${item.icon}-outline`) as never} size={20} color={active ? colors.white : colors.navy} />
                    </View>
                    <Text style={[styles.menuItemText, active && styles.menuItemTextActive]}>{item.label}</Text>
                    {count > 0 ? <View style={styles.menuBadge}><Text style={styles.menuBadgeText}>{count > 9 ? '9+' : count}</Text></View> : needsVerification ? <View style={styles.menuDot} /> : null}
                    <Ionicons name="chevron-forward" size={18} color={active ? 'rgba(255,255,255,.7)' : colors.faded} />
                  </Pressable>
                );
              })}
            </View>
            <Pressable
              accessibilityRole="button"
              accessibilityLabel="Log out"
              onPress={() => { setMenuOpen(false); onSwitchRole(); }}
              style={styles.menuLogout}
            >
              <Ionicons name="log-out-outline" size={20} color={colors.danger} />
              <Text style={styles.menuLogoutText}>Log out</Text>
            </Pressable>
            <View style={styles.menuFooter}>
              <Image source={require('../assets/moro_logo_mark_transparent.png')} style={styles.menuMark} resizeMode="contain" />
              <Text style={styles.menuFooterText}>MoroRide Driver</Text>
            </View>
          </View>
        </View>
      ) : null}
      {notificationsOpen ? (
        <DriverNotifications
          notifications={notifications}
          onClose={() => setNotificationsOpen(false)}
          onOpenCall={(orderId) => {
            setNotificationsOpen(false);
            setCallShouldNotify(false);
            setCallOrderId(orderId);
          }}
        />
      ) : null}

      {chatOrderId ? <ChatOverlay orderId={chatOrderId} onClose={() => setChatOrderId(null)} /> : null}
      {callOrderId ? (
        <View style={styles.callOverlay}>
          <VoiceCallScreen orderId={callOrderId} peerLabel="Your rider" notify={callShouldNotify} onEnd={() => setCallOrderId(null)} />
        </View>
      ) : null}
      {incomingCall ? (
        <IncomingCallPrompt
          title={incomingCall.title}
          body={incomingCall.body}
          peerLabel="Your rider"
          onAnswer={answerIncomingCall}
          onDecline={declineIncomingCall}
        />
      ) : null}
      </View>
    </SafeAreaView>
  );
}

/* ---------------- Drive tab ---------------- */
function DriveTab(props: {
  cities: CatalogCity[]; cityId: number | null; setCityId: (id: number) => void; online: boolean; busy: boolean;
  queue: Order[]; activeOrder: Order | null; earnings: Order | null; wallet: Wallet | null;
  freeLaunch: boolean;
  onGoOnline: () => void; onGoOffline: () => void; onAccept: (o: Order) => void; onCounter: (o: Order, a: number) => void;
  onDecline: (o: Order) => void; onAdvance: () => void; onChat: () => void; onCall: () => void; onClearEarnings: () => void; onRefresh: () => void;
  approvalState: string; onOpenVerification: () => void;
}) {
  const { cities, cityId, online, busy, queue, activeOrder, earnings, wallet, freeLaunch } = props;
  return (
    <>
      {props.approvalState !== 'approved' ? (
        <View style={[styles.card, styles.verificationCard]}>
          <Ionicons name="shield-checkmark-outline" size={28} color={colors.rust} />
          <View style={{ flex: 1 }}>
            <Text style={styles.cardTitle}>Complete driver verification</Text>
            <Text style={styles.dim}>Upload your documents. You can receive rider requests after admin approval.</Text>
          </View>
          <Pressable onPress={props.onOpenVerification} style={[styles.btn, styles.btnGhost]}><Text style={styles.btnGhostText}>Verify</Text></Pressable>
        </View>
      ) : null}

      {freeLaunch ? (
        <View style={[styles.card, styles.freeWalletCard]}>
          <Ionicons name="gift-outline" size={28} color={colors.success} />
          <View style={{ flex: 1 }}>
            <Text style={styles.cardTitle}>Billing off during launch</Text>
            <Text style={styles.dim}>Wallet, points, and free-ride deductions are disabled. Complete rides without buying points.</Text>
          </View>
        </View>
      ) : (
        <View style={styles.walletRow}>
          <Stat label="Points" value={fmt(wallet?.points_balance)} icon="star" />
          <Stat label="Balance" value={`${fmt(wallet?.wallet_balance)}`} icon="cash" />
          <Stat label="Free rides" value={String(wallet?.free_rides_remaining ?? 0)} icon="gift" />
        </View>
      )}

      <View style={styles.currentRideBar}>
        <View style={[styles.currentRideIcon, activeOrder && styles.currentRideIconOn]}><Ionicons name="navigate" size={18} color={activeOrder ? colors.white : colors.navy} /></View>
        <View style={{ flex: 1 }}>
          <Text style={styles.currentRideTitle}>Current Ride</Text>
          <Text style={styles.dimSmall}>{activeOrder ? `Ride #${activeOrder.id} · ${activeOrder.status.replace('_', ' ')}` : 'Waiting for a rider to accept your offer'}</Text>
        </View>
        <View style={[styles.currentRideBadge, activeOrder && styles.currentRideBadgeOn]}><Text style={[styles.currentRideBadgeText, activeOrder && styles.currentRideBadgeTextOn]}>{activeOrder ? 'ACTIVE' : 'NONE'}</Text></View>
      </View>

      {earnings ? (
        <View style={[styles.card, styles.earnCard]}>
          <Ionicons name="checkmark-circle" size={38} color={colors.success} />
          <Text style={styles.earnTitle}>Ride completed</Text>
          <Text style={styles.earnAmount}>{fmt(earnings.final_fare ?? earnings.offered_fare)} MAD</Text>
          <Text style={styles.dim}>{freeLaunch ? 'Free launch — no wallet or points deduction' : (earnings.payment_method === 'card' ? 'Card — net credited to wallet' : 'Cash — commission from points')}</Text>
          <Pressable style={[styles.btn, styles.btnPrimary, styles.wFull]} onPress={props.onClearEarnings}><Text style={styles.btnPrimaryText}>Back to requests</Text></Pressable>
        </View>
      ) : activeOrder ? (
        <RidePanel order={activeOrder} busy={busy} onAdvance={props.onAdvance} onChat={props.onChat} onCall={props.onCall} />
      ) : (
        <>
          <View style={[styles.card, styles.availabilityCard]}>
            {online ? (
              <>
                <View style={styles.rowBetween}>
                  <View style={styles.availabilityTop}>
                    <View style={[styles.powerIcon, styles.powerIconOn]}><Ionicons name="car-sport" size={22} color={colors.white} /></View>
                    <View><Text style={styles.availabilityTitle}>You're online</Text><Text style={styles.dim}>Requests are coming in</Text></View>
                  </View>
                  <View style={styles.livePill}><View style={styles.liveDot} /><Text style={styles.liveText}>LIVE</Text></View>
                </View>
                <Text style={styles.dimSmall}>Tap another city to switch the incoming request area.</Text>
                <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.cityScroll}>
                  {cities.map((c) => (
                    <Pressable disabled={busy} key={c.id} onPress={() => props.setCityId(c.id)} style={[styles.chip, cityId === c.id && styles.chipActive]}>
                      <Text style={[styles.chipText, cityId === c.id && styles.chipTextActive]}>{c.name}</Text>
                    </Pressable>
                  ))}
                </ScrollView>
                <Pressable style={[styles.btn, styles.offlineButton, styles.wFull]} disabled={busy} onPress={props.onGoOffline}>
                  <Ionicons name="power" size={18} color={colors.white} />
                  <Text style={styles.btnPrimaryText}>{busy ? '…' : 'Go offline'}</Text>
                </Pressable>
              </>
            ) : (
              <>
                <View style={styles.availabilityTop}>
                  <View style={styles.powerIcon}><Ionicons name="power" size={23} color={colors.navy} /></View>
                  <View><Text style={styles.availabilityTitle}>Ready to drive?</Text><Text style={styles.dim}>Choose where you want to receive rides</Text></View>
                </View>
                <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.cityScroll}>
                  {cities.map((c) => (
                    <Pressable key={c.id} onPress={() => props.setCityId(c.id)} style={[styles.chip, cityId === c.id && styles.chipActive]}>
                      <Text style={[styles.chipText, cityId === c.id && styles.chipTextActive]}>{c.name}</Text>
                    </Pressable>
                  ))}
                </ScrollView>
                <Pressable style={[styles.btn, styles.onlineButton, styles.wFull]} disabled={busy || !cityId} onPress={props.onGoOnline}>
                  <Ionicons name="power" size={18} color={colors.white} />
                  <Text style={styles.btnPrimaryText}>{busy ? '…' : 'Go online'}</Text>
                </Pressable>
              </>
            )}
          </View>

          {online && (
            <>
              <View style={styles.queueHead}>
                <View>
                  <Text style={styles.sectionTitle}>Incoming requests ({queue.length})</Text>
                  <Text style={styles.dimSmall}>{cities.find((city) => city.id === cityId)?.name}</Text>
                </View>
                <Pressable onPress={props.onRefresh} style={styles.refreshBtn}><Ionicons name="refresh" size={16} color={colors.navy} /><Text style={styles.link}>Refresh</Text></Pressable>
              </View>
              {queue.length === 0 ? (
                <View style={[styles.card, styles.center, { paddingVertical: 30 }]}>
                  <Ionicons name="time-outline" size={30} color={colors.faded} />
                  <Text style={styles.dim}>No open requests here yet.</Text>
                  <Text style={styles.dimSmall}>The rider must choose {cities.find((city) => city.id === cityId)?.name ?? 'the same city'}.</Text>
                </View>
              ) : queue.map((o) => (
                <QueueCard key={o.id} order={o} busy={busy} onAccept={() => props.onAccept(o)} onCounter={props.onCounter} onDecline={() => props.onDecline(o)} />
              ))}
            </>
          )}
        </>
      )}
    </>
  );
}

/* ---------------- Ride history + message archive ---------------- */
function RideHistoryTab({ orders, loading, onRefresh, onOpenChat }: {
  orders: Order[]; loading: boolean; onRefresh: () => void; onOpenChat: (orderId: number) => void;
}) {
  return (
    <>
      <ArchiveHeader title="Your rides" subtitle="Completed and cancelled trips" loading={loading} onRefresh={onRefresh} />
      {!loading && orders.length === 0 ? <ArchiveEmpty icon="time-outline" title="No ride history yet" body="Your finished rides will appear here." /> : null}
      {orders.map((order) => (
        <View key={order.id} style={[styles.card, styles.archiveCard]}>
          <View style={styles.rowBetween}>
            <View><Text style={styles.cardTitle}>Ride #{order.id}</Text><Text style={styles.requestMeta}>{formatRideDate(order.created_at)}</Text></View>
            <StatusBadge status={order.status} />
          </View>
          <Route from={order.pickup_address} to={order.dropoff_address} />
          <View style={styles.rowBetween}>
            <Text style={styles.dim}>{Number(order.distance_km || 0)} km · {order.payment_method ?? 'cash'}</Text>
            <Text style={styles.fare}>{fmt(order.final_fare ?? order.offered_fare)} MAD</Text>
          </View>
          <Pressable style={[styles.btn, styles.btnGhost, styles.wFull]} onPress={() => onOpenChat(order.id)}>
            <Ionicons name="chatbubble-ellipses-outline" size={17} color={colors.navy} /><Text style={styles.btnGhostText}>Open ride chat</Text>
          </Pressable>
        </View>
      ))}
    </>
  );
}

function MessagesTab({ conversations, loading, onRefresh, onOpenChat }: {
  conversations: DriverConversation[]; loading: boolean; onRefresh: () => void; onOpenChat: (orderId: number) => void;
}) {
  return (
    <>
      <ArchiveHeader title="Messages" subtitle="All your ride conversations" loading={loading} onRefresh={onRefresh} />
      {!loading && conversations.length === 0 ? <ArchiveEmpty icon="chatbubbles-outline" title="No messages yet" body="Chats with riders will appear here." /> : null}
      {conversations.map(({ order, messages_count, last_message }) => (
        <Pressable key={order.id} style={[styles.card, styles.conversationCard]} onPress={() => onOpenChat(order.id)}>
          <View style={styles.messageIcon}><Ionicons name="chatbubble" size={19} color={colors.white} /></View>
          <View style={{ flex: 1 }}>
            <View style={styles.rowBetween}>
              <Text style={styles.cardTitle}>Ride #{order.id}</Text>
              <Text style={styles.messageTime}>{formatRideDate(last_message?.created_at)}</Text>
            </View>
            <Text style={styles.messageRoute} numberOfLines={1}>{order.pickup_address} → {order.dropoff_address}</Text>
            <Text style={styles.messagePreview} numberOfLines={1}>{last_message?.text || (last_message?.image_url ? 'Photo' : 'Open conversation')}</Text>
          </View>
          <View style={styles.messageCount}><Text style={styles.messageCountText}>{messages_count}</Text></View>
          <Ionicons name="chevron-forward" size={17} color={colors.muted} />
        </Pressable>
      ))}
    </>
  );
}

function ArchiveHeader({ title, subtitle, loading, onRefresh }: { title: string; subtitle: string; loading: boolean; onRefresh: () => void }) {
  return <View style={styles.rowBetween}><View><Text style={styles.sectionTitle}>{title}</Text><Text style={styles.dimSmall}>{subtitle}</Text></View><Pressable onPress={onRefresh} style={styles.refreshBtn}>{loading ? <ActivityIndicator size="small" color={colors.navy} /> : <Ionicons name="refresh" size={17} color={colors.navy} />}<Text style={styles.link}>Refresh</Text></Pressable></View>;
}

function ArchiveEmpty({ icon, title, body }: { icon: string; title: string; body: string }) {
  return <View style={[styles.card, styles.center, styles.archiveEmpty]}><Ionicons name={icon as never} size={35} color={colors.faded} /><Text style={styles.cardTitle}>{title}</Text><Text style={styles.dim}>{body}</Text></View>;
}

function StatusBadge({ status }: { status: string }) {
  const completed = status === 'completed';
  return <View style={[styles.badge, { backgroundColor: completed ? colors.greenSoft : '#fbecec' }]}><Text style={[styles.badgeText, { color: completed ? colors.success : colors.danger }]}>{status.replace('_', ' ').toUpperCase()}</Text></View>;
}

function formatRideDate(value?: string): string {
  if (!value) return '';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
}

/* ---------------- Verify tab ---------------- */
function VerifyTab({ docs, uploading, onUpload, onRefresh }: {
  docs: DriverDocuments | null; uploading: string | null; onUpload: (type: string) => void; onRefresh: () => void;
}) {
  const list: DocChecklistItem[] = docs?.checklist ?? [];
  const uploaded = list.filter((d) => d.status !== 'missing').length;
  const total = list.length || 7;
  const pct = Math.round((uploaded / total) * 100);

  return (
    <>
      <View style={[styles.card, styles.verifyHead]}>
        <View style={styles.rowBetween}>
          <Text style={styles.cardTitle}>Documents</Text>
          <Text style={styles.dim}>{uploaded}/{total}</Text>
        </View>
        <View style={styles.progressTrack}><View style={[styles.progressFill, { width: `${pct}%` }]} /></View>
        <Text style={styles.dim}>
          {docs?.has_all_required ? 'All documents uploaded — waiting for admin review.' : 'Upload the 7 documents below to submit for verification.'}
        </Text>
      </View>

      {list.length === 0 ? (
        <View style={[styles.card, styles.center]}><ActivityIndicator color={colors.gold} /></View>
      ) : list.map((item) => (
        <DocRow key={item.type} item={item} uploading={uploading === item.type} onUpload={() => onUpload(item.type)} />
      ))}

      <Pressable onPress={onRefresh} style={[styles.btn, styles.btnGhost, styles.wFull]}><Text style={styles.btnGhostText}>Refresh status</Text></Pressable>
    </>
  );
}

function DocRow({ item, uploading, onUpload }: { item: DocChecklistItem; uploading: boolean; onUpload: () => void }) {
  const st = item.status;
  const badge = st === 'approved' ? { bg: colors.greenSoft, fg: colors.success, label: 'Approved' }
    : st === 'rejected' ? { bg: '#fbecec', fg: colors.danger, label: 'Rejected' }
    : st === 'pending' ? { bg: '#fbf1dd', fg: '#b8862f', label: 'Pending' }
    : { bg: '#eef0f3', fg: colors.muted, label: 'Missing' };
  return (
    <View style={styles.docRow}>
      <View style={styles.docIcon}><Ionicons name={(DOC_ICONS[item.type] ?? 'document-outline') as never} size={20} color={colors.navy} /></View>
      <View style={{ flex: 1 }}>
        <Text style={styles.docTitle}>{DOC_LABELS[item.type] ?? item.type}</Text>
        <View style={[styles.badge, { backgroundColor: badge.bg }]}><Text style={[styles.badgeText, { color: badge.fg }]}>{badge.label}</Text></View>
      </View>
      <Pressable style={[styles.btn, styles.btnSm, st === 'missing' ? styles.btnPrimary : styles.btnGhost]} disabled={uploading} onPress={onUpload}>
        {uploading ? <ActivityIndicator size="small" color={st === 'missing' ? colors.white : colors.navy} />
          : <Text style={st === 'missing' ? styles.btnPrimaryText : styles.btnGhostText}>{st === 'missing' ? 'Upload' : 'Replace'}</Text>}
      </Pressable>
    </View>
  );
}

/* ---------------- Wallet tab ---------------- */
function WalletTab({ wallet, pointsConfig, onRefresh, onBuyPoints, onSetupPayouts, topUpBusy, topUpStatus }: {
  wallet: Wallet | null;
  pointsConfig: PointsPurchaseConfig | null;
  onRefresh: () => void;
  onBuyPoints: (amount: number) => void;
  onSetupPayouts: () => void;
  topUpBusy: boolean;
  topUpStatus: string | null;
}) {
  const ledger = wallet?.ledger_entries ?? [];
  const [amount, setAmount] = useState('100');
  const numericAmount = Number(amount) || 0;
  const pointsToReceive = numericAmount * (pointsConfig?.points_per_currency_unit ?? 1);
  const purchaseDisabled = topUpBusy
    || !pointsConfig?.available
    || numericAmount < (pointsConfig?.minimum_payment ?? 0)
    || numericAmount > (pointsConfig?.maximum_payment ?? Number.MAX_SAFE_INTEGER);
  return (
    <>
      <View style={[styles.card, styles.balanceCard]}>
        <Text style={styles.balanceLabel}>Wallet balance</Text>
        <Text style={styles.balanceValue}>{fmt(wallet?.wallet_balance)} <Text style={styles.balanceCur}>{wallet?.currency ?? 'MAD'}</Text></Text>
        <View style={styles.balanceMeta}>
          <View style={styles.balanceMetaItem}><Text style={styles.balanceMetaVal}>{fmt(wallet?.points_balance)}</Text><Text style={styles.balanceMetaLbl}>Points</Text></View>
          <View style={styles.balanceDivider} />
          <View style={styles.balanceMetaItem}><Text style={styles.balanceMetaVal}>{wallet?.free_rides_remaining ?? 0}</Text><Text style={styles.balanceMetaLbl}>Free rides</Text></View>
        </View>
      </View>

      <View style={styles.card}>
        <Text style={styles.cardTitle}>Buy points</Text>
        <Text style={styles.dim}>Points cover the platform fee on cash rides. Purchases are confirmed by card.</Text>
        <View style={styles.notice}>
          <Ionicons name="pricetag-outline" size={18} color={colors.rust} />
          <Text style={styles.noticeText}>1 point = {fmt(pointsConfig?.point_price ?? 1)} MAD · You receive {fmt(pointsToReceive)} points</Text>
        </View>
        <View style={styles.topUpRow}>
          {[50, 100, 250, 500].map((preset) => (
            <Pressable key={preset} onPress={() => setAmount(String(preset))} style={[styles.chip, amount === String(preset) && styles.chipActive]}>
              <Text style={[styles.chipText, amount === String(preset) && styles.chipTextActive]}>{preset}</Text>
            </Pressable>
          ))}
        </View>
        <View style={styles.topUpInputRow}>
          <TextInput value={amount} onChangeText={setAmount} keyboardType="numeric" style={styles.topUpInput} placeholderTextColor={colors.faded} />
          <Text style={styles.topUpCur}>{pointsConfig?.currency === 'USD' ? '$' : (pointsConfig?.currency ?? 'MAD')}</Text>
          <Pressable style={[styles.btn, styles.btnPrimary, purchaseDisabled && { opacity: 0.5 }]} disabled={purchaseDisabled} onPress={() => onBuyPoints(Number(amount))}>
            {topUpBusy ? <ActivityIndicator size="small" color={colors.white} /> : <Text style={styles.btnPrimaryText}>Buy</Text>}
          </Pressable>
        </View>
        {pointsConfig ? <Text style={styles.dimSmall}>Allowed payment: {fmt(pointsConfig.minimum_payment)}–{fmt(pointsConfig.maximum_payment)} {pointsConfig.currency}</Text> : null}
        {pointsConfig && !pointsConfig.available ? <Text style={[styles.dimSmall, { color: colors.danger }]}>Card purchases are disabled by admin.</Text> : null}
        {topUpStatus ? <Text style={styles.dimSmall}>{topUpStatus}</Text> : null}
      </View>

      <View style={styles.card}>
        <Text style={styles.cardTitle}>Payout account</Text>
        <Text style={styles.dim}>Connect a payout account to withdraw your card earnings to your bank.</Text>
        <Pressable style={[styles.btn, styles.btnGhost, styles.wFull]} onPress={onSetupPayouts}>
          <Ionicons name="business-outline" size={18} color={colors.navy} />
          <Text style={styles.btnGhostText}>Set up payouts</Text>
        </Pressable>
      </View>

      <View style={styles.rowBetween}>
        <Text style={styles.sectionTitle}>Recent activity</Text>
        <Pressable onPress={onRefresh}><Text style={styles.link}>Refresh</Text></Pressable>
      </View>
      {ledger.length === 0 ? (
        <View style={[styles.card, styles.center, { paddingVertical: 24 }]}><Text style={styles.dim}>No transactions yet. Complete a ride to earn.</Text></View>
      ) : ledger.slice(0, 20).map((e) => (
        <View key={e.id} style={styles.ledgerRow}>
          <View style={[styles.ledgerIcon, { backgroundColor: e.direction === 'credit' ? colors.greenSoft : '#fbecec' }]}>
            <Ionicons name={(e.direction === 'credit' ? 'arrow-down' : 'arrow-up') as never} size={16} color={e.direction === 'credit' ? colors.success : colors.danger} />
          </View>
          <View style={{ flex: 1 }}>
            <Text style={styles.ledgerTitle}>{(e.entry_type || '').replace(/_/g, ' ')}</Text>
            <Text style={styles.dimSmall}>{e.reason}</Text>
          </View>
          <Text style={[styles.ledgerAmount, { color: e.direction === 'credit' ? colors.success : colors.danger }]}>
            {e.direction === 'credit' ? '+' : '-'}{fmt(Math.abs(e.amount || e.points_delta))}
          </Text>
        </View>
      ))}
    </>
  );
}

/* ---------------- Profile tab ---------------- */
function ProfileTab({ driver, online, docs, uploading, onUpload, onSwitchRole }: {
  driver: { name: string; email: string; approvalState: string };
  online: boolean;
  docs: DriverDocuments | null;
  uploading: string | null;
  onUpload: (type: string) => void;
  onSwitchRole: () => void;
}) {
  const vehiclePhotos = ['vehicle_out', 'vehicle_in'].map((type) => ({
    type,
    label: type === 'vehicle_out' ? 'Exterior' : 'Interior',
    document: docs?.documents.find((item) => item.type === type),
  }));
  return (
    <>
      <View style={[styles.card, styles.center, { paddingVertical: 26 }]}>
        <View style={styles.avatar}><Text style={styles.avatarText}>{(driver.name || 'D').charAt(0).toUpperCase()}</Text></View>
        <Text style={styles.profileName}>{driver.name}</Text>
        <Text style={styles.dim}>{driver.email}</Text>
        <Text style={styles.requestMeta}>Verification: {driver.approvalState}</Text>
        <View style={[styles.pill, online ? styles.pillOn : styles.pillOff, { marginTop: 8 }]}>
          <View style={[styles.dot, { backgroundColor: online ? colors.success : colors.faded }]} />
          <Text style={[styles.pillText, { color: online ? colors.success : colors.muted }]}>{online ? 'Online' : 'Offline'}</Text>
        </View>
      </View>
      <View style={styles.profileGalleryHead}>
        <View><Text style={styles.sectionTitle}>Vehicle gallery</Text><Text style={styles.dimSmall}>Show riders a clean exterior and interior photo.</Text></View>
        <Ionicons name="images-outline" size={24} color={colors.rust} />
      </View>
      <View style={styles.profileGallery}>
        {vehiclePhotos.map(({ type, label, document }) => (
          <Pressable key={type} onPress={() => onUpload(type)} disabled={uploading !== null} style={styles.profileUploadCard}>
            {document?.preview_url ? <Image source={{ uri: document.preview_url }} style={styles.profileUploadImage} resizeMode="cover" /> : (
              <View style={styles.profileUploadEmpty}><Ionicons name="camera-outline" size={28} color={colors.rust} /><Text style={styles.profileUploadEmptyText}>Add photo</Text></View>
            )}
            <View style={styles.profileUploadBar}>
              <View><Text style={styles.profileUploadTitle}>{label}</Text><Text style={styles.profileUploadStatus}>{document?.status ?? 'Required'}</Text></View>
              {uploading === type ? <ActivityIndicator size="small" color={colors.rust} /> : <Ionicons name={document ? 'camera-reverse-outline' : 'add-circle-outline'} size={21} color={colors.rust} />}
            </View>
          </Pressable>
        ))}
      </View>
      <Pressable style={[styles.btn, styles.btnGhost, styles.wFull]} onPress={onSwitchRole}>
        <Ionicons name="log-out-outline" size={18} color={colors.navy} />
        <Text style={styles.btnGhostText}>Log out</Text>
      </Pressable>
    </>
  );
}

/* ---------------- Ride + Queue + Chat ---------------- */
function RidePanel({ order, busy, onAdvance, onChat, onCall }: { order: Order; busy: boolean; onAdvance: () => void; onChat: () => void; onCall: () => void }) {
  const steps = ['assigned', 'arrived', 'in_progress'];
  const labels: Record<string, string> = { assigned: 'Assigned', arrived: 'Arrived', in_progress: 'In progress' };
  const idx = steps.indexOf(order.status);
  const cta = order.status === 'assigned' ? "I've arrived" : order.status === 'arrived' ? 'Start ride' : 'Complete ride';
  return (
    <View style={[styles.card, styles.rideCard]}>
      <View style={styles.rowBetween}>
        <Text style={styles.cardTitle}>Active ride #{order.id}</Text>
        <View style={[styles.badge, { backgroundColor: colors.blueSoft }]}><Text style={[styles.badgeText, { color: colors.blue }]}>{labels[order.status] ?? order.status}</Text></View>
      </View>
      <Route from={order.pickup_address} to={order.dropoff_address} />
      <View style={styles.rowBetween}>
        <Text style={styles.dim}>{Number(order.distance_km || 0)} km · {order.eta_min} min · {order.pax} pax</Text>
        <Text style={styles.fare}>{fmt(order.final_fare ?? order.offered_fare)} MAD</Text>
      </View>
      <View style={styles.stepper}>
        {steps.map((s, i) => (
          <View key={s} style={styles.step}>
            <View style={[styles.stepDot, i <= idx && styles.stepDotOn]}>{i <= idx ? <Ionicons name="checkmark" size={11} color={colors.white} /> : null}</View>
            <Text style={[styles.stepText, i <= idx && styles.stepTextOn]}>{labels[s]}</Text>
          </View>
        ))}
      </View>
      <View style={styles.rowGap}>
        <Pressable style={[styles.btn, styles.btnGhost, { flex: 1 }]} onPress={onChat}><Ionicons name="chatbubble-ellipses-outline" size={17} color={colors.navy} /><Text style={styles.btnGhostText}>Chat</Text></Pressable>
        <Pressable style={[styles.btn, styles.callButton]} onPress={onCall}><Ionicons name="call-outline" size={17} color={colors.white} /></Pressable>
        <Pressable style={[styles.btn, styles.btnPrimary, { flex: 1.6 }]} disabled={busy} onPress={onAdvance}><Text style={styles.btnPrimaryText}>{busy ? '…' : cta}</Text></Pressable>
      </View>
    </View>
  );
}

function QueueCard({ order, busy, onAccept, onCounter, onDecline }: {
  order: Order; busy: boolean; onAccept: () => void; onCounter: (o: Order, a: number) => void; onDecline: () => void;
}) {
  const [countering, setCountering] = useState(false);
  const [amount, setAmount] = useState(String(Math.round(Number(order.offered_fare || 0))));
  const cityName = typeof order.city === 'string' ? order.city : order.city?.name;
  const validOffer = Number(amount) >= 1;
  return (
    <View style={[styles.card, styles.requestCard]}>
      <View style={styles.rowBetween}>
        <View>
          <Text style={styles.cardTitle}>New ride #{order.id}</Text>
          <Text style={styles.requestMeta}>{cityName ?? 'Current city'} · {order.source === 'concierge' ? 'Hotel guest' : 'Rider request'}</Text>
        </View>
        <Text style={styles.fare}>{fmt(order.offered_fare)} MAD</Text>
      </View>
      <Route from={order.pickup_address} to={order.dropoff_address} />
      <View style={styles.tripFacts}>
        <View style={styles.tripFact}><Ionicons name="navigate-outline" size={15} color={colors.navy} /><Text style={styles.tripFactText}>{Number(order.distance_km || 0)} km</Text></View>
        <View style={styles.tripFact}><Ionicons name="time-outline" size={15} color={colors.navy} /><Text style={styles.tripFactText}>{order.eta_min} min</Text></View>
        <View style={styles.tripFact}><Ionicons name="people-outline" size={15} color={colors.navy} /><Text style={styles.tripFactText}>{order.pax} pax</Text></View>
        <View style={styles.tripFact}><Ionicons name="cash-outline" size={15} color={colors.navy} /><Text style={styles.tripFactText}>{order.payment_method ?? 'cash'}</Text></View>
      </View>
      {countering ? (
        <View style={styles.offerComposer}>
          <Text style={styles.offerLabel}>Your offer</Text>
          <View style={styles.offerInputRow}><TextInput style={styles.amountInput} keyboardType="numeric" value={amount} onChangeText={setAmount} placeholder="Your price" placeholderTextColor={colors.muted} /><Text style={styles.offerCurrency}>MAD</Text></View>
          <View style={styles.rowGap}>
            <Pressable style={[styles.btn, styles.btnPrimary, { flex: 1 }]} disabled={busy || !validOffer} onPress={() => { setCountering(false); onCounter(order, Number(amount)); }}><Text style={styles.btnPrimaryText}>Send offer</Text></Pressable>
            <Pressable style={[styles.btn, styles.btnGhost]} onPress={() => setCountering(false)}><Text style={styles.btnGhostText}>Cancel</Text></Pressable>
          </View>
        </View>
      ) : (
        <View style={styles.requestActions}>
          <Pressable style={[styles.btn, styles.acceptButton, styles.wFull]} disabled={busy} onPress={onAccept}><Ionicons name="checkmark-circle-outline" size={19} color={colors.white} /><Text style={styles.btnPrimaryText}>Accept {fmt(order.offered_fare)} MAD</Text></Pressable>
          <View style={styles.rowGap}>
            <Pressable style={[styles.btn, styles.btnGhost, { flex: 1 }]} disabled={busy} onPress={() => setCountering(true)}><Text style={styles.btnGhostText}>Make an offer</Text></Pressable>
            <Pressable style={[styles.btn, styles.skipButton, { flex: 1 }]} disabled={busy} onPress={onDecline}><Text style={styles.skipText}>Not now</Text></Pressable>
          </View>
        </View>
      )}
    </View>
  );
}

function DriverNotifications({ notifications, onClose, onOpenCall }: {
  notifications: AppNotification[];
  onClose: () => void;
  onOpenCall: (orderId: number) => void;
}) {
  return (
    <View style={styles.overlay}>
      <Pressable style={StyleSheet.absoluteFill} onPress={onClose} />
      <View style={styles.notificationSheet}>
        <View style={styles.rowBetween}>
          <View><Text style={styles.eyebrow}>ACTIVITY</Text><Text style={styles.notificationTitle}>Notifications</Text></View>
          <Pressable onPress={onClose} style={styles.menuClose}><Ionicons name="close" size={22} color={colors.navy} /></Pressable>
        </View>
        <ScrollView style={styles.notificationScroll} showsVerticalScrollIndicator={false}>
          {notifications.length === 0 ? <Text style={styles.dim}>No notifications yet.</Text> : notifications.map((item) => {
            const orderId = Number(item.data?.order_id);
            const incomingCall = item.type === 'incoming_voice_call' && Number.isFinite(orderId);
            return (
            <Pressable
              key={item.id}
              disabled={!incomingCall}
              onPress={() => incomingCall && onOpenCall(orderId)}
              style={[styles.notificationItem, !item.read_at && styles.notificationItemUnread]}
            >
              <View style={styles.notificationItemIcon}>
                <Ionicons
                  name={(incomingCall ? 'call' : item.type === 'offer_accepted' ? 'checkmark-circle' : item.type === 'chat_message' ? 'chatbubble' : item.type.includes('payout') ? 'wallet' : 'notifications') as never}
                  size={20}
                  color={colors.white}
                />
              </View>
              <View style={{ flex: 1 }}>
                <Text style={styles.notificationItemTitle}>{item.title}</Text>
                <Text style={styles.notificationItemBody}>{item.body}</Text>
                <Text style={styles.notificationItemMeta}>{item.type.replace(/_/g, ' ')}</Text>
                {incomingCall ? <Text style={styles.notificationItemMeta}>Tap to answer</Text> : null}
              </View>
            </Pressable>
          )})}
        </ScrollView>
      </View>
    </View>
  );
}

function ChatOverlay({ orderId, onClose }: { orderId: number; onClose: () => void }) {
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [draft, setDraft] = useState('');
  const [loading, setLoading] = useState(true);
  const [sending, setSending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const load = useCallback(async () => {
    try { setMessages(await getOrderMessages(orderId)); setError(null); }
    catch (e) { setError(e instanceof Error ? e.message : 'Could not load chat'); }
    finally { setLoading(false); }
  }, [orderId]);
  useEffect(() => { load(); const id = setInterval(load, 4000); return () => clearInterval(id); }, [load]);
  useEffect(() => {
    let active = true;
    let realtime: ReturnType<typeof createRealtimeClient> | null = null;
    getAccessToken().then((token) => {
      if (!active || !token) return;
      realtime = createRealtimeClient(token);
      realtime.private(`chat.${orderId}`).listen('.chat.message.sent', (event: {
        message_id: number; order_id: number; sender_id: number; sender_role: string;
        text?: string | null; image_url?: string | null; sent_at: string;
      }) => {
        if (!active) return;
        setMessages((current) => current.some((item) => item.id === event.message_id) ? current : [...current, {
          id: event.message_id, order_id: event.order_id, sender_id: event.sender_id,
          sender_role: event.sender_role, text: event.text, image_url: event.image_url, created_at: event.sent_at,
        }]);
      });
    }).catch(() => undefined);
    return () => { active = false; realtime?.disconnect(); };
  }, [orderId]);
  async function send() {
    const text = draft.trim(); if (!text || sending) return;
    setSending(true); setError(null);
    try {
      const m = await sendOrderMessage(orderId, text);
      setDraft('');
      setMessages((c) => (c.some((x) => x.id === m.id) ? c : [...c, m]));
    } catch (e) { setError(e instanceof Error ? e.message : 'Could not send message'); }
    finally { setSending(false); }
  }
  return (
    <View style={styles.chatPage}>
      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : 'height'} style={styles.chatPageAvoider}>
        <View style={styles.chatPageHeader}>
          <Pressable accessibilityRole="button" accessibilityLabel="Back" onPress={onClose} style={styles.chatBack}>
            <Ionicons name="chevron-back" size={24} color={colors.white} />
          </Pressable>
          <View style={styles.chatHeaderIdentity}>
            <View style={styles.chatHeaderAvatar}><Ionicons name="person" size={20} color={colors.navy} /></View>
            <View>
              <Text style={styles.chatHeaderTitle}>Ride chat</Text>
              <View style={styles.chatHeaderMeta}>
                <View style={styles.chatOnlineDot} />
                <Text style={styles.chatHeaderSubtitle}>Order #{orderId} · Private conversation</Text>
              </View>
            </View>
          </View>
          <View style={styles.chatSecure}><Ionicons name="shield-checkmark" size={20} color={colors.gold} /></View>
        </View>

        <ScrollView
          style={styles.chatPageMessages}
          contentContainerStyle={styles.chatPageMessagesContent}
          showsVerticalScrollIndicator={false}
        >
          {loading ? <ActivityIndicator color={colors.gold} /> : null}
          {!loading && messages.length === 0 ? (
            <View style={styles.chatEmpty}>
              <View style={styles.chatEmptyIcon}><Ionicons name="chatbubbles-outline" size={30} color={colors.rust} /></View>
              <Text style={styles.chatEmptyTitle}>Start the conversation</Text>
              <Text style={styles.chatEmptyText}>Coordinate the pickup with your rider here.</Text>
            </View>
          ) : null}
          {messages.map((m) => (
            <View key={m.id} style={[styles.chatMessage, m.sender_role === 'driver' ? styles.chatMessageMine : styles.chatMessageTheirs]}>
              <Text style={[styles.bubbleMeta, m.sender_role === 'driver' && styles.chatMessageMetaMine]}>{m.sender_role === 'driver' ? 'You' : 'Rider'}</Text>
              <Text style={[styles.bubbleText, m.sender_role === 'driver' && { color: colors.white }]}>{m.text}</Text>
            </View>
          ))}
          {error ? <Text style={styles.chatError}>{error}</Text> : null}
        </ScrollView>

        <View style={styles.chatPageComposer}>
          <View style={styles.chatComposerField}>
            <Ionicons name="chatbubble-ellipses-outline" size={20} color={colors.muted} />
            <TextInput
              style={styles.chatPageInput}
              value={draft}
              onChangeText={setDraft}
              onSubmitEditing={send}
              placeholder="Write a message…"
              placeholderTextColor={colors.muted}
              returnKeyType="send"
            />
          </View>
          <Pressable disabled={sending || !draft.trim()} style={[styles.chatPageSend, (sending || !draft.trim()) && styles.btnDisabled]} onPress={send}>
            {sending ? <ActivityIndicator size="small" color={colors.white} /> : <Ionicons name="arrow-up" size={22} color={colors.white} />}
          </Pressable>
        </View>
      </KeyboardAvoidingView>
    </View>
  );
}

function Route({ from, to }: { from: string; to: string }) {
  return (
    <View style={styles.route}>
      <View style={styles.routeLine}><View style={[styles.routeDot, { backgroundColor: colors.success }]} /><View style={styles.routeBar} /><View style={[styles.routeDot, { backgroundColor: colors.rust }]} /></View>
      <View style={{ flex: 1 }}><Text style={styles.routeText} numberOfLines={1}>{from}</Text><Text style={[styles.routeText, { marginTop: 12 }]} numberOfLines={1}>{to}</Text></View>
    </View>
  );
}

function Stat({ label, value, icon }: { label: string; value: string; icon: string }) {
  return (
    <View style={styles.stat}>
      <Ionicons name={(icon + '-outline') as never} size={16} color={colors.gold} />
      <Text style={styles.statValue}>{value}</Text>
      <Text style={styles.statLabel}>{label}</Text>
    </View>
  );
}

function fmt(n?: number | null): string { return Number(n ?? 0).toLocaleString('en-US', { maximumFractionDigits: 0 }); }

const styles = StyleSheet.create({
  page: { alignItems: Platform.OS === 'web' ? 'center' : 'stretch', flex: 1, backgroundColor: '#ece8e2' },
  phone: {
    backgroundColor: colors.sand,
    flex: 1,
    overflow: 'hidden',
    position: 'relative',
    width: '100%',
    ...(Platform.OS === 'web' ? { borderColor: '#d8d5d0', borderRadius: 30, borderWidth: 1, marginVertical: 14, maxHeight: 920, maxWidth: 430, boxShadow: '0 24px 70px rgba(8,26,45,.18)' as never } : null),
  },
  body: { flex: 1 },
  center: { alignItems: 'center', justifyContent: 'center', gap: 8 },
  dim: { color: colors.muted, fontSize: 13 },
  dimSmall: { color: colors.muted, fontSize: 11, marginTop: 2 },

  header: {
    alignItems: 'center',
    backgroundColor: colors.navy,
    flexDirection: 'row',
    gap: 11,
    minHeight: 92,
    paddingBottom: 14,
    paddingHorizontal: 14,
    paddingTop: 14,
  },
  headerIdentity: { alignItems: 'center', flex: 1, flexDirection: 'row', gap: 9 },
  menuButton: { alignItems: 'center', borderColor: 'rgba(255,255,255,.2)', borderRadius: 12, borderWidth: 1, height: 40, justifyContent: 'center', width: 40 },
  headerAvatar: { alignItems: 'center', backgroundColor: colors.gold, borderColor: 'rgba(255,255,255,.35)', borderRadius: 18, borderWidth: 2, height: 42, justifyContent: 'center', width: 42 },
  headerAvatarText: { color: colors.white, fontSize: 17, fontWeight: '900' },
  eyebrow: { color: colors.gold, fontSize: 11, fontWeight: '800', letterSpacing: 1.5, textTransform: 'uppercase' },
  title: { color: colors.white, fontSize: 19, fontWeight: '900', lineHeight: 23, marginTop: 1 },
  headerMark: { width: 30, height: 30 },
  headerBell: { alignItems: 'center', borderColor: 'rgba(255,255,255,.22)', borderRadius: 13, borderWidth: 1, height: 42, justifyContent: 'center', width: 42 },
  headerBellBadge: { alignItems: 'center', backgroundColor: colors.rust, borderColor: colors.navy, borderRadius: 9, borderWidth: 2, justifyContent: 'center', minHeight: 18, minWidth: 18, paddingHorizontal: 3, position: 'absolute', right: -5, top: -5 },
  headerBellBadgeText: { color: colors.white, fontSize: 8, fontWeight: '900' },

  scrollView: { flex: 1 },
  scroll: { padding: 16, paddingBottom: 32, gap: 14 },

  pill: { flexDirection: 'row', alignItems: 'center', gap: 6, paddingHorizontal: 11, paddingVertical: 6, borderRadius: 999 },
  pillOn: { backgroundColor: colors.greenSoft }, pillOff: { backgroundColor: 'rgba(255,255,255,0.14)' },
  pillText: { fontSize: 12, fontWeight: '800' }, dot: { width: 8, height: 8, borderRadius: 4 },

  notice: { flexDirection: 'row', alignItems: 'center', gap: 8, backgroundColor: '#fff3e6', borderColor: colors.gold, borderWidth: 1, borderRadius: radius.md, padding: 12 },
  noticeText: { color: colors.rustDark, fontSize: 13, fontWeight: '600', flex: 1 },

  walletRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  freeWalletCard: { alignItems: 'center', backgroundColor: colors.greenSoft, borderColor: '#c9ead7', flexDirection: 'row' },
  signupCard: { alignItems: 'center', borderColor: colors.gold, flexDirection: 'row' },
  signupIcon: { alignItems: 'center', backgroundColor: '#fff3e6', borderRadius: 14, height: 46, justifyContent: 'center', width: 46 },
  verificationCard: { alignItems: 'center', backgroundColor: '#fff8ef', borderColor: colors.gold, flexDirection: 'row' },
  stat: { flex: 1, backgroundColor: colors.card, borderRadius: radius.md, borderWidth: 1, borderColor: colors.line, paddingVertical: 12, alignItems: 'center', gap: 2 },
  statValue: { fontSize: 17, fontWeight: '900', color: colors.navy }, statLabel: { fontSize: 11, color: colors.muted },
  currentRideBar: { alignItems: 'center', backgroundColor: colors.white, borderColor: colors.line, borderRadius: radius.lg, borderWidth: 1, flexDirection: 'row', gap: 10, padding: 13 },
  currentRideIcon: { alignItems: 'center', backgroundColor: colors.cream, borderRadius: 15, height: 38, justifyContent: 'center', width: 38 },
  currentRideIconOn: { backgroundColor: colors.navy },
  currentRideTitle: { color: colors.navy, fontSize: 14, fontWeight: '900' },
  currentRideBadge: { backgroundColor: colors.cream, borderRadius: 999, paddingHorizontal: 9, paddingVertical: 5 },
  currentRideBadgeOn: { backgroundColor: colors.greenSoft },
  currentRideBadgeText: { color: colors.muted, fontSize: 9, fontWeight: '900', letterSpacing: .6 },
  currentRideBadgeTextOn: { color: colors.success },

  card: { backgroundColor: colors.white, borderRadius: radius.lg, borderWidth: 1, borderColor: colors.line, padding: 16, gap: 12, ...shadow },
  availabilityCard: { borderWidth: 0, padding: 18 },
  availabilityTop: { alignItems: 'center', flexDirection: 'row', gap: 11 },
  availabilityTitle: { color: colors.navy, fontSize: 18, fontWeight: '900' },
  powerIcon: { alignItems: 'center', backgroundColor: colors.cream, borderRadius: 18, height: 48, justifyContent: 'center', width: 48 },
  powerIconOn: { backgroundColor: colors.success },
  livePill: { alignItems: 'center', backgroundColor: colors.greenSoft, borderRadius: 999, flexDirection: 'row', gap: 5, paddingHorizontal: 9, paddingVertical: 5 },
  liveDot: { backgroundColor: colors.success, borderRadius: 4, height: 7, width: 7 },
  liveText: { color: colors.success, fontSize: 10, fontWeight: '900', letterSpacing: .8 },
  cityScroll: { gap: 8, paddingVertical: 4 },
  onlineButton: { backgroundColor: colors.success, minHeight: 52 },
  offlineButton: { backgroundColor: colors.navy, minHeight: 50 },
  cardTitle: { fontSize: 15, fontWeight: '800', color: colors.navy },
  sectionTitle: { fontSize: 15, fontWeight: '800', color: colors.navy },
  rowBetween: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  rowGap: { flexDirection: 'row', gap: 8, alignItems: 'center' },
  wFull: { width: '100%' },

  cityWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginVertical: 2 },
  chip: { paddingHorizontal: 12, paddingVertical: 7, borderRadius: 999, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.cream },
  chipActive: { backgroundColor: colors.navy, borderColor: colors.navy },
  chipText: { fontSize: 12.5, color: colors.inkSoft, fontWeight: '700' }, chipTextActive: { color: colors.white },

  topUpRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 10, marginBottom: 10 },
  topUpInputRow: { alignItems: 'center', flexDirection: 'row', gap: 8 },
  topUpInput: { backgroundColor: colors.cream, borderColor: colors.line, borderRadius: radius.md, borderWidth: 1, color: colors.ink, flex: 1, fontSize: 16, fontWeight: '800', paddingHorizontal: 12, paddingVertical: 10 },
  topUpCur: { color: colors.muted, fontWeight: '800' },

  btn: { flexDirection: 'row', gap: 7, paddingVertical: 12, paddingHorizontal: 14, borderRadius: radius.md, alignItems: 'center', justifyContent: 'center' },
  btnSm: { paddingVertical: 9, paddingHorizontal: 13 },
  btnPrimary: { backgroundColor: colors.navy }, btnPrimaryText: { color: colors.white, fontWeight: '800', fontSize: 14 },
  btnDisabled: { opacity: .45 },
  btnGhost: { backgroundColor: colors.cream, borderWidth: 1, borderColor: colors.line }, btnGhostText: { color: colors.navy, fontWeight: '800', fontSize: 14 },
  btnDanger: { backgroundColor: '#fbecec', borderWidth: 1, borderColor: '#f0cfcf' }, btnDangerText: { color: colors.danger, fontWeight: '800', fontSize: 14 },
  btnDangerSolid: { backgroundColor: colors.danger }, btnDangerGhost: { flex: 1, backgroundColor: '#fbecec', borderWidth: 1, borderColor: '#f0cfcf' },

  queueHead: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 2 },
  refreshBtn: { flexDirection: 'row', alignItems: 'center', gap: 4 },
  link: { color: colors.blue, fontWeight: '800', fontSize: 13 },
  amountInput: { flex: 1, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, paddingHorizontal: 12, paddingVertical: 10, fontSize: 14, color: colors.ink, backgroundColor: colors.cream },
  fare: { fontSize: 15, fontWeight: '900', color: colors.gold },
  requestCard: { borderLeftColor: colors.rust, borderLeftWidth: 4 },
  requestMeta: { color: colors.muted, fontSize: 11, fontWeight: '600', marginTop: 3 },
  tripFacts: { flexDirection: 'row', flexWrap: 'wrap', gap: 7 },
  tripFact: { alignItems: 'center', backgroundColor: colors.cream, borderRadius: 999, flexDirection: 'row', gap: 4, paddingHorizontal: 9, paddingVertical: 6 },
  tripFactText: { color: colors.navy, fontSize: 11, fontWeight: '700', textTransform: 'capitalize' },
  requestActions: { gap: 8, marginTop: 2 },
  acceptButton: { backgroundColor: colors.success, minHeight: 50 },
  skipButton: { backgroundColor: '#f3f1ed', borderRadius: radius.md },
  skipText: { color: colors.muted, fontSize: 14, fontWeight: '800' },
  offerComposer: { backgroundColor: colors.cream, borderRadius: radius.md, gap: 9, padding: 12 },
  offerLabel: { color: colors.navy, fontSize: 12, fontWeight: '800' },
  offerInputRow: { alignItems: 'center', flexDirection: 'row' },
  offerCurrency: { color: colors.muted, fontSize: 13, fontWeight: '900', marginLeft: 8 },

  rideCard: { borderColor: colors.gold, borderWidth: 1.5 },
  stepper: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 4 },
  step: { alignItems: 'center', flex: 1, gap: 6 },
  stepDot: { width: 20, height: 20, borderRadius: 10, backgroundColor: '#e2ded4', alignItems: 'center', justifyContent: 'center' },
  stepDotOn: { backgroundColor: colors.success },
  stepText: { fontSize: 11, color: colors.muted }, stepTextOn: { color: colors.navy, fontWeight: '700' },

  route: { flexDirection: 'row', gap: 12 }, routeLine: { alignItems: 'center', paddingTop: 4 },
  routeDot: { width: 10, height: 10, borderRadius: 5 }, routeBar: { width: 2, flex: 1, backgroundColor: colors.line, marginVertical: 3 },
  routeText: { fontSize: 13.5, color: colors.ink, fontWeight: '600' },

  earnCard: { borderColor: colors.success, alignItems: 'center', gap: 4 },
  earnTitle: { fontSize: 15, fontWeight: '800', color: colors.success }, earnAmount: { fontSize: 30, fontWeight: '900', color: colors.navy },

  // Ride history + messages
  archiveCard: { gap: 12 },
  archiveEmpty: { paddingVertical: 38 },
  conversationCard: { alignItems: 'center', flexDirection: 'row', gap: 10, padding: 13 },
  messageIcon: { alignItems: 'center', backgroundColor: colors.navy, borderRadius: 18, height: 40, justifyContent: 'center', width: 40 },
  messageRoute: { color: colors.inkSoft, fontSize: 11.5, fontWeight: '700', marginTop: 3 },
  messagePreview: { color: colors.muted, fontSize: 12, marginTop: 4 },
  messageTime: { color: colors.muted, fontSize: 10 },
  messageCount: { alignItems: 'center', backgroundColor: colors.rust, borderRadius: 10, justifyContent: 'center', minHeight: 20, minWidth: 20, paddingHorizontal: 5 },
  messageCountText: { color: colors.white, fontSize: 10, fontWeight: '900' },

  // Verify
  verifyHead: { gap: 8 },
  progressTrack: { height: 8, borderRadius: 4, backgroundColor: '#ece7de', overflow: 'hidden' },
  progressFill: { height: 8, borderRadius: 4, backgroundColor: colors.gold },
  docRow: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.white, borderRadius: radius.md, borderWidth: 1, borderColor: colors.line, padding: 12 },
  docIcon: { width: 40, height: 40, borderRadius: 12, backgroundColor: colors.blueSoft, alignItems: 'center', justifyContent: 'center' },
  docTitle: { fontSize: 14, fontWeight: '700', color: colors.ink, marginBottom: 4 },
  badge: { alignSelf: 'flex-start', paddingHorizontal: 8, paddingVertical: 3, borderRadius: 999 },
  badgeText: { fontSize: 11, fontWeight: '800' },

  // Wallet
  balanceCard: { backgroundColor: colors.navy, borderColor: colors.navy, alignItems: 'flex-start', gap: 4 },
  balanceLabel: { color: 'rgba(255,255,255,0.7)', fontSize: 12, fontWeight: '700' },
  balanceValue: { color: colors.white, fontSize: 32, fontWeight: '900' }, balanceCur: { fontSize: 15, color: colors.gold },
  balanceMeta: { flexDirection: 'row', alignItems: 'center', gap: 18, marginTop: 8 },
  balanceMetaItem: { alignItems: 'flex-start' }, balanceMetaVal: { color: colors.white, fontSize: 16, fontWeight: '800' },
  balanceMetaLbl: { color: 'rgba(255,255,255,0.6)', fontSize: 11 }, balanceDivider: { width: 1, height: 26, backgroundColor: 'rgba(255,255,255,0.2)' },
  ledgerRow: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.white, borderRadius: radius.md, borderWidth: 1, borderColor: colors.line, padding: 12 },
  ledgerIcon: { width: 34, height: 34, borderRadius: 17, alignItems: 'center', justifyContent: 'center' },
  ledgerTitle: { fontSize: 13.5, fontWeight: '700', color: colors.ink, textTransform: 'capitalize' },
  ledgerAmount: { fontSize: 15, fontWeight: '900' },

  // Profile
  avatar: { width: 66, height: 66, borderRadius: 33, backgroundColor: colors.gold, alignItems: 'center', justifyContent: 'center' },
  avatarText: { color: colors.white, fontSize: 26, fontWeight: '900' },
  profileName: { fontSize: 18, fontWeight: '800', color: colors.navy, marginTop: 4 },
  profileGalleryHead: { alignItems: 'center', flexDirection: 'row', justifyContent: 'space-between', marginTop: 4 },
  profileGallery: { flexDirection: 'row', gap: 10 },
  profileUploadCard: { backgroundColor: colors.white, borderColor: colors.line, borderRadius: 17, borderWidth: 1, flex: 1, overflow: 'hidden', ...shadow },
  profileUploadImage: { height: 120, width: '100%' },
  profileUploadEmpty: { alignItems: 'center', backgroundColor: '#f8f3ec', gap: 5, height: 120, justifyContent: 'center' },
  profileUploadEmptyText: { color: colors.muted, fontSize: 11, fontWeight: '800' },
  profileUploadBar: { alignItems: 'center', flexDirection: 'row', justifyContent: 'space-between', minHeight: 54, paddingHorizontal: 10, paddingVertical: 8 },
  profileUploadTitle: { color: colors.navy, fontSize: 12, fontWeight: '900' },
  profileUploadStatus: { color: colors.muted, fontSize: 9, marginTop: 2, textTransform: 'capitalize' },

  // Side navigation
  menuLayer: { ...StyleSheet.absoluteFillObject, zIndex: 80 },
  menuBackdrop: { ...StyleSheet.absoluteFillObject, backgroundColor: 'rgba(3,18,32,.52)' },
  menuPanel: {
    backgroundColor: colors.sand,
    bottom: 0,
    left: 0,
    maxWidth: 340,
    paddingBottom: 24,
    paddingHorizontal: 16,
    paddingTop: 18,
    position: 'absolute',
    top: 0,
    width: '86%',
    ...shadow,
  },
  menuHead: { alignItems: 'center', borderBottomColor: colors.line, borderBottomWidth: 1, flexDirection: 'row', gap: 11, paddingBottom: 16 },
  menuAvatar: { alignItems: 'center', backgroundColor: colors.gold, borderRadius: 16, height: 46, justifyContent: 'center', width: 46 },
  menuAvatarText: { color: colors.white, fontSize: 18, fontWeight: '900' },
  menuName: { color: colors.navy, fontSize: 16, fontWeight: '900' },
  menuEmail: { color: colors.muted, fontSize: 11, marginTop: 2 },
  menuClose: { alignItems: 'center', backgroundColor: colors.white, borderColor: colors.line, borderRadius: 12, borderWidth: 1, height: 38, justifyContent: 'center', width: 38 },
  menuSectionLabel: { color: colors.rust, fontSize: 10, fontWeight: '900', letterSpacing: 1.2, marginBottom: 10, marginTop: 18 },
  menuItems: { gap: 8 },
  menuItem: { alignItems: 'center', backgroundColor: colors.white, borderColor: colors.line, borderRadius: 16, borderWidth: 1, flexDirection: 'row', gap: 11, minHeight: 56, paddingHorizontal: 10 },
  menuItemActive: { backgroundColor: colors.navy, borderColor: colors.navy },
  menuItemIcon: { alignItems: 'center', backgroundColor: colors.blueSoft, borderRadius: 11, height: 36, justifyContent: 'center', width: 36 },
  menuItemIconActive: { backgroundColor: 'rgba(255,255,255,.14)' },
  menuItemText: { color: colors.navy, flex: 1, fontSize: 14, fontWeight: '800' },
  menuItemTextActive: { color: colors.white },
  menuBadge: { alignItems: 'center', backgroundColor: colors.rust, borderRadius: 10, justifyContent: 'center', minHeight: 20, minWidth: 20, paddingHorizontal: 5 },
  menuBadgeText: { color: colors.white, fontSize: 9, fontWeight: '900' },
  menuDot: { backgroundColor: colors.rust, borderRadius: 5, height: 9, width: 9 },
  menuLogout: { alignItems: 'center', backgroundColor: '#fff1ef', borderColor: '#f0c8c1', borderRadius: 14, borderWidth: 1, flexDirection: 'row', gap: 9, marginTop: 14, minHeight: 48, paddingHorizontal: 14 },
  menuLogoutText: { color: colors.danger, fontSize: 14, fontWeight: '900' },
  menuFooter: { alignItems: 'center', flexDirection: 'row', gap: 8, marginTop: 'auto', paddingTop: 18 },
  menuMark: { height: 30, width: 30 },
  menuFooterText: { color: colors.muted, fontSize: 12, fontWeight: '800' },

  // Legacy tab styles retained for compatibility with older builds.
  tabbar: { flexDirection: 'row', backgroundColor: colors.white, borderTopWidth: 1, borderTopColor: colors.line, paddingTop: 10, paddingBottom: 18, paddingHorizontal: 3 },
  tabItem: { flex: 1, alignItems: 'center', gap: 3 },
  tabLabel: { fontSize: 9.5, color: colors.muted, fontWeight: '700' }, tabLabelActive: { color: colors.navy },
  tabBadge: { position: 'absolute', top: -2, right: -6, width: 9, height: 9, borderRadius: 5, backgroundColor: colors.rust, borderWidth: 1.5, borderColor: colors.white },
  tabCount: { alignItems: 'center', backgroundColor: colors.rust, borderColor: colors.white, borderRadius: 9, borderWidth: 1.5, justifyContent: 'center', minHeight: 17, minWidth: 17, paddingHorizontal: 3, position: 'absolute', right: -10, top: -6 },
  tabCountText: { color: colors.white, fontSize: 8, fontWeight: '900' },

  // Chat overlay
  overlay: { ...StyleSheet.absoluteFillObject, backgroundColor: 'rgba(8,20,38,0.5)', justifyContent: 'flex-end', zIndex: 120 },
  callOverlay: { ...StyleSheet.absoluteFillObject, backgroundColor: colors.navy, zIndex: 120 },
  callButton: { backgroundColor: colors.success, borderColor: colors.success, width: 48 },
  chatPage: { ...StyleSheet.absoluteFillObject, backgroundColor: colors.sand, zIndex: 100 },
  chatPageAvoider: { flex: 1 },
  chatPageHeader: { alignItems: 'center', backgroundColor: colors.navy, flexDirection: 'row', gap: 12, minHeight: 86, paddingHorizontal: 14, paddingVertical: 14 },
  chatBack: { alignItems: 'center', borderColor: 'rgba(255,255,255,.2)', borderRadius: 13, borderWidth: 1, height: 42, justifyContent: 'center', width: 42 },
  chatHeaderIdentity: { alignItems: 'center', flex: 1, flexDirection: 'row', gap: 10 },
  chatHeaderAvatar: { alignItems: 'center', backgroundColor: colors.gold, borderRadius: 18, height: 42, justifyContent: 'center', width: 42 },
  chatHeaderTitle: { color: colors.white, fontSize: 17, fontWeight: '900' },
  chatHeaderMeta: { alignItems: 'center', flexDirection: 'row', gap: 5, marginTop: 3 },
  chatOnlineDot: { backgroundColor: colors.success, borderRadius: 4, height: 7, width: 7 },
  chatHeaderSubtitle: { color: '#b9cbe0', fontSize: 10, fontWeight: '700' },
  chatSecure: { alignItems: 'center', justifyContent: 'center', width: 28 },
  chatPageMessages: { flex: 1 },
  chatPageMessagesContent: { flexGrow: 1, gap: 7, justifyContent: 'flex-end', paddingHorizontal: 15, paddingVertical: 18 },
  chatEmpty: { alignItems: 'center', alignSelf: 'center', marginVertical: 'auto', maxWidth: 260 },
  chatEmptyIcon: { alignItems: 'center', backgroundColor: '#f8e3d8', borderRadius: 24, height: 64, justifyContent: 'center', width: 64 },
  chatEmptyTitle: { color: colors.navy, fontSize: 18, fontWeight: '900', marginTop: 12 },
  chatEmptyText: { color: colors.muted, fontSize: 13, lineHeight: 19, marginTop: 5, textAlign: 'center' },
  chatMessage: { borderRadius: 18, maxWidth: '80%', paddingHorizontal: 14, paddingVertical: 11 },
  chatMessageMine: { alignSelf: 'flex-end', backgroundColor: colors.navy, borderBottomRightRadius: 5 },
  chatMessageTheirs: { alignSelf: 'flex-start', backgroundColor: colors.white, borderBottomLeftRadius: 5, borderColor: colors.line, borderWidth: 1 },
  chatMessageMetaMine: { color: 'rgba(255,255,255,.65)' },
  chatPageComposer: { alignItems: 'center', backgroundColor: colors.white, borderTopColor: colors.line, borderTopWidth: 1, flexDirection: 'row', gap: 9, paddingBottom: 14, paddingHorizontal: 14, paddingTop: 12 },
  chatComposerField: { alignItems: 'center', backgroundColor: colors.sand, borderColor: colors.line, borderRadius: 22, borderWidth: 1, flex: 1, flexDirection: 'row', gap: 8, minHeight: 48, paddingHorizontal: 14 },
  chatPageInput: { color: colors.ink, flex: 1, fontSize: 15, paddingVertical: 10 },
  chatPageSend: { alignItems: 'center', backgroundColor: colors.rust, borderRadius: 24, height: 48, justifyContent: 'center', width: 48 },
  bubble: { maxWidth: '82%', padding: 10, borderRadius: 14, marginVertical: 4 },
  bubbleMine: { alignSelf: 'flex-end', backgroundColor: colors.navy }, bubbleTheirs: { alignSelf: 'flex-start', backgroundColor: colors.white, borderWidth: 1, borderColor: colors.line },
  bubbleMeta: { fontSize: 10, color: colors.muted, marginBottom: 2 }, bubbleText: { fontSize: 13.5, color: colors.ink },
  chatInput: { flex: 1, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, paddingHorizontal: 12, paddingVertical: 10, backgroundColor: colors.white, color: colors.ink },
  chatError: { color: colors.danger, fontSize: 12, fontWeight: '700', marginTop: 6, textAlign: 'center' },
  notificationSheet: { alignSelf: 'center', backgroundColor: colors.sand, borderTopLeftRadius: 26, borderTopRightRadius: 26, gap: 14, maxHeight: '78%', paddingBottom: 24, paddingHorizontal: 16, paddingTop: 18, width: '100%', maxWidth: 560 },
  notificationTitle: { color: colors.navy, fontSize: 22, fontWeight: '900', marginTop: 2 },
  notificationScroll: { maxHeight: 520 },
  notificationItem: { alignItems: 'flex-start', backgroundColor: colors.white, borderColor: colors.line, borderRadius: 16, borderWidth: 1, flexDirection: 'row', gap: 11, marginBottom: 9, padding: 12 },
  notificationItemUnread: { backgroundColor: '#fff7ed', borderColor: colors.gold },
  notificationItemIcon: { alignItems: 'center', backgroundColor: colors.navy, borderRadius: 12, height: 40, justifyContent: 'center', width: 40 },
  notificationItemTitle: { color: colors.navy, fontSize: 14, fontWeight: '900' },
  notificationItemBody: { color: colors.muted, fontSize: 12, lineHeight: 17, marginTop: 3 },
  notificationItemMeta: { color: colors.rust, fontSize: 9, fontWeight: '900', letterSpacing: .7, marginTop: 6, textTransform: 'uppercase' },
});
