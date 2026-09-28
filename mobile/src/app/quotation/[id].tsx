import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  ActivityIndicator,
  Linking,
  Alert,
} from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { api } from '../../services/api';
import { Colors, Spacing, Radius } from '../../constants/theme';
import { Quotation } from '../../types/crm';

export default function QuotationDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const [quotation, setQuotation] = useState<Quotation | null>(null);
  const [items, setItems] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    (async () => {
      if (!id) return;
      try {
        const res = await api.getQuotation(id);
        if (res) {
          setQuotation(res.quotation || res);
          if (Array.isArray(res.items)) {
            setItems(res.items);
          }
        }
      } catch (e) {
        console.warn('Failed to load quotation', e);
      } finally {
        setLoading(false);
      }
    })();
  }, [id]);

  const shareWhatsApp = () => {
    if (!quotation) return;
    const clean = (quotation.customer_mobile || '').replace(/[^0-9]/g, '');
    const phone = clean.length === 10 ? `91${clean}` : clean;
    const msg = encodeURIComponent(
      `Hello ${quotation.customer_name}, here is your Quotation #${quotation.quotation_number} for ₹${quotation.total_amount}.`
    );
    Linking.openURL(`whatsapp://send?phone=${phone}&text=${msg}`);
  };

  const openPdfPrint = () => {
    const baseUrl = api.getBaseUrl().replace('/api', '');
    const url = `${baseUrl}/public/index.php/quotation/print?id=${id}`;
    router.push({ pathname: '/webview', params: { url, title: `Quotation #${quotation?.quotation_number}` } });
  };

  const handleConvert = async () => {
    if (!quotation) return;
    Alert.alert(
      'Convert Quotation',
      `Convert Quotation #${quotation.quotation_number} into an official Invoice?`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Convert',
          onPress: async () => {
            try {
              const res = await api.convertQuotationToInvoice(quotation.id);
              if (res.success) {
                Alert.alert('Success', 'Converted to Invoice!');
                router.back();
              } else {
                Alert.alert('Error', res.error || 'Conversion failed.');
              }
            } catch (err: any) {
              Alert.alert('Error', err.message);
            }
          },
        },
      ]
    );
  };

  if (loading || !quotation) {
    return (
      <View style={styles.centerBox}>
        <ActivityIndicator size="large" color={Colors.primary} />
        <Text style={styles.loadingText}>Loading quotation details...</Text>
      </View>
    );
  }

  const isConverted = quotation.status === 'invoiced';

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <View>
            <Text style={styles.quoNumber}>Quotation #{quotation.quotation_number}</Text>
            <Text style={styles.clientName}>{quotation.customer_name}</Text>
            <Text style={styles.mobileText}>📞 {quotation.customer_mobile}</Text>
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
              {(quotation.status || 'pending').toUpperCase()}
            </Text>
          </View>
        </View>

        <View style={styles.row}>
          <Text style={styles.rowLabel}>Total Quotation Amount:</Text>
          <Text style={styles.amountText}>
            ₹{parseFloat(String(quotation.total_amount)).toLocaleString('en-IN')}
          </Text>
        </View>

        <View style={styles.row}>
          <Text style={styles.rowLabel}>Valid Until:</Text>
          <Text style={styles.rowValue}>{quotation.valid_until || 'No expiry date'}</Text>
        </View>

        <View style={styles.actionRow}>
          <TouchableOpacity style={[styles.actionBtn, { backgroundColor: '#d1fae5' }]} onPress={shareWhatsApp}>
            <Ionicons name="logo-whatsapp" size={16} color="#065f46" />
            <Text style={[styles.actionBtnText, { color: '#065f46' }]}>WhatsApp</Text>
          </TouchableOpacity>

          <TouchableOpacity style={[styles.actionBtn, { backgroundColor: '#e0e7ff' }]} onPress={openPdfPrint}>
            <Ionicons name="document-text" size={16} color="#4338ca" />
            <Text style={[styles.actionBtnText, { color: '#4338ca' }]}>View Document</Text>
          </TouchableOpacity>
        </View>

        {!isConverted && (
          <TouchableOpacity style={styles.convertBigBtn} onPress={handleConvert}>
            <Ionicons name="swap-horizontal" size={18} color="#ffffff" />
            <Text style={styles.convertBigBtnText}>Convert To Live Invoice</Text>
          </TouchableOpacity>
        )}
      </View>

      {/* Line Items */}
      {items.length > 0 && (
        <View style={styles.card}>
          <Text style={styles.sectionTitle}>Proposed Services & Items</Text>
          {items.map((item, idx) => (
            <View key={idx} style={styles.itemRow}>
              <View style={{ flex: 1 }}>
                <Text style={styles.itemDesc}>{item.description || item.name || 'Service Item'}</Text>
                {item.quantity ? <Text style={styles.itemQty}>Qty: {item.quantity}</Text> : null}
              </View>
              <Text style={styles.itemRate}>₹{parseFloat(String(item.total || item.rate || 0)).toLocaleString('en-IN')}</Text>
            </View>
          ))}
        </View>
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
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 12,
  },
  quoNumber: {
    fontSize: 14,
    fontWeight: '800',
    color: '#6366f1',
  },
  clientName: {
    fontSize: 18,
    fontWeight: '800',
    color: '#0f172a',
    marginTop: 2,
  },
  mobileText: {
    fontSize: 13,
    color: '#64748b',
    marginTop: 2,
  },
  statusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: Radius.sm,
  },
  statusBadgeText: {
    fontSize: 10,
    fontWeight: '800',
  },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginVertical: 4,
  },
  rowLabel: {
    fontSize: 13,
    color: '#64748b',
  },
  rowValue: {
    fontSize: 13,
    fontWeight: '600',
    color: '#0f172a',
  },
  amountText: {
    fontSize: 16,
    fontWeight: '800',
    color: '#10b981',
  },
  actionRow: {
    flexDirection: 'row',
    gap: 10,
    borderTopWidth: 1,
    borderTopColor: '#f1f5f9',
    paddingTop: 12,
    marginTop: 12,
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
  convertBigBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    backgroundColor: '#10b981',
    paddingVertical: 12,
    borderRadius: Radius.md,
    marginTop: 12,
  },
  convertBigBtnText: {
    color: '#ffffff',
    fontSize: 14,
    fontWeight: '700',
  },
  itemRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#f1f5f9',
  },
  itemDesc: {
    fontSize: 14,
    color: '#334155',
    fontWeight: '600',
    marginBottom: 4,
  },
  itemQty: {
    fontSize: 12,
    color: '#64748b',
  },
  itemRate: {
    fontSize: 14,
    fontWeight: '700',
    color: '#0f172a',
  },
});
