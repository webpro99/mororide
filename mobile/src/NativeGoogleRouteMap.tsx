import { FontAwesome5 } from '@expo/vector-icons';
import MapView, { Marker, Polyline, PROVIDER_GOOGLE } from 'react-native-maps';
import { StyleSheet, View } from 'react-native';

type DemoCoord = { lat: number; lng: number };

type Props = {
  pickupCoord: DemoCoord;
  dropoffCoord: DemoCoord;
  carCoord: DemoCoord;
  center: DemoCoord;
  pickup: string;
  dropoff: string;
  showCar?: boolean;
};

export function NativeGoogleRouteMap({ pickupCoord, dropoffCoord, carCoord, center, pickup, dropoff, showCar = true }: Props) {
  const route = (showCar ? [pickupCoord, carCoord, dropoffCoord] : [pickupCoord, dropoffCoord]).map((point) => ({
    latitude: point.lat,
    longitude: point.lng,
  }));

  return (
    <MapView
      provider={PROVIDER_GOOGLE}
      style={StyleSheet.absoluteFill}
      initialRegion={{
        latitude: center.lat,
        longitude: center.lng,
        latitudeDelta: 0.035,
        longitudeDelta: 0.035,
      }}
    >
      <Polyline coordinates={route} strokeColor="#0a2745" strokeWidth={6} />
      {showCar ? <Polyline coordinates={route.slice(0, 2)} strokeColor="#c85f37" strokeWidth={6} /> : null}
      <Marker coordinate={{ latitude: pickupCoord.lat, longitude: pickupCoord.lng }} title="Pickup" description={pickup} pinColor="#0a2745" />
      <Marker coordinate={{ latitude: dropoffCoord.lat, longitude: dropoffCoord.lng }} title="Dropoff" description={dropoff} pinColor="#c85f37" />
      {showCar ? <Marker coordinate={{ latitude: carCoord.lat, longitude: carCoord.lng }} title="Driver">
        <View style={styles.carMarker}>
          <FontAwesome5 name="car-side" size={18} color="#ffffff" />
        </View>
      </Marker> : null}
    </MapView>
  );
}

const styles = StyleSheet.create({
  carMarker: {
    alignItems: 'center',
    backgroundColor: '#0a2745',
    borderColor: '#ffffff',
    borderRadius: 999,
    borderWidth: 3,
    height: 42,
    justifyContent: 'center',
    width: 42,
  },
});
