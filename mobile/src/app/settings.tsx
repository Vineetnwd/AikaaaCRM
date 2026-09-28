import React from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  Image,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useAuth } from '../context/AuthContext';
import { DEFAULT_BASE_URL } from '../services/api';
import { Colors, Spacing, Radius } from '../constants/theme';
import { AppTopBar } from '../components/AppTopBar';

export default function SettingsScreen() {
  const { user, company } = useAuth();

  return (
    <View style={styles.container}>
      <AppTopBar
        title="Settings"
        subtitle="System & Cloud Configuration"
        showBack={true}
      />
      <ScrollView contentContainerStyle={styles.content}>
        {/* Backend Cloud Server */}
        <View style={styles.card}>
          <View style={styles.cardHeader}>
            <Ionicons name="cloud-done" size={20} color="#10b981" />
            <Text style={styles.cardTitle}>Production Cloud Server</Text>
          </View>

          <Text style={styles.cardDesc}>
            Connected to official AIKAAA CRM enterprise cloud:
          </Text>

          <View style={styles.liveServerBadge}>
            <View style={styles.statusDotGreen} />
            <Text style={styles.liveServerUrl}>{DEFAULT_BASE_URL}</Text>
          </View>
        </View>

      {/* Account Info */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Ionicons name="person-circle-outline" size={20} color="#10b981" />
          <Text style={styles.cardTitle}>Session Information</Text>
        </View>

        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>User Name:</Text>
          <Text style={styles.infoValue}>{user?.name}</Text>
        </View>
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Email / Login:</Text>
          <Text style={styles.infoValue}>{user?.email || user?.mobile}</Text>
        </View>
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Assigned Role:</Text>
          <Text style={styles.infoValue}>{(user?.role || 'Executive').toUpperCase()}</Text>
        </View>
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Company ID:</Text>
          <Text style={styles.infoValue}>#{user?.company_id || 'N/A'}</Text>
        </View>
      </View>

      {/* App Details */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Image
            source={require('../../assets/images/logo.png')}
            style={{ width: 22, height: 22, marginRight: 2 }}
            resizeMode="contain"
          />
          <Text style={styles.cardTitle}>App Details</Text>
        </View>

        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Application:</Text>
          <Text style={styles.infoValue}>AIKAAA CRM Mobile</Text>
        </View>
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Framework:</Text>
          <Text style={styles.infoValue}>React Native (Expo SDK 52)</Text>
        </View>
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Architecture:</Text>
          <Text style={styles.infoValue}>Expo Router + Bearer Token Auth</Text>
        </View>
      </View>
    </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f8fafc',
  },
  content: {
    padding: Spacing.lg,
    paddingBottom: 40,
  },
  card: {
    backgroundColor: '#ffffff',
    borderRadius: Radius.lg,
    padding: Spacing.lg,
    marginBottom: Spacing.lg,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  cardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 8,
  },
  cardTitle: {
    fontSize: 16,
    fontWeight: '800',
    color: '#0f172a',
  },
  cardDesc: {
    fontSize: 13,
    color: '#64748b',
    marginBottom: 12,
  },
  liveServerBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: '#f0fdf4',
    borderWidth: 1,
    borderColor: '#bbf7d0',
    borderRadius: Radius.md,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  statusDotGreen: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: '#22c55e',
  },
  liveServerUrl: {
    fontSize: 13,
    fontWeight: '700',
    color: '#15803d',
  },
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 6,
    borderBottomWidth: 1,
    borderBottomColor: '#f8fafc',
  },
  infoLabel: {
    fontSize: 13,
    color: '#64748b',
  },
  infoValue: {
    fontSize: 13,
    fontWeight: '700',
    color: '#0f172a',
  },
});
