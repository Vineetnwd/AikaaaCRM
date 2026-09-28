import React, { useState, useEffect } from 'react';
import { View, Text, TextInput, TouchableOpacity, ScrollView, Alert, ActivityIndicator, Switch, StyleSheet } from 'react-native';
import { useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { api } from '../../services/api';
import LeadSearchModal from '../../components/LeadSearchModal';
import { Lead } from '../../types/crm';

export default function AddQuotationScreen() {
  const router = useRouter();

  const [leadId, setLeadId] = useState<number | null>(null);
  const [selectedLeadName, setSelectedLeadName] = useState('');
  
  const [quotationNumber, setQuotationNumber] = useState(`QUO-${Date.now()}`);
  const [quotationDate, setQuotationDate] = useState(new Date().toISOString().split('T')[0]);
  
  const [items, setItems] = useState<{name: string; note: string; qty: string; rate: string}[]>([
    { name: '', note: '', qty: '1', rate: '' }
  ]);
  
  const [isGstEnabled, setIsGstEnabled] = useState(true);
  const [gstType, setGstType] = useState('intra'); // local vs interstate
  const [taxPercent, setTaxPercent] = useState('18');
  
  const [discountType, setDiscountType] = useState('fixed'); // fixed vs percent
  const [discountInput, setDiscountInput] = useState('0');

  const [reqMap, setReqMap] = useState<Record<string, {fee: number, desc: string}>>({});

  useEffect(() => {
    const fetchReqs = async () => {
      try {
        const res: any = await api.getRequirements();
        if (res && res.data) {
          const map: Record<string, {fee: number, desc: string}> = {};
          res.data.forEach((r: any) => {
            if (r.name) {
              map[r.name.trim()] = { fee: parseFloat(r.fee) || 0, desc: r.description || '' };
            }
          });
          setReqMap(map);
        }
      } catch (err) {
        console.warn('Failed to load requirement fees', err);
      }
    };
    fetchReqs();
  }, []);

  const [loading, setLoading] = useState(false);
  const [searchModal, setSearchModal] = useState(false);

  // Calculations
  const subtotalValue = items.reduce((sum, item) => {
    const q = parseFloat(item.qty) || 0;
    const r = parseFloat(item.rate) || 0;
    return sum + (q * r);
  }, 0);

  const discountAmount = discountType === 'percent' 
    ? (subtotalValue * (parseFloat(discountInput) || 0) / 100) 
    : (parseFloat(discountInput) || 0);

  const taxableAmount = Math.max(0, subtotalValue - discountAmount);
  
  const taxAmount = isGstEnabled ? (taxableAmount * (parseFloat(taxPercent) || 0) / 100) : 0;
  const totalAmountValue = taxableAmount + taxAmount;

  const handleSave = async () => {
    if (!leadId) {
      Alert.alert('Required Fields', 'Please select a lead.');
      return;
    }
    
    if (subtotalValue <= 0) {
      Alert.alert('Invalid Amount', 'Subtotal must be greater than 0.');
      return;
    }

    setLoading(true);
    try {
      const payload = {
        lead_id: leadId,
        quotation_number: quotationNumber,
        quotation_date: quotationDate,
        subtotal: subtotalValue,
        description: JSON.stringify(items),
        is_gst_enabled: isGstEnabled ? 1 : 0,
        gst_type: gstType,
        tax_percent: parseFloat(taxPercent) || 0,
        discount: discountAmount,
      };

      const res = await api.createQuotation(payload);
      if (res.success || res.id) {
        Alert.alert('Quotation Created', 'Proposal successfully generated!');
        router.back();
      } else {
        Alert.alert('Error', res.error || 'Failed to create quotation.');
      }
    } catch (err: any) {
      Alert.alert('Error', err.message || 'Network error.');
    } finally {
      setLoading(false);
    }
  };

  const updateItem = (index: number, field: string, value: string) => {
    const newItems = [...items];
    (newItems[index] as any)[field] = value;
    setItems(newItems);
  };

  const addItem = () => {
    setItems([...items, { name: '', note: '', qty: '1', rate: '' }]);
  };

  const removeItem = (index: number) => {
    if (items.length > 1) {
      const newItems = [...items];
      newItems.splice(index, 1);
      setItems(newItems);
    }
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <View style={styles.card}>
        <Text style={styles.sectionTitle}>Draft New Quotation</Text>

        <Text style={styles.label}>Quotation #</Text>
        <TextInput
          style={[styles.input, { backgroundColor: '#f1f5f9', color: '#64748b' }]}
          value={quotationNumber}
          editable={false}
        />

        <Text style={styles.label}>Quotation Date</Text>
        <TextInput
          style={styles.input}
          value={quotationDate}
          onChangeText={setQuotationDate}
        />

        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 15 }}>
          <Text style={[styles.label, { marginTop: 0 }]}>Select Lead *</Text>
        </View>
        <TouchableOpacity 
          style={[styles.input, { justifyContent: 'center', backgroundColor: '#f8fafc' }]}
          onPress={() => setSearchModal(true)}
        >
          <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
            <Text style={{ color: leadId ? '#0f172a' : '#94a3b8', fontSize: 14 }}>
              {selectedLeadName || '-- Choose Lead --'}
            </Text>
            <Ionicons name="chevron-down" size={16} color="#94a3b8" />
          </View>
        </TouchableOpacity>
      </View>

      <View style={styles.card}>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 15 }}>
          <Text style={[styles.sectionTitle, { marginBottom: 0 }]}>Services & Items</Text>
          <TouchableOpacity 
            style={[styles.searchBtn, { backgroundColor: '#e0f2fe' }]} 
            onPress={addItem}
          >
            <Ionicons name="add" size={16} color="#0284c7" style={{ marginRight: 2 }} />
            <Text style={[styles.searchBtnText, { color: '#0284c7' }]}>Add Item</Text>
          </TouchableOpacity>
        </View>

        {items.map((item, index) => (
          <View key={index} style={{ marginBottom: 16, padding: 12, backgroundColor: '#f8fafc', borderRadius: 8, borderWidth: 1, borderColor: '#e2e8f0' }}>
            <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
              <Text style={{ fontSize: 13, fontWeight: '700', color: '#64748b', textTransform: 'uppercase' }}>Item #{index + 1}</Text>
              {items.length > 1 && (
                <TouchableOpacity onPress={() => removeItem(index)} style={{ padding: 4 }}>
                  <Ionicons name="trash-outline" size={18} color="#ef4444" />
                </TouchableOpacity>
              )}
            </View>

            <TextInput
              style={[styles.input, { marginBottom: 8, backgroundColor: '#ffffff', fontWeight: '600' }]}
              placeholder="Service Name (e.g. Website Development)"
              placeholderTextColor="#94a3b8"
              value={item.name}
              onChangeText={(val) => {
                const newItems = [...items];
                newItems[index].name = val;
                const match = reqMap[val.trim()];
                if (match) {
                  newItems[index].rate = String(match.fee);
                  newItems[index].note = match.desc;
                }
                setItems(newItems);
              }}
            />
            
            <TextInput
              style={[styles.input, { marginBottom: 12, backgroundColor: '#ffffff', height: 40, fontSize: 13 }]}
              placeholder="Add detailed note/description..."
              placeholderTextColor="#94a3b8"
              value={item.note}
              onChangeText={(val) => updateItem(index, 'note', val)}
            />

            <View style={{ flexDirection: 'row', gap: 10, alignItems: 'center' }}>
              <View style={{ flex: 1 }}>
                <Text style={[styles.label, { marginTop: 0 }]}>Qty</Text>
                <TextInput
                  style={[styles.input, { backgroundColor: '#ffffff', textAlign: 'center' }]}
                  keyboardType="numeric"
                  value={item.qty}
                  onChangeText={(val) => updateItem(index, 'qty', val)}
                />
              </View>

              <View style={{ flex: 1.5 }}>
                <Text style={[styles.label, { marginTop: 0 }]}>Rate (₹)</Text>
                <TextInput
                  style={[styles.input, { backgroundColor: '#ffffff' }]}
                  keyboardType="numeric"
                  value={item.rate}
                  onChangeText={(val) => updateItem(index, 'rate', val)}
                />
              </View>

              <View style={{ flex: 1.5, alignItems: 'flex-end', justifyContent: 'center' }}>
                <Text style={[styles.label, { marginTop: 0 }]}>Amount</Text>
                <Text style={{ fontSize: 15, fontWeight: '800', color: '#0f172a', marginTop: 10 }}>
                  ₹{((parseFloat(item.qty) || 0) * (parseFloat(item.rate) || 0)).toFixed(2)}
                </Text>
              </View>
            </View>
          </View>
        ))}
      </View>

      <View style={styles.card}>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 15, paddingBottom: 15, borderBottomWidth: 1, borderBottomColor: '#e2e8f0' }}>
          <Text style={{ fontSize: 15, fontWeight: '800', color: '#1e293b' }}>Enable GST</Text>
          <Switch value={isGstEnabled} onValueChange={setIsGstEnabled} trackColor={{ false: '#cbd5e1', true: '#6366f1' }} />
        </View>

        {isGstEnabled && (
          <View style={{ marginBottom: 15 }}>
            <View style={{ flexDirection: 'row', gap: 15, marginBottom: 10 }}>
              <TouchableOpacity onPress={() => setGstType('intra')} style={{ flexDirection: 'row', alignItems: 'center' }}>
                <Ionicons name={gstType === 'intra' ? 'radio-button-on' : 'radio-button-off'} size={20} color={gstType === 'intra' ? '#6366f1' : '#94a3b8'} />
                <Text style={{ marginLeft: 5, color: '#334155', fontWeight: '500' }}>Local (CGST/SGST)</Text>
              </TouchableOpacity>
              <TouchableOpacity onPress={() => setGstType('inter')} style={{ flexDirection: 'row', alignItems: 'center' }}>
                <Ionicons name={gstType === 'inter' ? 'radio-button-on' : 'radio-button-off'} size={20} color={gstType === 'inter' ? '#6366f1' : '#94a3b8'} />
                <Text style={{ marginLeft: 5, color: '#334155', fontWeight: '500' }}>Interstate (IGST)</Text>
              </TouchableOpacity>
            </View>

            <Text style={styles.label}>Tax Rate (%)</Text>
            <TextInput
              style={styles.input}
              keyboardType="numeric"
              value={taxPercent}
              onChangeText={setTaxPercent}
            />
          </View>
        )}

        <View style={{ flexDirection: 'row', gap: 10, marginBottom: 15 }}>
          <View style={{ flex: 1 }}>
            <Text style={styles.label}>Discount Type</Text>
            <TouchableOpacity 
              style={[styles.input, { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', backgroundColor: '#e0f2fe', borderColor: '#bae6fd' }]} 
              onPress={() => setDiscountType(discountType === 'fixed' ? 'percent' : 'fixed')}
            >
              <Text style={{ color: '#0284c7', fontWeight: '700', fontSize: 13 }}>
                {discountType === 'fixed' ? 'Fixed (₹)' : 'Percent (%)'}
              </Text>
              <Ionicons name="sync-outline" size={16} color="#0284c7" />
            </TouchableOpacity>
          </View>
          <View style={{ flex: 1 }}>
            <Text style={styles.label}>Discount Value</Text>
            <TextInput
              style={styles.input}
              keyboardType="numeric"
              value={discountInput}
              onChangeText={setDiscountInput}
            />
          </View>
        </View>

        <View style={{ backgroundColor: '#f8fafc', padding: 15, borderRadius: 8, borderWidth: 1, borderColor: '#e2e8f0' }}>
          <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginBottom: 8 }}>
            <Text style={{ color: '#64748b' }}>Base Subtotal:</Text>
            <Text style={{ fontWeight: '700', color: '#1e293b' }}>₹{subtotalValue.toFixed(2)}</Text>
          </View>
          {discountAmount > 0 && (
            <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginBottom: 8 }}>
              <Text style={{ color: '#64748b' }}>Discount:</Text>
              <Text style={{ fontWeight: '700', color: '#ef4444' }}>-₹{discountAmount.toFixed(2)}</Text>
            </View>
          )}
          {isGstEnabled && (
            <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginBottom: 8 }}>
              <Text style={{ color: '#64748b' }}>Tax ({taxPercent}%):</Text>
              <Text style={{ fontWeight: '700', color: '#1e293b' }}>+₹{taxAmount.toFixed(2)}</Text>
            </View>
          )}
          <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginTop: 8, paddingTop: 8, borderTopWidth: 1, borderTopColor: '#e2e8f0' }}>
            <Text style={{ fontSize: 16, fontWeight: '800', color: '#0f172a' }}>TOTAL VALUE:</Text>
            <Text style={{ fontSize: 18, fontWeight: '800', color: '#6366f1' }}>₹{totalAmountValue.toFixed(2)}</Text>
          </View>
        </View>
      </View>

      <TouchableOpacity
        style={[styles.submitBtn, loading && { opacity: 0.7 }]}
        disabled={loading}
        onPress={handleSave}>
        {loading ? (
          <ActivityIndicator color="#ffffff" size="small" />
        ) : (
          <>
            <Ionicons name="document-text-outline" size={18} color="#ffffff" />
            <Text style={styles.submitBtnText}>Save Quotation</Text>
          </>
        )}
      </TouchableOpacity>
      
      <LeadSearchModal
        visible={searchModal}
        onClose={() => setSearchModal(false)}
        onSelect={(lead: Lead) => {
          setSelectedLeadName(lead.name || lead.company || 'Unknown Lead');
          setLeadId(lead.id);
          
          if (lead.requirement_names || lead.requirement || lead.deal_value) {
            const reqs = (lead.requirement_names || lead.requirement || '').split(',').map(s => s.trim()).filter(Boolean);
            if (reqs.length > 0) {
              setItems(reqs.map((r, i) => {
                const mapData = reqMap[r];
                return {
                  name: r,
                  note: mapData ? mapData.desc : '',
                  qty: '1',
                  rate: mapData ? String(mapData.fee) : (i === 0 ? String(lead.deal_value || '0') : '0')
                };
              }));
            } else {
              setItems([{
                name: 'Professional Services',
                note: '',
                qty: '1',
                rate: String(lead.deal_value || '0')
              }]);
            }
          }
        }}
      />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f8fafc' },
  content: { padding: 16, paddingBottom: 40 },
  card: { backgroundColor: '#ffffff', borderRadius: 12, padding: 16, marginBottom: 16, borderWidth: 1, borderColor: '#e2e8f0' },
  sectionTitle: { fontSize: 15, fontWeight: '800', color: '#0f172a', marginBottom: 10 },
  label: { fontSize: 11, fontWeight: '700', color: '#475569', textTransform: 'uppercase', marginTop: 8, marginBottom: 4 },
  input: { backgroundColor: '#f8fafc', borderWidth: 1, borderColor: '#e2e8f0', borderRadius: 8, paddingHorizontal: 12, height: 44, fontSize: 14, color: '#0f172a' },
  searchBtn: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#e0f2fe', paddingHorizontal: 10, paddingVertical: 6, borderRadius: 6 },
  searchBtnText: { color: '#0284c7', fontSize: 12, fontWeight: '700' },
  submitBtn: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, backgroundColor: '#6366f1', height: 48, borderRadius: 8, shadowColor: '#6366f1', shadowOffset: { width: 0, height: 4 }, shadowOpacity: 0.3, shadowRadius: 6, elevation: 4 },
  submitBtnText: { color: '#ffffff', fontSize: 15, fontWeight: '700' }
});
