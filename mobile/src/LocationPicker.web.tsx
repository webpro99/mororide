import { Ionicons } from '@expo/vector-icons';
import { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Modal, Pressable, StyleSheet, Text, View } from 'react-native';

export type PickedLocation = { address: string; lat: number; lng: number };

type Props = {
  visible: boolean;
  label: string;
  center: { lat: number; lng: number };
  initial?: PickedLocation | null;
  onCancel: () => void;
  onConfirm: (location: PickedLocation) => void;
};

let leafletLoader: Promise<any> | null = null;

function loadLeaflet(): Promise<any> {
  if ((window as any).L) return Promise.resolve((window as any).L);
  if (leafletLoader) return leafletLoader;

  leafletLoader = new Promise((resolve, reject) => {
    if (!document.querySelector('link[data-mororide-leaflet]')) {
      const css = document.createElement('link');
      css.rel = 'stylesheet';
      css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
      css.setAttribute('data-mororide-leaflet', 'true');
      document.head.appendChild(css);
    }

    const existing = document.querySelector('script[data-mororide-leaflet]') as HTMLScriptElement | null;
    if (existing) {
      existing.addEventListener('load', () => resolve((window as any).L), { once: true });
      existing.addEventListener('error', reject, { once: true });
      return;
    }

    const script = document.createElement('script');
    script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
    script.async = true;
    script.setAttribute('data-mororide-leaflet', 'true');
    script.onload = () => resolve((window as any).L);
    script.onerror = reject;
    document.head.appendChild(script);
  });

  return leafletLoader;
}

function coordinateAddress(lat: number, lng: number) {
  return `Pinned location (${lat.toFixed(5)}, ${lng.toFixed(5)})`;
}

async function reverseGeocode(lat: number, lng: number) {
  try {
    const response = await fetch(
      `https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=18&lat=${lat}&lon=${lng}`,
      { headers: { 'Accept-Language': 'en' } },
    );
    if (!response.ok) return coordinateAddress(lat, lng);
    const data = await response.json();
    return typeof data.display_name === 'string' && data.display_name.trim()
      ? data.display_name.trim()
      : coordinateAddress(lat, lng);
  } catch {
    return coordinateAddress(lat, lng);
  }
}

export function LocationPicker({ visible, label, center, initial, onCancel, onConfirm }: Props) {
  const containerRef = useRef<HTMLDivElement | null>(null);
  const mapRef = useRef<any>(null);
  const markerRef = useRef<any>(null);
  const [point, setPoint] = useState<{ lat: number; lng: number } | null>(initial ?? null);
  const [loading, setLoading] = useState(false);
  const [mapError, setMapError] = useState(false);

  useEffect(() => {
    if (!visible) return;
    setPoint(initial ?? null);
    setMapError(false);
    let active = true;

    loadLeaflet().then((L) => {
      if (!active || !containerRef.current) return;
      const start = initial ?? center;
      const map = L.map(containerRef.current, { zoomControl: true }).setView([start.lat, start.lng], initial ? 16 : 13);
      L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
      }).addTo(map);
      mapRef.current = map;

      const placeMarker = (lat: number, lng: number) => {
        if (markerRef.current) markerRef.current.setLatLng([lat, lng]);
        else markerRef.current = L.marker([lat, lng]).addTo(map);
      };

      if (initial) placeMarker(initial.lat, initial.lng);
      map.on('click', (event: any) => {
        const next = { lat: event.latlng.lat, lng: event.latlng.lng };
        setPoint(next);
        placeMarker(next.lat, next.lng);
      });
      setTimeout(() => map.invalidateSize(), 0);
    }).catch(() => {
      if (active) setMapError(true);
    });

    return () => {
      active = false;
      markerRef.current = null;
      mapRef.current?.remove();
      mapRef.current = null;
    };
  }, [visible, center.lat, center.lng, initial?.lat, initial?.lng]);

  async function confirm() {
    if (!point) return;
    setLoading(true);
    const address = await reverseGeocode(point.lat, point.lng);
    setLoading(false);
    onConfirm({ ...point, address: address.slice(0, 255) });
  }

  return (
    <Modal visible={visible} transparent animationType="slide" onRequestClose={onCancel}>
      <View style={styles.backdrop}>
        <View style={styles.sheet}>
          <View style={styles.header}>
            <View>
              <Text style={styles.eyebrow}>Choose on map</Text>
              <Text style={styles.title}>{label}</Text>
            </View>
            <Pressable accessibilityRole="button" accessibilityLabel="Close map" onPress={onCancel} style={styles.iconButton}>
              <Ionicons name="close" size={24} color="#0a2745" />
            </Pressable>
          </View>

          <Text style={styles.hint}>Tap the exact location to place the pin.</Text>
          {mapError ? (
            <View style={[styles.map, styles.center]}><Text style={styles.error}>The map could not load. Check your internet connection.</Text></View>
          ) : (
            // @ts-ignore react-native-web supports raw DOM nodes.
            <div ref={containerRef} style={{ width: '100%', height: 430, borderRadius: 16, overflow: 'hidden' }} />
          )}

          <Pressable
            accessibilityRole="button"
            disabled={!point || loading}
            onPress={confirm}
            style={[styles.confirm, (!point || loading) && styles.disabled]}
          >
            {loading ? <ActivityIndicator color="#fff" /> : <Ionicons name="location" size={20} color="#fff" />}
            <Text style={styles.confirmText}>{point ? `Confirm ${label}` : 'Tap the map first'}</Text>
          </Pressable>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: { backgroundColor: 'rgba(7,29,52,.55)', flex: 1, justifyContent: 'flex-end' },
  sheet: { alignSelf: 'center', backgroundColor: '#fffdf9', borderTopLeftRadius: 24, borderTopRightRadius: 24, maxWidth: 520, padding: 18, width: '100%' },
  header: { alignItems: 'center', flexDirection: 'row', justifyContent: 'space-between', marginBottom: 6 },
  eyebrow: { color: '#c85f37', fontSize: 13, fontWeight: '800' },
  title: { color: '#0a2745', fontSize: 24, fontWeight: '900' },
  iconButton: { alignItems: 'center', borderColor: '#eadfd3', borderRadius: 12, borderWidth: 1, height: 44, justifyContent: 'center', width: 44 },
  hint: { color: '#637184', fontSize: 14, marginBottom: 12 },
  map: { backgroundColor: '#eef2ed', borderRadius: 16, height: 430, overflow: 'hidden' },
  center: { alignItems: 'center', justifyContent: 'center', padding: 30 },
  error: { color: '#ad4f2e', textAlign: 'center' },
  confirm: { alignItems: 'center', backgroundColor: '#c85f37', borderRadius: 14, flexDirection: 'row', gap: 8, justifyContent: 'center', marginTop: 14, minHeight: 54 },
  disabled: { opacity: .45 },
  confirmText: { color: '#fff', fontSize: 16, fontWeight: '800' },
});
