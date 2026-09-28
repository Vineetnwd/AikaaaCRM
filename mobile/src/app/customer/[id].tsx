import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  ActivityIndicator,
  Linking,
} from 'react-native';
import { useLocalSearchParams } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { api } from '../../services/api';
import { Colors, Spacing, Radius } from '../../constants/theme';
import { Customer } from '../../types/crm';

export default function CustomerDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const [customer, setCustomer] = useState<Customer | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    (async () => {
      if (!id) return;
      try {
        const res = await api.getCustomerProfile(id);
        if (res) {
          setCustomer(res.customer || res);
        }
      } catch (e) {
        console.warn('Customer fetch error', e);
      } finally {
        setLoading(false);
      }
    })();
  }, [id]);

  if (loading || !customer) {
    return (
      <View style={styles.centerBox}>
        <ActivityIndicator size="large" color={Colors.primary} />
        <Text style={styles.loadingText}>Loading customer profile...</Text>
      </View>
    );
  }

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <View style={styles.card}>
        <Text style={styles.name}>{customer.name}</Text>
        {customer.company_name ? (
          <Text style={styles.company}>🏢 {customer.company_name}</Text>
        ) : null}

        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Mobile Number:</Text>
          <Text style={styles.infoValue}>📞 {customer.mobile}</Text>
        </View>

        {customer.email ? (
          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Email Address:</Text>
            <Text style={styles.infoValue}>✉️ {customer.email}</Text>
          </View>
        ) : null}

        {customer.address ? (
          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>Address / City:</Text>
            <Text style={styles.infoValue}>📍 {customer.address}</Text>
          </View>
        ) : null}

        {customer.gstin ? (
          <View style={styles.infoRow}>
            <Text style={styles.infoLabel}>GST / Tax ID:</Text>
            <Text style={styles.infoValue}>{customer.gstin}</Text>
          </View>
        ) : null}

        <View style={styles.btnRow}>
          <TouchableOpacity
            style={[styles.actionBtn, { backgroundColor: '#d1fae5' }]}
            onPress={() => {
              const clean = customer.mobile.replace(/[^0-9]/g, '');
              const phone = clean.length === 10 ? `91${clean}` : clean;
              Linking.openURL(`whatsapp://send?phone=${phone}`);
            }}>
            <Ionicons name="logo-whatsapp" size={16} color="#065f46" />
            <Text style={[styles.actionBtnText, { color: '#065f46' }]}>WhatsApp</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.actionBtn, { backgroundColor: '#e0e7ff' }]}
            onPress={() => Linking.openURL(`tel:${customer.mobile}`)}>
            <Ionicons name="call" size={16} color="#4338ca" />
            <Text style={[styles.actionBtnText, { color: '#4338ca' }]}>Direct Call</Text>
          </TouchableOpacity>
        </View>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f8fafc',
  },
  content: {
    padding: Spacing.lg,
  },
  centerBox: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  loadingText: {
    fontSize: 13,
    color: '#64748b',
    marginTop: 8,
  },
  card: {
    backgroundColor: '#ffffff',
    borderRadius: Radius.lg,
    padding: Spacing.lg,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  name: {
    fontSize: 20,
    fontWeight: '800',
    color: '#0f172a',
  },
  company: {
    fontSize: 14,
    color: '#475569',
    marginTop: 2,
    marginBottom: 12,
    fontWeight: '600',
  },
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 8,
    borderBottomWidth: 1,
    borderBottomColor: '#f1f5f9',
  },
  infoLabel: {
    fontSize: 13,
    color: '#64748b',
  },
  infoValue: {
    fontSize: 13,
    fontWeight: '600',
    color: '#0f172a',
  },
  btnRow: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 16,
  },
  actionBtn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    paddingVertical: 10,
    borderRadius: Radius.md,
  },
  actionBtnText: {
    fontSize: 12,
    fontWeight: '700',
  },
});
