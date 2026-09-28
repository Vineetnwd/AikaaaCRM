import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  ActivityIndicator,
  Alert,
  Modal,
  TextInput,
  Linking,
} from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { api } from '../../services/api';
import { useAuth } from '../../context/AuthContext';
import { Colors, Spacing, Radius } from '../../constants/theme';
import { Lead, Followup, Employee } from '../../types/crm';

export default function LeadDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const { isExecutive } = useAuth();

  const [lead, setLead] = useState<Lead | null>(null);
  const [followups, setFollowups] = useState<Followup[]>([]);
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [loading, setLoading] = useState(true);

  // Interaction / Follow-up modal
  const [interactionModal, setInteractionModal] = useState(false);
  const [callStatus, setCallStatus] = useState('connected');
  const [remark, setRemark] = useState('');
  const [nextFollowupDate, setNextFollowupDate] = useState('');
  const [savingInteraction, setSavingInteraction] = useState(false);

  // Transfer modal
  const [transferModal, setTransferModal] = useState(false);
  const [targetEmployeeId, setTargetEmployeeId] = useState<number | null>(null);
  const [transferRemark, setTransferRemark] = useState('');
  const [savingTransfer, setSavingTransfer] = useState(false);
  const [transferSearch, setTransferSearch] = useState('');

  const fetchLeadDetails = useCallback(async () => {
    if (!id) return;
    try {
      const [leadRes, followRes, empRes] = await Promise.allSettled([
        api.getLead(id),
        api.getFollowups(id),
        api.getEmployees(),
      ]);

      if (leadRes.status === 'fulfilled' && leadRes.value) {
        setLead(leadRes.value);
      }
      if (followRes.status === 'fulfilled' && Array.isArray(followRes.value)) {
        setFollowups(followRes.value);
      }
      if (empRes.status === 'fulfilled' && Array.isArray(empRes.value)) {
        setEmployees(empRes.value);
      }
    } catch (e) {
      console.warn('Error loading lead detail', e);
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    fetchLeadDetails();
  }, [fetchLeadDetails]);

  const handleCall = () => {
    if (!lead?.mobile) return;
    Linking.openURL(`tel:${lead.mobile}`);
  };

  const handleWhatsApp = async () => {
    if (!lead?.mobile) return;
    const clean = lead.mobile.replace(/[^0-9]/g, '');
    const phone = clean.length === 10 ? `91${clean}` : clean;
    const msg = encodeURIComponent(`Hello ${lead.name || ''}, regarding your requirement with Aikaa CRM.`);
    const appUrl = `whatsapp://send?phone=${phone}&text=${msg}`;
    const webUrl = `https://wa.me/${phone}?text=${msg}`;

    try {
      const supported = await Linking.canOpenURL(appUrl);
      if (supported) {
        await Linking.openURL(appUrl);
      } else {
        await Linking.openURL(webUrl);
      }
    } catch (err) {
      // If opening fails entirely, fallback to web or show error
      Linking.openURL(webUrl).catch(() => {
        Alert.alert('Error', 'Could not open WhatsApp.');
      });
    }
  };

  const handleEmail = () => {
    if (!lead?.email) {
      Alert.alert('No Email', 'This lead does not have an email address recorded.');
      return;
    }
    Linking.openURL(`mailto:${lead.email}`);
  };

  const handleAddFollowup = async () => {
    if (!remark.trim()) {
      Alert.alert('Remark Required', 'Please enter a note / remark for this interaction.');
      return;
    }

    setSavingInteraction(true);
    try {
      const res = await api.addFollowup({
        lead_id: Number(id),
        remark: remark.trim(),
        call_status: callStatus,
        follow_up_date: nextFollowupDate.trim() || undefined,
      });

      if (res.success) {
        setInteractionModal(false);
        setRemark('');
        setNextFollowupDate('');
        fetchLeadDetails();
        Alert.alert('Success', 'Interaction note saved successfully!');
      } else {
        Alert.alert('Error', res.error || 'Failed to save interaction.');
      }
    } catch (err: any) {
      Alert.alert('Error', err.message || 'Network error.');
    } finally {
      setSavingInteraction(false);
    }
  };

  const handleTransferLead = async () => {
    if (!targetEmployeeId) {
      Alert.alert('Staff Required', 'Please select a staff member to transfer this lead to.');
      return;
    }

    setSavingTransfer(true);
    try {
      const res = await api.transferLead(Number(id), targetEmployeeId, transferRemark.trim());
      if (res.success) {
        setTransferModal(false);
        setTransferRemark('');
        fetchLeadDetails();
        Alert.alert('Lead Transferred', 'Lead was transferred successfully.');
      } else {
        Alert.alert('Transfer Failed', res.error || 'Could not transfer lead.');
      }
    } catch (err: any) {
      Alert.alert('Transfer Failed', err.message || 'Network error.');
    } finally {
      setSavingTransfer(false);
    }
  };

  const handleStatusChange = async (newStatus: string) => {
    if (!lead) return;
    try {
      await api.updateLead(lead.id, { ...lead, status: newStatus });
      setLead({ ...lead, status: newStatus });
    } catch (err: any) {
      Alert.alert('Update Failed', err.message);
    }
  };

  const handleCategoryChange = async (cat: string) => {
    if (!lead) return;
    try {
      await api.updateLead(lead.id, { ...lead, category: cat });
      setLead({ ...lead, category: cat });
    } catch (err: any) {
      Alert.alert('Update Failed', err.message);
    }
  };

  if (loading || !lead) {
    return (
      <View style={styles.centerBox}>
        <ActivityIndicator size="large" color={Colors.primary} />
        <Text style={styles.loadingText}>Loading lead profile...</Text>
      </View>
    );
  }

  const categoryColor =
    lead.category === 'red'
      ? '#ef4444'
      : lead.category === 'green'
      ? '#10b981'
      : lead.category === 'yellow'
      ? '#f59e0b'
      : '#94a3b8';

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content}>
      {/* Header Profile Card */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <View style={{ flex: 1 }}>
            <View style={styles.nameRow}>
              <View style={[styles.categoryDot, { backgroundColor: categoryColor }]} />
              <Text style={styles.clientName}>{lead.name || 'Unnamed'}</Text>
            </View>
            <Text style={styles.mobileText}>📞 {lead.mobile}</Text>
            {lead.email ? <Text style={styles.emailText}>✉️ {lead.email}</Text> : null}
            {lead.address ? <Text style={styles.addressText}>📍 {lead.address}</Text> : null}
          </View>

          <View style={styles.statusBadge}>
            <Text style={styles.statusBadgeText}>{(lead.status || 'new').toUpperCase()}</Text>
          </View>
        </View>

        {/* Action Buttons Row */}
        <View style={styles.actionRow}>
          <TouchableOpacity style={[styles.actionBtn, { backgroundColor: '#e0e7ff' }]} onPress={handleCall}>
            <Ionicons name="call" size={16} color="#4338ca" />
            <Text style={[styles.actionBtnText, { color: '#4338ca' }]}>Call</Text>
          </TouchableOpacity>

          <TouchableOpacity style={[styles.actionBtn, { backgroundColor: '#d1fae5' }]} onPress={handleWhatsApp}>
            <Ionicons name="logo-whatsapp" size={16} color="#065f46" />
            <Text style={[styles.actionBtnText, { color: '#065f46' }]}>WhatsApp</Text>
          </TouchableOpacity>

          <TouchableOpacity style={[styles.actionBtn, { backgroundColor: '#ede9fe' }]} onPress={handleEmail}>
            <Ionicons name="mail" size={16} color="#5b21b6" />
            <Text style={[styles.actionBtnText, { color: '#5b21b6' }]}>Email</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.actionBtn, { backgroundColor: '#fef3c7' }]}
            onPress={() => setTransferModal(true)}>
            <Ionicons name="swap-horizontal" size={16} color="#b45309" />
            <Text style={[styles.actionBtnText, { color: '#b45309' }]}>Transfer</Text>
          </TouchableOpacity>
        </View>
      </View>

      {/* Deal & Requirements Card */}
      <View style={styles.card}>
        <Text style={styles.sectionTitle}>Requirement & Scope</Text>
        <Text style={styles.reqText}>
          {lead.requirement || lead.requirement_names || 'No specific requirements listed.'}
        </Text>

        <View style={styles.metaGrid}>
          <View style={styles.metaCol}>
            <Text style={styles.metaLabel}>Deal Value</Text>
            <Text style={styles.metaValue}>
              ₹{parseFloat(String(lead.deal_value || 0)).toLocaleString('en-IN')}
            </Text>
          </View>
          <View style={styles.metaCol}>
            <Text style={styles.metaLabel}>Lead Manager</Text>
            <Text style={styles.metaText}>{lead.assigned_to_name || 'Unassigned'}</Text>
          </View>
          <View style={styles.metaCol}>
            <Text style={styles.metaLabel}>Task Staff</Text>
            <Text style={styles.metaText}>{lead.assigned_employee_name || 'Unassigned'}</Text>
          </View>
        </View>
      </View>

      {/* Quick Status & Category Toggles */}
      <View style={styles.card}>
        <Text style={styles.sectionTitle}>Pipeline Stage</Text>
        <View style={styles.statusRow}>
          {['new', 'in_progress', 'won', 'lost'].map(st => (
            <TouchableOpacity
              key={st}
              style={[styles.statusSelectBtn, lead.status === st && styles.statusSelectActive]}
              onPress={() => handleStatusChange(st)}>
              <Text
                style={[
                  styles.statusSelectText,
                  lead.status === st && styles.statusSelectTextActive,
                ]}>
                {st.replace('_', ' ').toUpperCase()}
              </Text>
            </TouchableOpacity>
          ))}
        </View>

        <Text style={[styles.sectionTitle, { marginTop: 14 }]}>Category Color</Text>
        <View style={styles.catRow}>
          {[
            { key: 'red', label: '🔴 RED', color: '#ef4444' },
            { key: 'green', label: '🟢 GREEN', color: '#10b981' },
            { key: 'yellow', label: '🟡 YELLOW', color: '#f59e0b' },
          ].map(c => (
            <TouchableOpacity
              key={c.key}
              style={[
                styles.catBtn,
                lead.category === c.key && { borderColor: c.color, backgroundColor: `${c.color}15` },
              ]}
              onPress={() => handleCategoryChange(c.key)}>
              <Text style={{ fontWeight: '700', fontSize: 12, color: c.color }}>{c.label}</Text>
            </TouchableOpacity>
          ))}
        </View>
      </View>

      {/* Interaction Timeline & Followups */}
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Text style={styles.sectionTitle}>Interactions & Follow-ups</Text>
          <TouchableOpacity
            style={styles.addNoteBtn}
            onPress={() => setInteractionModal(true)}>
            <Ionicons name="add" size={14} color="#ffffff" />
            <Text style={styles.addNoteBtnText}>Add Note</Text>
          </TouchableOpacity>
        </View>

        {followups.length === 0 ? (
          <Text style={styles.emptyFollowupText}>No previous interactions recorded.</Text>
        ) : (
          followups.map(f => (
            <View key={f.id} style={styles.timelineItem}>
              <View style={styles.timelineDot} />
              <View style={styles.timelineContent}>
                <View style={styles.timelineTop}>
                  <Text style={styles.timelineRemark}>{f.remark}</Text>
                  <Text style={styles.timelineDate}>{f.created_at?.split(' ')[0]}</Text>
                </View>
                {f.call_status ? (
                  <Text style={styles.timelineStatus}>Call Status: {f.call_status.toUpperCase()}</Text>
                ) : null}
                {f.follow_up_date ? (
                  <Text style={styles.timelineNext}>📅 Next: {f.follow_up_date}</Text>
                ) : null}
              </View>
            </View>
          ))
        )}
      </View>

      {/* Add Follow-up Modal */}
      <Modal
        visible={interactionModal}
        transparent
        animationType="slide"
        onRequestClose={() => setInteractionModal(false)}>
        <View style={styles.modalOverlay}>
          <View style={styles.modalCard}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Record Interaction Note</Text>
              <TouchableOpacity onPress={() => setInteractionModal(false)}>
                <Ionicons name="close" size={20} color="#94a3b8" />
              </TouchableOpacity>
            </View>

            {/* Call Status Selector */}
            <Text style={styles.inputLabel}>Call Outcome</Text>
            <View style={styles.callStatusRow}>
              {['connected', 'busy', 'not_picked', 'switch_off'].map(cs => (
                <TouchableOpacity
                  key={cs}
                  style={[styles.callStatusBtn, callStatus === cs && styles.callStatusActive]}
                  onPress={() => setCallStatus(cs)}>
                  <Text
                    style={[
                      styles.callStatusText,
                      callStatus === cs && styles.callStatusTextActive,
                    ]}>
                    {cs.replace('_', ' ').toUpperCase()}
                  </Text>
                </TouchableOpacity>
              ))}
            </View>

            {/* Note / Remark */}
            <Text style={styles.inputLabel}>Follow-up Remark (Required)</Text>
            <TextInput
              style={[styles.modalInput, { height: 70, textAlignVertical: 'top' }]}
              placeholder="e.g. Client requested quotation revision by tomorrow morning..."
              value={remark}
              onChangeText={setRemark}
              multiline
            />

            {/* Next Date */}
            <Text style={styles.inputLabel}>Next Follow-up Date (YYYY-MM-DD)</Text>
            <TextInput
              style={styles.modalInput}
              placeholder="e.g. 2026-10-02"
              value={nextFollowupDate}
              onChangeText={setNextFollowupDate}
            />

            <View style={styles.modalActions}>
              <TouchableOpacity
                style={styles.cancelBtn}
                onPress={() => setInteractionModal(false)}>
                <Text style={styles.cancelBtnText}>Cancel</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={styles.saveBtn}
                disabled={savingInteraction}
                onPress={handleAddFollowup}>
                {savingInteraction ? (
                  <ActivityIndicator size="small" color="#ffffff" />
                ) : (
                  <Text style={styles.saveBtnText}>Save Interaction</Text>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* Transfer Lead Modal */}
      <Modal
        visible={transferModal}
        transparent
        animationType="slide"
        onRequestClose={() => { setTransferModal(false); setTransferSearch(''); }}>
        <View style={styles.modalOverlay}>
          <View style={styles.modalCard}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Transfer Lead</Text>
              <TouchableOpacity onPress={() => { setTransferModal(false); setTransferSearch(''); }}>
                <Ionicons name="close" size={20} color="#94a3b8" />
              </TouchableOpacity>
            </View>

            <Text style={styles.inputLabel}>Select Target Staff Member</Text>

            {/* Search box */}
            <View style={styles.transferSearchBox}>
              <Ionicons name="search" size={15} color="#94a3b8" style={{ marginRight: 6 }} />
              <TextInput
                style={styles.transferSearchInput}
                placeholder="Search by name or role…"
                placeholderTextColor="#94a3b8"
                value={transferSearch}
                onChangeText={setTransferSearch}
                autoCorrect={false}
              />
              {transferSearch.length > 0 && (
                <TouchableOpacity onPress={() => setTransferSearch('')}>
                  <Ionicons name="close-circle" size={16} color="#94a3b8" />
                </TouchableOpacity>
              )}
            </View>

            <ScrollView style={{ maxHeight: 160, marginBottom: 10 }}>
              {employees
                .filter(emp => {
                  const q = transferSearch.toLowerCase().trim();
                  if (!q) return true;
                  return (
                    emp.name?.toLowerCase().includes(q) ||
                    emp.designation?.toLowerCase().includes(q)
                  );
                })
                .map(emp => (
                  <TouchableOpacity
                    key={emp.id}
                    style={[
                      styles.empChoiceBtn,
                      targetEmployeeId === emp.id && styles.empChoiceActive,
                    ]}
                    onPress={() => setTargetEmployeeId(emp.id)}>
                    <Text
                      style={[
                        styles.empChoiceText,
                        targetEmployeeId === emp.id && styles.empChoiceTextActive,
                      ]}>
                      {emp.name} ({emp.designation || 'Staff'})
                    </Text>
                  </TouchableOpacity>
                ))}
              {transferSearch.trim().length > 0 &&
                employees.filter(emp => {
                  const q = transferSearch.toLowerCase().trim();
                  return (
                    emp.name?.toLowerCase().includes(q) ||
                    emp.designation?.toLowerCase().includes(q)
                  );
                }).length === 0 && (
                <Text style={styles.transferNoResults}>No staff found for "{transferSearch}"</Text>
              )}
            </ScrollView>

            <Text style={styles.inputLabel}>Transfer Reason / Handover Remark</Text>
            <TextInput
              style={[styles.modalInput, { height: 60, textAlignVertical: 'top' }]}
              placeholder="e.g. Handing over client due to regional territory change..."
              value={transferRemark}
              onChangeText={setTransferRemark}
              multiline
            />

            <View style={styles.modalActions}>
              <TouchableOpacity
                style={styles.cancelBtn}
                onPress={() => { setTransferModal(false); setTransferSearch(''); }}>
                <Text style={styles.cancelBtnText}>Cancel</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.saveBtn, { backgroundColor: '#6366f1' }]}
                disabled={savingTransfer}
                onPress={handleTransferLead}>
                {savingTransfer ? (
                  <ActivityIndicator size="small" color="#ffffff" />
                ) : (
                  <Text style={styles.saveBtnText}>Execute Transfer</Text>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
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
    marginBottom: 12,
  },
  nameRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  categoryDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
  },
  clientName: {
    fontSize: 18,
    fontWeight: '800',
    color: '#0f172a',
  },
  mobileText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#475569',
    marginTop: 4,
  },
  emailText: {
    fontSize: 12,
    color: '#64748b',
    marginTop: 2,
  },
  addressText: {
    fontSize: 12,
    color: '#64748b',
    marginTop: 2,
  },
  statusBadge: {
    backgroundColor: '#e0e7ff',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: Radius.sm,
  },
  statusBadgeText: {
    fontSize: 11,
    fontWeight: '800',
    color: '#4338ca',
  },
  actionRow: {
    flexDirection: 'row',
    gap: 8,
    borderTopWidth: 1,
    borderTopColor: '#f1f5f9',
    paddingTop: 12,
    marginTop: 6,
  },
  actionBtn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 4,
    paddingVertical: 8,
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
    marginBottom: 6,
  },
  reqText: {
    fontSize: 13,
    color: '#334155',
    lineHeight: 18,
    marginBottom: 12,
  },
  metaGrid: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    backgroundColor: '#f8fafc',
    padding: 10,
    borderRadius: Radius.md,
  },
  metaCol: {
    flex: 1,
  },
  metaLabel: {
    fontSize: 10,
    fontWeight: '700',
    color: '#94a3b8',
    textTransform: 'uppercase',
  },
  metaValue: {
    fontSize: 15,
    fontWeight: '800',
    color: '#10b981',
    marginTop: 2,
  },
  metaText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#334155',
    marginTop: 2,
  },
  statusRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 6,
    marginTop: 4,
  },
  statusSelectBtn: {
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: Radius.sm,
    backgroundColor: '#f1f5f9',
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  statusSelectActive: {
    backgroundColor: Colors.primary,
    borderColor: Colors.primary,
  },
  statusSelectText: {
    fontSize: 11,
    fontWeight: '700',
    color: '#64748b',
  },
  statusSelectTextActive: {
    color: '#ffffff',
  },
  catRow: {
    flexDirection: 'row',
    gap: 8,
    marginTop: 4,
  },
  catBtn: {
    flex: 1,
    paddingVertical: 6,
    borderRadius: Radius.sm,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    alignItems: 'center',
    backgroundColor: '#ffffff',
  },
  addNoteBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: Colors.primary,
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: Radius.sm,
  },
  addNoteBtnText: {
    color: '#ffffff',
    fontSize: 11,
    fontWeight: '700',
  },
  emptyFollowupText: {
    fontSize: 12,
    color: '#94a3b8',
    fontStyle: 'italic',
    marginTop: 4,
  },
  timelineItem: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 10,
  },
  timelineDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: Colors.primary,
    marginTop: 5,
  },
  timelineContent: {
    flex: 1,
    backgroundColor: '#f8fafc',
    padding: 10,
    borderRadius: Radius.md,
  },
  timelineTop: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
  },
  timelineRemark: {
    fontSize: 13,
    color: '#1e293b',
    fontWeight: '600',
    flex: 1,
  },
  timelineDate: {
    fontSize: 10,
    color: '#94a3b8',
    marginLeft: 6,
  },
  timelineStatus: {
    fontSize: 11,
    color: '#64748b',
    fontWeight: '700',
    marginTop: 4,
  },
  timelineNext: {
    fontSize: 11,
    color: '#6366f1',
    fontWeight: '700',
    marginTop: 2,
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(15, 23, 42, 0.6)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: Spacing.lg,
  },
  modalCard: {
    width: '100%',
    maxWidth: 420,
    backgroundColor: '#ffffff',
    borderRadius: Radius.lg,
    padding: Spacing.lg,
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 10,
  },
  modalTitle: {
    fontSize: 16,
    fontWeight: '800',
    color: '#0f172a',
  },
  inputLabel: {
    fontSize: 11,
    fontWeight: '700',
    color: '#475569',
    textTransform: 'uppercase',
    marginTop: 8,
    marginBottom: 4,
  },
  callStatusRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 6,
    marginBottom: 6,
  },
  callStatusBtn: {
    paddingHorizontal: 8,
    paddingVertical: 5,
    borderRadius: Radius.sm,
    backgroundColor: '#f1f5f9',
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  callStatusActive: {
    backgroundColor: '#e0e7ff',
    borderColor: '#6366f1',
  },
  callStatusText: {
    fontSize: 10,
    fontWeight: '700',
    color: '#64748b',
  },
  callStatusTextActive: {
    color: '#4338ca',
  },
  modalInput: {
    borderWidth: 1,
    borderColor: '#e2e8f0',
    borderRadius: Radius.md,
    paddingHorizontal: 10,
    height: 40,
    fontSize: 13,
    backgroundColor: '#f8fafc',
    marginBottom: 8,
    color: '#0f172a',
  },
  modalActions: {
    flexDirection: 'row',
    justifyContent: 'flex-end',
    gap: 10,
    marginTop: 10,
  },
  cancelBtn: {
    paddingVertical: 8,
    paddingHorizontal: 12,
  },
  cancelBtnText: {
    color: '#64748b',
    fontWeight: '700',
  },
  saveBtn: {
    backgroundColor: Colors.primary,
    paddingVertical: 8,
    paddingHorizontal: 16,
    borderRadius: Radius.sm,
  },
  saveBtnText: {
    color: '#ffffff',
    fontWeight: '700',
  },
  empChoiceBtn: {
    padding: 10,
    borderRadius: Radius.sm,
    backgroundColor: '#f8fafc',
    borderWidth: 1,
    borderColor: '#e2e8f0',
    marginBottom: 6,
  },
  empChoiceActive: {
    backgroundColor: '#e0e7ff',
    borderColor: '#6366f1',
  },
  empChoiceText: {
    fontSize: 13,
    color: '#334155',
    fontWeight: '600',
  },
  empChoiceTextActive: {
    color: '#4338ca',
    fontWeight: '700',
  },
  transferSearchBox: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#f1f5f9',
    borderRadius: Radius.sm,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    paddingHorizontal: 10,
    paddingVertical: 7,
    marginBottom: 8,
  },
  transferSearchInput: {
    flex: 1,
    fontSize: 13,
    color: '#1e293b',
    padding: 0,
  },
  transferNoResults: {
    textAlign: 'center',
    color: '#94a3b8',
    fontSize: 13,
    paddingVertical: 12,
    fontStyle: 'italic',
  },
});
