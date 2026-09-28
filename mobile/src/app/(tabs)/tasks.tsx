import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  TouchableOpacity,
  RefreshControl,
  ActivityIndicator,
  Modal,
  TextInput,
  Alert,
  Linking,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { api } from '../../services/api';
import { Colors, Spacing, Radius } from '../../constants/theme';
import { Task } from '../../types/crm';
import { AppTopBar } from '../../components/AppTopBar';

export default function TasksScreen() {
  const [tasks, setTasks] = useState<Task[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [statusFilter, setStatusFilter] = useState<'all' | 'not_started' | 'work_in_progress' | 'work_pending' | 'work_done'>('all');
  
  // Status update modal state
  const [selectedTask, setSelectedTask] = useState<Task | null>(null);
  const [updateModalVisible, setUpdateModalVisible] = useState(false);
  const [newStatus, setNewStatus] = useState<string>('work_in_progress');
  const [pendingRemark, setPendingRemark] = useState('');
  const [deliveryDate, setDeliveryDate] = useState('');
  const [saving, setSaving] = useState(false);

  const fetchTasks = useCallback(async () => {
    try {
      const res = await api.getTasks();
      if (Array.isArray(res)) {
        setTasks(res);
      }
    } catch (e) {
      console.warn('Failed to fetch tasks', e);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    fetchTasks();
  }, [fetchTasks]);

  const onRefresh = () => {
    setRefreshing(true);
    fetchTasks();
  };

  const openStatusModal = (task: Task) => {
    setSelectedTask(task);
    setNewStatus(task.task_status || 'not_started');
    setDeliveryDate(task.expected_delivery_date || '');
    setPendingRemark('');
    setUpdateModalVisible(true);
  };

  const handleUpdateStatus = async () => {
    if (!selectedTask) return;

    if (newStatus === 'work_pending' && !pendingRemark.trim()) {
      Alert.alert('Remark Required', 'Please enter a reason why the task is pending / delayed.');
      return;
    }

    setSaving(true);
    try {
      await api.updateTaskStatus({
        id: selectedTask.id,
        status: newStatus,
        remark: pendingRemark.trim(),
        expected_delivery_date: deliveryDate.trim() || undefined,
      });
      setUpdateModalVisible(false);
      fetchTasks();
    } catch (err: any) {
      Alert.alert('Update Failed', err.message || 'Could not update task status.');
    } finally {
      setSaving(false);
    }
  };

  const filteredTasks = tasks.filter(task => {
    if (statusFilter === 'all') return true;
    return task.task_status === statusFilter;
  });

  const todayStr = new Date().toISOString().split('T')[0];

  const renderTask = ({ item }: { item: Task }) => {
    const isOverdue = item.expected_delivery_date && item.expected_delivery_date < todayStr && item.task_status !== 'work_done';
    const isDone = item.task_status === 'work_done';

    const statusBadge = {
      not_started: { label: 'NOT STARTED', bg: '#e2e8f0', text: '#475569' },
      work_in_progress: { label: 'IN PROGRESS', bg: '#e0e7ff', text: '#4338ca' },
      work_pending: { label: 'PENDING / DELAY', bg: '#fee2e2', text: '#b91c1c' },
      work_done: { label: 'COMPLETED', bg: '#d1fae5', text: '#065f46' },
    }[item.task_status || 'not_started'] || { label: 'NOT STARTED', bg: '#e2e8f0', text: '#475569' };

    return (
      <View style={styles.card}>
        <View style={styles.cardTop}>
          <View style={{ flex: 1 }}>
            <Text style={styles.clientName}>{item.name || 'Client'}</Text>
            <Text style={styles.mobileText}>📞 {item.mobile}</Text>
          </View>
          <View style={[styles.statusBadge, { backgroundColor: statusBadge.bg }]}>
            <Text style={[styles.statusBadgeText, { color: statusBadge.text }]}>
              {statusBadge.label}
            </Text>
          </View>
        </View>

        <View style={styles.serviceBox}>
          <Text style={styles.serviceLabel}>Service / Deliverable:</Text>
          <Text style={styles.serviceValue}>
            {item.requirement || item.requirement_names || 'General Service'}
          </Text>
        </View>

        <View style={styles.dateStaffRow}>
          <View style={styles.dateCol}>
            <Text style={styles.infoLabel}>Expected Delivery:</Text>
            <Text style={[styles.infoValue, isOverdue && styles.overdueText]}>
              📅 {item.expected_delivery_date || 'No deadline'}
              {isOverdue && ' (OVERDUE)'}
            </Text>
          </View>
          <View style={styles.staffCol}>
            <Text style={styles.infoLabel}>Assigned Staff:</Text>
            <Text style={styles.infoValue}>
              👤 {item.assigned_employee_name || 'Unassigned'}
            </Text>
          </View>
        </View>

        {/* Action Row */}
        <View style={styles.cardActions}>
          <View style={styles.contactActions}>
            <TouchableOpacity
              style={styles.actionIconBtn}
              onPress={() => Linking.openURL(`tel:${item.mobile}`)}>
              <Ionicons name="call" size={15} color="#4338ca" />
            </TouchableOpacity>
            <TouchableOpacity
              style={styles.actionIconBtn}
              onPress={() => {
                const clean = item.mobile.replace(/[^0-9]/g, '');
                const phone = clean.length === 10 ? `91${clean}` : clean;
                Linking.openURL(`whatsapp://send?phone=${phone}`);
              }}>
              <Ionicons name="logo-whatsapp" size={15} color="#059669" />
            </TouchableOpacity>
          </View>

          <TouchableOpacity
            style={styles.updateStatusBtn}
            onPress={() => openStatusModal(item)}>
            <Ionicons name="create-outline" size={15} color="#ffffff" />
            <Text style={styles.updateStatusBtnText}>Update Status</Text>
          </TouchableOpacity>
        </View>
      </View>
    );
  };

  return (
    <View style={styles.container}>
      <AppTopBar
        title="Task Operations"
        subtitle={`${tasks.length} Total Assigned Service Tasks`}
      />
      {/* Header & Filter Tabs */}
      <View style={styles.topHeader}>
        <View style={styles.tabRow}>
          <TouchableOpacity
            style={[styles.tabBtn, statusFilter === 'all' && styles.tabBtnActive]}
            onPress={() => setStatusFilter('all')}>
            <Text style={[styles.tabBtnText, statusFilter === 'all' && styles.tabBtnTextActive]}>
              All ({tasks.length})
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.tabBtn, statusFilter === 'work_in_progress' && styles.tabBtnActive]}
            onPress={() => setStatusFilter('work_in_progress')}>
            <Text style={[styles.tabBtnText, statusFilter === 'work_in_progress' && styles.tabBtnTextActive]}>
              WIP
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.tabBtn, statusFilter === 'work_pending' && styles.tabBtnActive]}
            onPress={() => setStatusFilter('work_pending')}>
            <Text style={[styles.tabBtnText, statusFilter === 'work_pending' && styles.tabBtnTextActive]}>
              Delayed
            </Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.tabBtn, statusFilter === 'work_done' && styles.tabBtnActive]}
            onPress={() => setStatusFilter('work_done')}>
            <Text style={[styles.tabBtnText, statusFilter === 'work_done' && styles.tabBtnTextActive]}>
              Completed
            </Text>
          </TouchableOpacity>
        </View>
      </View>

      {loading ? (
        <View style={styles.centerBox}>
          <ActivityIndicator size="large" color={Colors.primary} />
          <Text style={styles.loadingText}>Loading assigned tasks...</Text>
        </View>
      ) : (
        <FlatList
          data={filteredTasks}
          keyExtractor={item => String(item.id)}
          renderItem={renderTask}
          contentContainerStyle={styles.listContent}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[Colors.primary]} />
          }
          ListEmptyComponent={
            <View style={styles.centerBox}>
              <Ionicons name="checkmark-done-circle-outline" size={48} color="#cbd5e1" />
              <Text style={styles.emptyTitle}>No Tasks In This Category</Text>
              <Text style={styles.emptySub}>All deliverables are up to date</Text>
            </View>
          }
        />
      )}

      {/* Status Update Modal */}
      <Modal
        visible={updateModalVisible}
        transparent
        animationType="slide"
        onRequestClose={() => setUpdateModalVisible(false)}>
        <View style={styles.modalOverlay}>
          <View style={styles.modalCard}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Update Task Status</Text>
              <TouchableOpacity onPress={() => setUpdateModalVisible(false)}>
                <Ionicons name="close" size={20} color="#94a3b8" />
              </TouchableOpacity>
            </View>

            <Text style={styles.modalClientName}>{selectedTask?.name}</Text>
            <Text style={styles.modalServiceText}>
              Service: {selectedTask?.requirement || selectedTask?.requirement_names}
            </Text>

            {/* Select Status */}
            <Text style={styles.inputLabel}>New Status</Text>
            <View style={styles.statusOptionsRow}>
              {[
                { key: 'not_started', label: 'Not Started' },
                { key: 'work_in_progress', label: 'In Progress' },
                { key: 'work_pending', label: 'Delayed' },
                { key: 'work_done', label: 'Completed' },
              ].map(st => (
                <TouchableOpacity
                  key={st.key}
                  style={[
                    styles.statusChoiceBtn,
                    newStatus === st.key && styles.statusChoiceActive,
                  ]}
                  onPress={() => setNewStatus(st.key)}>
                  <Text
                    style={[
                      styles.statusChoiceText,
                      newStatus === st.key && styles.statusChoiceTextActive,
                    ]}>
                    {st.label}
                  </Text>
                </TouchableOpacity>
              ))}
            </View>

            {/* Delivery Date */}
            <Text style={styles.inputLabel}>Expected Delivery Date (YYYY-MM-DD)</Text>
            <TextInput
              style={styles.modalInput}
              value={deliveryDate}
              onChangeText={setDeliveryDate}
              placeholder="e.g. 2026-10-15"
            />

            {/* If work_pending, require reason */}
            {newStatus === 'work_pending' && (
              <>
                <Text style={styles.inputLabel}>Pending / Delay Reason (Required)</Text>
                <TextInput
                  style={[styles.modalInput, { height: 60, textAlignVertical: 'top' }]}
                  value={pendingRemark}
                  onChangeText={setPendingRemark}
                  placeholder="Explain why this task is delayed..."
                  multiline
                />
              </>
            )}

            <View style={styles.modalActions}>
              <TouchableOpacity
                style={styles.cancelBtn}
                onPress={() => setUpdateModalVisible(false)}>
                <Text style={styles.cancelBtnText}>Cancel</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.saveBtn, saving && { opacity: 0.7 }]}
                disabled={saving}
                onPress={handleUpdateStatus}>
                {saving ? (
                  <ActivityIndicator size="small" color="#ffffff" />
                ) : (
                  <Text style={styles.saveBtnText}>Save Status</Text>
                )}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f8fafc',
  },
  topHeader: {
    backgroundColor: '#ffffff',
    paddingHorizontal: Spacing.lg,
    paddingTop: Spacing.md,
    paddingBottom: Spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: '#e2e8f0',
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: '800',
    color: '#0f172a',
  },
  headerSub: {
    fontSize: 12,
    color: '#64748b',
    marginTop: 2,
    marginBottom: Spacing.md,
  },
  tabRow: {
    flexDirection: 'row',
    gap: 6,
  },
  tabBtn: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: Radius.full,
    backgroundColor: '#f1f5f9',
  },
  tabBtnActive: {
    backgroundColor: Colors.primary,
  },
  tabBtnText: {
    fontSize: 12,
    fontWeight: '700',
    color: '#64748b',
  },
  tabBtnTextActive: {
    color: '#ffffff',
  },
  listContent: {
    padding: Spacing.lg,
    paddingBottom: 40,
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
  cardTop: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 8,
  },
  clientName: {
    fontSize: 15,
    fontWeight: '800',
    color: '#0f172a',
  },
  mobileText: {
    fontSize: 13,
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
  serviceBox: {
    backgroundColor: '#f8fafc',
    padding: 10,
    borderRadius: Radius.md,
    marginVertical: 6,
  },
  serviceLabel: {
    fontSize: 10,
    fontWeight: '700',
    color: '#94a3b8',
    textTransform: 'uppercase',
  },
  serviceValue: {
    fontSize: 13,
    fontWeight: '600',
    color: '#334155',
    marginTop: 2,
  },
  dateStaffRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginVertical: 4,
  },
  dateCol: {
    flex: 1,
  },
  staffCol: {
    flex: 1,
    alignItems: 'flex-end',
  },
  infoLabel: {
    fontSize: 10,
    fontWeight: '700',
    color: '#94a3b8',
    textTransform: 'uppercase',
  },
  infoValue: {
    fontSize: 12,
    fontWeight: '600',
    color: '#475569',
    marginTop: 2,
  },
  overdueText: {
    color: '#ef4444',
    fontWeight: '800',
  },
  cardActions: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    borderTopWidth: 1,
    borderTopColor: '#f1f5f9',
    paddingTop: 10,
    marginTop: 8,
  },
  contactActions: {
    flexDirection: 'row',
    gap: 8,
  },
  actionIconBtn: {
    width: 32,
    height: 32,
    borderRadius: 8,
    backgroundColor: '#f1f5f9',
    alignItems: 'center',
    justifyContent: 'center',
  },
  updateStatusBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: Colors.primary,
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: Radius.md,
  },
  updateStatusBtnText: {
    color: '#ffffff',
    fontSize: 12,
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
    marginBottom: 6,
  },
  modalTitle: {
    fontSize: 17,
    fontWeight: '800',
    color: '#0f172a',
  },
  modalClientName: {
    fontSize: 14,
    fontWeight: '700',
    color: '#4f46e5',
  },
  modalServiceText: {
    fontSize: 12,
    color: '#64748b',
    marginBottom: 12,
  },
  inputLabel: {
    fontSize: 11,
    fontWeight: '700',
    color: '#475569',
    textTransform: 'uppercase',
    marginTop: 8,
    marginBottom: 4,
  },
  statusOptionsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 6,
    marginBottom: 8,
  },
  statusChoiceBtn: {
    paddingHorizontal: 12,
    paddingVertical: 7,
    borderRadius: Radius.sm,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    backgroundColor: '#f8fafc',
  },
  statusChoiceActive: {
    backgroundColor: '#e0e7ff',
    borderColor: '#6366f1',
  },
  statusChoiceText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#64748b',
  },
  statusChoiceTextActive: {
    color: '#4338ca',
    fontWeight: '700',
  },
  modalInput: {
    borderWidth: 1,
    borderColor: '#e2e8f0',
    borderRadius: Radius.md,
    paddingHorizontal: 12,
    height: 42,
    fontSize: 13,
    backgroundColor: '#f8fafc',
    marginBottom: 10,
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
    paddingHorizontal: 14,
  },
  cancelBtnText: {
    color: '#64748b',
    fontWeight: '700',
  },
  saveBtn: {
    backgroundColor: Colors.primary,
    paddingVertical: 8,
    paddingHorizontal: 18,
    borderRadius: Radius.sm,
  },
  saveBtnText: {
    color: '#ffffff',
    fontWeight: '700',
  },
});
