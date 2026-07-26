import { useMemo, useState } from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';
import { WebView } from 'react-native-webview';

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

function escapeHtml(value: string) {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function routeHtml(props: Required<Props>) {
  const route = props.showCar
    ? [props.pickupCoord, props.carCoord, props.dropoffCoord]
    : [props.pickupCoord, props.dropoffCoord];

  return `<!doctype html>
<html>
<head>
  <meta name="viewport" content="initial-scale=1,maximum-scale=1,user-scalable=no,width=device-width" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <style>
    html, body, #map { height: 100%; width: 100%; margin: 0; padding: 0; background: #edf2f3; }
    .leaflet-control-attribution { font-size: 9px; }
    .marker {
      align-items: center; border: 3px solid #fff; border-radius: 999px; box-shadow: 0 8px 18px rgba(10,39,69,.24);
      color: #fff; display: flex; font-family: Arial, sans-serif; font-size: 15px; font-weight: 800;
      height: 34px; justify-content: center; width: 34px;
    }
    .pickup { background: #0a2745; }
    .dropoff { background: #c85f37; }
    .car { background: #2f8f62; font-size: 13px; }
  </style>
</head>
<body>
  <div id="map"></div>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    var center = ${JSON.stringify(props.center)};
    var route = ${JSON.stringify(route.map((point) => [point.lat, point.lng]))};
    var pickup = ${JSON.stringify([props.pickupCoord.lat, props.pickupCoord.lng])};
    var dropoff = ${JSON.stringify([props.dropoffCoord.lat, props.dropoffCoord.lng])};
    var car = ${JSON.stringify([props.carCoord.lat, props.carCoord.lng])};
    var showCar = ${JSON.stringify(props.showCar)};
    var map = L.map('map', { zoomControl: false, attributionControl: false }).setView([center.lat, center.lng], 13);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
    L.polyline(route, { color: '#ffffff', weight: 9, opacity: .95 }).addTo(map);
    L.polyline(route, { color: '#0a2745', weight: 5, opacity: .98 }).addTo(map);
    if (showCar) L.polyline([pickup, car], { color: '#c85f37', weight: 5, opacity: .98 }).addTo(map);
    function icon(html, className) {
      return L.divIcon({ className: '', html: '<div class="marker ' + className + '">' + html + '</div>', iconSize: [34, 34], iconAnchor: [17, 17] });
    }
    L.marker(pickup, { icon: icon('A', 'pickup') }).addTo(map).bindPopup('${escapeHtml(props.pickup)}');
    L.marker(dropoff, { icon: icon('B', 'dropoff') }).addTo(map).bindPopup('${escapeHtml(props.dropoff)}');
    if (showCar) L.marker(car, { icon: icon('CAR', 'car') }).addTo(map);
    setTimeout(function() {
      map.invalidateSize();
      map.fitBounds(route, { paddingTopLeft: [40, 60], paddingBottomRight: [40, 120], animate: false });
    }, 200);
  </script>
</body>
</html>`;
}

export function NativeGoogleRouteMap({ pickupCoord, dropoffCoord, carCoord, center, pickup, dropoff, showCar = true }: Props) {
  const [ready, setReady] = useState(false);
  const html = useMemo(
    () => routeHtml({ pickupCoord, dropoffCoord, carCoord, center, pickup, dropoff, showCar }),
    [pickupCoord.lat, pickupCoord.lng, dropoffCoord.lat, dropoffCoord.lng, carCoord.lat, carCoord.lng, center.lat, center.lng, pickup, dropoff, showCar],
  );

  return (
    <View style={StyleSheet.absoluteFill}>
      <WebView
        source={{ html, baseUrl: 'https://mororide.com' }}
        style={styles.map}
        javaScriptEnabled
        domStorageEnabled
        mixedContentMode="always"
        scrollEnabled={false}
        onLoadEnd={() => setReady(true)}
        originWhitelist={['*']}
      />
      {!ready ? (
        <View pointerEvents="none" style={styles.loading}>
          <ActivityIndicator color="#c85f37" />
          <Text style={styles.loadingText}>Loading route...</Text>
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  map: {
    backgroundColor: '#edf2f3',
    flex: 1,
  },
  loading: {
    ...StyleSheet.absoluteFillObject,
    alignItems: 'center',
    backgroundColor: '#edf2f3',
    gap: 10,
    justifyContent: 'center',
  },
  loadingText: { color: '#637184', fontSize: 12, fontWeight: '700' },
});
