import { FontAwesome5, Ionicons, MaterialCommunityIcons } from '@expo/vector-icons';
import * as ImagePicker from 'expo-image-picker';
import { StatusBar } from 'expo-status-bar';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Image,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import {
  ActionButton,
  EmptyState,
  LoadingBlock,
  Message,
  OrderCard,
  Panel,
  SectionTitle,
} from './components';
import {
  cancelConciergeOrder,
  chooseConciergeOffer,
  createConciergeOrder,
  getAccessToken,
  getCatalog,
  getConciergeOffers,
  getConciergeOrder,
  getConciergeOrders,
  getOrderMessages,
  rateConciergeOrder,
  restoreSession,
  sendOrderImage,
  sendOrderMessage,
} from './api';
import { registerForPush, unregisterPush } from './push';
import { createRealtimeClient } from './realtime';
import { colors, radius } from './theme';
import { Catalog, ChatMessage, Order, OrderOffer } from './types';

const moroLogoMark = require('../assets/moro_logo_mark_transparent.png');

type Screen = 'book' | 'offers' | 'track' | 'chat' | 'history';

export default function ConciergeApp({ onSwitchRole }: { onSwitchRole: () => void }) {
  const [screen, setScreen] = useState<Screen>('book');
  const [catalog, setCatalog] = useState<Catalog | null>(null);
  const [ready, setReady] = useState(false);
  const [loading, setLoading] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);
  const [tone, setTone] = useState<'error' | 'success' | 'info'>('info');

  // Booking form
  const [cityId, setCityId] = useState<number | null>(null);
  const [hotelName, setHotelName] = useState('Riad Dar Zina');
  const [guestName, setGuestName] = useState('');
  const [pickup, setPickup] = useState('Riad Dar Zina, Marrakech');
  const [dropoff, setDropoff] = useState('Marrakech Menara Airport');
  const [pax, setPax] = useState(2);
  const [price, setPrice] = useState('140');
  const [payment, setPayment] = useState<'cash' | 'card'>('cash');

  const [order, setOrder] = useState<Order | null>(null);
  const [offers, setOffers] = useState<OrderOffer[]>([]);
  const [history, setHistory] = useState<Order[]>([]);
  const [messages, setMessages] = useState<ChatMessage[]>([]);

  function flash(message: string, nextTone: 'error' | 'success' | 'info' = 'info') {
    setTone(nextTone);
    setNotice(message);
  }

  // Sign in as the concierge account, load the catalog, and register for push.
  useEffect(() => {
    let mounted = true;
    (async () => {
      try {
        const session = await restoreSession().catch(() => null);
        if (session?.role !== 'concierge') throw new Error('Sign in with a concierge account to continue.');
        const data = await getCatalog();
        if (!mounted) return;
        setCatalog(data);
        setCityId((current) => current ?? data.cities[0]?.id ?? null);
        registerForPush();
      } catch (error) {
        if (mounted) flash(error instanceof Error ? error.message : 'Could not start concierge app', 'error');
      } finally {
        if (mounted) setReady(true);
      }
    })();
    return () => {
      mounted = false;
    };
  }, []);

  // Realtime: offers, status, and chat for the active order.
  useEffect(() => {
    if (!order) return undefined;
    let active = true;
    let realtime: ReturnType<typeof createRealtimeClient> | null = null;
    const orderId = order.id;

    getAccessToken()
      .then((token) => {
        if (!active || !token) return;
        realtime = createRealtimeClient(token);
        realtime
          .private(`order.${orderId}`)
          .listen('.order.offer.submitted', () => {
            getConciergeOffers(orderId).then((next) => active && setOffers(next)).catch(() => undefined);
          })
          .listen('.order.status.changed', () => {
            getConciergeOrder(orderId).then((next) => active && setOrder(next)).catch(() => undefined);
          });
        realtime.private(`chat.${orderId}`).listen('.chat.message.sent', (event: {
          message_id: number;
          order_id: number;
          sender_id: number;
          sender_role: string;
          text?: string | null;
          image_url?: string | null;
          sent_at: string;
        }) => {
          if (!active) return;
          setMessages((current) => current.some((m) => m.id === event.message_id)
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
      })
      .catch(() => undefined);

    return () => {
      active = false;
      realtime?.disconnect();
    };
  }, [order?.id]);

  async function submitBooking() {
    if (!cityId) {
      flash('Choose a city first.', 'error');
      return;
    }
    setLoading(true);
    setNotice(null);
    try {
      const created = await createConciergeOrder({
        city_id: cityId,
        hotel_name: hotelName.trim() || undefined,
        guest_name: guestName.trim() || undefined,
        pickup_address: pickup.trim(),
        dropoff_address: dropoff.trim(),
        distance_km: 7,
        eta_min: 22,
        pax,
        offered_fare: Number(price) || undefined,
        payment_method: payment,
      });
      setOrder(created);
      setOffers([]);
      await refreshOffers(created.id);
      setScreen('offers');
    } catch (error) {
      flash(error instanceof Error ? error.message : 'Could not create the guest ride', 'error');
    } finally {
      setLoading(false);
    }
  }

  async function refreshOffers(orderId = order?.id) {
    if (!orderId) return;
    setLoading(true);
    try {
      const [nextOffers, nextOrder] = await Promise.all([
        getConciergeOffers(orderId),
        getConciergeOrder(orderId),
      ]);
      setOffers(nextOffers);
      setOrder(nextOrder);
    } catch (error) {
      flash(error instanceof Error ? error.message : 'Could not refresh offers', 'error');
    } finally {
      setLoading(false);
    }
  }

  async function choose(offer: OrderOffer) {
    if (!order) return;
    setLoading(true);
    setNotice(null);
    try {
      const next = offer.status === 'accepted' || order.assigned_driver_id === offer.driver.id
        ? await getConciergeOrder(order.id)
        : await chooseConciergeOffer(order.id, offer.id);
      setOrder(next);
      setScreen('track');
    } catch (error) {
      flash(error instanceof Error ? error.message : 'Could not choose this driver', 'error');
    } finally {
      setLoading(false);
    }
  }

  async function cancel() {
    if (!order) return;
    setLoading(true);
    try {
      await cancelConciergeOrder(order.id);
      setOrder(null);
      setOffers([]);
      setScreen('book');
      flash('Guest ride cancelled.', 'success');
    } catch (error) {
      flash(error instanceof Error ? error.message : 'Could not cancel', 'error');
    } finally {
      setLoading(false);
    }
  }

  async function rate(score: number) {
    if (!order) return;
    setLoading(true);
    try {
      await rateConciergeOrder(order.id, score);
      flash('Thanks for rating the ride.', 'success');
      setOrder(null);
      setScreen('history');
      loadHistory();
    } catch (error) {
      flash(error instanceof Error ? error.message : 'Could not submit rating', 'error');
    } finally {
      setLoading(false);
    }
  }

  async function openChat() {
    if (!order) return;
    setLoading(true);
    try {
      setMessages(await getOrderMessages(order.id));
      setScreen('chat');
    } catch (error) {
      flash(error instanceof Error ? error.message : 'Could not load chat', 'error');
    } finally {
      setLoading(false);
    }
  }

  async function sendText(text: string) {
    if (!order || !text.trim()) return;
    try {
      const message = await sendOrderMessage(order.id, text.trim());
      setMessages((current) => current.some((m) => m.id === message.id) ? current : [...current, message]);
    } catch (error) {
      flash(error instanceof Error ? error.message : 'Could not send message', 'error');
    }
  }

  async function sendImage() {
    if (!order) return;
    const result = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ImagePicker.MediaTypeOptions.Images, quality: 0.6 });
    if (result.canceled || !result.assets.length) return;
    const asset = result.assets[0];
    try {
      const message = await sendOrderImage(order.id, {
        uri: asset.uri,
        name: asset.fileName ?? `chat-${Date.now()}.jpg`,
        mimeType: asset.mimeType,
      });
      setMessages((current) => current.some((m) => m.id === message.id) ? current : [...current, message]);
    } catch (error) {
      flash(error instanceof Error ? error.message : 'Could not send image', 'error');
    }
  }

  async function loadHistory() {
    setLoading(true);
    try {
      setHistory(await getConciergeOrders());
    } catch (error) {
      flash(error instanceof Error ? error.message : 'Could not load history', 'error');
    } finally {
      setLoading(false);
    }
  }

  async function switchRole() {
    await unregisterPush();
    onSwitchRole();
  }

  if (!ready) {
    return (
      <SafeAreaView style={styles.page}>
        <View style={styles.center}>
          <ActivityIndicator color={colors.gold} size="large" />
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.page}>
      <StatusBar style="dark" translucent={false} backgroundColor={colors.sand} />
      <View style={styles.header}>
        <Image source={moroLogoMark} style={styles.logo} resizeMode="contain" />
        <View style={{ flex: 1 }}>
          <Text style={styles.brand}>MoroRide Concierge</Text>
          <Text style={styles.brandSub}>Dispatch rides for your guests</Text>
        </View>
        <Pressable onPress={switchRole} accessibilityLabel="Log out" style={styles.switchBtn}>
          <Ionicons name="log-out-outline" size={19} color={colors.white} />
          <Text style={styles.switchBtnText}>Log out</Text>
        </Pressable>
      </View>

      <View style={styles.tabs}>
        {(['book', 'offers', 'track', 'chat', 'history'] as Screen[]).map((tab) => (
          <Pressable
            key={tab}
            onPress={() => {
              if (tab === 'history') loadHistory();
              setScreen(tab);
            }}
            style={[styles.tab, screen === tab && styles.tabActive]}
          >
            <Text style={[styles.tabText, screen === tab && styles.tabTextActive]}>{tab}</Text>
          </Pressable>
        ))}
      </View>

      <ScrollView contentContainerStyle={styles.body} showsVerticalScrollIndicator={false}>
        <Message text={notice} tone={tone} />

        {screen === 'book' ? (
          <Panel>
            <SectionTitle eyebrow="New guest ride" title="Create a booking" />
            <Field label="Hotel / Riad" value={hotelName} onChange={setHotelName} />
            <Field label="Guest name" value={guestName} onChange={setGuestName} placeholder="e.g. John & Sarah" />
            <Text style={styles.label}>City</Text>
            <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.chips}>
              {(catalog?.cities ?? []).map((city) => (
                <Pressable key={city.id} onPress={() => setCityId(city.id)} style={[styles.chip, cityId === city.id && styles.chipActive]}>
                  <Text style={[styles.chipText, cityId === city.id && styles.chipTextActive]}>{city.name}</Text>
                </Pressable>
              ))}
            </ScrollView>
            <Field label="Pickup" value={pickup} onChange={setPickup} />
            <Field label="Dropoff" value={dropoff} onChange={setDropoff} />
            <View style={styles.row}>
              <View style={{ flex: 1 }}>
                <Text style={styles.label}>Passengers</Text>
                <View style={styles.stepper}>
                  <Pressable onPress={() => setPax(Math.max(1, pax - 1))} style={styles.stepBtn}><Ionicons name="remove" size={18} color={colors.navy} /></Pressable>
                  <Text style={styles.stepValue}>{pax}</Text>
                  <Pressable onPress={() => setPax(pax + 1)} style={styles.stepBtn}><Ionicons name="add" size={18} color={colors.navy} /></Pressable>
                </View>
              </View>
              <View style={{ flex: 1 }}>
                <Text style={styles.label}>Budget (MAD)</Text>
                <TextInput value={price} onChangeText={setPrice} keyboardType="numeric" style={styles.input} placeholderTextColor={colors.faded} />
              </View>
            </View>
            <Text style={styles.label}>Payment</Text>
            <View style={styles.row}>
              <Pressable onPress={() => setPayment('cash')} style={[styles.payChoice, payment === 'cash' && styles.payChoiceActive]}>
                <Ionicons name="cash-outline" size={18} color={payment === 'cash' ? colors.white : colors.navy} />
                <Text style={[styles.payText, payment === 'cash' && styles.payTextActive]}>Cash</Text>
              </Pressable>
              <Pressable onPress={() => setPayment('card')} style={[styles.payChoice, payment === 'card' && styles.payChoiceActive]}>
                <Ionicons name="card-outline" size={18} color={payment === 'card' ? colors.white : colors.navy} />
                <Text style={[styles.payText, payment === 'card' && styles.payTextActive]}>Card</Text>
              </Pressable>
            </View>
            <ActionButton label="Dispatch guest ride" onPress={submitBooking} disabled={loading} icon={<MaterialCommunityIcons name="bell-ring-outline" size={18} color={colors.white} />} />
          </Panel>
        ) : null}

        {screen === 'offers' ? (
          <Panel>
            <SectionTitle eyebrow="Driver offers" title="Choose a driver" />
            {order ? <OrderCard order={order} /> : <EmptyState title="No active ride" body="Create a guest ride first." />}
            {loading ? <LoadingBlock /> : null}
            {order && offers.length === 0 && !loading ? (
              <EmptyState title="Waiting for offers" body="Drivers nearby are reviewing this request. Offers arrive live." />
            ) : null}
            {offers.map((offer) => (
              <View key={offer.id} style={styles.offer}>
                <View style={{ flex: 1 }}>
                  <Text style={styles.offerName}>{offer.driver.name}</Text>
                  <Text style={styles.offerMeta}>
                    {offer.type === 'counter' ? 'Counter offer' : 'Accepted your price'}
                    {offer.driver.vehicle?.name ? ` · ${offer.driver.vehicle.name}` : ''}
                  </Text>
                </View>
                <Text style={styles.offerAmount}>{offer.amount ?? order?.offered_fare} MAD</Text>
                <ActionButton label="Choose" onPress={() => choose(offer)} disabled={loading} />
              </View>
            ))}
            {order ? (
              <View style={styles.actionsRow}>
                <ActionButton label="Refresh" variant="ghost" onPress={() => refreshOffers()} disabled={loading} />
                <ActionButton label="Cancel ride" variant="danger" onPress={cancel} disabled={loading} />
              </View>
            ) : null}
          </Panel>
        ) : null}

        {screen === 'track' ? (
          <Panel>
            <SectionTitle eyebrow="Guest ride" title="Track ride" />
            {order ? (
              <>
                <OrderCard order={order} />
                <View style={styles.trackRow}>
                  <MaterialCommunityIcons name="account-tie" size={22} color={colors.rust} />
                  <Text style={styles.trackText}>{order.driver?.name ?? 'Assigned driver'} · {order.status}</Text>
                </View>
                <View style={styles.actionsRow}>
                  <ActionButton label="Chat" variant="secondary" onPress={openChat} icon={<Ionicons name="chatbox-outline" size={18} color={colors.ink} />} />
                  {order.status !== 'completed' ? (
                    <ActionButton label="Cancel" variant="danger" onPress={cancel} disabled={loading} />
                  ) : null}
                </View>
                {order.status === 'completed' ? (
                  <View style={styles.rateBox}>
                    <Text style={styles.label}>Rate this ride</Text>
                    <View style={styles.stars}>
                      {[1, 2, 3, 4, 5].map((n) => (
                        <Pressable key={n} onPress={() => rate(n)}>
                          <Ionicons name="star" size={34} color={colors.gold} />
                        </Pressable>
                      ))}
                    </View>
                  </View>
                ) : null}
              </>
            ) : (
              <EmptyState title="No assigned ride" body="Choose a driver offer to start tracking." />
            )}
          </Panel>
        ) : null}

        {screen === 'chat' ? (
          <ConciergeChat messages={messages} onSendText={sendText} onSendImage={sendImage} />
        ) : null}

        {screen === 'history' ? (
          <Panel>
            <SectionTitle eyebrow="Bookings" title="Ride history" />
            {loading ? <LoadingBlock /> : null}
            {!loading && history.length === 0 ? <EmptyState title="No bookings yet" body="Guest rides you dispatch appear here." /> : null}
            {history.map((item) => (
              <OrderCard key={item.id} order={item} />
            ))}
          </Panel>
        ) : null}
      </ScrollView>
    </SafeAreaView>
  );
}

