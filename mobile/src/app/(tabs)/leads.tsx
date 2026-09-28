import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  TextInput,
  TouchableOpacity,
  RefreshControl,
  ActivityIndicator,
  Linking,
  Alert,
  ScrollView,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { api } from '../../services/api';
import { Colors, Spacing, Radius } from '../../constants/theme';
import { Lead } from '../../types/crm';
import { AppTopBar } from '../../components/AppTopBar';

export default function LeadsScreen() {
  const router = useRouter();
  const [leads, setLeads] = useState<Lead[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [search, setSearch] = useState('');
  const [selectedFilter, setSelectedFilter] = useState<'all' | 'transferred' | 'won' | 'in_progress' | 'red' | 'green'>(
    'all'
  );

  const fetchLeads = useCallback(async () => {
    try {
      const res = await api.getLeads({ all: 1, search: search.trim() });
      if (Array.isArray(res)) {
        setLeads(res);
      }
    } catch (e: any) {
      console.warn('Failed to fetch leads', e);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [search]);

  useEffect(() => {
    fetchLeads();
  }, [fetchLeads]);

  const onRefresh = () => {
    setRefreshing(true);
    fetchLeads();
  };

  const handleCall = (mobile: string) => {
    if (!mobile) return;
    Linking.openURL(`tel:${mobile}`);
  };

  const handleWhatsApp = (mobile: string, name: string) => {
    if (!mobile) return;
    const cleanNumber = mobile.replace(/[^0-9]/g, '');
    const phoneWithCountry = cleanNumber.length === 10 ? `91${cleanNumber}` : cleanNumber;
    const msg = encodeURIComponent(`Hello ${name || ''}, regarding your inquiry on Aikaa CRM.`);
    Linking.openURL(`whatsapp://send?phone=${phoneWithCountry}&text=${msg}`).catch(() => {
      Alert.alert('WhatsApp Unavailable', 'Could not launch WhatsApp. Is it installed on your device?');
    });
  };

  // Filter leads based on selectedFilter
  const filteredLeads = leads.filter(lead => {
    if (selectedFilter === 'transferred') {
      return lead.is_transferred == 1;
    }
    if (selectedFilter === 'won') {
      return lead.status === 'won';
    }
    if (selectedFilter === 'in_progress') {
      return lead.status === 'in_progress' || lead.status === 'new';
    }
    if (selectedFilter === 'red') {
      return lead.category === 'red';
    }
    if (selectedFilter === 'green') {
      return lead.category === 'green';
    }
    return true;
  });

  const transferredCount = leads.filter(l => l.is_transferred == 1).length;

  const renderLeadCard = ({ item }: { item: Lead }) => {
    const categoryColor =
      item.category === 'red'
        ? '#ef4444'
        : item.category === 'green'
        ? '#10b981'
        : item.category === 'yellow'
        ? '#f59e0b'
        : '#94a3b8';

    return (
      <TouchableOpacity
        style={styles.card}
        activeOpacity={0.8}
        onPress={() => router.push(`/lead/${item.id}`)}>
        {/* Card Header */}
        <View style={styles.cardHeader}>
          <View style={{ flex: 1 }}>
            <View style={styles.nameRow}>
              <View style={[styles.categoryDot, { backgroundColor: categoryColor }]} />
              <Text style={styles.clientName} numberOfLines={1}>
                {item.name || 'NO NAME'}
              </Text>
              {item.is_transferred == 1 && (
                <View style={styles.transferredBadge}>
                  <Ionicons name="swap-horizontal" size={10} color="#4338ca" />
                  <Text style={styles.transferredText}>TRANSFERRED</Text>
                </View>
              )}
            </View>
            <Text style={styles.mobileText}>{item.mobile}</Text>
          </View>

          <View
            style={[
              styles.statusPill,
              item.status === 'won' && styles.statusWon,
              item.status === 'lost' && styles.statusLost,
            ]}>
            <Text style={styles.statusPillText}>{(item.status || 'new').toUpperCase()}</Text>
          </View>
        </View>

        {/* Requirements & Deal Value */}
        <View style={styles.reqRow}>
          <View style={{ flex: 1 }}>
            <Text style={styles.reqLabel}>Requirement / Service:</Text>
            <Text style={styles.reqValue} numberOfLines={2}>
              {item.requirement || item.requirement_names || 'None specified'}
            </Text>
          </View>
          {item.deal_value ? (
            <View style={styles.dealBox}>
              <Text style={styles.dealLabel}>Deal Value</Text>
              <Text style={styles.dealValue}>
                ₹{parseFloat(String(item.deal_value)).toLocaleString('en-IN')}
              </Text>
            </View>
          ) : null}
        </View>

        {/* Latest Interaction / Note */}
        {item.latest_remark ? (
          <View style={styles.followupBox}>
            <Ionicons name="chatbubble-ellipses-outline" size={14} color="#6366f1" style={{ marginTop: 2 }} />
            <View style={{ flex: 1 }}>
              <Text style={styles.followupText} numberOfLines={2}>
                {item.latest_remark}
              </Text>
              {item.latest_next_followup_date && (
                <Text style={styles.followupDate}>
                  📅 Next Follow-up: {item.latest_next_followup_date}
                </Text>
              )}
            </View>
          </View>
        ) : null}

        {/* Card Footer Staff & Quick Actions */}
        <View style={styles.cardFooter}>
          <View style={styles.staffInfo}>
            <Text style={styles.staffText} numberOfLines={1}>
              Mgr: {item.assigned_to_name || 'Unassigned'}
            </Text>
            {item.assigned_employee_name && (
              <Text style={styles.staffText} numberOfLines={1}>
                • Staff: {item.assigned_employee_name}
              </Text>
            )}
          </View>

          <View style={styles.actionButtons}>
            <TouchableOpacity
              style={[styles.miniBtn, { backgroundColor: '#d1fae5' }]}
              onPress={() => handleWhatsApp(item.mobile, item.name)}>
              <Ionicons name="logo-whatsapp" size={15} color="#059669" />
            </TouchableOpacity>

            <TouchableOpacity
              style={[styles.miniBtn, { backgroundColor: '#e0e7ff' }]}
              onPress={() => handleCall(item.mobile)}>
              <Ionicons name="call" size={15} color="#4338ca" />
            </TouchableOpacity>

            <TouchableOpacity
              style={[styles.miniBtn, { backgroundColor: '#f1f5f9' }]}
              onPress={() => router.push(`/lead/${item.id}`)}>
              <Ionicons name="chevron-forward" size={15} color="#475569" />
            </TouchableOpacity>
          </View>
        </View>
      </TouchableOpacity>
    );
  };

  return (
    <View style={styles.container}>
      <AppTopBar
        title="Leads & Pipeline"
        subtitle={filteredLeads.length > 0 ? `${filteredLeads.length} Opportunities Active` : 'Lead Management'}
        rightAction={{
          icon: 'add-circle',
          label: 'Add',
          onPress: () => router.push('/lead/add'),
        }}
      />
      {/* Top Search & Filter Bar */}
      <View style={styles.topContainer}>
        <View style={styles.searchBar}>
          <Ionicons name="search" size={18} color="#94a3b8" />
          <TextInput
            style={styles.searchInput}
            placeholder="Search by client or mobile..."
            placeholderTextColor="#94a3b8"
            value={search}
            onChangeText={setSearch}
            returnKeyType="search"
          />
          {search ? (
            <TouchableOpacity onPress={() => setSearch('')}>
              <Ionicons name="close-circle" size={16} color="#94a3b8" />
            </TouchableOpacity>
          ) : null}
        </View>

        {/* Horizontal Filter Tabs */}
        <ScrollView 
          horizontal 
          showsHorizontalScrollIndicator={false} 
          style={styles.filterRowWrapper}
          contentContainerStyle={styles.filterRowContent}
        >
          <TouchableOpacity
            style={[styles.filterChip, selectedFilter === 'all' && styles.filterChipActive]}
            onPress={() => setSelectedFilter('all')}>
            <Text style={[styles.filterChipText, selectedFilter === 'all' && styles.filterChipTextActive]}>
              All ({leads.length})
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.filterChip, selectedFilter === 'transferred' && styles.filterChipActive]}
            onPress={() => setSelectedFilter('transferred')}>
            <Text style={[styles.filterChipText, selectedFilter === 'transferred' && styles.filterChipTextActive]}>
              Transferred ({transferredCount})
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.filterChip, selectedFilter === 'won' && styles.filterChipActive]}
            onPress={() => setSelectedFilter('won')}>
            <Text style={[styles.filterChipText, selectedFilter === 'won' && styles.filterChipTextActive]}>
              Won
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.filterChip, selectedFilter === 'red' && styles.filterChipActive]}
            onPress={() => setSelectedFilter('red')}>
            <Text style={[styles.filterChipText, selectedFilter === 'red' && styles.filterChipTextActive]}>
              🔴 Red
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.filterChip, selectedFilter === 'green' && styles.filterChipActive]}
            onPress={() => setSelectedFilter('green')}>
            <Text style={[styles.filterChipText, selectedFilter === 'green' && styles.filterChipTextActive]}>
              🟢 Green
            </Text>
          </TouchableOpacity>
        </ScrollView>
      </View>

      {/* Leads List */}
      {loading ? (
        <View style={styles.centerContainer}>
          <ActivityIndicator size="large" color={Colors.primary} />
          <Text style={styles.loadingText}>Fetching opportunities...</Text>
        </View>
      ) : (
        <FlatList
          data={filteredLeads}
          keyExtractor={item => String(item.id)}
          renderItem={renderLeadCard}
          contentContainerStyle={styles.listContent}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[Colors.primary]} />
          }
          ListEmptyComponent={
            <View style={styles.emptyContainer}>
              <Ionicons name="people-outline" size={48} color="#cbd5e1" />
              <Text style={styles.emptyTitle}>No Leads Found</Text>
              <Text style={styles.emptySub}>
                {search ? 'Try modifying your search query' : 'Add new leads to grow your pipeline'}
              </Text>
            </View>
          }
        />
      )}

      {/* Floating Add Lead Button */}
      <TouchableOpacity
        style={styles.fab}
        onPress={() => router.push('/lead/add')}>
        <Ionicons name="add" size={26} color="#ffffff" />
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f8fafc',
  },
  topContainer: {
    backgroundColor: '#ffffff',
    paddingHorizontal: Spacing.lg,
    paddingTop: Spacing.sm,
    paddingBottom: Spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: '#e2e8f0',
  },
  searchBar: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f1f5f9',
    borderRadius: Radius.md,
    paddingHorizontal: 12,
    height: 42,
    gap: 8,
  },
  searchInput: {
    flex: 1,
    fontSize: 14,
    color: '#0f172a',
  },
  filterRowWrapper: {
    marginTop: 10,
    marginHorizontal: -Spacing.lg,
  },
  filterRowContent: {
    flexDirection: 'row',
    gap: 6,
    paddingHorizontal: Spacing.lg,
  },
  filterChip: {
    paddingHorizontal: 12,
    paddingVertical: 5,
    borderRadius: Radius.full,
    backgroundColor: '#f1f5f9',
  },
  filterChipActive: {
    backgroundColor: Colors.primary,
  },
  filterChipText: {
    fontSize: 12,
    fontWeight: '700',
    color: '#64748b',
  },
  filterChipTextActive: {
    color: '#ffffff',
  },
  listContent: {
    padding: Spacing.lg,
    paddingBottom: 80,
  },
  card: {
    backgroundColor: '#ffffff',
    borderRadius: Radius.lg,
    padding: Spacing.md,
    marginBottom: Spacing.md,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    shadowColor: '#0f172a',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.04,
    shadowRadius: 5,
    elevation: 2,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 8,
  },
  nameRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    flexWrap: 'wrap',
  },
  categoryDot: {
    width: 9,
    height: 9,
    borderRadius: 5,
  },
  clientName: {
    fontSize: 15,
    fontWeight: '800',
    color: '#0f172a',
  },
  transferredBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 3,
    backgroundColor: '#e0e7ff',
    paddingHorizontal: 6,
    paddingVertical: 1,
    borderRadius: Radius.full,
  },
  transferredText: {
    fontSize: 9,
    fontWeight: '800',
    color: '#4338ca',
  },
  mobileText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#475569',
    marginTop: 2,
  },
  statusPill: {
    backgroundColor: '#e2e8f0',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: Radius.sm,
  },
  statusWon: {
    backgroundColor: '#d1fae5',
  },
  statusLost: {
    backgroundColor: '#fee2e2',
  },
  statusPillText: {
    fontSize: 10,
    fontWeight: '800',
    color: '#475569',
  },
  reqRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    backgroundColor: '#f8fafc',
    padding: 10,
    borderRadius: Radius.md,
    marginTop: 4,
    marginBottom: 8,
    gap: 8,
  },
  reqLabel: {
    fontSize: 10,
    fontWeight: '700',
    color: '#94a3b8',
    textTransform: 'uppercase',
  },
  reqValue: {
    fontSize: 12,
    fontWeight: '600',
    color: '#334155',
    marginTop: 2,
  },
  dealBox: {
    alignItems: 'flex-end',
  },
  dealLabel: {
    fontSize: 10,
    fontWeight: '700',
    color: '#94a3b8',
    textTransform: 'uppercase',
  },
  dealValue: {
    fontSize: 14,
    fontWeight: '800',
    color: '#10b981',
    marginTop: 2,
  },
  followupBox: {
    flexDirection: 'row',
    gap: 6,
    backgroundColor: '#f1f5f9',
    padding: 8,
    borderRadius: Radius.sm,
    marginBottom: 8,
  },
  followupText: {
    fontSize: 12,
    color: '#334155',
    fontStyle: 'italic',
  },
  followupDate: {
    fontSize: 11,
    fontWeight: '700',
    color: '#4f46e5',
    marginTop: 2,
  },
  cardFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    borderTopWidth: 1,
    borderTopColor: '#f1f5f9',
    paddingTop: 8,
    marginTop: 4,
  },
  staffInfo: {
    flex: 1,
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 4,
  },
  staffText: {
    fontSize: 11,
    color: '#64748b',
    fontWeight: '500',
  },
  actionButtons: {
    flexDirection: 'row',
    gap: 6,
  },
  miniBtn: {
    width: 32,
    height: 32,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  centerContainer: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  loadingText: {
    fontSize: 13,
    color: '#64748b',
    marginTop: 8,
  },
  emptyContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingTop: 60,
  },
  emptyTitle: {
    fontSize: 16,
    fontWeight: '800',
    color: '#0f172a',
    marginTop: 12,
  },
  emptySub: {
    fontSize: 13,
    color: '#64748b',
    marginTop: 4,
    textAlign: 'center',
  },
  fab: {
    position: 'absolute',
    right: 20,
    bottom: 20,
    width: 54,
    height: 54,
    borderRadius: 27,
    backgroundColor: Colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: Colors.primary,
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.35,
    shadowRadius: 8,
    elevation: 6,
  },
});
