import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  Alert,
  Modal,
  FlatList,
  ActivityIndicator,
  TextInput,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { useAuth } from '../../context/AuthContext';
import { api } from '../../services/api';
import { Colors, Spacing, Radius } from '../../constants/theme';
import { Customer, Employee } from '../../types/crm';
import { AppTopBar } from '../../components/AppTopBar';

export default function MoreScreen() {
  const router = useRouter();
  const {
    user,
    company,
    logout,
    isExecutive,
    isAdmin,
    isSuperAdmin,
    isImpersonating,
    impersonateCompany,
    revertImpersonation,
  } = useAuth();

  // Modal states for customers & employees
  const [customersModal, setCustomersModal] = useState(false);
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [loadingCustomers, setLoadingCustomers] = useState(false);

  const [employeesModal, setEmployeesModal] = useState(false);
  const [employees, setEmployees] = useState<Employee[]>([]);
  const [loadingEmployees, setLoadingEmployees] = useState(false);

  // Super Admin: Companies Modal states
  const [companiesModal, setCompaniesModal] = useState(false);
  const [companies, setCompanies] = useState<any[]>([]);
  const [loadingCompanies, setLoadingCompanies] = useState(false);
  const [companySearch, setCompanySearch] = useState('');
  const [switchingCompanyId, setSwitchingCompanyId] = useState<number | null>(null);

  const handleLogout = () => {
    Alert.alert('Sign Out', 'Are you sure you want to log out of your session?', [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Sign Out',
        style: 'destructive',
        onPress: async () => {
          await logout();
        },
      },
    ]);
  };

  const openCustomers = async () => {
    setCustomersModal(true);
    setLoadingCustomers(true);
    try {
      const res = await api.getCustomers();
      if (Array.isArray(res)) {
        setCustomers(res);
      }
    } catch (e) {
      console.warn('Failed to load customers', e);
    } finally {
      setLoadingCustomers(false);
    }
  };

  const openEmployees = async () => {
    setEmployeesModal(true);
    setLoadingEmployees(true);
    try {
      const res = await api.getEmployees();
      if (Array.isArray(res)) {
        setEmployees(res);
      }
    } catch (e) {
      console.warn('Failed to load employees', e);
    } finally {
      setLoadingEmployees(false);
    }
  };

  const openCompanies = async () => {
    setCompaniesModal(true);
    setLoadingCompanies(true);
    try {
      const res = await api.getCompanies();
      if (Array.isArray(res)) {
        setCompanies(res);
      }
    } catch (e: any) {
      Alert.alert('Error', e.message || 'Failed to fetch companies');
    } finally {
      setLoadingCompanies(false);
    }
  };

  const handleImpersonate = async (comp: any) => {
    if (comp.id === company?.id) {
      Alert.alert('Already Active', `You are currently logged into ${comp.name}.`);
      return;
    }

    Alert.alert(
      'Switch Company',
      `Login as Admin of "${comp.name}"? You will be able to manage this tenant's leads, tasks, invoices, and staff.`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Login As Company',
          onPress: async () => {
            setSwitchingCompanyId(comp.id);
            const res = await impersonateCompany(comp.id);
            setSwitchingCompanyId(null);
            if (res.success) {
              setCompaniesModal(false);
              Alert.alert('Switched Successfully', `You are now logged in as ${comp.name}.`);
            } else {
              Alert.alert('Failed', res.error || 'Could not login as company');
            }
          },
        },
      ]
    );
  };

  const handleExitImpersonation = () => {
    Alert.alert(
      'Exit Company Session',
      'Return to Super Admin command center?',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Return to Super Admin',
          onPress: async () => {
            await revertImpersonation();
            Alert.alert('Session Reverted', 'Returned to Super Admin account.');
          },
        },
      ]
    );
  };

  const filteredCompanies = companies.filter(c => {
    if (!companySearch.trim()) return true;
    const term = companySearch.toLowerCase();
    return (
      (c.name && c.name.toLowerCase().includes(term)) ||
      (c.subdomain && c.subdomain.toLowerCase().includes(term)) ||
      (c.plan && c.plan.toLowerCase().includes(term))
    );
  });

  return (
    <View style={styles.container}>
      <AppTopBar
        title="Account & System"
        subtitle={company?.name ? `🏢 ${company.name}` : 'Enterprise CRM Modules'}
        rightAction={{
          icon: 'settings-outline',
          onPress: () => router.push('/settings'),
        }}
      />
      <ScrollView contentContainerStyle={styles.scrollContent}>

        {/* Profile Card */}
        <View style={styles.profileCard}>
          <View style={[styles.avatar, isSuperAdmin && { backgroundColor: '#6366f1' }]}>
            <Text style={styles.avatarText}>{(user?.name || 'U')[0].toUpperCase()}</Text>
          </View>
          <View style={{ flex: 1 }}>
            <Text style={styles.userName}>{user?.name}</Text>
            <Text style={styles.userEmail}>{user?.email || user?.mobile}</Text>
            <View style={styles.badgeRow}>
              <View
                style={[
                  styles.roleBadge,
                  isSuperAdmin && { backgroundColor: '#ede9fe' },
                  isImpersonating && { backgroundColor: '#fee2e2' },
                ]}>
                <Text
                  style={[
                    styles.roleText,
                    isSuperAdmin && { color: '#6d28d9' },
                    isImpersonating && { color: '#b91c1c' },
                  ]}>
                  {isImpersonating ? 'IMPERSONATING' : (user?.role || 'Executive').toUpperCase()}
                </Text>
              </View>
              {company?.name ? (
                <Text style={styles.companyText} numberOfLines={1}>
                  🏢 {company.name}
                </Text>
              ) : null}
            </View>
          </View>
        </View>

        {/* Super Admin Control Section */}
        {(isSuperAdmin || isImpersonating) && (
          <>
            <Text style={styles.sectionHeader}>Super Admin Command Center</Text>
            <View style={styles.menuCard}>
              <TouchableOpacity style={styles.menuItem} onPress={openCompanies}>
                <View style={[styles.menuIconBox, { backgroundColor: '#fee2e2' }]}>
                  <Ionicons name="business" size={18} color="#dc2626" />
                </View>
                <View style={styles.menuTextBox}>
                  <Text style={styles.menuTitle}>Companies & Switch Tenant</Text>
                  <Text style={styles.menuSub}>
                    {companies.length > 0 ? `${companies.length} Registered Companies` : 'View all companies & login directly as any tenant'}
                  </Text>
                </View>
                <View style={styles.superBadge}>
                  <Text style={styles.superBadgeText}>SUPER</Text>
                </View>
                <Ionicons name="chevron-forward" size={18} color="#94a3b8" />
              </TouchableOpacity>

              {isImpersonating && (
                <>
                  <View style={styles.divider} />
                  <TouchableOpacity style={styles.menuItem} onPress={handleExitImpersonation}>
                    <View style={[styles.menuIconBox, { backgroundColor: '#fef2f2' }]}>
                      <Ionicons name="arrow-undo" size={18} color="#dc2626" />
                    </View>
                    <View style={styles.menuTextBox}>
                      <Text style={[styles.menuTitle, { color: '#dc2626' }]}>Exit Company Session</Text>
                      <Text style={styles.menuSub}>Return back to Super Admin command center</Text>
                    </View>
                    <Ionicons name="chevron-forward" size={18} color="#dc2626" />
                  </TouchableOpacity>
                </>
              )}
            </View>
          </>
        )}

        {/* Management Features Section - only for tenant users or when impersonating a company */}
        {(!isSuperAdmin || isImpersonating) && (
          <>
            <Text style={styles.sectionHeader}>Enterprise CRM Modules</Text>
            <View style={styles.menuCard}>
              <TouchableOpacity style={styles.menuItem} onPress={openCustomers}>
                <View style={[styles.menuIconBox, { backgroundColor: '#e0e7ff' }]}>
                  <Ionicons name="people" size={18} color="#4338ca" />
                </View>
                <View style={styles.menuTextBox}>
                  <Text style={styles.menuTitle}>Customers Directory</Text>
                  <Text style={styles.menuSub}>Customer profiles, ledger, and contact info</Text>
                </View>
                <Ionicons name="chevron-forward" size={18} color="#94a3b8" />
              </TouchableOpacity>

              <View style={styles.divider} />

              <TouchableOpacity style={styles.menuItem} onPress={openEmployees}>
                <View style={[styles.menuIconBox, { backgroundColor: '#fef3c7' }]}>
                  <Ionicons name="briefcase" size={18} color="#b45309" />
                </View>
                <View style={styles.menuTextBox}>
                  <Text style={styles.menuTitle}>Employees & Staff</Text>
                  <Text style={styles.menuSub}>Team roster, designations, and departments</Text>
                </View>
                <Ionicons name="chevron-forward" size={18} color="#94a3b8" />
              </TouchableOpacity>

              <View style={styles.divider} />

              <TouchableOpacity
                style={styles.menuItem}
                onPress={() => router.push('/attendance')}>
                <View style={[styles.menuIconBox, { backgroundColor: '#d1fae5' }]}>
                  <Ionicons name="time" size={18} color="#065f46" />
                </View>
                <View style={styles.menuTextBox}>
                  <Text style={styles.menuTitle}>Daily Attendance</Text>
                  <Text style={styles.menuSub}>Check in, check out, and attendance logs</Text>
                </View>
                <Ionicons name="chevron-forward" size={18} color="#94a3b8" />
              </TouchableOpacity>

              <View style={styles.divider} />

              <TouchableOpacity
                style={styles.menuItem}
                onPress={() => router.push('/commissions')}>
                <View style={[styles.menuIconBox, { backgroundColor: '#ede9fe' }]}>
                  <Ionicons name="cash" size={18} color="#5b21b6" />
                </View>
                <View style={styles.menuTextBox}>
                  <Text style={styles.menuTitle}>Commissions & Performance</Text>
                  <Text style={styles.menuSub}>Conversion tracking and incentives earned</Text>
                </View>
                <Ionicons name="chevron-forward" size={18} color="#94a3b8" />
              </TouchableOpacity>
            </View>
          </>
        )}

        {/* System Settings Section */}
        <Text style={styles.sectionHeader}>Preferences & Setup</Text>
        <View style={styles.menuCard}>
          <TouchableOpacity
            style={styles.menuItem}
            onPress={() => router.push('/settings')}>
            <View style={[styles.menuIconBox, { backgroundColor: '#f1f5f9' }]}>
              <Ionicons name="settings-outline" size={18} color="#334155" />
            </View>
            <View style={styles.menuTextBox}>
              <Text style={styles.menuTitle}>Settings & Backend Connection</Text>
              <Text style={styles.menuSub}>Switch API server, clear cache, and app details</Text>
            </View>
            <Ionicons name="chevron-forward" size={18} color="#94a3b8" />
          </TouchableOpacity>
        </View>

        {/* Logout Button */}
        <TouchableOpacity style={styles.logoutBtn} onPress={handleLogout}>
          <Ionicons name="log-out-outline" size={18} color="#ef4444" />
          <Text style={styles.logoutText}>Sign Out from Account</Text>
        </TouchableOpacity>

        <Text style={styles.versionText}>Aikaa CRM Mobile • Version 1.0.0 (Expo)</Text>
      </ScrollView>

      {/* Customers List Modal */}
      <Modal
        visible={customersModal}
        animationType="slide"
        onRequestClose={() => setCustomersModal(false)}>
        <SafeAreaView style={styles.modalFullContainer}>
          <View style={styles.modalBar}>
            <Text style={styles.modalBarTitle}>Customers Directory</Text>
            <TouchableOpacity onPress={() => setCustomersModal(false)}>
              <Ionicons name="close" size={24} color="#0f172a" />
            </TouchableOpacity>
          </View>

          {loadingCustomers ? (
            <View style={styles.centerBox}>
              <ActivityIndicator size="large" color={Colors.primary} />
            </View>
          ) : (
            <FlatList
              data={customers}
              keyExtractor={item => String(item.id)}
              contentContainerStyle={{ padding: Spacing.lg }}
              renderItem={({ item }) => (
                <View style={styles.customerCard}>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.custName}>{item.name}</Text>
                    <Text style={styles.custSub}>📞 {item.mobile}</Text>
                    {item.company_name ? (
                      <Text style={styles.custSub}>🏢 {item.company_name}</Text>
                    ) : null}
                    {item.email ? <Text style={styles.custSub}>✉️ {item.email}</Text> : null}
                  </View>
                </View>
              )}
              ListEmptyComponent={
                <View style={styles.centerBox}>
                  <Text style={styles.emptyTitle}>No Customers Found</Text>
                </View>
              }
            />
          )}
        </SafeAreaView>
      </Modal>

      {/* Employees List Modal */}
      <Modal
        visible={employeesModal}
        animationType="slide"
        onRequestClose={() => setEmployeesModal(false)}>
        <SafeAreaView style={styles.modalFullContainer}>
          <View style={styles.modalBar}>
            <Text style={styles.modalBarTitle}>Team Members & Staff</Text>
            <TouchableOpacity onPress={() => setEmployeesModal(false)}>
              <Ionicons name="close" size={24} color="#0f172a" />
            </TouchableOpacity>
          </View>

          {loadingEmployees ? (
            <View style={styles.centerBox}>
              <ActivityIndicator size="large" color={Colors.primary} />
            </View>
          ) : (
            <FlatList
              data={employees}
              keyExtractor={item => String(item.id)}
              contentContainerStyle={{ padding: Spacing.lg }}
              renderItem={({ item }) => (
                <View style={styles.customerCard}>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.custName}>{item.name}</Text>
                    <Text style={styles.custSub}>
                      💼 {item.designation || 'Staff'} {item.department ? `(${item.department})` : ''}
                    </Text>
                    <Text style={styles.custSub}>📞 {item.mobile}</Text>
                    {item.email ? <Text style={styles.custSub}>✉️ {item.email}</Text> : null}
                  </View>
                  <View style={styles.statusActivePill}>
                    <Text style={styles.statusActiveText}>{(item.status || 'Active').toUpperCase()}</Text>
                  </View>
                </View>
              )}
              ListEmptyComponent={
                <View style={styles.centerBox}>
                  <Text style={styles.emptyTitle}>No Employees Found</Text>
                </View>
              }
            />
          )}
        </SafeAreaView>
      </Modal>

      {/* Super Admin Companies Switcher Modal */}
      <Modal
        visible={companiesModal}
        animationType="slide"
        onRequestClose={() => setCompaniesModal(false)}>
        <SafeAreaView style={styles.modalFullContainer} edges={['top', 'left', 'right']}>
          <View style={styles.modalBar}>
            <View style={{ flex: 1 }}>
              <Text style={styles.modalBarTitle}>🏢 Tenant Companies</Text>
              <Text style={styles.custSub}>Login directly into any company account</Text>
            </View>
            <TouchableOpacity onPress={() => setCompaniesModal(false)} style={{ padding: 4 }}>
              <Ionicons name="close" size={24} color="#0f172a" />
            </TouchableOpacity>
          </View>

          {/* Search Box */}
          <View style={styles.searchBoxWrapper}>
            <Ionicons name="search" size={18} color="#94a3b8" />
            <TextInput
              style={styles.searchInput}
              placeholder="Search companies by name or subdomain..."
              placeholderTextColor="#94a3b8"
              value={companySearch}
              onChangeText={setCompanySearch}
            />
            {companySearch ? (
              <TouchableOpacity onPress={() => setCompanySearch('')}>
                <Ionicons name="close-circle" size={18} color="#94a3b8" />
              </TouchableOpacity>
            ) : null}
          </View>

          {loadingCompanies ? (
            <View style={styles.centerBox}>
              <ActivityIndicator size="large" color="#dc2626" />
              <Text style={[styles.custSub, { marginTop: 12 }]}>Loading registered companies...</Text>
            </View>
          ) : (
            <FlatList
              data={filteredCompanies}
              keyExtractor={item => String(item.id)}
              contentContainerStyle={{ padding: Spacing.lg, paddingBottom: 40 }}
              renderItem={({ item }) => {
                const isCurrent = company?.id === item.id;
                const isSwitching = switchingCompanyId === item.id;
                const statusColor = (item.status === 'active' || !item.status) ? '#16a34a' : '#dc2626';
                return (
                  <View style={[styles.companyCard, isCurrent && styles.companyCardActive]}>
                    <View style={styles.companyCardTop}>
                      <View style={[styles.companyIconBox, isCurrent && { backgroundColor: '#dcfce7' }]}>
                        <Ionicons
                          name="business"
                          size={22}
                          color={isCurrent ? '#16a34a' : '#6366f1'}
                        />
                      </View>
                      <View style={{ flex: 1 }}>
                        <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                          <Text style={styles.companyNameText} numberOfLines={1}>{item.name}</Text>
                          {isCurrent && (
                            <View style={styles.activePill}>
                              <Text style={styles.activePillText}>CURRENT</Text>
                            </View>
                          )}
                        </View>
                        <Text style={styles.companySubText}>
                          {item.subdomain ? `${item.subdomain}.aikocrm.com` : `Tenant ID: #${item.id}`}
                        </Text>
                      </View>
                    </View>

                    {/* Company Details Row */}
                    <View style={styles.companyMetaRow}>
                      <View style={styles.companyMetaItem}>
                        <Text style={styles.companyMetaLabel}>PLAN</Text>
                        <Text style={[styles.companyMetaValue, { color: '#6366f1' }]}>
                          {(item.plan || 'TRIAL').toUpperCase()}
                        </Text>
                      </View>
                      <View style={styles.companyMetaItem}>
                        <Text style={styles.companyMetaLabel}>STATUS</Text>
                        <Text style={[styles.companyMetaValue, { color: statusColor }]}>
                          {(item.status || 'ACTIVE').toUpperCase()}
                        </Text>
                      </View>
                      <View style={styles.companyMetaItem}>
                        <Text style={styles.companyMetaLabel}>LEADS</Text>
                        <Text style={styles.companyMetaValue}>
                          {item.leads_count !== undefined ? item.leads_count : '—'}
                        </Text>
                      </View>
                    </View>

                    {/* Switch Button */}
                    <TouchableOpacity
                      style={[
                        styles.companyActionBtn,
                        isCurrent && styles.companyActionBtnCurrent,
                        isSwitching && { opacity: 0.7 },
                      ]}
                      onPress={() => handleImpersonate(item)}
                      disabled={isCurrent || isSwitching}>
                      {isSwitching ? (
                        <ActivityIndicator size="small" color="#ffffff" />
                      ) : (
                        <>
                          <Ionicons
                            name={isCurrent ? 'checkmark-circle' : 'log-in-outline'}
                            size={16}
                            color={isCurrent ? '#16a34a' : '#ffffff'}
                          />
                          <Text
                            style={[
                              styles.companyActionBtnText,
                              isCurrent && styles.companyActionBtnTextCurrent,
                            ]}>
                            {isCurrent ? 'Active Tenant Session' : 'Login As This Company'}
                          </Text>
                        </>
                      )}
                    </TouchableOpacity>
                  </View>
                );
              }}
              ListEmptyComponent={
                <View style={styles.centerBox}>
                  <Ionicons name="business-outline" size={48} color="#cbd5e1" />
                  <Text style={styles.emptyTitle}>No Companies Found</Text>
                  <Text style={styles.emptySub}>
                    {companySearch ? 'No company matches your search filter.' : 'No companies registered yet.'}
                  </Text>
                </View>
              }
            />
          )}
        </SafeAreaView>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f8fafc',
  },
  scrollContent: {
    padding: Spacing.lg,
    paddingBottom: Spacing.xxxl,
  },
  profileCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#ffffff',
    borderRadius: Radius.lg,
    padding: Spacing.lg,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    gap: 14,
    marginBottom: Spacing.xl,
    shadowColor: '#0f172a',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.04,
    shadowRadius: 5,
    elevation: 2,
  },
  avatar: {
    width: 54,
    height: 54,
    borderRadius: 27,
    backgroundColor: Colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: {
    fontSize: 22,
    fontWeight: '800',
    color: '#ffffff',
  },
  userName: {
    fontSize: 17,
    fontWeight: '800',
    color: '#0f172a',
  },
  userEmail: {
    fontSize: 12,
    color: '#64748b',
    marginTop: 2,
  },
  badgeRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginTop: 6,
  },
  roleBadge: {
    backgroundColor: '#e0e7ff',
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: Radius.full,
  },
  roleText: {
    fontSize: 9,
    fontWeight: '800',
    color: '#4338ca',
  },
  companyText: {
    fontSize: 12,
    color: '#475569',
    fontWeight: '600',
    flex: 1,
  },
  sectionHeader: {
    fontSize: 12,
    fontWeight: '700',
    color: '#64748b',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
    marginBottom: 8,
    marginLeft: 4,
  },
  menuCard: {
    backgroundColor: '#ffffff',
    borderRadius: Radius.lg,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    marginBottom: Spacing.xl,
    overflow: 'hidden',
  },
  menuItem: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: Spacing.md,
    gap: 12,
  },
  menuIconBox: {
    width: 38,
    height: 38,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  menuTextBox: {
    flex: 1,
  },
  menuTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: '#0f172a',
  },
  menuSub: {
    fontSize: 11,
    color: '#64748b',
    marginTop: 2,
  },
  divider: {
    height: 1,
    backgroundColor: '#f1f5f9',
    marginLeft: 62,
  },
  logoutBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    backgroundColor: '#fee2e2',
    paddingVertical: 14,
    borderRadius: Radius.md,
    marginTop: Spacing.sm,
  },
  logoutText: {
    color: '#ef4444',
    fontSize: 14,
    fontWeight: '700',
  },
  versionText: {
    textAlign: 'center',
    fontSize: 11,
    color: '#94a3b8',
    marginTop: Spacing.xl,
  },
  modalFullContainer: {
    flex: 1,
    backgroundColor: '#f8fafc',
  },
  modalBar: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: Spacing.lg,
    backgroundColor: '#ffffff',
    borderBottomWidth: 1,
    borderBottomColor: '#e2e8f0',
  },
  modalBarTitle: {
    fontSize: 18,
    fontWeight: '800',
    color: '#0f172a',
  },
  customerCard: {
    flexDirection: 'row',
    backgroundColor: '#ffffff',
    borderRadius: Radius.md,
    padding: Spacing.md,
    marginBottom: 8,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    alignItems: 'center',
  },
  custName: {
    fontSize: 15,
    fontWeight: '700',
    color: '#0f172a',
  },
  custSub: {
    fontSize: 12,
    color: '#64748b',
    marginTop: 2,
  },
  statusActivePill: {
    backgroundColor: '#d1fae5',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: Radius.sm,
  },
  statusActiveText: {
    color: '#065f46',
    fontSize: 10,
    fontWeight: '800',
  },
  centerBox: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingTop: 80,
  },
  emptyTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#64748b',
  },
  impersonateBanner: {
    backgroundColor: '#dc2626',
    borderRadius: Radius.lg,
    padding: Spacing.md,
    marginBottom: Spacing.lg,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
    shadowColor: '#dc2626',
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.25,
    shadowRadius: 6,
    elevation: 4,
  },
  impersonateRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  impersonateTitle: {
    color: '#ffffff',
    fontWeight: '800',
    fontSize: 12,
    letterSpacing: 0.5,
  },
  impersonateDesc: {
    color: '#fef2f2',
    fontSize: 11,
    marginTop: 2,
  },
  revertBtn: {
    backgroundColor: '#ffffff',
    paddingVertical: 6,
    paddingHorizontal: 12,
    borderRadius: Radius.full,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  revertBtnText: {
    color: '#b91c1c',
    fontSize: 11,
    fontWeight: '800',
  },
  superBadge: {
    backgroundColor: '#fee2e2',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: Radius.sm,
    marginRight: 6,
  },
  superBadgeText: {
    color: '#dc2626',
    fontSize: 10,
    fontWeight: '800',
  },
  searchBoxWrapper: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#ffffff',
    borderWidth: 1,
    borderColor: '#e2e8f0',
    borderRadius: Radius.md,
    marginHorizontal: Spacing.lg,
    marginTop: Spacing.md,
    paddingHorizontal: 12,
    height: 44,
    gap: 8,
  },
  searchInput: {
    flex: 1,
    fontSize: 13,
    color: '#0f172a',
  },
  companyCard: {
    backgroundColor: '#ffffff',
    borderRadius: Radius.lg,
    padding: Spacing.lg,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    shadowColor: '#0f172a',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.04,
    shadowRadius: 4,
    elevation: 2,
  },
  companyCardActive: {
    borderColor: '#22c55e',
    backgroundColor: '#f0fdf4',
  },
  companyCardTop: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  companyIconBox: {
    width: 44,
    height: 44,
    borderRadius: 12,
    backgroundColor: '#e0e7ff',
    alignItems: 'center',
    justifyContent: 'center',
  },
  companyNameText: {
    fontSize: 16,
    fontWeight: '800',
    color: '#0f172a',
    flex: 1,
  },
  companySubText: {
    fontSize: 12,
    color: '#64748b',
    marginTop: 2,
  },
  activePill: {
    backgroundColor: '#dcfce7',
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: Radius.full,
  },
  activePillText: {
    color: '#15803d',
    fontSize: 9,
    fontWeight: '800',
  },
  companyMetaRow: {
    flexDirection: 'row',
    backgroundColor: '#f8fafc',
    borderRadius: Radius.md,
    padding: 8,
    marginTop: 12,
    marginBottom: 12,
    justifyContent: 'space-around',
    borderWidth: 1,
    borderColor: '#f1f5f9',
  },
  companyMetaItem: {
    alignItems: 'center',
  },
  companyMetaLabel: {
    fontSize: 10,
    fontWeight: '700',
    color: '#94a3b8',
    marginBottom: 2,
  },
  companyMetaValue: {
    fontSize: 12,
    fontWeight: '800',
    color: '#0f172a',
  },
  companyActionBtn: {
    backgroundColor: '#4f46e5',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 10,
    borderRadius: Radius.md,
    gap: 6,
  },
  companyActionBtnCurrent: {
    backgroundColor: '#ffffff',
    borderWidth: 1,
    borderColor: '#86efac',
  },
  companyActionBtnText: {
    color: '#ffffff',
    fontSize: 13,
    fontWeight: '700',
  },
  companyActionBtnTextCurrent: {
    color: '#16a34a',
  },
  emptySub: {
    fontSize: 12,
    color: '#94a3b8',
    marginTop: 4,
    textAlign: 'center',
  },
});