function Field({ label, value, onChange, placeholder }: { label: string; value: string; onChange: (v: string) => void; placeholder?: string }) {
  return (
    <View style={{ marginBottom: 12 }}>
      <Text style={styles.label}>{label}</Text>
      <TextInput value={value} onChangeText={onChange} placeholder={placeholder} placeholderTextColor={colors.faded} style={styles.input} />
    </View>
  );
}

function ConciergeChat({ messages, onSendText, onSendImage }: {
  messages: ChatMessage[];
  onSendText: (text: string) => void;
  onSendImage: () => void;
}) {
  const [draft, setDraft] = useState('');
  const scroller = useRef<ScrollView>(null);

  return (
    <Panel>
      <SectionTitle eyebrow="Private" title="Ride chat" />
      <ScrollView ref={scroller} style={styles.chatScroll} onContentSizeChange={() => scroller.current?.scrollToEnd({ animated: true })}>
        {messages.length === 0 ? <EmptyState title="No messages" body="Message the assigned driver about your guest." /> : null}
        {messages.map((message) => (
          <View key={message.id} style={[styles.bubble, message.sender_role === 'concierge' && styles.bubbleMine]}>
            <Text style={styles.bubbleRole}>{message.sender_role}</Text>
            {message.image_url ? (
              <Image source={{ uri: message.image_url }} style={styles.chatImage} resizeMode="cover" />
            ) : null}
            {message.text ? <Text style={styles.bubbleText}>{message.text}</Text> : null}
          </View>
        ))}
      </ScrollView>
      <View style={styles.composer}>
        <Pressable onPress={onSendImage} style={styles.imageBtn} accessibilityLabel="Attach image">
          <Ionicons name="image-outline" size={22} color={colors.navy} />
        </Pressable>
        <TextInput
          value={draft}
          onChangeText={setDraft}
          placeholder="Message driver..."
          placeholderTextColor={colors.faded}
          style={styles.composerInput}
          onSubmitEditing={() => { if (draft.trim()) { onSendText(draft); setDraft(''); } }}
        />
        <Pressable
          onPress={() => { if (draft.trim()) { onSendText(draft); setDraft(''); } }}
          style={styles.sendBtn}
        >
          <Ionicons name="send" size={20} color={colors.white} />
        </Pressable>
      </View>
    </Panel>
  );
}

