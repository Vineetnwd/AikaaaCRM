import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  RefreshControl,
  ActivityIndicator,
  TextInput,
  Alert,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { useAuth } from '../../context/AuthContext';
import { api } from '../../services/api';
import { Colors, Spacing, Radius } from '../../constants/theme';
import { Lead, Task, Invoice } from '../../types/crm';
import { AppTopBar } from '../../components/AppTopBar';

export default function DashboardScreen() {
  const {
    user,
    company,
    isExecutive,
    isSuperAdmin,
    isImpersonating,
    revertImpersonation,
    impersonateCompany,
  } = useAuth();
  const router = useRouter();

  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [leads, setLeads] = useState<Lead[]>([]);
  const [tasks, setTasks] = useState<Task[]>([]);
  const [invoices, setInvoices] = useState<Invoice[]>([]);

  // Super Admin specific state
  const [companies, setCompanies] = useState<any[]>([]);
  const [companySearch, setCompanySearch] = useState('');
  const [switchingCompanyId, setSwitchingCompanyId] = useState<number | null>(null);

  const loadData = useCallback(async (pullToRefresh = false) => {
    // Show full-page spinner on mode switch; only pull indicator on pull-to-refresh
    if (!pullToRefresh) {
      setLoading(true);
    }
    // Clear stale data so we never show wrong mode's data
    setLeads([]);
    setTasks([]);
    setInvoices([]);
    setCompanies([]);
    try {
      if (isSuperAdmin && !isImpersonating) {
        const compRes = await api.getCompanies();
        if (Array.isArray(compRes)) {
          setCompanies(compRes);
        }
      } else {
        const [leadsRes, tasksRes, invoicesRes] = await Promise.allSettled([
          api.getLeads({ all: 1 }),
          api.getTasks(),
          api.getInvoices(),
        ]);

        if (leadsRes.status === 'fulfilled' && Array.isArray(leadsRes.value)) {
          setLeads(leadsRes.value);
        }
        if (tasksRes.status === 'fulfilled' && Array.isArray(tasksRes.value)) {
          setTasks(tasksRes.value);
        }
        if (invoicesRes.status === 'fulfilled') {
          const invList = Array.isArray(invoicesRes.value)
            ? invoicesRes.value
            : invoicesRes.value?.invoices || [];
          setInvoices(invList);
        }
      }
    } catch (e) {
      console.warn('Dashboard fetch error', e);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [isSuperAdmin, isImpersonating]);

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await loadData(true);
  }, [loadData]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleLoginAs = async (comp: any) => {
    setSwitchingCompanyId(comp.id);
    try {
      const res = await impersonateCompany(comp.id);
      if (!res.success) {
        Alert.alert('Login Failed', res.error || 'Failed to login as company');
      }
      // On success: isImpersonating becomes true → loadData auto-fires via useEffect → tenant data loads
    } catch (err: any) {
      Alert.alert('Error', err.message || 'Failed to switch company session');
    } finally {
      setSwitchingCompanyId(null);
    }
  };

  // If Super Admin (not impersonating), ONLY show companies and their activity counts & login as option
  if (isSuperAdmin && !isImpersonating) {
    const filteredCompanies = companies.filter(c => {
      if (!companySearch.trim()) return true;
      const term = companySearch.toLowerCase();
      return (
        (c.name && c.name.toLowerCase().includes(term)) ||
        (c.subdomain && c.subdomain.toLowerCase().includes(term)) ||
        (c.plan && c.plan.toLowerCase().includes(term))
      );
    });

    const totalCompanies = companies.length;
    const activeCompanies = companies.filter(c => (c.status || '').toLowerCase() === 'active').length;
    const totalTodayActivity = companies.reduce((acc, c) => acc + (c.today_activities || 0), 0);
    const totalMonthActivity = companies.reduce((acc, c) => acc + (c.month_activities || 0), 0);

    return (
      <View style={styles.container}>
        <AppTopBar
          title="Companies Directory"
          subtitle={`${totalCompanies} Registered Enterprise Tenants`}
        />
        <ScrollView
          contentContainerStyle={styles.scrollContent}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={onRefresh}
              colors={[Colors.primary]}
            />
          }>
          {/* Super Admin Overview Card */}
          <View style={styles.saOverviewCard}>
            <View style={styles.saHeaderRow}>
              <View style={styles.saBadge}>
                <Ionicons name="shield-checkmark" size={14} color="#7c3aed" />
                <Text style={styles.saBadgeText}>SUPER ADMIN COMMAND</Text>
              </View>
              <Text style={styles.saMetaText}>{totalCompanies} Tenants</Text>
            </View>

            <Text style={styles.saTitle}>Enterprise CRM Network</Text>
            <Text style={styles.saSub}>
              Monitor daily and monthly tenant activity across all client instances.
            </Text>

            <View style={styles.saMetricsRow}>
              <View style={styles.saMetricBox}>
                <Text style={styles.saMetricNum}>{totalTodayActivity}</Text>
                <Text style={styles.saMetricLabel}>Today's Actions</Text>
              </View>
              <View style={styles.saMetricBox}>
                <Text style={styles.saMetricNum}>{totalMonthActivity}</Text>
                <Text style={styles.saMetricLabel}>Month Actions</Text>
              </View>
              <View style={styles.saMetricBox}>
                <Text style={[styles.saMetricNum, { color: '#16a34a' }]}>{activeCompanies}</Text>
                <Text style={styles.saMetricLabel}>Active Clients</Text>
              </View>
            </View>
          </View>

          {/* Search Box */}
          <View style={styles.searchContainer}>
            <Ionicons name="search" size={18} color="#64748b" />
            <TextInput
              style={styles.searchInput}
              placeholder="Search companies by name or subdomain..."
              placeholderTextColor="#94a3b8"
              value={companySearch}
              onChangeText={setCompanySearch}
              autoCapitalize="none"
              autoCorrect={false}
            />
            {companySearch.length > 0 && (
              <TouchableOpacity onPress={() => setCompanySearch('')} style={{ padding: 4 }}>
                <Ionicons name="close-circle" size={16} color="#94a3b8" />
              </TouchableOpacity>
            )}
          </View>

          {/* Companies List */}
          {loading ? (
            <View style={{ paddingVertical: 40, alignItems: 'center' }}>
              <ActivityIndicator size="large" color={Colors.primary} />
              <Text style={{ marginTop: 12, color: '#64748b', fontSize: 13, fontWeight: '600' }}>
                Loading tenants and activity counts...
              </Text>
            </View>
          ) : filteredCompanies.length === 0 ? (
            <View style={styles.emptyContainer}>
              <Ionicons name="business-outline" size={48} color="#94a3b8" />
              <Text style={styles.emptyTitle}>No Companies Found</Text>
              <Text style={styles.emptySub}>
                {companySearch ? 'No company matches your search filter.' : 'No companies registered in system.'}
              </Text>
            </View>
          ) : (
            filteredCompanies.map(comp => {
              const isSwitching = switchingCompanyId === comp.id;
              const isPro = (comp.plan || '').toLowerCase() === 'pro';
              const isBasic = (comp.plan || '').toLowerCase() === 'basic';
              const isActive = (comp.status || '').toLowerCase() === 'active';

              return (
                <View key={comp.id} style={styles.compCard}>
                  {/* Header Row */}
                  <View style={styles.compHeader}>
                    <View style={styles.compIconBox}>
                      <Ionicons name="business" size={20} color="#4f46e5" />
                    </View>
                    <View style={{ flex: 1 }}>
                      <View style={styles.compTitleRow}>
                        <Text style={styles.compNameText} numberOfLines={1}>
                          {comp.name}
                        </Text>
                        <View
                          style={[
                            styles.planBadge,
                            isPro ? styles.planPro : isBasic ? styles.planBasic : styles.planTrial,
                          ]}>
                          <Text
                            style={[
                              styles.planBadgeText,
                              isPro ? styles.planProText : isBasic ? styles.planBasicText : styles.planTrialText,
                            ]}>
                            {(comp.plan || 'TRIAL').toUpperCase()}
                          </Text>
                        </View>
                      </View>
                      <View style={styles.compMetaRow}>
                        <Text style={styles.compSubdomainText}>
                          {comp.subdomain ? `${comp.subdomain}.aikocrm.com` : `Tenant #${comp.id}`}
                        </Text>
                        <View style={[styles.statusDotSmall, { backgroundColor: isActive ? '#22c55e' : '#eab308' }]} />
                        <Text style={[styles.compStatusText, { color: isActive ? '#15803d' : '#a16207' }]}>
                          {(comp.status || 'Active').toUpperCase()}
                        </Text>
                      </View>
                    </View>
                  </View>

                  {/* Activity Stats Section */}
                  <View style={styles.activityStatsSection}>
                    {/* Today's Activity */}
                    <View style={styles.activityBox}>
                      <View style={styles.activityBoxHeader}>
                        <Ionicons name="today" size={14} color="#0284c7" />
                        <Text style={styles.activityBoxTitle}>Today's Activity</Text>
                        <View style={styles.activityCountBadge}>
                          <Text style={styles.activityCountBadgeText}>{comp.today_activities ?? 0}</Text>
                        </View>
                      </View>
                      <View style={styles.activityPillsGrid}>
                        <View style={styles.activityMetricPill}>
                          <Text style={styles.activityPillKey}>Leads</Text>
                          <Text style={styles.activityPillVal}>{comp.today_leads ?? 0}</Text>
                        </View>
                        <View style={styles.activityMetricPill}>
                          <Text style={styles.activityPillKey}>Follow-ups</Text>
                          <Text style={styles.activityPillVal}>{comp.today_followups ?? 0}</Text>
                        </View>
                        <View style={styles.activityMetricPill}>
                          <Text style={styles.activityPillKey}>Tasks</Text>
                          <Text style={styles.activityPillVal}>{comp.today_tasks ?? 0}</Text>
                        </View>
                        <View style={styles.activityMetricPill}>
                          <Text style={styles.activityPillKey}>Invoices</Text>
                          <Text style={styles.activityPillVal}>{comp.today_invoices ?? 0}</Text>
                        </View>
                      </View>
                    </View>

                    {/* Monthly Activity */}
                    <View style={[styles.activityBox, { backgroundColor: '#f5f3ff', borderColor: '#ddd6fe' }]}>
                      <View style={styles.activityBoxHeader}>
                        <Ionicons name="calendar" size={14} color="#7c3aed" />
                        <Text style={[styles.activityBoxTitle, { color: '#6d28d9' }]}>Monthly Activity</Text>
                        <View style={[styles.activityCountBadge, { backgroundColor: '#7c3aed' }]}>
                          <Text style={styles.activityCountBadgeText}>{comp.month_activities ?? 0}</Text>
                        </View>
                      </View>
                      <View style={styles.activityPillsGrid}>
                        <View style={[styles.activityMetricPill, { backgroundColor: '#ede9fe' }]}>
                          <Text style={[styles.activityPillKey, { color: '#6d28d9' }]}>Leads</Text>
                          <Text style={[styles.activityPillVal, { color: '#6d28d9' }]}>{comp.month_leads ?? 0}</Text>
                        </View>
                        <View style={[styles.activityMetricPill, { backgroundColor: '#ede9fe' }]}>
                          <Text style={[styles.activityPillKey, { color: '#6d28d9' }]}>Follow-ups</Text>
                          <Text style={[styles.activityPillVal, { color: '#6d28d9' }]}>{comp.month_followups ?? 0}</Text>
                        </View>
                        <View style={[styles.activityMetricPill, { backgroundColor: '#ede9fe' }]}>
                          <Text style={[styles.activityPillKey, { color: '#6d28d9' }]}>Tasks</Text>
                          <Text style={[styles.activityPillVal, { color: '#6d28d9' }]}>{comp.month_tasks ?? 0}</Text>
                        </View>
                        <View style={[styles.activityMetricPill, { backgroundColor: '#ede9fe' }]}>
                          <Text style={[styles.activityPillKey, { color: '#6d28d9' }]}>Invoices</Text>
                          <Text style={[styles.activityPillVal, { color: '#6d28d9' }]}>{comp.month_invoices ?? 0}</Text>
                        </View>
                      </View>
                    </View>
                  </View>

                  {/* Login as Button */}
                  <TouchableOpacity
                    style={[styles.loginAsBtn, isSwitching && { opacity: 0.7 }]}
                    disabled={isSwitching}
                    onPress={() => handleLoginAs(comp)}
                    activeOpacity={0.85}>
                    {isSwitching ? (
                      <View style={styles.btnRow}>
                        <ActivityIndicator size="small" color="#ffffff" />
                        <Text style={styles.loginAsBtnText}>Logging in as {comp.name}...</Text>
                      </View>
                    ) : (
                      <View style={styles.btnRow}>
                        <Ionicons name="log-in-outline" size={18} color="#ffffff" />
                        <Text style={styles.loginAsBtnText}>Login as {comp.name}</Text>
                        <Ionicons name="arrow-forward" size={15} color="#ffffff" style={{ marginLeft: 'auto' }} />
                      </View>
                    )}
                  </TouchableOpacity>
                </View>
              );
            })
          )}
        </ScrollView>
      </View>
    );
  }

  // Tenant Calculations
  const totalLeads = leads.length;
  const wonLeads = leads.filter(l => l.status === 'won').length;
  const inProgressLeads = leads.filter(l => l.status === 'in_progress' || l.status === 'new').length;
  const transferredLeads = leads.filter(l => l.is_transferred == 1).length;

  const totalRevenue = invoices.reduce((acc, inv) => acc + (parseFloat(String(inv.total_amount)) || 0), 0);
  const totalBalanceDue = invoices.reduce((acc, inv) => acc + (parseFloat(String(inv.balance_due)) || 0), 0);

  const todayStr = new Date().toISOString().split('T')[0];
  const todayTasks = tasks.filter(t => t.expected_delivery_date === todayStr && t.task_status !== 'work_done');
  const overdueTasks = tasks.filter(
    t => t.expected_delivery_date && t.expected_delivery_date < todayStr && t.task_status !== 'work_done'
  );

  return (
    <View style={styles.container}>
      <AppTopBar
        title="Command Center"
        subtitle={company?.name ? `🏢 ${company.name}` : 'Aikaa CRM Enterprise'}
      />
      <ScrollView
        contentContainerStyle={styles.scrollContent}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[Colors.primary]} />}>

        {/* Header Bar */}
        <View style={styles.topHeader}>
          <View>
            <Text style={styles.greetingText}>Welcome back,</Text>
            <Text style={styles.userName}>{user?.name || 'Team Member'}</Text>
            <View style={styles.roleRow}>
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
                  {isImpersonating ? 'IMPERSONATING' : (user?.role?.toUpperCase() || 'EXECUTIVE')}
                </Text>
              </View>
              {company?.name && (
                <Text style={styles.companyName} numberOfLines={1}>
                  • {company.name}
                </Text>
              )}
            </View>
          </View>
          <TouchableOpacity
            style={styles.profileAvatar}
            onPress={() => router.push('/(tabs)/more')}>
            <Text style={styles.avatarLetter}>{(user?.name || 'A')[0].toUpperCase()}</Text>
          </TouchableOpacity>
        </View>

        {/* Super Admin Quick Switcher Shortcut */}
        {isSuperAdmin && !isImpersonating && (
          <TouchableOpacity
            style={styles.superAdminSwitchCard}
            onPress={() => router.push('/(tabs)/more')}>
            <View style={styles.superIconBox}>
              <Ionicons name="business" size={20} color="#dc2626" />
            </View>
            <View style={{ flex: 1 }}>
              <Text style={styles.superCardTitle}>🏢 Super Admin Control</Text>
              <Text style={styles.superCardSub}>Switch tenant & login directly as any registered company</Text>
            </View>
            <Ionicons name="chevron-forward" size={18} color="#dc2626" />
          </TouchableOpacity>
        )}

        {/* Quick Actions Bar */}
        <View style={styles.quickActionsContainer}>
          <TouchableOpacity
            style={[styles.actionBtn, { backgroundColor: '#e0e7ff' }]}
            onPress={() => router.push('/lead/add')}>
            <View style={[styles.actionIconBox, { backgroundColor: '#6366f1' }]}>
              <Ionicons name="person-add" size={16} color="#ffffff" />
            </View>
            <Text style={[styles.actionBtnText, { color: '#4338ca' }]}>Add Lead</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.actionBtn, { backgroundColor: '#d1fae5' }]}
            onPress={() => router.push('/invoice/add')}>
            <View style={[styles.actionIconBox, { backgroundColor: '#10b981' }]}>
              <Ionicons name="receipt" size={16} color="#ffffff" />
            </View>
            <Text style={[styles.actionBtnText, { color: '#065f46' }]}>New Invoice</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.actionBtn, { backgroundColor: '#fef3c7' }]}
            onPress={() => router.push('/quotation/add')}>
            <View style={[styles.actionIconBox, { backgroundColor: '#f59e0b' }]}>
              <Ionicons name="document-text" size={16} color="#ffffff" />
            </View>
            <Text style={[styles.actionBtnText, { color: '#92400e' }]}>Quotation</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.actionBtn, { backgroundColor: '#ede9fe' }]}
            onPress={() => router.push('/attendance')}>
            <View style={[styles.actionIconBox, { backgroundColor: '#8b5cf6' }]}>
              <Ionicons name="time" size={16} color="#ffffff" />
            </View>
            <Text style={[styles.actionBtnText, { color: '#5b21b6' }]}>Attendance</Text>
          </TouchableOpacity>
        </View>

        {loading ? (
          <View style={styles.loadingBox}>
            <ActivityIndicator size="small" color={Colors.primary} />
            <Text style={styles.loadingText}>Loading dashboard analytics...</Text>
          </View>
        ) : (
          <>
            {/* Lead Metrics Section */}
            <Text style={styles.sectionTitle}>Sales Pipeline</Text>
            <View style={styles.grid}>
              <View style={[styles.statCard, { borderLeftColor: '#6366f1' }]}>
                <View style={styles.statHeader}>
                  <Text style={styles.statLabel}>Total Opportunities</Text>
                  <Ionicons name="funnel" size={18} color="#6366f1" />
                </View>
                <Text style={styles.statValue}>{totalLeads}</Text>
                <Text style={styles.statSub}>All active records</Text>
              </View>

              <View style={[styles.statCard, { borderLeftColor: '#10b981' }]}>
                <View style={styles.statHeader}>
                  <Text style={styles.statLabel}>Won Conversions</Text>
                  <Ionicons name="checkmark-circle" size={18} color="#10b981" />
                </View>
                <Text style={[styles.statValue, { color: '#10b981' }]}>{wonLeads}</Text>
                <Text style={styles.statSub}>Closed successfully</Text>
              </View>

              <View style={[styles.statCard, { borderLeftColor: '#3b82f6' }]}>
                <View style={styles.statHeader}>
                  <Text style={styles.statLabel}>In Progress</Text>
                  <Ionicons name="trending-up" size={18} color="#3b82f6" />
                </View>
                <Text style={[styles.statValue, { color: '#3b82f6' }]}>{inProgressLeads}</Text>
                <Text style={styles.statSub}>Follow-up required</Text>
              </View>

              <View style={[styles.statCard, { borderLeftColor: '#8b5cf6' }]}>
                <View style={styles.statHeader}>
                  <Text style={styles.statLabel}>Transferred</Text>
                  <Ionicons name="swap-horizontal" size={18} color="#8b5cf6" />
                </View>
                <Text style={[styles.statValue, { color: '#8b5cf6' }]}>{transferredLeads}</Text>
                <Text style={styles.statSub}>Assigned transfers</Text>
              </View>
            </View>

            {/* Financial Overview */}
            <Text style={[styles.sectionTitle, { marginTop: Spacing.xl }]}>Billing & Financials</Text>
            <View style={styles.financialBanner}>
              <View style={styles.finCol}>
                <Text style={styles.finLabel}>Total Invoiced</Text>
                <Text style={styles.finValue}>₹{totalRevenue.toLocaleString('en-IN')}</Text>
              </View>
              <View style={styles.finDivider} />
              <View style={styles.finCol}>
                <Text style={styles.finLabel}>Outstanding Dues</Text>
                <Text style={[styles.finValue, { color: '#ef4444' }]}>
                  ₹{totalBalanceDue.toLocaleString('en-IN')}
                </Text>
              </View>
            </View>

            {/* Tasks Summary */}
            <Text style={[styles.sectionTitle, { marginTop: Spacing.xl }]}>Tasks & Deliveries</Text>
            <View style={styles.tasksRow}>
              <TouchableOpacity
                style={[styles.taskSummaryCard, { backgroundColor: '#fee2e2' }]}
                onPress={() => router.push('/(tabs)/tasks')}>
                <Text style={[styles.taskCount, { color: '#b91c1c' }]}>{overdueTasks.length}</Text>
                <Text style={[styles.taskLabel, { color: '#991b1b' }]}>Overdue</Text>
              </TouchableOpacity>

              <TouchableOpacity
                style={[styles.taskSummaryCard, { backgroundColor: '#e0e7ff' }]}
                onPress={() => router.push('/(tabs)/tasks')}>
                <Text style={[styles.taskCount, { color: '#4338ca' }]}>{todayTasks.length}</Text>
                <Text style={[styles.taskLabel, { color: '#3730a3' }]}>Due Today</Text>
              </TouchableOpacity>

              <TouchableOpacity
                style={[styles.taskSummaryCard, { backgroundColor: '#f1f5f9' }]}
                onPress={() => router.push('/(tabs)/tasks')}>
                <Text style={[styles.taskCount, { color: '#334155' }]}>{tasks.length}</Text>
                <Text style={[styles.taskLabel, { color: '#475569' }]}>Total Tasks</Text>
              </TouchableOpacity>
            </View>

            {/* Recent Leads Activity */}
            <View style={styles.sectionHeaderRow}>
              <Text style={styles.sectionTitle}>Recent Leads</Text>
              <TouchableOpacity onPress={() => router.push('/(tabs)/leads')}>
                <Text style={styles.viewAllText}>View All ({totalLeads})</Text>
              </TouchableOpacity>
            </View>

            {leads.slice(0, 5).map(lead => (
              <TouchableOpacity
                key={lead.id}
                style={styles.recentLeadCard}
                onPress={() => router.push(`/lead/${lead.id}`)}>
                <View style={styles.leadCardTop}>
                  <Text style={styles.leadName}>{lead.name || 'Unnamed Client'}</Text>
                  <View
                    style={[
                      styles.leadStatusPill,
                      lead.status === 'won' && styles.statusWon,
                      lead.status === 'lost' && styles.statusLost,
                    ]}>
                    <Text style={styles.leadStatusText}>{(lead.status || 'new').toUpperCase()}</Text>
                  </View>
                </View>
                <Text style={styles.leadMobile}>📞 {lead.mobile}</Text>
                {lead.requirement ? (
                  <Text style={styles.leadReq} numberOfLines={1}>
                    📌 {lead.requirement}
                  </Text>
                ) : null}
              </TouchableOpacity>
            ))}
          </>
        )}
      </ScrollView>
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
  topHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: Spacing.lg,
  },
  greetingText: {
    fontSize: 13,
    color: '#64748b',
    fontWeight: '500',
  },
  userName: {
    fontSize: 22,
    fontWeight: '800',
    color: '#0f172a',
    letterSpacing: -0.3,
  },
  roleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 4,
    gap: 6,
  },
  roleBadge: {
    backgroundColor: '#e0e7ff',
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: Radius.full,
  },
  roleText: {
    fontSize: 10,
    fontWeight: '800',
    color: '#4338ca',
  },
  companyName: {
    fontSize: 12,
    color: '#64748b',
    fontWeight: '600',
  },
  profileAvatar: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: Colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: Colors.primary,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.25,
    shadowRadius: 6,
    elevation: 3,
  },
  avatarLetter: {
    fontSize: 18,
    fontWeight: '800',
    color: '#ffffff',
  },
  quickActionsContainer: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: Spacing.xl,
    gap: 8,
  },
  actionBtn: {
    flex: 1,
    paddingVertical: 12,
    paddingHorizontal: 6,
    borderRadius: Radius.md,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
  },
  actionIconBox: {
    width: 32,
    height: 32,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  actionBtnText: {
    fontSize: 11,
    fontWeight: '700',
  },
  sectionTitle: {
    fontSize: 15,
    fontWeight: '800',
    color: '#0f172a',
    marginBottom: Spacing.md,
  },
  sectionHeaderRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: Spacing.xl,
    marginBottom: Spacing.md,
  },
  viewAllText: {
    fontSize: 13,
    color: Colors.primary,
    fontWeight: '700',
  },
  grid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  statCard: {
    width: '48%',
    backgroundColor: '#ffffff',
    borderRadius: Radius.md,
    padding: Spacing.md,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    borderLeftWidth: 4,
    shadowColor: '#0f172a',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.03,
    shadowRadius: 4,
    elevation: 1,
  },
  statHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 6,
  },
  statLabel: {
    fontSize: 11,
    fontWeight: '700',
    color: '#64748b',
    textTransform: 'uppercase',
  },
  statValue: {
    fontSize: 22,
    fontWeight: '800',
    color: '#0f172a',
  },
  statSub: {
    fontSize: 11,
    color: '#94a3b8',
    marginTop: 2,
  },
  financialBanner: {
    flexDirection: 'row',
    backgroundColor: '#0f172a',
    borderRadius: Radius.lg,
    padding: Spacing.lg,
    alignItems: 'center',
    justifyContent: 'space-around',
  },
  finCol: {
    alignItems: 'center',
  },
  finLabel: {
    fontSize: 11,
    color: '#94a3b8',
    textTransform: 'uppercase',
    fontWeight: '700',
    marginBottom: 4,
  },
  finValue: {
    fontSize: 18,
    fontWeight: '800',
    color: '#ffffff',
  },
  finDivider: {
    width: 1,
    height: 36,
    backgroundColor: 'rgba(255, 255, 255, 0.2)',
  },
  tasksRow: {
    flexDirection: 'row',
    gap: 10,
  },
  taskSummaryCard: {
    flex: 1,
    borderRadius: Radius.md,
    padding: 12,
    alignItems: 'center',
  },
  taskCount: {
    fontSize: 20,
    fontWeight: '800',
  },
  taskLabel: {
    fontSize: 11,
    fontWeight: '700',
    marginTop: 2,
  },
  recentLeadCard: {
    backgroundColor: '#ffffff',
    borderRadius: Radius.md,
    padding: Spacing.md,
    marginBottom: 8,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  leadCardTop: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 4,
  },
  leadName: {
    fontSize: 14,
    fontWeight: '700',
    color: '#0f172a',
  },
  leadStatusPill: {
    backgroundColor: '#e2e8f0',
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: Radius.sm,
  },
  statusWon: {
    backgroundColor: '#d1fae5',
  },
  statusLost: {
    backgroundColor: '#fee2e2',
  },
  leadStatusText: {
    fontSize: 9,
    fontWeight: '800',
    color: '#475569',
  },
  leadMobile: {
    fontSize: 12,
    color: '#64748b',
    fontWeight: '600',
  },
  leadReq: {
    fontSize: 12,
    color: '#475569',
    marginTop: 2,
  },
  loadingBox: {
    padding: Spacing.xxl,
    alignItems: 'center',
  },
  loadingText: {
    fontSize: 12,
    color: '#64748b',
    marginTop: 8,
  },
  impersonateBanner: {
    backgroundColor: '#dc2626',
    borderRadius: Radius.md,
    padding: Spacing.md,
    marginBottom: Spacing.md,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 10,
    shadowColor: '#dc2626',
    shadowOffset: { width: 0, height: 3 },
    shadowOpacity: 0.2,
    shadowRadius: 5,
    elevation: 3,
  },
  impersonateTitle: {
    color: '#ffffff',
    fontWeight: '800',
    fontSize: 11,
    letterSpacing: 0.5,
  },
  impersonateSub: {
    color: '#fef2f2',
    fontSize: 11,
    marginTop: 1,
  },
  exitBtn: {
    backgroundColor: '#ffffff',
    paddingVertical: 4,
    paddingHorizontal: 10,
    borderRadius: Radius.full,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  exitBtnText: {
    color: '#b91c1c',
    fontSize: 11,
    fontWeight: '800',
  },
  superAdminSwitchCard: {
    backgroundColor: '#ffffff',
    borderRadius: Radius.md,
    padding: Spacing.md,
    marginBottom: Spacing.md,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    borderWidth: 1,
    borderColor: '#fca5a5',
  },
  superIconBox: {
    width: 40,
    height: 40,
    borderRadius: Radius.md,
    backgroundColor: '#fee2e2',
    alignItems: 'center',
    justifyContent: 'center',
  },
  superCardTitle: {
    fontSize: 14,
    fontWeight: '800',
    color: '#0f172a',
  },
  superCardSub: {
    fontSize: 11,
    color: '#64748b',
    marginTop: 1,
  },
  saOverviewCard: {
    backgroundColor: '#ffffff',
    borderRadius: Radius.lg,
    padding: Spacing.md,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    marginBottom: Spacing.sm,
    shadowColor: '#4f46e5',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },
  saHeaderRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  saBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    backgroundColor: '#ede9fe',
    borderWidth: 1,
    borderColor: '#ddd6fe',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: Radius.full,
  },
  saBadgeText: {
    fontSize: 10,
    fontWeight: '800',
    color: '#6d28d9',
    letterSpacing: 0.5,
  },
  saMetaText: {
    fontSize: 11,
    fontWeight: '700',
    color: '#64748b',
  },
  saTitle: {
    fontSize: 18,
    fontWeight: '800',
    color: '#0f172a',
  },
  saSub: {
    fontSize: 12,
    color: '#64748b',
    marginTop: 2,
  },
  saMetricsRow: {
    flexDirection: 'row',
    gap: 8,
    marginTop: 12,
  },
  saMetricBox: {
    flex: 1,
    backgroundColor: '#f8fafc',
    padding: 10,
    borderRadius: Radius.md,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    alignItems: 'center',
  },
  saMetricNum: {
    fontSize: 18,
    fontWeight: '800',
    color: '#4338ca',
  },
  saMetricLabel: {
    fontSize: 10,
    fontWeight: '700',
    color: '#64748b',
    marginTop: 2,
  },
  searchContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#ffffff',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: Radius.md,
    borderWidth: 1,
    borderColor: '#cbd5e1',
    marginVertical: 10,
    gap: 8,
  },
  searchInput: {
    flex: 1,
    fontSize: 13,
    color: '#0f172a',
    padding: 0,
  },
  emptyContainer: {
    paddingVertical: 40,
    paddingHorizontal: 20,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  emptyTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#334155',
  },
  emptySub: {
    fontSize: 12,
    color: '#64748b',
    textAlign: 'center',
  },
  compCard: {
    backgroundColor: '#ffffff',
    borderRadius: Radius.lg,
    padding: 14,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    shadowColor: '#000000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.04,
    shadowRadius: 4,
    elevation: 2,
  },
  compHeader: {
    flexDirection: 'row',
    gap: 10,
    alignItems: 'center',
  },
  compIconBox: {
    width: 38,
    height: 38,
    borderRadius: Radius.md,
    backgroundColor: '#eef2ff',
    alignItems: 'center',
    justifyContent: 'center',
  },
  compTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 8,
  },
  compNameText: {
    fontSize: 15,
    fontWeight: '800',
    color: '#0f172a',
    flex: 1,
  },
  planBadge: {
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 6,
  },
  planBadgeText: {
    fontSize: 9,
    fontWeight: '800',
  },
  planPro: {
    backgroundColor: '#fef3c7',
    borderWidth: 1,
    borderColor: '#fde68a',
  },
  planProText: {
    color: '#b45309',
  },
  planBasic: {
    backgroundColor: '#e0e7ff',
    borderWidth: 1,
    borderColor: '#c7d2fe',
  },
  planBasicText: {
    color: '#3730a3',
  },
  planTrial: {
    backgroundColor: '#f1f5f9',
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  planTrialText: {
    color: '#475569',
  },
  compMetaRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginTop: 3,
  },
  compSubdomainText: {
    fontSize: 11,
    color: '#64748b',
    fontWeight: '600',
  },
  statusDotSmall: {
    width: 6,
    height: 6,
    borderRadius: 3,
  },
  compStatusText: {
    fontSize: 10,
    fontWeight: '700',
  },
  activityStatsSection: {
    gap: 8,
    marginVertical: 10,
  },
  activityBox: {
    backgroundColor: '#f0f9ff',
    borderWidth: 1,
    borderColor: '#bae6fd',
    borderRadius: Radius.md,
    padding: 10,
  },
  activityBoxHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    marginBottom: 8,
  },
  activityBoxTitle: {
    fontSize: 12,
    fontWeight: '700',
    color: '#0369a1',
    flex: 1,
  },
  activityCountBadge: {
    backgroundColor: '#0284c7',
    paddingHorizontal: 6,
    paddingVertical: 1,
    borderRadius: Radius.full,
  },
  activityCountBadgeText: {
    color: '#ffffff',
    fontSize: 10,
    fontWeight: '800',
  },
  activityPillsGrid: {
    flexDirection: 'row',
    gap: 8,
  },
  activityMetricPill: {
    flex: 1,
    backgroundColor: '#e0f2fe',
    padding: 6,
    borderRadius: 6,
    alignItems: 'center',
  },
  activityPillKey: {
    fontSize: 9,
    fontWeight: '600',
    color: '#0369a1',
  },
  activityPillVal: {
    fontSize: 14,
    fontWeight: '800',
    color: '#0369a1',
  },
  loginAsBtn: {
    backgroundColor: '#4f46e5',
    paddingVertical: 10,
    paddingHorizontal: 14,
    borderRadius: Radius.md,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 4,
  },
  loginAsBtnText: {
    color: '#ffffff',
    fontSize: 13,
    fontWeight: '700',
  },
  btnRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    width: '100%',
  },
});
