import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  TouchableOpacity,
  RefreshControl,
  ActivityIndicator,
  TextInput,
  Linking,
  Alert,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { useRouter, useFocusEffect } from 'expo-router';
import { api } from '../../services/api';
import { Colors, Spacing, Radius } from '../../constants/theme';
import { Invoice, Quotation } from '../../types/crm';
import { AppTopBar } from '../../components/AppTopBar';

export default function BillingScreen() {
  const router = useRouter();
  const [activeTab, setActiveTab] = useState<'invoices' | 'quotations'>('invoices');
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [search, setSearch] = useState('');
  const [timeFilter, setTimeFilter] = useState<'all' | 'month' | 'year'>('month');

  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [quotations, setQuotations] = useState<Quotation[]>([]);

  const loadBillingData = useCallback(async () => {
    try {
      let params: any = { search: search.trim() };
      if (timeFilter !== 'all') {
        const now = new Date();
        const y = now.getFullYear();
        if (timeFilter === 'month') {
          const m = String(now.getMonth() + 1).padStart(2, '0');
          const lastDay = new Date(y, now.getMonth() + 1, 0).getDate();
          params.start = `${y}-${m}-01`;
          params.end = `${y}-${m}-${lastDay}`;
        } else if (timeFilter === 'year') {
          params.start = `${y}-01-01`;
          params.end = `${y}-12-31`;
        }
      }

      if (activeTab === 'invoices') {
        const res = await api.getInvoices(params);
        const list = Array.isArray(res) ? res : res?.invoices || [];
        setInvoices(list);
      } else {
        const res = await api.getQuotations(params);
        const list = Array.isArray(res) ? res : res?.quotations || [];
        setQuotations(list);
      }
    } catch (e) {
      console.warn('Billing fetch error', e);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [activeTab, search, timeFilter]);

  useFocusEffect(
    useCallback(() => {
      // Don't set loading to true every single time to avoid UI flash,
      // just fetch silently in the background when focused
      loadBillingData();
    }, [loadBillingData])
  );

  const onRefresh = () => {
    setRefreshing(true);
    loadBillingData();
  };

  const shareInvoiceWhatsApp = (inv: Invoice) => {
    const clean = (inv.customer_mobile || '').replace(/[^0-9]/g, '');
    const phone = clean.length === 10 ? `91${clean}` : clean;
    const msg = encodeURIComponent(
      `Hello ${inv.customer_name}, your invoice #${inv.invoice_number} for ₹${inv.total_amount} is available. Outstanding balance: ₹${inv.due_amount || '0.00'}.`
    );
    Linking.openURL(`whatsapp://send?phone=${phone}&text=${msg}`).catch(() => {
      Alert.alert('Error', 'WhatsApp could not be opened.');
    });
  };

  const shareQuotationWhatsApp = (quo: Quotation) => {
    const clean = (quo.customer_mobile || '').replace(/[^0-9]/g, '');
    const phone = clean.length === 10 ? `91${clean}` : clean;
    const msg = encodeURIComponent(
      `Hello ${quo.customer_name}, here is your quotation #${quo.quotation_number} for ₹${quo.total_amount}.`
    );
    Linking.openURL(`whatsapp://send?phone=${phone}&text=${msg}`).catch(() => {
      Alert.alert('Error', 'WhatsApp could not be opened.');
    });
  };

  const handleConvertQuotation = async (quo: Quotation) => {
    Alert.alert(
      'Convert to Invoice',
      `Are you sure you want to convert Quotation #${quo.quotation_number} into an invoice?`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Convert',
          style: 'default',
          onPress: async () => {
            try {
              const res = await api.convertQuotationToInvoice(quo.id);
              if (res.success) {
                Alert.alert('Converted', 'Quotation successfully converted to invoice!');
                setActiveTab('invoices');
              } else {
                Alert.alert('Error', res.error || 'Failed to convert quotation.');
              }
            } catch (err: any) {
              Alert.alert('Error', err.message || 'Network error.');
            }
          },
        },
      ]
    );
  };

  const handleTimeFilterPress = () => {
    Alert.alert(
      'Select Time Period',
      'Filter records by date range',
      [
        { text: 'All Time', onPress: () => setTimeFilter('all') },
        { text: 'This Month', onPress: () => setTimeFilter('month') },
        { text: 'This Year', onPress: () => setTimeFilter('year') },
        { text: 'Cancel', style: 'cancel' }
      ]
    );
  };

  const renderInvoice = ({ item }: { item: Invoice }) => {
    const isPaid = item.payment_status === 'paid';
    return (
      <TouchableOpacity
        style={styles.card}
        activeOpacity={0.8}
        onPress={() => router.push(`/invoice/${item.id}`)}>
        <View style={styles.cardHeader}>
          <View style={{ flex: 1 }}>
            <Text style={styles.numberText}>#{item.invoice_number || 'INV'}</Text>
            <Text style={styles.clientText}>{item.customer_name}</Text>
            <Text style={styles.mobileText}>📞 {item.customer_mobile}</Text>
          </View>
          <View
            style={[
              styles.statusBadge,
              item.payment_status === 'paid' 
                ? { backgroundColor: '#d1fae5' } 
                : item.payment_status === 'partial' 
                  ? { backgroundColor: '#fef3c7' } 
                  : { backgroundColor: '#fee2e2' },
            ]}>
            <Text
              style={[
                styles.statusBadgeText,
                item.payment_status === 'paid' 
                  ? { color: '#065f46' } 
                  : item.payment_status === 'partial' 
                    ? { color: '#d97706' } 
                    : { color: '#b91c1c' },
              ]}>
              {(item.payment_status || 'pending').toUpperCase()}
            </Text>
          </View>
        </View>

        <View style={styles.finRow}>
          <View style={styles.finCol}>
            <Text style={styles.finLabel}>Total Amount</Text>
            <Text style={styles.finAmount}>₹{parseFloat(String(item.total_amount)).toLocaleString('en-IN')}</Text>
          </View>
          <View style={styles.finCol}>
            <Text style={styles.finLabel}>Balance Due</Text>
            <Text style={[styles.finAmount, { color: parseFloat(String(item.due_amount)) > 0 ? '#ef4444' : '#10b981' }]}>
              ₹{parseFloat(String(item.due_amount || 0)).toLocaleString('en-IN')}
            </Text>
          </View>
        </View>

        <View style={styles.cardFooter}>
          <Text style={styles.dateText}>📅 {item.invoice_date || item.created_at?.split(' ')[0]}</Text>
          <View style={styles.actionBtnsRow}>
            <TouchableOpacity
              style={styles.iconBtn}
              onPress={() => shareInvoiceWhatsApp(item)}>
              <Ionicons name="logo-whatsapp" size={16} color="#059669" />
            </TouchableOpacity>
            <TouchableOpacity
              style={[styles.iconBtn, { backgroundColor: '#e0e7ff' }]}
              onPress={() => router.push(`/invoice/${item.id}`)}>
              <Ionicons name="eye-outline" size={16} color="#4338ca" />
            </TouchableOpacity>
          </View>
        </View>
      </TouchableOpacity>
    );
  };

  const renderQuotation = ({ item }: { item: Quotation }) => {
    const isConverted = item.status === 'invoiced';
    return (
      <TouchableOpacity
        style={styles.card}
        activeOpacity={0.8}
        onPress={() => router.push(`/quotation/${item.id}`)}>
        <View style={styles.cardHeader}>
          <View style={{ flex: 1 }}>
            <Text style={styles.numberText}>#{item.quotation_number || 'QUO'}</Text>
            <Text style={styles.clientText}>{item.customer_name}</Text>
            <Text style={styles.mobileText}>📞 {item.customer_mobile}</Text>
          </View>
          <View
            style={[
              styles.statusBadge,
              isConverted ? { backgroundColor: '#d1fae5' } : { backgroundColor: '#fef3c7' },
            ]}>
            <Text
              style={[
                styles.statusBadgeText,
                isConverted ? { color: '#065f46' } : { color: '#92400e' },
              ]}>
              {(item.status || 'pending').toUpperCase()}
            </Text>
          </View>
        </View>

        <View style={styles.finRow}>
          <View style={styles.finCol}>
            <Text style={styles.finLabel}>Quotation Value</Text>
            <Text style={styles.finAmount}>₹{parseFloat(String(item.total_amount)).toLocaleString('en-IN')}</Text>
          </View>
          <View style={styles.finCol}>
            <Text style={styles.finLabel}>Valid Until</Text>
            <Text style={styles.finAmountText}>{item.valid_until || 'Not specified'}</Text>
          </View>
        </View>

        <View style={styles.cardFooter}>
          <Text style={styles.dateText}>📅 {item.created_at?.split(' ')[0]}</Text>
          <View style={styles.actionBtnsRow}>
            {!isConverted && (
              <TouchableOpacity
                style={styles.convertBtn}
                onPress={() => handleConvertQuotation(item)}>
                <Ionicons name="swap-horizontal" size={13} color="#ffffff" />
                <Text style={styles.convertBtnText}>To Invoice</Text>
              </TouchableOpacity>
            )}
            <TouchableOpacity
              style={styles.iconBtn}
              onPress={() => shareQuotationWhatsApp(item)}>
              <Ionicons name="logo-whatsapp" size={16} color="#059669" />
            </TouchableOpacity>
            <TouchableOpacity
              style={[styles.iconBtn, { backgroundColor: '#e0e7ff' }]}
              onPress={() => router.push(`/quotation/${item.id}`)}>
              <Ionicons name="eye-outline" size={16} color="#4338ca" />
            </TouchableOpacity>
          </View>
        </View>
      </TouchableOpacity>
    );
  };

  return (
    <View style={styles.container}>
      <AppTopBar
        title="Billing & Accounts"
        subtitle={
          activeTab === 'invoices'
            ? `${invoices.length} Invoices Active`
            : `${quotations.length} Quotations Active`
        }
        rightAction={{
          icon: 'add-circle',
          label: 'Create',
          onPress: () =>
            router.push(activeTab === 'invoices' ? '/invoice/add' : '/quotation/add'),
        }}
      />
      {/* Top Segmented Controls */}
      <View style={styles.topBar}>
        <View style={styles.segmentContainer}>
          <TouchableOpacity
            style={[styles.segmentBtn, activeTab === 'invoices' && styles.segmentBtnActive]}
            onPress={() => setActiveTab('invoices')}>
            <Ionicons
              name="receipt-outline"
              size={15}
              color={activeTab === 'invoices' ? '#ffffff' : '#64748b'}
            />
            <Text
              style={[styles.segmentText, activeTab === 'invoices' && styles.segmentTextActive]}>
              Invoices
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.segmentBtn, activeTab === 'quotations' && styles.segmentBtnActive]}
            onPress={() => setActiveTab('quotations')}>
            <Ionicons
              name="document-text-outline"
              size={15}
              color={activeTab === 'quotations' ? '#ffffff' : '#64748b'}
            />
            <Text
              style={[styles.segmentText, activeTab === 'quotations' && styles.segmentTextActive]}>
              Quotations
            </Text>
          </TouchableOpacity>
        </View>

        {/* Filters Row */}
        <View style={{ flexDirection: 'row', alignItems: 'center', paddingHorizontal: Spacing.m, marginBottom: Spacing.m }}>
          <View style={[styles.searchBar, { flex: 1, marginHorizontal: 0, marginBottom: 0, marginRight: Spacing.s }]}>
            <Ionicons name="search" size={16} color="#94a3b8" />
            <TextInput
              style={styles.searchInput}
              placeholder={`Search ${activeTab}...`}
              placeholderTextColor="#94a3b8"
              value={search}
              onChangeText={setSearch}
            />
            {search ? (
              <TouchableOpacity onPress={() => setSearch('')}>
                <Ionicons name="close-circle" size={16} color="#94a3b8" />
              </TouchableOpacity>
            ) : null}
          </View>
          
          <TouchableOpacity 
            style={{ 
              backgroundColor: timeFilter !== 'all' ? '#e0e7ff' : '#f1f5f9', 
              paddingHorizontal: 12, 
              paddingVertical: 10, 
              borderRadius: Radius.m,
              flexDirection: 'row',
              alignItems: 'center'
            }}
            onPress={handleTimeFilterPress}
          >
            <Ionicons name="calendar-outline" size={16} color={timeFilter !== 'all' ? '#4338ca' : '#64748b'} style={{ marginRight: 4 }} />
            <Text style={{ color: timeFilter !== 'all' ? '#4338ca' : '#64748b', fontSize: 13, fontWeight: '600' }}>
              {timeFilter === 'all' ? 'All Time' : timeFilter === 'month' ? 'Month' : 'Year'}
            </Text>
          </TouchableOpacity>
        </View>
      </View>

      {/* Main List */}
      {loading ? (
        <View style={styles.centerBox}>
          <ActivityIndicator size="large" color={Colors.primary} />
          <Text style={styles.loadingText}>Fetching billing records...</Text>
        </View>
      ) : activeTab === 'invoices' ? (
        <FlatList
          data={invoices}
          keyExtractor={item => String(item.id)}
          renderItem={renderInvoice}
          contentContainerStyle={styles.listContent}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[Colors.primary]} />
          }
          ListEmptyComponent={
            <View style={styles.centerBox}>
              <Ionicons name="receipt-outline" size={48} color="#cbd5e1" />
              <Text style={styles.emptyTitle}>No Invoices Found</Text>
              <Text style={styles.emptySub}>Generate invoices for your clients</Text>
            </View>
          }
        />
      ) : (
        <FlatList
          data={quotations}
          keyExtractor={item => String(item.id)}
          renderItem={renderQuotation}
          contentContainerStyle={styles.listContent}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[Colors.primary]} />
          }
          ListEmptyComponent={
            <View style={styles.centerBox}>
              <Ionicons name="document-text-outline" size={48} color="#cbd5e1" />
              <Text style={styles.emptyTitle}>No Quotations Found</Text>
              <Text style={styles.emptySub}>Create proposals and estimate sheets</Text>
            </View>
          }
        />
      )}

      {/* Floating Add Button */}
      <TouchableOpacity
        style={styles.fab}
        onPress={() => {
          if (activeTab === 'invoices') {
            router.push('/invoice/add');
          } else {
            router.push('/quotation/add');
          }
        }}>
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
  topBar: {
    backgroundColor: '#ffffff',
    paddingHorizontal: Spacing.lg,
    paddingTop: Spacing.sm,
    paddingBottom: Spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: '#e2e8f0',
  },
  segmentContainer: {
    flexDirection: 'row',
    backgroundColor: '#f1f5f9',
    borderRadius: Radius.md,
    padding: 3,
    marginBottom: 10,
  },
  segmentBtn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    paddingVertical: 7,
    borderRadius: Radius.sm,
  },
  segmentBtnActive: {
    backgroundColor: Colors.primary,
  },
  segmentText: {
    fontSize: 13,
    fontWeight: '700',
    color: '#64748b',
  },
  segmentTextActive: {
    color: '#ffffff',
  },
  searchBar: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f1f5f9',
    borderRadius: Radius.md,
    paddingHorizontal: 12,
    height: 40,
    gap: 8,
  },
  searchInput: {
    flex: 1,
    fontSize: 13,
    color: '#0f172a',
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
    shadowRadius: 4,
    elevation: 2,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 8,
  },
  numberText: {
    fontSize: 12,
    fontWeight: '800',
    color: '#6366f1',
    letterSpacing: 0.5,
  },
  clientText: {
    fontSize: 15,
    fontWeight: '800',
    color: '#0f172a',
    marginTop: 1,
  },
  mobileText: {
    fontSize: 12,
    color: '#64748b',
    marginTop: 2,
  },
  statusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: Radius.sm,
  },
  statusBadgeText: {
    fontSize: 10,
    fontWeight: '800',
  },
  finRow: {
    flexDirection: 'row',
    backgroundColor: '#f8fafc',
    padding: 10,
    borderRadius: Radius.md,
    marginVertical: 6,
  },
  finCol: {
    flex: 1,
  },
  finLabel: {
    fontSize: 10,
    fontWeight: '700',
    color: '#94a3b8',
    textTransform: 'uppercase',
  },
  finAmount: {
    fontSize: 15,
    fontWeight: '800',
    color: '#0f172a',
    marginTop: 2,
  },
  finAmountText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#475569',
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
  dateText: {
    fontSize: 11,
    color: '#94a3b8',
    fontWeight: '500',
  },
  actionBtnsRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  iconBtn: {
    width: 32,
    height: 32,
    borderRadius: 8,
    backgroundColor: '#d1fae5',
    alignItems: 'center',
    justifyContent: 'center',
  },
  convertBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: '#10b981',
    paddingHorizontal: 8,
    paddingVertical: 5,
    borderRadius: Radius.sm,
  },
  convertBtnText: {
    color: '#ffffff',
    fontSize: 11,
    fontWeight: '700',
  },
  centerBox: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingTop: 80,
  },
  loadingText: {
    fontSize: 13,
    color: '#64748b',
    marginTop: 8,
  },
  emptyTitle: {
    fontSize: 16,
    fontWeight: '800',
    color: '#0f172a',
    marginTop: 10,
  },
  emptySub: {
    fontSize: 13,
    color: '#64748b',
    marginTop: 4,
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