const styles = StyleSheet.create({
  page: { backgroundColor: colors.sand, flex: 1 },
  center: { alignItems: 'center', flex: 1, justifyContent: 'center' },
  header: {
    alignItems: 'center',
    backgroundColor: colors.navy,
    flexDirection: 'row',
    gap: 12,
    minHeight: 92,
    paddingHorizontal: 18,
    paddingVertical: 14,
  },
  logo: { height: 38, width: 38 },
  brand: { color: colors.white, fontSize: 18, fontWeight: '900' },
  brandSub: { color: '#b9cbe0', fontSize: 12, fontWeight: '600' },
  switchBtn: { alignItems: 'center', backgroundColor: 'rgba(255,255,255,0.14)', borderRadius: 999, flexDirection: 'row', gap: 6, paddingHorizontal: 12, paddingVertical: 9 },
  switchBtnText: { color: colors.white, fontSize: 12, fontWeight: '800' },
  tabs: { backgroundColor: colors.navy, flexDirection: 'row', gap: 6, paddingBottom: 12, paddingHorizontal: 12 },
  tab: { borderRadius: 999, flex: 1, paddingVertical: 8 },
  tabActive: { backgroundColor: colors.gold },
  tabText: { color: '#b9cbe0', fontSize: 12, fontWeight: '800', textAlign: 'center', textTransform: 'capitalize' },
  tabTextActive: { color: colors.navy },
  body: { padding: 16, paddingBottom: 64 },
  label: { color: colors.inkSoft, fontSize: 13, fontWeight: '800', marginBottom: 6 },
  input: {
    backgroundColor: colors.white,
    borderColor: colors.line,
    borderRadius: radius.sm,
    borderWidth: 1,
    color: colors.ink,
    fontSize: 15,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  row: { flexDirection: 'row', flexWrap: 'wrap', gap: 12, marginBottom: 12 },
  chips: { gap: 8, paddingVertical: 4, marginBottom: 12 },
  chip: { backgroundColor: colors.white, borderColor: colors.line, borderRadius: 999, borderWidth: 1, paddingHorizontal: 14, paddingVertical: 8 },
  chipActive: { backgroundColor: colors.navy, borderColor: colors.navy },
  chipText: { color: colors.inkSoft, fontWeight: '800' },
  chipTextActive: { color: colors.white },
  stepper: { alignItems: 'center', flexDirection: 'row', gap: 14, justifyContent: 'center', backgroundColor: colors.white, borderColor: colors.line, borderRadius: radius.sm, borderWidth: 1, paddingVertical: 6 },
  stepBtn: { backgroundColor: colors.sand, borderRadius: 999, padding: 6 },
  stepValue: { color: colors.ink, fontSize: 18, fontWeight: '900', minWidth: 24, textAlign: 'center' },
  payChoice: { alignItems: 'center', backgroundColor: colors.white, borderColor: colors.line, borderRadius: radius.sm, borderWidth: 1, flex: 1, flexDirection: 'row', gap: 8, justifyContent: 'center', paddingVertical: 12 },
  payChoiceActive: { backgroundColor: colors.navy, borderColor: colors.navy },
  payText: { color: colors.navy, fontWeight: '800' },
  payTextActive: { color: colors.white },
  offer: { alignItems: 'center', backgroundColor: colors.cream, borderColor: colors.line, borderRadius: radius.md, borderWidth: 1, flexDirection: 'row', gap: 10, marginBottom: 10, padding: 12 },
  offerName: { color: colors.ink, fontSize: 15, fontWeight: '900' },
  offerMeta: { color: colors.muted, fontSize: 12, marginTop: 2 },
  offerAmount: { color: colors.rust, fontSize: 15, fontWeight: '900' },
  actionsRow: { flexDirection: 'row', gap: 10, marginTop: 6 },
  trackRow: { alignItems: 'center', flexDirection: 'row', gap: 10, marginVertical: 10 },
  trackText: { color: colors.ink, fontSize: 15, fontWeight: '800', textTransform: 'capitalize' },
  rateBox: { marginTop: 14 },
  stars: { flexDirection: 'row', gap: 8 },
  chatScroll: { maxHeight: 380 },
  bubble: { alignSelf: 'flex-start', backgroundColor: colors.cream, borderColor: colors.line, borderRadius: radius.md, borderWidth: 1, marginBottom: 8, maxWidth: '82%', padding: 10 },
  bubbleMine: { alignSelf: 'flex-end', backgroundColor: colors.blueSoft },
  bubbleRole: { color: colors.muted, fontSize: 11, fontWeight: '800', marginBottom: 3, textTransform: 'capitalize' },
  bubbleText: { color: colors.ink, fontSize: 14 },
  chatImage: { borderRadius: 10, height: 150, marginBottom: 6, width: 200 },
  composer: { alignItems: 'center', backgroundColor: colors.white, borderColor: colors.line, borderRadius: 20, borderWidth: 1, flexDirection: 'row', gap: 8, marginTop: 12, padding: 8, paddingBottom: 16 },
  imageBtn: { backgroundColor: colors.sand, borderColor: colors.line, borderRadius: 999, borderWidth: 1, padding: 10 },
  composerInput: { backgroundColor: colors.white, borderColor: colors.line, borderRadius: 999, borderWidth: 1, color: colors.ink, flex: 1, paddingHorizontal: 14, paddingVertical: 10 },
  sendBtn: { backgroundColor: colors.navy, borderRadius: 999, padding: 12 },
});
