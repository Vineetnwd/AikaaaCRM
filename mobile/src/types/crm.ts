export interface User {
  id: number;
  name: string;
  email: string;
  mobile: string;
  role: 'super_admin' | 'admin' | 'manager' | 'executive';
  company_id: number;
  employee_id?: number | null;
  status: string;
}

export interface Company {
  id: number;
  name: string;
  email?: string;
  phone?: string;
  address?: string;
  logo?: string;
  logo_path?: string;
  gstin?: string;
}

export interface Lead {
  id: number;
  company_id: number;
  name: string;
  mobile: string;
  email?: string;
  address?: string;
  status: string; // 'new' | 'in_progress' | 'won' | 'lost' | etc.
  category?: 'red' | 'green' | 'yellow' | string;
  source?: string;
  requirement?: string;
  requirement_names?: string;
  deal_value?: number | string;
  assigned_to?: number;
  assigned_to_name?: string;
  assigned_employee_id?: number;
  assigned_employee_name?: string;
  task_status?: string;
  expected_delivery_date?: string;
  is_transferred?: number;
  last_transfer_remark?: string;
  last_transferred_at?: string;
  latest_remark?: string;
  latest_call_status?: string;
  latest_interaction_at?: string;
  latest_next_followup_date?: string;
  interaction_user_name?: string;
  created_at: string;
  month?: string | number;
  year?: string | number;
}

export interface Followup {
  id: number;
  lead_id: number;
  user_id: number;
  user_name?: string;
  call_status?: string;
  remark: string;
  follow_up_date?: string;
  follow_up_time?: string;
  created_at: string;
}

export interface Task {
  id: number;
  name: string;
  mobile: string;
  requirement?: string;
  requirement_names?: string;
  task_status: 'not_started' | 'work_in_progress' | 'work_pending' | 'work_done';
  expected_delivery_date?: string;
  assigned_employee_name?: string;
  assigned_to_name?: string;
  deal_value?: number | string;
  status: string;
  created_at: string;
}

export interface Invoice {
  id: number;
  invoice_number: string;
  lead_id?: number;
  customer_name: string;
  customer_mobile: string;
  customer_email?: string;
  customer_address?: string;
  total_amount: number | string;
  paid_amount?: number | string;
  due_amount?: number | string;
  payment_status: 'paid' | 'partial' | 'due' | 'pending' | 'cancelled';
  due_date?: string;
  invoice_date?: string;
  created_at: string;
  description?: string;
}

export interface Quotation {
  id: number;
  quotation_number: string;
  lead_id?: number;
  customer_name: string;
  customer_mobile: string;
  customer_email?: string;
  total_amount: number | string;
  status: 'pending' | 'invoiced' | 'declined';
  valid_until?: string;
  created_at: string;
  notes?: string;
}

export interface Customer {
  id: number;
  name: string;
  mobile: string;
  email?: string;
  company_name?: string;
  address?: string;
  gstin?: string;
  created_at: string;
}

export interface Employee {
  id: number;
  name: string;
  email: string;
  mobile: string;
  designation?: string;
  department?: string;
  status: string;
}

export interface AttendanceRecord {
  id: number;
  employee_id: number;
  employee_name?: string;
  date: string;
  check_in?: string;
  check_out?: string;
  status: 'present' | 'absent' | 'half_day' | 'leave';
  notes?: string;
}
