import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  ActivityIndicator,
  RefreshControl,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { api } from '../services/api';
import { useAuth } from '../context/AuthContext';
import { Colors, Spacing, Radius } from '../constants/theme';

export default function CommissionsScreen() {
  const { user } = useAuth();
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [performance, setPerformance] = useState<any>(null);
  const [commissionsList, setCommissionsList] = useState<any[]>([]);

  const loadData = useCallback(async () => {
    try {
      const [perfRes, commRes] = await Promise.allSettled([
        api.getPerformance(),
        api.getCommissions(),
      ]);

      if (perfRes.status === 'fulfilled' && perfRes.value) {
        setPerformance(perfRes.value);
      }
      if (commRes.status === 'fulfilled') {
        const list = Array.isArray(commRes.value)
          ? commRes.value
          : commRes.value?.commissions || [];
        setCommissionsList(list);
      }
    } catch (e) {
      console.warn('Failed to load performance', e);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const onRefresh = () => {
    setRefreshing(true);
    loadData();
  };

  const totalEarned = commissionsList.reduce(
    (acc, cur) => acc + (parseFloat(cur.amount || cur.commission || 0)),
    0
  );

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={styles.content}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[Colors.primary]} />}>
      {/* Overview Banner */}
      <View style={styles.banner}>
        <Text style={styles.bannerTitle}>Performance & Earnings</Text>
        <Text style={styles.bannerSubtitle}>{user?.name} • Incentives Summary</Text>

        <View style={styles.bannerRow}>
          <View style={styles.bannerCol}>
            <Text style={styles.bannerLabel}>Total Commission</Text>
            <Text style={styles.bannerAmount}>₹{totalEarned.toLocaleString('en-IN')}</Text>
          </View>
          <View style={styles.bannerDivider} />
          <View style={styles.bannerCol}>
            <Text style={styles.bannerLabel}>Deals Converted</Text>
            <Text style={styles.bannerAmount}>
              {performance?.won_leads_count || commissionsList.length || 0}
            </Text>
          </View>
        </View>
      </View>

      {/* Target & KPI Card */}
      <View style={styles.card}>
        <Text style={styles.cardTitle}>Sales Achievements</Text>
        <View style={styles.kpiRow}>
          <View style={styles.kpiBox}>
            <Ionicons name="trophy-outline" size={24} color="#f59e0b" />
            <Text style={styles.kpiValue}>
              ₹{parseFloat(String(performance?.total_deal_value || 0)).toLocaleString('en-IN')}
            </Text>
            <Text style={styles.kpiLabel}>Closed Volume</Text>
          </View>

          <View style={styles.kpiBox}>
            <Ionicons name="pie-chart-outline" size={24} color="#6366f1" />
            <Text style={styles.kpiValue}>
              {performance?.conversion_rate ? `${performance.conversion_rate}%` : 'High'}
            </Text>
            <Text style={styles.kpiLabel}>Win Rate</Text>
          </View>
        </View>
      </View>

      {/* Commission Ledger */}
      <Text style={styles.sectionTitle}>Commission Breakdown</Text>
      {loading ? (
        <View style={styles.centerBox}>
          <ActivityIndicator size="small" color={Colors.primary} />
        </View>
      ) : commissionsList.length === 0 ? (
        <View style={styles.emptyBox}>
          <Ionicons name="cash-outline" size={38} color="#cbd5e1" />
          <Text style={styles.emptyText}>No commission records available yet</Text>
        </View>
      ) : (
        commissionsList.map((comm, idx) => (
          <View key={idx} style={styles.commCard}>
            <View style={{ flex: 1 }}>
              <Text style={styles.commClient}>{comm.client_name || comm.lead_name || 'Client Lead'}</Text>
              <Text style={styles.commDate}>📅 {comm.date || comm.created_at?.split(' ')[0]}</Text>
            </View>
            <Text style={styles.commAmount}>
              +₹{parseFloat(String(comm.amount || comm.commission || 0)).toLocaleString('en-IN')}
            </Text>
          </View>
        ))
      )}
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
    paddingBottom: 40,
  },
  banner: {
    backgroundColor: '#0f172a',
    borderRadius: Radius.lg,
    padding: Spacing.lg,
    marginBottom: Spacing.lg,
  },
  bannerTitle: {
    fontSize: 18,
    fontWeight: '800',
    color: '#ffffff',
  },
  bannerSubtitle: {
    fontSize: 12,
    color: '#94a3b8',
    marginTop: 2,
    marginBottom: Spacing.lg,
  },
  bannerRow: {
    flexDirection: 'row',
    justifyContent: 'space-around',
    alignItems: 'center',
    backgroundColor: 'rgba(255, 255, 255, 0.05)',
    borderRadius: Radius.md,
    paddingVertical: 12,
  },
  bannerCol: {
    alignItems: 'center',
  },
  bannerDivider: {
    width: 1,
    height: 36,
    backgroundColor: 'rgba(255, 255, 255, 0.15)',
  },
  bannerLabel: {
    fontSize: 11,
    color: '#94a3b8',
    textTransform: 'uppercase',
    fontWeight: '700',
  },
  bannerAmount: {
    fontSize: 18,
    fontWeight: '800',
    color: '#10b981',
    marginTop: 4,
  },
  card: {
    backgroundColor: '#ffffff',
    borderRadius: Radius.lg,
    padding: Spacing.lg,
    marginBottom: Spacing.xl,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  cardTitle: {
    fontSize: 15,
    fontWeight: '800',
    color: '#0f172a',
    marginBottom: 12,
  },
  kpiRow: {
    flexDirection: 'row',
    gap: 12,
  },
  kpiBox: {
    flex: 1,
    backgroundColor: '#f8fafc',
    borderRadius: Radius.md,
    padding: 14,
    alignItems: 'center',
  },
  kpiValue: {
    fontSize: 16,
    fontWeight: '800',
    color: '#0f172a',
    marginTop: 6,
  },
  kpiLabel: {
    fontSize: 11,
    color: '#64748b',
    fontWeight: '600',
    marginTop: 2,
  },
  sectionTitle: {
    fontSize: 15,
    fontWeight: '800',
    color: '#0f172a',
    marginBottom: 10,
  },
  centerBox: {
    padding: Spacing.xl,
    alignItems: 'center',
  },
  emptyBox: {
    padding: Spacing.xxl,
    alignItems: 'center',
  },
  emptyText: {
    fontSize: 13,
    color: '#94a3b8',
    marginTop: 8,
  },
  commCard: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: '#ffffff',
    padding: Spacing.md,
    borderRadius: Radius.md,
    marginBottom: 8,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  commClient: {
    fontSize: 14,
    fontWeight: '700',
    color: '#0f172a',
  },
  commDate: {
    fontSize: 11,
    color: '#64748b',
    marginTop: 2,
  },
  commAmount: {
    fontSize: 15,
    fontWeight: '800',
    color: '#10b981',
  },
});
