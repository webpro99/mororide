import { Ionicons } from '@expo/vector-icons';
import * as Location from 'expo-location';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ActivityIndicator, Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { WebView, WebViewMessageEvent } from 'react-native-webview';

export type PickedLocation = { address: string; lat: number; lng: number };

type Props = {
  visible: boolean;
  label: string;
  center: { lat: number; lng: number };
  initial?: PickedLocation | null;
  onCancel: () => void;
  onConfirm: (location: PickedLocation) => void;
};

function coordinateAddress(lat: number, lng: number) {
  return `Pinned location (${lat.toFixed(5)}, ${lng.toFixed(5)})`;
}

function mapHtml(start: { lat: number; lng: number }, initial?: PickedLocation | null) {
  const marker = initial ? JSON.stringify({ lat: initial.lat, lng: initial.lng }) : 'null';
  return `<!doctype html>
<html>
<head>
  <meta name="viewport" content="initial-scale=1,maximum-scale=1,user-scalable=no,width=device-width" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <style>
    html, body, #map { height: 100%; width: 100%; margin: 0; padding: 0; background: #f3eee7; }
    .leaflet-control-attribution { font-size: 10px; }
    .pin {
      align-items: center; background: #c85f37; border: 3px solid #fff; border-radius: 999px;
      box-shadow: 0 8px 18px rgba(10,39,69,.22); color: #fff; display: flex; height: 34px;
      justify-content: center; width: 34px;
    }
  </style>
</head>
<body>
  <div id="map"></div>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    var start = ${JSON.stringify(start)};
    var selected = ${marker};
    var map = L.map('map', { zoomControl: true }).setView([start.lat, start.lng], selected ? 16 : 13);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors',
      maxZoom: 19
    }).addTo(map);
    var icon = L.divIcon({ className: '', html: '<div class="pin">●</div>', iconSize: [34, 34], iconAnchor: [17, 17] });
    var marker = null;
    function post(lat, lng) {
      window.ReactNativeWebView && window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'pick', lat: lat, lng: lng }));
    }
    function place(lat, lng, send) {
      selected = { lat: lat, lng: lng };
      if (marker) marker.setLatLng([lat, lng]);
      else marker = L.marker([lat, lng], { icon: icon }).addTo(map);
      map.panTo([lat, lng]);
      if (send) post(lat, lng);
    }
    if (selected) place(selected.lat, selected.lng, false);
    map.on('click', function(event) { place(event.latlng.lat, event.latlng.lng, true); });
    document.addEventListener('message', function(event) {
      try {
        var payload = JSON.parse(event.data);
        if (payload.type === 'locate') {
          place(payload.lat, payload.lng, true);
          map.setZoom(16);
        }
      } catch (error) {}
    });
    window.addEventListener('message', function(event) {
      try {
        var payload = JSON.parse(event.data);
        if (payload.type === 'locate') {
          place(payload.lat, payload.lng, true);
          map.setZoom(16);
        }
      } catch (error) {}
    });
    setTimeout(function() { map.invalidateSize(); }, 200);
  </script>
</body>
</html>`;
}

async function reverseGeocode(lat: number, lng: number) {
  try {
    const results = await Location.reverseGeocodeAsync({ latitude: lat, longitude: lng });
    const place = results[0];
    if (place) {
      const address = [place.name, place.street, place.district, place.city, place.region, place.country]
        .filter(Boolean)
        .filter((value, index, all) => all.indexOf(value) === index)
        .join(', ');
      if (address) return address;
    }
  } catch {
    // The coordinate fallback keeps the rider flow usable offline or without geocoder support.
  }
  return coordinateAddress(lat, lng);
}

