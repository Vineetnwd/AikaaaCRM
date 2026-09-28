import { SafeStorage } from './storage';

export const STORAGE_KEYS = {
  TOKEN: '@aikocrm_token',
  USER: '@aikocrm_user',
  COMPANY: '@aikocrm_company',
  BASE_URL: '@aikocrm_base_url',
};

export const DEFAULT_BASE_URL = 'https://aikocrm.com/crm/api';

class ApiService {
  private baseUrl: string = DEFAULT_BASE_URL;
  private token: string | null = null;

  async init() {
    try {
      // Always enforce the live production server
      this.baseUrl = DEFAULT_BASE_URL;
      await SafeStorage.removeItem(STORAGE_KEYS.BASE_URL);
      const storedToken = await SafeStorage.getItem(STORAGE_KEYS.TOKEN);
      if (storedToken) {
        this.token = storedToken;
      }
    } catch (e) {
      console.warn('Failed to load stored API settings', e);
    }
  }

  async setBaseUrl(url: string) {
    this.baseUrl = DEFAULT_BASE_URL;
  }

  getBaseUrl(): string {
    return DEFAULT_BASE_URL;
  }

  setToken(token: string | null) {
    this.token = token;
    if (token) {
      SafeStorage.setItem(STORAGE_KEYS.TOKEN, token);
    } else {
      SafeStorage.removeItem(STORAGE_KEYS.TOKEN);
    }
  }

  getToken(): string | null {
    return this.token;
  }

  private async request<T = any>(endpoint: string, options: RequestInit = {}): Promise<T> {
    const url = `${this.baseUrl}/${endpoint.replace(/^\/+/, '')}`;
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...(options.headers as Record<string, string> || {}),
    };

    if (this.token) {
      headers['Authorization'] = `Bearer ${this.token}`;
    }

    // 🔍 DEBUG — remove after fixing
    console.log(`[API] ${options.method || 'GET'} ${endpoint} | token: ${this.token ? this.token.substring(0, 20) + '…' : 'NONE'}`);

    try {
      const response = await fetch(url, {
        ...options,
        headers,
      });

      const text = await response.text();
      let data: any;
      try {
        data = JSON.parse(text);
      } catch (err) {
        data = { text };
      }

      if (!response.ok) {
        throw new Error(data.error || data.message || `Request failed with status ${response.status}`);
      }

      return data as T;
    } catch (error: any) {
      console.error(`API Error on [${options.method || 'GET'} ${url}]:`, error);
      throw error;
    }
  }

  // --- Auth ---
  async login(identifier: string, password: string) {
    return this.request('login.php', {
      method: 'POST',
      body: JSON.stringify({ identifier, password }),
    });
  }

  // --- Leads ---
  async getLeads(params: Record<string, any> = {}) {
    const query = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') {
        query.append(k, String(v));
      }
    });
    const qs = query.toString() ? `?${query.toString()}` : '';
    return this.request(`leads.php${qs}`);
  }

  async getLead(id: number | string) {
    return this.request(`leads.php?id=${id}`);
  }

  async createLead(payload: Record<string, any>) {
    return this.request('leads.php', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  async updateLead(id: number | string, payload: Record<string, any>) {
    return this.request(`leads.php?id=${id}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  }

  async deleteLead(id: number | string) {
    return this.request(`leads.php?id=${id}`, {
      method: 'DELETE',
    });
  }

  async transferLead(leadId: number, targetEmployeeId: number, remark: string) {
    return this.request('leads.php?action=transfer', {
      method: 'POST',
      body: JSON.stringify({
        lead_id: leadId,
        target_employee_id: targetEmployeeId,
        remark,
      }),
    });
  }

  // --- Followups / Interactions ---
  async getFollowups(leadId: number | string) {
    return this.request(`lead_followups.php?lead_id=${leadId}`);
  }

  async addFollowup(payload: {
    lead_id: number;
    remark: string;
    call_status?: string;
    follow_up_date?: string;
    follow_up_time?: string;
  }) {
    return this.request('lead_followups.php', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  // --- Tasks ---
  async getTasks(params: Record<string, any> = {}) {
    const query = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') {
        query.append(k, String(v));
      }
    });
    const qs = query.toString() ? `?${query.toString()}` : '';
    return this.request(`tasks.php${qs}`);
  }

  async updateTaskStatus(payload: {
    id: number;
    status: string;
    remark?: string;
    expected_delivery_date?: string;
  }) {
    return this.request('tasks.php', {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
  }

  // --- Invoices ---
  async getInvoices(params: Record<string, any> = {}) {
    const query = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') {
        query.append(k, String(v));
      }
    });
    const qs = query.toString() ? `?${query.toString()}` : '';
    return this.request(`invoices.php${qs}`);
  }

  async getInvoice(id: number | string) {
    return this.request(`invoices.php?id=${id}`);
  }

  async getInvoicePayments(id: number | string) {
    return this.request(`invoices.php?action=payments&id=${id}`);
  }

  async createInvoice(payload: Record<string, any>) {
    return this.request('invoices.php', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  // --- Quotations ---
  async getQuotations(params: Record<string, any> = {}) {
    const query = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') {
        query.append(k, String(v));
      }
    });
    const qs = query.toString() ? `?${query.toString()}` : '';
    return this.request(`quotations.php${qs}`);
  }

  async getQuotation(id: number | string) {
    return this.request(`quotations.php?id=${id}`);
  }

  async createQuotation(payload: Record<string, any>) {
    return this.request('quotations.php', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  async convertQuotationToInvoice(quotationId: number | string) {
    return this.request('quotations.php?action=convert_to_invoice', {
      method: 'POST',
      body: JSON.stringify({ id: quotationId }),
    });
  }

  // --- Customers ---
  async getCustomers(params: Record<string, any> = {}) {
    const query = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') {
        query.append(k, String(v));
      }
    });
    const qs = query.toString() ? `?${query.toString()}` : '';
    return this.request(`customers.php${qs}`);
  }

  async getCustomerProfile(id: number | string) {
    return this.request(`customer_profile.php?id=${id}`);
  }

  // --- Employees ---
  async getEmployees() {
    return this.request('employees.php');
  }

  async getUsers() {
    return this.request('users.php');
  }

  // --- Requirements ---
  async getRequirements() {
    return this.request('requirements.php?limit=all');
  }

  // --- Attendance ---
  async getAttendance(params: Record<string, any> = {}) {
    const query = new URLSearchParams(params).toString();
    return this.request(`attendance.php${query ? `?${query}` : ''}`);
  }

  async markAttendance(payload: { status: string; notes?: string; latitude?: number; longitude?: number }) {
    return this.request('attendance.php', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  // --- Commissions & Performance ---
  async getCommissions(params: Record<string, any> = {}) {
    const query = new URLSearchParams(params).toString();
    return this.request(`commissions.php${query ? `?${query}` : ''}`);
  }

  async getPerformance(params: Record<string, any> = {}) {
    const query = new URLSearchParams(params).toString();
    return this.request(`performance_details.php${query ? `?${query}` : ''}`);
  }

  // --- Share / WhatsApp ---
  async sendWhatsApp(payload: { phone: string; message: string; template_name?: string }) {
    return this.request('whatsapp_send.php', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  // --- Companies / Super Admin Impersonation ---
  async getCompanies(search?: string) {
    const qs = search ? `?search=${encodeURIComponent(search)}` : '';
    return this.request(`companies.php${qs}`);
  }

  async impersonateCompany(companyId: number | string) {
    return this.request(`companies.php?action=impersonate&company_id=${companyId}`);
  }
}

export const api = new ApiService();
