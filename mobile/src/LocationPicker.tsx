import { Ionicons } from '@expo/vector-icons';
import * as Location from 'expo-location';
import { useEffect, useState } from 'react';
import { ActivityIndicator, Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import MapView, { Marker, PROVIDER_GOOGLE } from 'react-native-maps';

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

export function LocationPicker({ visible, label, center, initial, onCancel, onConfirm }: Props) {
  const [point, setPoint] = useState<{ lat: number; lng: number } | null>(initial ?? null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (visible) setPoint(initial ?? null);
  }, [visible, initial?.lat, initial?.lng]);

  async function confirm() {
    if (!point) return;
    setLoading(true);
    let address = coordinateAddress(point.lat, point.lng);
    try {
      const results = await Location.reverseGeocodeAsync({ latitude: point.lat, longitude: point.lng });
      const place = results[0];
      if (place) {
        address = [place.name, place.street, place.district, place.city, place.region, place.country]
          .filter(Boolean)
          .filter((value, index, all) => all.indexOf(value) === index)
          .join(', ') || address;
      }
    } catch { /* Coordinate fallback is still a valid address. */ }
    setLoading(false);
    onConfirm({ ...point, address: address.slice(0, 255) });
  }

  const start = initial ?? center;
  return (
    <Modal visible={visible} animationType="slide" onRequestClose={onCancel}>
      <View style={styles.page}>
        <View style={styles.header}>
          <Pressable onPress={onCancel} style={styles.iconButton}><Ionicons name="close" size={24} color="#0a2745" /></Pressable>
          <View style={{ flex: 1 }}><Text style={styles.eyebrow}>Choose on map</Text><Text style={styles.title}>{label}</Text></View>
        </View>
        <Text style={styles.hint}>Tap the exact location to place the pin.</Text>
        <MapView
          provider={PROVIDER_GOOGLE}
          style={styles.map}
          initialRegion={{ latitude: start.lat, longitude: start.lng, latitudeDelta: .08, longitudeDelta: .08 }}
          onPress={(event) => setPoint({ lat: event.nativeEvent.coordinate.latitude, lng: event.nativeEvent.coordinate.longitude })}
        >
          {point ? <Marker coordinate={{ latitude: point.lat, longitude: point.lng }} pinColor="#c85f37" /> : null}
        </MapView>
        <Pressable disabled={!point || loading} onPress={confirm} style={[styles.confirm, (!point || loading) && styles.disabled]}>
          {loading ? <ActivityIndicator color="#fff" /> : <Ionicons name="location" size={20} color="#fff" />}
          <Text style={styles.confirmText}>{point ? `Confirm ${label}` : 'Tap the map first'}</Text>
        </Pressable>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  page: { backgroundColor: '#fffdf9', flex: 1, padding: 18, paddingTop: 50 },
  header: { alignItems: 'center', flexDirection: 'row', gap: 12, marginBottom: 8 },
  iconButton: { alignItems: 'center', borderColor: '#eadfd3', borderRadius: 12, borderWidth: 1, height: 44, justifyContent: 'center', width: 44 },
  eyebrow: { color: '#c85f37', fontSize: 13, fontWeight: '800' },
  title: { color: '#0a2745', fontSize: 24, fontWeight: '900' },
  hint: { color: '#637184', fontSize: 14, marginBottom: 12 },
  map: { borderRadius: 16, flex: 1, overflow: 'hidden' },
  confirm: { alignItems: 'center', backgroundColor: '#c85f37', borderRadius: 14, flexDirection: 'row', gap: 8, justifyContent: 'center', marginTop: 14, minHeight: 56 },
  disabled: { opacity: .45 },
  confirmText: { color: '#fff', fontSize: 16, fontWeight: '800' },
});
