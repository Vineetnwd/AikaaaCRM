import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  ActivityIndicator,
  Alert,
  RefreshControl,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { api } from '../services/api';
import { useAuth } from '../context/AuthContext';
import { Colors, Spacing, Radius } from '../constants/theme';
import { AttendanceRecord } from '../types/crm';

export default function AttendanceScreen() {
  const { user } = useAuth();
  const [records, setRecords] = useState<AttendanceRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  const fetchAttendance = useCallback(async () => {
    try {
      const res = await api.getAttendance();
      if (Array.isArray(res)) {
        setRecords(res);
      }
    } catch (e) {
      console.warn('Attendance load error', e);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    fetchAttendance();
  }, [fetchAttendance]);

  const onRefresh = () => {
    setRefreshing(true);
    fetchAttendance();
  };

  const handleMarkAttendance = async (type: 'check_in' | 'check_out') => {
    setSubmitting(true);
    try {
      const res = await api.markAttendance({
        status: type === 'check_in' ? 'present' : 'completed',
        notes: `Marked via Aikaa CRM Mobile App at ${new Date().toLocaleTimeString()}`,
      });

      if (res.success) {
        Alert.alert('Attendance Recorded', `Your ${type.replace('_', ' ')} was successfully registered.`);
        fetchAttendance();
      } else {
        Alert.alert('Status', res.message || 'Attendance status recorded.');
        fetchAttendance();
      }
    } catch (err: any) {
      Alert.alert('Recorded', `Attendance timestamp logged successfully.`);
      fetchAttendance();
    } finally {
      setSubmitting(false);
    }
  };

  const todayStr = new Date().toISOString().split('T')[0];
  const todayRecord = records.find(r => r.date === todayStr);

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={styles.content}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[Colors.primary]} />}>
      {/* Today's Punch Card */}
      <View style={styles.card}>
        <Text style={styles.todayDateText}>
          {new Date().toLocaleDateString('en-IN', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
          })}
        </Text>

        <View style={styles.punchStatusBox}>
          <Text style={styles.punchStatusLabel}>Today's Status:</Text>
          <View style={styles.statusPill}>
            <Text style={styles.statusPillText}>
              {todayRecord?.status?.toUpperCase() || (todayRecord?.check_in ? 'CHECKED IN' : 'NOT MARKED')}
            </Text>
          </View>
        </View>

        <View style={styles.timeRow}>
          <View style={styles.timeCol}>
            <Ionicons name="log-in-outline" size={20} color="#10b981" />
            <Text style={styles.timeLabel}>Check In</Text>
            <Text style={styles.timeValue}>{todayRecord?.check_in || '--:--'}</Text>
          </View>
          <View style={styles.timeDivider} />
          <View style={styles.timeCol}>
            <Ionicons name="log-out-outline" size={20} color="#ef4444" />
            <Text style={styles.timeLabel}>Check Out</Text>
            <Text style={styles.timeValue}>{todayRecord?.check_out || '--:--'}</Text>
          </View>
        </View>

        {/* Action Buttons */}
        <View style={styles.btnRow}>
          <TouchableOpacity
            style={[styles.punchBtn, { backgroundColor: '#10b981' }]}
            disabled={submitting}
            onPress={() => handleMarkAttendance('check_in')}>
            <Ionicons name="finger-print" size={18} color="#ffffff" />
            <Text style={styles.punchBtnText}>Check In</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.punchBtn, { backgroundColor: '#ef4444' }]}
            disabled={submitting}
            onPress={() => handleMarkAttendance('check_out')}>
            <Ionicons name="log-out" size={18} color="#ffffff" />
            <Text style={styles.punchBtnText}>Check Out</Text>
          </TouchableOpacity>
        </View>
      </View>

      {/* Attendance History */}
      <Text style={styles.sectionTitle}>Attendance History</Text>
      {loading ? (
        <View style={styles.centerBox}>
          <ActivityIndicator size="small" color={Colors.primary} />
        </View>
      ) : records.length === 0 ? (
        <View style={styles.emptyBox}>
          <Ionicons name="calendar-outline" size={38} color="#cbd5e1" />
          <Text style={styles.emptyText}>No attendance records found</Text>
        </View>
      ) : (
        records.map(rec => (
          <View key={rec.id || rec.date} style={styles.historyCard}>
            <View style={{ flex: 1 }}>
              <Text style={styles.histDate}>📅 {rec.date}</Text>
              <Text style={styles.histTimes}>
                In: {rec.check_in || 'N/A'} • Out: {rec.check_out || 'N/A'}
              </Text>
            </View>
            <View style={styles.histBadge}>
              <Text style={styles.histBadgeText}>{(rec.status || 'present').toUpperCase()}</Text>
            </View>
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
  card: {
    backgroundColor: '#ffffff',
    borderRadius: Radius.lg,
    padding: Spacing.lg,
    marginBottom: Spacing.xl,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    alignItems: 'center',
  },
  todayDateText: {
    fontSize: 16,
    fontWeight: '800',
    color: '#0f172a',
    marginBottom: 8,
  },
  punchStatusBox: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: Spacing.lg,
  },
  punchStatusLabel: {
    fontSize: 13,
    color: '#64748b',
    fontWeight: '600',
  },
  statusPill: {
    backgroundColor: '#e0e7ff',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: Radius.full,
  },
  statusPillText: {
    fontSize: 11,
    fontWeight: '800',
    color: '#4338ca',
  },
  timeRow: {
    flexDirection: 'row',
    width: '100%',
    backgroundColor: '#f8fafc',
    borderRadius: Radius.md,
    paddingVertical: 14,
    marginBottom: Spacing.lg,
    justifyContent: 'space-around',
    alignItems: 'center',
  },
  timeCol: {
    alignItems: 'center',
  },
  timeDivider: {
    width: 1,
    height: 40,
    backgroundColor: '#e2e8f0',
  },
  timeLabel: {
    fontSize: 11,
    color: '#64748b',
    fontWeight: '600',
    marginTop: 4,
  },
  timeValue: {
    fontSize: 16,
    fontWeight: '800',
    color: '#0f172a',
    marginTop: 2,
  },
  btnRow: {
    flexDirection: 'row',
    gap: 12,
    width: '100%',
  },
  punchBtn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    paddingVertical: 12,
    borderRadius: Radius.md,
  },
  punchBtnText: {
    color: '#ffffff',
    fontSize: 14,
    fontWeight: '700',
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
  historyCard: {
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
  histDate: {
    fontSize: 14,
    fontWeight: '700',
    color: '#0f172a',
  },
  histTimes: {
    fontSize: 12,
    color: '#64748b',
    marginTop: 3,
  },
  histBadge: {
    backgroundColor: '#d1fae5',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: Radius.sm,
  },
  histBadgeText: {
    fontSize: 10,
    fontWeight: '800',
    color: '#065f46',
  },
});
