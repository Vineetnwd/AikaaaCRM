import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TextInput,
  TouchableOpacity,
  ActivityIndicator,
  Alert,
} from 'react-native';
import { useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { api } from '../../services/api';
import { Colors, Spacing, Radius } from '../../constants/theme';

export default function AddLeadScreen() {
  const router = useRouter();

  const [name, setName] = useState('');
  const [mobile, setMobile] = useState('');
  const [email, setEmail] = useState('');
  const [address, setAddress] = useState('');
  const [category, setCategory] = useState<'red' | 'green' | 'yellow'>('green');
  const [dealValue, setDealValue] = useState('');
  const [source, setSource] = useState('direct');
  const [customRequirement, setCustomRequirement] = useState('');

  const [requirementsList, setRequirementsList] = useState<{ id: number; name: string; default_fee?: number }[]>([]);
  const [selectedReqs, setSelectedReqs] = useState<number[]>([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    (async () => {
      try {
        const res = await api.getRequirements();
        if (Array.isArray(res)) {
          setRequirementsList(res);
        }
      } catch (e) {
        console.warn('Failed to load requirements', e);
      }
    })();
  }, []);

  const toggleReq = (reqId: number, fee: number = 0) => {
    let updated: number[];
    if (selectedReqs.includes(reqId)) {
      updated = selectedReqs.filter(id => id !== reqId);
    } else {
      updated = [...selectedReqs, reqId];
    }
    setSelectedReqs(updated);

    // Sum fee
    const sum = updated.reduce((acc, curId) => {
      const found = requirementsList.find(r => r.id === curId);
      return acc + (Number(found?.default_fee) || 0);
    }, 0);
    if (sum > 0) {
      setDealValue(String(sum));
    }
  };

  const handleSave = async () => {
    const cleanMobile = mobile.replace(/[^0-9]/g, '');
    if (cleanMobile.length !== 10) {
      Alert.alert('Invalid Mobile', 'Please enter a valid 10-digit mobile number.');
      return;
    }

    setLoading(true);
    try {
      const selectedNames = requirementsList
        .filter(r => selectedReqs.includes(r.id))
        .map(r => r.name);
      if (customRequirement.trim()) {
        selectedNames.push(customRequirement.trim());
      }

      const payload = {
        name: name.trim() || 'NO NAME',
        mobile: cleanMobile,
        email: email.trim(),
        address: address.trim(),
        category,
        source,
        requirement: selectedNames.join(', '),
        requirement_ids: selectedReqs,
        deal_value: parseFloat(dealValue) || 0,
        status: 'new',
      };

      const res = await api.createLead(payload);
      if (res.id || res.success) {
        Alert.alert('Opportunity Created', 'The lead was successfully added to your pipeline!');
        router.back();
      } else {
        Alert.alert('Error', res.error || 'Failed to create lead.');
      }
    } catch (err: any) {
      Alert.alert('Error', err.message || 'Network error occurred.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      <View style={styles.card}>
        <Text style={styles.sectionTitle}>Client Information</Text>

        <Text style={styles.label}>Client Name</Text>
        <TextInput
          style={styles.input}
          placeholder="e.g. Ramesh Kumar"
          placeholderTextColor="#94a3b8"
          value={name}
          onChangeText={setName}
        />

        <Text style={styles.label}>Mobile Number * (10 Digits)</Text>
        <TextInput
          style={styles.input}
          placeholder="e.g. 9876543210"
          placeholderTextColor="#94a3b8"
          keyboardType="numeric"
          maxLength={10}
          value={mobile}
          onChangeText={setMobile}
        />

        <Text style={styles.label}>Email Address</Text>
        <TextInput
          style={styles.input}
          placeholder="e.g. ramesh@example.com"
          placeholderTextColor="#94a3b8"
          keyboardType="email-address"
          autoCapitalize="none"
          value={email}
          onChangeText={setEmail}
        />

        <Text style={styles.label}>Location / Address</Text>
        <TextInput
          style={styles.input}
          placeholder="e.g. Mumbai, Maharashtra"
          placeholderTextColor="#94a3b8"
          value={address}
          onChangeText={setAddress}
        />
      </View>

      {/* Category & Source */}
      <View style={styles.card}>
        <Text style={styles.sectionTitle}>Priority & Category</Text>
        <View style={styles.catRow}>
          {[
            { key: 'green', label: '🟢 GREEN (Hot)', color: '#10b981' },
            { key: 'yellow', label: '🟡 YELLOW (Warm)', color: '#f59e0b' },
            { key: 'red', label: '🔴 RED (Cold)', color: '#ef4444' },
          ].map(c => (
            <TouchableOpacity
              key={c.key}
              style={[
                styles.catBtn,
                category === c.key && { borderColor: c.color, backgroundColor: `${c.color}15` },
              ]}
              onPress={() => setCategory(c.key as any)}>
              <Text style={{ fontWeight: '700', fontSize: 11, color: c.color }}>{c.label}</Text>
            </TouchableOpacity>
          ))}
        </View>

        <Text style={[styles.label, { marginTop: 14 }]}>Deal Value (₹)</Text>
        <TextInput
          style={[styles.input, { fontWeight: '700', color: '#10b981' }]}
          placeholder="0.00"
          placeholderTextColor="#94a3b8"
          keyboardType="numeric"
          value={dealValue}
          onChangeText={setDealValue}
        />
      </View>

      {/* Requirements Selection */}
      <View style={styles.card}>
        <Text style={styles.sectionTitle}>Services & Requirements</Text>
        <Text style={styles.subtext}>Select services requested by client:</Text>

        <View style={styles.reqGrid}>
          {requirementsList.map(req => {
            const isSelected = selectedReqs.includes(req.id);
            return (
              <TouchableOpacity
                key={req.id}
                style={[styles.reqChip, isSelected && styles.reqChipActive]}
                onPress={() => toggleReq(req.id, req.default_fee)}>
                <Ionicons
                  name={isSelected ? 'checkbox' : 'square-outline'}
                  size={16}
                  color={isSelected ? '#ffffff' : '#64748b'}
                />
                <Text style={[styles.reqChipText, isSelected && styles.reqChipTextActive]}>
                  {req.name}
                </Text>
              </TouchableOpacity>
            );
          })}
        </View>

        <Text style={[styles.label, { marginTop: 14 }]}>Other Custom Requirement</Text>
        <TextInput
          style={styles.input}
          placeholder="e.g. Express 24-hr expedited service"
          placeholderTextColor="#94a3b8"
          value={customRequirement}
          onChangeText={setCustomRequirement}
        />
      </View>

      {/* Submit Button */}
      <TouchableOpacity
        style={[styles.submitBtn, loading && { opacity: 0.7 }]}
        disabled={loading}
        onPress={handleSave}>
        {loading ? (
          <ActivityIndicator color="#ffffff" size="small" />
        ) : (
          <>
            <Ionicons name="checkmark-circle" size={18} color="#ffffff" />
            <Text style={styles.submitBtnText}>Save Lead to Pipeline</Text>
          </>
        )}
      </TouchableOpacity>
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
    marginBottom: Spacing.lg,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  sectionTitle: {
    fontSize: 15,
    fontWeight: '800',
    color: '#0f172a',
    marginBottom: 10,
  },
  subtext: {
    fontSize: 12,
    color: '#64748b',
    marginBottom: 8,
  },
  label: {
    fontSize: 11,
    fontWeight: '700',
    color: '#475569',
    textTransform: 'uppercase',
    marginTop: 8,
    marginBottom: 4,
  },
  input: {
    backgroundColor: '#f8fafc',
    borderWidth: 1,
    borderColor: '#e2e8f0',
    borderRadius: Radius.md,
    paddingHorizontal: 12,
    height: 44,
    fontSize: 14,
    color: '#0f172a',
  },
  catRow: {
    flexDirection: 'row',
    gap: 6,
  },
  catBtn: {
    flex: 1,
    paddingVertical: 8,
    borderRadius: Radius.sm,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    alignItems: 'center',
    backgroundColor: '#ffffff',
  },
  reqGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  reqChip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: Radius.sm,
    backgroundColor: '#f1f5f9',
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  reqChipActive: {
    backgroundColor: Colors.primary,
    borderColor: Colors.primary,
  },
  reqChipText: {
    fontSize: 12,
    color: '#334155',
    fontWeight: '600',
  },
  reqChipTextActive: {
    color: '#ffffff',
    fontWeight: '700',
  },
  submitBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    backgroundColor: Colors.primary,
    height: 48,
    borderRadius: Radius.md,
    shadowColor: Colors.primary,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.3,
    shadowRadius: 6,
    elevation: 4,
  },
  submitBtnText: {
    color: '#ffffff',
    fontSize: 15,
    fontWeight: '700',
  },
});
