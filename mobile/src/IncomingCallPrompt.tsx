import { Ionicons } from '@expo/vector-icons';
import { useEffect } from 'react';
import { Image, Pressable, StyleSheet, Text, Vibration, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

type Props = {
  title: string;
  body: string;
  peerLabel: string;
  onAnswer: () => void;
  onDecline: () => void;
};

export function IncomingCallPrompt({ title, body, peerLabel, onAnswer, onDecline }: Props) {
  useEffect(() => {
    Vibration.vibrate([0, 700, 450, 700, 450, 700], true);
    return () => Vibration.cancel();
  }, []);

  return (
    <View style={styles.layer}>
      <SafeAreaView style={styles.safe}>
        <View style={styles.card}>
          <View style={styles.logoWrap}>
            <Image source={require('../assets/moro_logo_mark_transparent.png')} style={styles.logo} resizeMode="contain" />
          </View>
          <Text style={styles.eyebrow}>MORORIDE PRIVATE CALL</Text>
          <Text numberOfLines={2} style={styles.title}>{title || 'Incoming call'}</Text>
          <Text numberOfLines={2} style={styles.peer}>{peerLabel}</Text>
          <Text style={styles.body}>{body || 'Answer the ride call to speak now.'}</Text>

          <View style={styles.actions}>
            <Pressable accessibilityRole="button" accessibilityLabel="Decline call" onPress={onDecline} style={[styles.actionButton, styles.decline]}>
              <Ionicons name="call" size={28} color="#fff" style={styles.declineIcon} />
            </Pressable>
            <Pressable accessibilityRole="button" accessibilityLabel="Answer call" onPress={onAnswer} style={[styles.actionButton, styles.answer]}>
              <Ionicons name="call" size={30} color="#fff" />
            </Pressable>
          </View>
          <View style={styles.labels}>
            <Text style={styles.label}>Decline</Text>
            <Text style={styles.label}>Answer</Text>
          </View>
        </View>
      </SafeAreaView>
    </View>
  );
}

const styles = StyleSheet.create({
  layer: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(4,22,40,.92)',
    justifyContent: 'center',
    zIndex: 180,
  },
  safe: {
    flex: 1,
    justifyContent: 'center',
    paddingHorizontal: 22,
    paddingVertical: 28,
  },
  card: {
    alignItems: 'center',
    alignSelf: 'center',
    backgroundColor: '#fffdf9',
    borderColor: 'rgba(255,255,255,.18)',
    borderRadius: 28,
    borderWidth: 1,
    maxWidth: 420,
    paddingHorizontal: 22,
    paddingVertical: 30,
    width: '100%',
  },
  logoWrap: {
    alignItems: 'center',
    backgroundColor: '#082b4c',
    borderRadius: 42,
    height: 84,
    justifyContent: 'center',
    marginBottom: 18,
    width: 84,
  },
  logo: { height: 58, width: 58 },
  eyebrow: { color: '#c85f37', fontSize: 11, fontWeight: '900', letterSpacing: 1.2, textAlign: 'center' },
  title: { color: '#082b4c', fontSize: 24, fontWeight: '900', marginTop: 8, textAlign: 'center' },
  peer: { color: '#233e5a', fontSize: 17, fontWeight: '800', marginTop: 8, textAlign: 'center' },
  body: { color: '#66768a', fontSize: 14, lineHeight: 20, marginTop: 10, textAlign: 'center' },
  actions: { flexDirection: 'row', gap: 48, marginTop: 34 },
  actionButton: {
    alignItems: 'center',
    borderRadius: 40,
    height: 78,
    justifyContent: 'center',
    width: 78,
  },
  answer: { backgroundColor: '#2f8f62' },
  decline: { backgroundColor: '#d94b4b' },
  declineIcon: { transform: [{ rotate: '135deg' }] },
  labels: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 10, width: 204 },
  label: { color: '#66768a', fontSize: 12, fontWeight: '800', textAlign: 'center', width: 78 },
});
