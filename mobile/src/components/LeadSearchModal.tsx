import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  Modal,
  TouchableOpacity,
  TextInput,
  FlatList,
  ActivityIndicator,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { api } from '../services/api';
import { Colors, Radius, Spacing } from '../constants/theme';
import { Lead } from '../types/crm';

interface Props {
  visible: boolean;
  onClose: () => void;
  onSelect: (lead: Lead) => void;
}

export default function LeadSearchModal({ visible, onClose, onSelect }: Props) {
  const [leads, setLeads] = useState<Lead[]>([]);
  const [loading, setLoading] = useState(false);
  const [search, setSearch] = useState('');

  useEffect(() => {
    if (visible && leads.length === 0) {
      fetchLeads();
    }
  }, [visible]);

  const fetchLeads = async () => {
    setLoading(true);
    try {
      const res = await api.getLeads({ all: 1 });
      setLeads(Array.isArray(res) ? res : res?.leads || []);
    } catch (e) {
      console.warn(e);
    } finally {
      setLoading(false);
    }
  };

  const filteredLeads = leads.filter(
    l =>
      (l.name && l.name.toLowerCase().includes(search.toLowerCase())) ||
      (l.mobile && l.mobile.includes(search)) ||
      (l.company && l.company.toLowerCase().includes(search.toLowerCase()))
  );

  return (
    <Modal visible={visible} transparent animationType="slide" onRequestClose={onClose}>
      <View style={styles.overlay}>
        <View style={styles.card}>
          <View style={styles.header}>
            <Text style={styles.title}>Select Lead</Text>
            <TouchableOpacity onPress={onClose}>
              <Ionicons name="close" size={24} color={Colors.light.textSecondary} />
            </TouchableOpacity>
          </View>

          <View style={styles.searchBox}>
            <Ionicons name="search" size={18} color={Colors.light.textMuted} />
            <TextInput
              style={styles.searchInput}
              placeholder="Search by name, mobile, or company..."
              placeholderTextColor={Colors.light.textMuted}
              value={search}
              onChangeText={setSearch}
            />
            {search.length > 0 && (
              <TouchableOpacity onPress={() => setSearch('')}>
                <Ionicons name="close-circle" size={16} color={Colors.light.textMuted} />
              </TouchableOpacity>
            )}
          </View>

          {loading ? (
            <ActivityIndicator size="small" color={Colors.primary} style={{ marginTop: 20 }} />
          ) : (
            <FlatList
              data={filteredLeads}
              keyExtractor={item => String(item.id)}
              renderItem={({ item }) => (
                <TouchableOpacity
                  style={styles.item}
                  onPress={() => {
                    onSelect(item);
                    onClose();
                  }}>
                  <Text style={styles.name}>{item.name}</Text>
                  <Text style={styles.details}>
                    {item.mobile} {item.company ? `• ${item.company}` : ''}
                  </Text>
                </TouchableOpacity>
              )}
              ListEmptyComponent={
                <Text style={styles.emptyText}>No leads found matching "{search}"</Text>
              }
              style={styles.list}
            />
          )}
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.5)',
    justifyContent: 'flex-end',
  },
  card: {
    backgroundColor: Colors.light.card,
    borderTopLeftRadius: Radius.xl,
    borderTopRightRadius: Radius.xl,
    padding: Spacing.lg,
    maxHeight: '80%',
    minHeight: '50%',
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: Spacing.md,
  },
  title: {
    fontSize: 18,
    fontWeight: '700',
    color: Colors.light.text,
  },
  searchBox: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: Colors.light.backgroundElement,
    paddingHorizontal: Spacing.md,
    height: 44,
    borderRadius: Radius.md,
    marginBottom: Spacing.md,
    gap: 8,
  },
  searchInput: {
    flex: 1,
    fontSize: 14,
    color: Colors.light.text,
  },
  list: {
    flexGrow: 1,
  },
  item: {
    paddingVertical: Spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: Colors.light.backgroundSelected,
  },
  name: {
    fontSize: 15,
    fontWeight: '600',
    color: Colors.light.text,
    marginBottom: 2,
  },
  details: {
    fontSize: 13,
    color: Colors.light.textSecondary,
  },
  emptyText: {
    textAlign: 'center',
    marginTop: Spacing.lg,
    color: Colors.light.textMuted,
    fontStyle: 'italic',
  },
});
