import { Ionicons } from '@expo/vector-icons';
import Constants from 'expo-constants';
import { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';
import { getVoiceCallToken } from './api';

const isExpoGo = Constants.executionEnvironment === 'storeClient';

export function VoiceCallScreen({ orderId, peerLabel, notify = true, onEnd }: {
  orderId: number;
  peerLabel: string;
  notify?: boolean;
  onEnd: () => void;
}) {
  const roomRef = useRef<any>(null);
  const audioSessionRef = useRef<any>(null);
  const [status, setStatus] = useState(isExpoGo ? 'Voice preview · Native audio requires the APK' : 'Connecting…');
  const [muted, setMuted] = useState(false);
  const [speaker, setSpeaker] = useState(true);
  const [elapsed, setElapsed] = useState(0);

  useEffect(() => {
    if (isExpoGo) return undefined;
    let active = true;
    let timer: ReturnType<typeof setInterval> | null = null;
    const { AudioSession, registerGlobals } = require('@livekit/react-native') as typeof import('@livekit/react-native');
    const { Room, RoomEvent } = require('livekit-client') as typeof import('livekit-client');
    registerGlobals();
    audioSessionRef.current = AudioSession;
    const room = new Room({ adaptiveStream: true, dynacast: true });
    roomRef.current = room;

    const connected = () => {
      if (!active) return;
      setStatus('Connected');
      timer = setInterval(() => setElapsed((value) => value + 1), 1000);
    };
    const participantConnected = () => active && setStatus('Call in progress');
    const participantDisconnected = () => active && setStatus('Other participant left');
    room.on(RoomEvent.Connected, connected);
    room.on(RoomEvent.ParticipantConnected, participantConnected);
    room.on(RoomEvent.ParticipantDisconnected, participantDisconnected);

    void (async () => {
      try {
        const session = await getVoiceCallToken(orderId, notify);
        await AudioSession.startAudioSession();
        await room.connect(session.server_url, session.participant_token, { autoSubscribe: true });
        await room.localParticipant.setMicrophoneEnabled(true);
      } catch (error) {
        if (active) setStatus(error instanceof Error ? error.message : 'Could not start voice call');
      }
    })();

    return () => {
      active = false;
      if (timer) clearInterval(timer);
      room.disconnect();
      void AudioSession.stopAudioSession();
    };
  }, [orderId, notify]);

  const toggleMute = async () => {
    const next = !muted;
    await roomRef.current?.localParticipant.setMicrophoneEnabled(!next);
    setMuted(next);
  };
  const toggleSpeaker = async () => {
    const next = !speaker;
    setSpeaker(next);
    await audioSessionRef.current?.selectAudioOutput(next ? 'speaker' : 'earpiece');
  };
  const finish = () => {
    roomRef.current?.disconnect();
    void audioSessionRef.current?.stopAudioSession();
    onEnd();
  };
  const clock = `${String(Math.floor(elapsed / 60)).padStart(2, '0')}:${String(elapsed % 60).padStart(2, '0')}`;

  return (
    <View style={styles.page}>
      <View style={styles.secure}><Ionicons name="lock-closed" size={14} color="#d49a55" /><Text style={styles.secureText}>PRIVATE RIDE CALL</Text></View>
      <View style={styles.avatar}><Ionicons name="person" size={48} color="#082b4c" /></View>
      <Text numberOfLines={2} style={styles.name}>{peerLabel}</Text>
      <Text style={styles.status}>{status}</Text>
      {status.includes('Connect') || status === 'Connecting…' ? <ActivityIndicator color="#d49a55" style={{ marginTop: 16 }} /> : <Text style={styles.timer}>{clock}</Text>}
      <View style={styles.controls}>
        <Pressable onPress={toggleMute} style={[styles.control, muted && styles.controlActive]}><Ionicons name={muted ? 'mic-off' : 'mic'} size={25} color="#fff" /><Text style={styles.controlText}>{muted ? 'Unmute' : 'Mute'}</Text></Pressable>
        <Pressable onPress={toggleSpeaker} style={[styles.control, speaker && styles.controlActive]}><Ionicons name={speaker ? 'volume-high' : 'ear'} size={25} color="#fff" /><Text style={styles.controlText}>{speaker ? 'Speaker' : 'Earpiece'}</Text></Pressable>
      </View>
      <Pressable onPress={finish} style={styles.end}><Ionicons name="call" size={29} color="#fff" /></Pressable>
      <Text style={styles.hint}>Audio only · No phone number shared</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  page: { alignItems: 'center', backgroundColor: '#061f38', flex: 1, justifyContent: 'center', padding: 24 },
  secure: { alignItems: 'center', flexDirection: 'row', gap: 6, marginBottom: 32 },
  secureText: { color: '#d49a55', fontSize: 10, fontWeight: '900', letterSpacing: 1.4 },
  avatar: { alignItems: 'center', backgroundColor: '#e8d8c7', borderColor: 'rgba(255,255,255,.18)', borderRadius: 54, borderWidth: 5, height: 108, justifyContent: 'center', width: 108 },
  name: { color: '#fff', fontSize: 25, fontWeight: '900', marginTop: 20, textAlign: 'center' },
  status: { color: '#b8c8d9', fontSize: 14, marginTop: 8 },
  timer: { color: '#fff', fontSize: 18, fontWeight: '800', marginTop: 14 },
  controls: { flexDirection: 'row', gap: 18, marginTop: 50 },
  control: { alignItems: 'center', backgroundColor: 'rgba(255,255,255,.1)', borderRadius: 22, gap: 7, height: 88, justifyContent: 'center', width: 88 },
  controlActive: { backgroundColor: 'rgba(212,154,85,.35)' },
  controlText: { color: '#fff', fontSize: 11, fontWeight: '800' },
  end: { alignItems: 'center', backgroundColor: '#d94b4b', borderRadius: 33, height: 66, justifyContent: 'center', marginTop: 34, transform: [{ rotate: '135deg' }], width: 66 },
  hint: { color: '#8396aa', fontSize: 11, marginTop: 28 },
});
