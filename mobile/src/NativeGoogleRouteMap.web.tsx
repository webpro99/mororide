import { View } from 'react-native';

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

const KEY = process.env.EXPO_PUBLIC_GOOGLE_MAPS_API_KEY;

/**
 * Web map — renders a real Google map via the Maps Embed API (an <iframe>,
 * which react-native-web renders as a DOM element). Requires "Maps Embed API"
 * enabled for the key in Google Cloud. Falls back to null (parent shows the
 * stylized map) when no key is set.
 */
export function NativeGoogleRouteMap({ pickupCoord, dropoffCoord, center }: Props) {
  if (!KEY) {
    return null;
  }

  const origin = `${pickupCoord.lat},${pickupCoord.lng}`;
  const destination = `${dropoffCoord.lat},${dropoffCoord.lng}`;
  const src =
    `https://www.google.com/maps/embed/v1/directions` +
    `?key=${KEY}&origin=${origin}&destination=${destination}&mode=driving&zoom=13` +
    `&center=${center.lat},${center.lng}`;

  return (
    <View style={{ position: 'absolute', top: 0, left: 0, right: 0, bottom: 0, overflow: 'hidden' }}>
      {/* @ts-ignore raw DOM element rendered by react-native-web */}
      <iframe
        title="MoroRide route"
        src={src}
        style={{ border: 0, width: '100%', height: '100%' }}
        loading="lazy"
        referrerPolicy="no-referrer-when-downgrade"
      />
    </View>
  );
}