export function LocationPicker({ visible, label, center, initial, onCancel, onConfirm }: Props) {
  const webViewRef = useRef<WebView>(null);
  const [point, setPoint] = useState<{ lat: number; lng: number } | null>(initial ?? null);
  const [loading, setLoading] = useState(false);
  const [locating, setLocating] = useState(false);
  const [mapReady, setMapReady] = useState(false);
  const [locationMessage, setLocationMessage] = useState<string | null>(null);

  const start = initial ?? center;
  const html = useMemo(() => mapHtml(start, initial), [start.lat, start.lng, initial?.lat, initial?.lng]);

  useEffect(() => {
    let active = true;
    if (visible) {
      setPoint(initial ?? null);
      setMapReady(false);
      setLocationMessage(null);

      if (!initial) {
        setLocating(true);
        void (async () => {
          try {
            const permission = await Location.requestForegroundPermissionsAsync();
            if (!active) return;
            if (permission.status !== 'granted') {
              setLocationMessage('Location access is off. You can still choose a point on the map.');
              return;
            }

            const cached = await Location.getLastKnownPositionAsync({ maxAge: 120_000, requiredAccuracy: 1_000 });
            if (cached && active) {
              showDeviceLocation(cached.coords.latitude, cached.coords.longitude);
              setLocating(false);
            }

            const current = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced });
            if (active) showDeviceLocation(current.coords.latitude, current.coords.longitude);
          } catch {
            if (active) setLocationMessage('Could not detect your location. Choose a point on the map.');
          } finally {
            if (active) setLocating(false);
          }
        })();
      }
    }
    return () => { active = false; };
  }, [visible, initial?.lat, initial?.lng]);

  useEffect(() => {
    if (!visible || !mapReady || !point) return;
    webViewRef.current?.postMessage(JSON.stringify({ type: 'locate', lat: point.lat, lng: point.lng }));
  }, [visible, mapReady, point?.lat, point?.lng]);

  function showDeviceLocation(lat: number, lng: number) {
    setPoint({ lat, lng });
    webViewRef.current?.postMessage(JSON.stringify({ type: 'locate', lat, lng }));
  }

  async function locateMe() {
    setLocating(true);
    setLocationMessage(null);
    try {
      const permission = await Location.requestForegroundPermissionsAsync();
      if (permission.status !== 'granted') {
        setLocationMessage('Allow location access from your phone settings.');
        return;
      }
      const current = await Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced });
      showDeviceLocation(current.coords.latitude, current.coords.longitude);
    } catch {
      setLocationMessage('Could not detect your location. Try again.');
    } finally {
      setLocating(false);
    }
  }

  async function confirm() {
    if (!point) return;
    setLoading(true);
    const address = await reverseGeocode(point.lat, point.lng);
    setLoading(false);
    onConfirm({ ...point, address: address.slice(0, 255) });
  }

  function handleMapMessage(event: WebViewMessageEvent) {
    try {
      const payload = JSON.parse(event.nativeEvent.data);
      if (payload?.type === 'pick' && Number.isFinite(payload.lat) && Number.isFinite(payload.lng)) {
        setPoint({ lat: payload.lat, lng: payload.lng });
      }
    } catch {
      // Ignore malformed messages from the embedded map.
    }
  }

  return (
    <Modal visible={visible} animationType="slide" onRequestClose={onCancel}>
      <SafeAreaView style={styles.page}>
        <View style={styles.header}>
          <Pressable onPress={onCancel} style={styles.iconButton}><Ionicons name="close" size={24} color="#0a2745" /></Pressable>
          <View style={{ flex: 1 }}><Text style={styles.eyebrow}>Choose on map</Text><Text style={styles.title}>{label}</Text></View>
        </View>
        <Text style={styles.hint}>Tap the exact location to place the pin.</Text>
        <View style={styles.mapWrap}>
          <WebView
            ref={webViewRef}
            source={{ html, baseUrl: 'https://mororide.com' }}
            style={styles.map}
            javaScriptEnabled
            domStorageEnabled
            geolocationEnabled
            mixedContentMode="always"
            onLoadEnd={() => setMapReady(true)}
            onMessage={handleMapMessage}
            originWhitelist={['*']}
          />
          {!mapReady ? (
            <View pointerEvents="none" style={styles.mapLoading}>
              <View style={styles.mapLoadingIcon}><Ionicons name="map-outline" size={30} color="#c85f37" /></View>
              <ActivityIndicator color="#c85f37" />
              <Text style={styles.mapLoadingText}>Preparing the map...</Text>
            </View>
          ) : null}
        </View>
        <Pressable accessibilityRole="button" accessibilityLabel="Use my current location" onPress={locateMe} style={styles.locateButton}>
          {locating ? <ActivityIndicator size="small" color="#0a2745" /> : <Ionicons name="navigate" size={21} color="#0a2745" />}
        </Pressable>
        {locationMessage ? <Text style={styles.locationMessage}>{locationMessage}</Text> : null}
        <Pressable disabled={!point || loading} onPress={confirm} style={[styles.confirm, (!point || loading) && styles.disabled]}>
          {loading ? <ActivityIndicator color="#fff" /> : <Ionicons name="location" size={20} color="#fff" />}
          <Text style={styles.confirmText}>{point ? `Confirm ${label}` : 'Tap the map first'}</Text>
        </Pressable>
      </SafeAreaView>
    </Modal>
  );
}

const styles = StyleSheet.create({
  page: { backgroundColor: '#fffdf9', flex: 1, padding: 18 },
  header: { alignItems: 'center', flexDirection: 'row', gap: 12, marginBottom: 8 },
  iconButton: { alignItems: 'center', borderColor: '#eadfd3', borderRadius: 12, borderWidth: 1, height: 44, justifyContent: 'center', width: 44 },
  eyebrow: { color: '#c85f37', fontSize: 13, fontWeight: '800' },
  title: { color: '#0a2745', fontSize: 24, fontWeight: '900' },
  hint: { color: '#637184', fontSize: 14, marginBottom: 12 },
  mapWrap: { backgroundColor: '#f3eee7', borderRadius: 20, flex: 1, overflow: 'hidden' },
  map: { backgroundColor: 'transparent', flex: 1 },
  mapLoading: { ...StyleSheet.absoluteFillObject, alignItems: 'center', backgroundColor: '#f8f3ec', gap: 10, justifyContent: 'center' },
  mapLoadingIcon: { alignItems: 'center', backgroundColor: '#fff', borderRadius: 18, height: 58, justifyContent: 'center', width: 58 },
  mapLoadingText: { color: '#637184', fontSize: 13, fontWeight: '700' },
  locateButton: { alignItems: 'center', backgroundColor: '#fff', borderColor: '#eadfd3', borderRadius: 16, borderWidth: 1, bottom: 92, height: 48, justifyContent: 'center', position: 'absolute', right: 30, width: 48 },
  locationMessage: { backgroundColor: 'rgba(255,255,255,.94)', borderRadius: 10, bottom: 94, color: '#637184', fontSize: 11, left: 30, maxWidth: 230, paddingHorizontal: 10, paddingVertical: 7, position: 'absolute' },
  confirm: { alignItems: 'center', backgroundColor: '#c85f37', borderRadius: 14, flexDirection: 'row', gap: 8, justifyContent: 'center', marginTop: 14, minHeight: 56 },
  disabled: { opacity: .45 },
  confirmText: { color: '#fff', fontSize: 16, fontWeight: '800' },
});
