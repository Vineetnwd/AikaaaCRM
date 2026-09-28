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
import { Invoice } from '../../types/crm';

export default function InvoiceDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const [invoice, setInvoice] = useState<Invoice | null>(null);
  const [items, setItems] = useState<any[]>([]);
  const [payments, setPayments] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    (async () => {
      if (!id) return;
      try {
        const res = await api.getInvoice(id);
        if (res) {
          setInvoice(res.invoice || res);
          if (Array.isArray(res.items)) {
            setItems(res.items);
          }
        }
        
        const payRes = await api.getInvoicePayments(id);
        if (Array.isArray(payRes)) {
          setPayments(payRes);
        }
      } catch (e) {
        console.warn('Failed to fetch invoice', e);
      } finally {
        setLoading(false);
      }
    })();
  }, [id]);

  const shareWhatsApp = () => {
    if (!invoice) return;
    const clean = (invoice.customer_mobile || '').replace(/[^0-9]/g, '');
    const phone = clean.length === 10 ? `91${clean}` : clean;
    const msg = encodeURIComponent(
      `Hello ${invoice.customer_name}, here is your Invoice #${invoice.invoice_number} for ₹${invoice.total_amount}. Balance Due: ₹${invoice.due_amount || '0.00'}.`
    );
    Linking.openURL(`whatsapp://send?phone=${phone}&text=${msg}`);
  };

  const openPdfPrint = () => {
    const baseUrl = api.getBaseUrl().replace('/api', '');
    const url = `${baseUrl}/public/index.php/invoice/print?id=${id}`;
    router.push({ pathname: '/webview', params: { url, title: `Invoice #${invoice?.invoice_number}` } });
  };

  if (loading || !invoice) {
    return (
      <View style={styles.centerBox}>
        <ActivityIndicator size="large" color={Colors.primary} />
        <Text style={styles.loadingText}>Loading invoice details...</Text>
      </View>
    );
  }

  const isPaid = invoice.payment_status === 'paid';

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      {/* Invoice Overview Card */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <View>
            <Text style={styles.invNumber}>Invoice #{invoice.invoice_number}</Text>
            <Text style={styles.clientName}>{invoice.customer_name}</Text>
            <Text style={styles.mobileText}>📞 {invoice.customer_mobile}</Text>
          </View>
          <View
            style={[
              styles.statusBadge,
              invoice.payment_status === 'paid' 
                ? { backgroundColor: '#d1fae5' } 
                : invoice.payment_status === 'partial' 
                  ? { backgroundColor: '#fef3c7' } 
                  : { backgroundColor: '#fee2e2' },
            ]}>
            <Text
              style={[
                styles.statusBadgeText,
                invoice.payment_status === 'paid' 
                  ? { color: '#065f46' } 
                  : invoice.payment_status === 'partial' 
                    ? { color: '#d97706' } 
                    : { color: '#b91c1c' },
              ]}>
              {(invoice.payment_status || 'pending').toUpperCase()}
            </Text>
          </View>
        </View>

        {invoice.customer_address ? (
          <Text style={styles.addressText}>📍 {invoice.customer_address}</Text>
        ) : null}

        {/* Action Buttons */}
        <View style={styles.actionRow}>
          <TouchableOpacity style={[styles.actionBtn, { backgroundColor: '#d1fae5' }]} onPress={shareWhatsApp}>
            <Ionicons name="logo-whatsapp" size={16} color="#065f46" />
            <Text style={[styles.actionBtnText, { color: '#065f46' }]}>WhatsApp Share</Text>
          </TouchableOpacity>

          <TouchableOpacity style={[styles.actionBtn, { backgroundColor: '#e0e7ff' }]} onPress={openPdfPrint}>
            <Ionicons name="document-text" size={16} color="#4338ca" />
            <Text style={[styles.actionBtnText, { color: '#4338ca' }]}>View Invoice PDF</Text>
          </TouchableOpacity>
        </View>
      </View>

      {/* Financial Summary Card */}
      <View style={styles.card}>
        <Text style={styles.sectionTitle}>Financial Breakdown</Text>
        <View style={styles.row}>
          <Text style={styles.rowLabel}>Total Amount:</Text>
          <Text style={styles.rowValue}>₹{parseFloat(String(invoice.total_amount)).toLocaleString('en-IN')}</Text>
        </View>
        <View style={styles.row}>
          <Text style={styles.rowLabel}>Amount Received:</Text>
          <Text style={[styles.rowValue, { color: '#10b981' }]}>
            ₹{parseFloat(String(invoice.paid_amount || 0)).toLocaleString('en-IN')}
          </Text>
        </View>
        <View style={[styles.row, { borderTopWidth: 1, borderTopColor: '#e2e8f0', paddingTop: 8 }]}>
          <Text style={[styles.rowLabel, { fontWeight: '800', color: '#0f172a' }]}>Balance Due:</Text>
          <Text style={[styles.rowValue, { fontWeight: '800', color: parseFloat(String(invoice.due_amount)) > 0 ? '#ef4444' : '#10b981' }]}>
            ₹{parseFloat(String(invoice.due_amount || 0)).toLocaleString('en-IN')}
          </Text>
        </View>
      </View>

      {/* Line Items */}
      {items.length > 0 && (
        <View style={styles.card}>
          <Text style={styles.sectionTitle}>Billed Services & Items</Text>
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

      {/* Transaction History */}
      {payments.length > 0 && (
        <View style={styles.card}>
          <Text style={styles.sectionTitle}>Transaction History</Text>
          {payments.map((pay, idx) => (
            <View key={idx} style={styles.paymentRow}>
              <View style={{ flex: 1 }}>
                <Text style={styles.paymentDate}>{pay.payment_date}</Text>
                <Text style={styles.paymentMethod}>
                  {pay.payment_method?.toUpperCase()} {pay.reference_number ? `• ${pay.reference_number}` : ''}
                </Text>
              </View>
              <View style={{ alignItems: 'flex-end' }}>
                <Text style={styles.paymentAmount}>₹{parseFloat(String(pay.amount)).toLocaleString('en-IN')}</Text>
                <TouchableOpacity 
                  onPress={() => {
                    const baseUrl = api.getBaseUrl().replace('/api', '');
                    const receiptUrl = `${baseUrl}/public/index.php/invoice/receipt?id=${pay.id}`;
                    router.push({ pathname: '/webview', params: { url: receiptUrl, title: 'Payment Receipt' } });
                  }}
                >
                  <Text style={styles.receiptLink}>View Receipt</Text>
                </TouchableOpacity>
              </View>
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
    paddingBottom: 40,
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
    marginBottom: Spacing.lg,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 8,
  },
  invNumber: {
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
  addressText: {
    fontSize: 12,
    color: '#64748b',
    marginTop: 4,
    marginBottom: 8,
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
  actionRow: {
    flexDirection: 'row',
    gap: 10,
    borderTopWidth: 1,
    borderTopColor: '#f1f5f9',
    paddingTop: 12,
    marginTop: 8,
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
  sectionTitle: {
    fontSize: 14,
    fontWeight: '800',
    color: '#0f172a',
    marginBottom: 10,
  },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  rowLabel: {
    fontSize: 13,
    color: '#64748b',
  },
  rowValue: {
    fontSize: 14,
    fontWeight: '700',
    color: '#0f172a',
  },
  itemRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 8,
    borderBottomWidth: 1,
    borderBottomColor: '#f1f5f9',
  },
  itemDesc: {
    fontSize: 13,
    fontWeight: '600',
    color: '#334155',
  },
  itemQty: {
    fontSize: 11,
    color: '#94a3b8',
    marginTop: 2,
  },
  itemRate: {
    fontSize: 13,
    fontWeight: '700',
    color: '#0f172a',
  },
  paymentRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#f1f5f9',
  },
  paymentDate: {
    fontSize: 14,
    color: '#334155',
    fontWeight: '600',
    marginBottom: 4,
  },
  paymentMethod: {
    fontSize: 12,
    color: '#64748b',
  },
  paymentAmount: {
    fontSize: 14,
    fontWeight: '800',
    color: '#10b981',
    marginBottom: 4,
  },
  receiptLink: {
    fontSize: 12,
    color: '#4338ca',
    fontWeight: '600',
  },
});
