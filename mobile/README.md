# Aikaa CRM - React Native Mobile Application (Expo SDK 52)

A production-ready, feature-complete enterprise CRM mobile application built with **React Native**, **Expo SDK 52**, and **Expo Router**. Designed for seamless lead conversion, service task delivery, quotation/invoice billing, attendance tracking, and executive performance metrics.

---

## 📱 Features & Capabilities

### 1. 📊 Executive Dashboard
- **Sales KPIs**: Real-time counters for Total Opportunities, Won Conversions, In-Progress pipelines, and Transferred leads.
- **Financial Overview**: Live calculation of Total Invoiced volume vs. Outstanding Dues.
- **Task Milestones**: Overdue warnings, Due Today badges, and quick links into task queues.
- **Quick Action Triggers**: Single-tap shortcuts to Add Leads, Invoices, Quotations, and Record Attendance.
- **Recent Activity Stream**: Quick-access lead cards with one-tap WhatsApp and phone dialing.

### 2. 🎯 Complete Lead Management
- **Search & Filter Pipeline**: Search by client name, mobile number, or required services.
- **Filter Tabs**: Filter by *All*, *Transferred Only*, *Won*, or Category Color (*Red*, *Green*, *Yellow*).
- **Lead Profile & Detail View**:
  - Full client information (Name, Mobile, Email, Physical Address).
  - Requirements breakdown & Deal Value.
  - Lead Manager and Task Staff designations.
  - Pipeline Stage Selector (*New*, *In Progress*, *Won*, *Lost*).
  - Category color picker.
- **Interaction & Follow-Up Tracking**:
  - Full chronological interaction timeline.
  - Add Interaction Note with call outcome (*Connected*, *Busy*, *Not Picked*, *Switch Off*), remarks, and Next Follow-up Date.
- **Lead Handover & Transfer**:
  - Reassign leads to team members with reason and handover remarks.
- **Communication Shortcuts**: One-tap Direct Call (`tel:`), WhatsApp (`whatsapp://send`), and Email (`mailto:`).

### 3. 📋 Service Tasks & Deliverables
- **Stage Categorization**: Tabs for *All Tasks*, *In Progress (WIP)*, *Delayed / Pending*, and *Completed*.
- **Deadline Monitoring**: Prominent overdue alerts for missed delivery dates.
- **Status Updates**: Modal to transition tasks between *Not Started*, *In Progress*, *Delayed*, and *Completed* (with required delay justification).

### 4. 💳 Billing & Financials
- **Invoices**:
  - Overview of Total Amount, Paid Amount, and Balance Due.
  - Payment status indicators (*Paid*, *Partial*, *Pending*, *Overdue*).
  - Direct WhatsApp receipt sharing with pre-formatted balance due message.
  - Open and view official printable PDF invoice.
  - Create new invoices with client details and line items.
- **Quotations**:
  - Proposal tracking with validity dates and terms.
  - One-tap conversion of Quotation into an official Invoice.
  - WhatsApp proposal delivery.
  - Create new quotation with customized pricing.

### 5. 👥 Enterprise Administration & Staff
- **Customer Directory**: Browse customers, associated companies, contact details, and GSTINs.
- **Team Roster**: Access employee directories, designations, and departments.
- **Daily Attendance**: Punch Check-In and Check-Out with real-time timestamps and monthly history logs.
- **Commissions & Incentives**: Track monthly converted deals, closed deal volume, win rates, and commission earnings.

### 6. 🌐 Environment & Security
- **Bearer Token Auth**: Enhanced with HMAC-SHA256 Bearer Token support in `crm/core/Auth.php` and `crm/api/login.php`.
- **Environment Switcher**: In-app toggle between Live Production Cloud (`https://aikocrm.com/crm/api`) and Local Development Server (`http://localhost/crm/api` or custom IP).
- **Persistent Storage**: Secure token and user data persistence via `@react-native-async-storage/async-storage`.

---

## 🚀 Running the App Locally

### Prerequisites
- Node.js (v18+)
- npm or yarn
- Expo Go on iOS / Android (or Xcode Simulator / Android Studio Emulator)

### Commands

1. **Navigate to the mobile directory**:
   ```bash
   cd mobile
   ```

2. **Start the Expo development server**:
   ```bash
   npx expo start
   ```

3. **Run on specific platforms**:
   - **iOS Simulator**: Press `i` in the terminal or run `npx expo start --ios`
   - **Android Emulator**: Press `a` in the terminal or run `npx expo start --android`
   - **Web Preview**: Press `w` in the terminal or run `npx expo start --web`
   - **Physical Device**: Scan the QR code using the **Expo Go** app on iOS Camera / Android Expo Go.

---

## 📁 Directory Architecture

```
mobile/
├── app.json                  # Expo app configuration (branding, splash, scheme)
├── package.json              # Dependencies and run scripts
├── tsconfig.json             # TypeScript configuration
└── src/
    ├── app/
    │   ├── _layout.tsx       # Root layout, AuthProvider, navigation stack
    │   ├── index.tsx         # Auth guard & entry redirector
    │   ├── (auth)/
    │   │   ├── _layout.tsx
    │   │   └── login.tsx     # Sign-in screen with environment switcher
    │   ├── (tabs)/
    │   │   ├── _layout.tsx   # Bottom tab bar with icons and badges
    │   │   ├── index.tsx     # Executive Dashboard
    │   │   ├── leads.tsx     # Leads management & filters
    │   │   ├── tasks.tsx     # Tasks & service deliveries
    │   │   ├── billing.tsx   # Invoices & Quotations
    │   │   └── more.tsx      # Customers, Employees, Attendance, Settings
    │   ├── lead/
    │   │   ├── [id].tsx      # Lead details, interactions, transfer
    │   │   └── add.tsx       # Create new opportunity modal
    │   ├── invoice/
    │   │   ├── [id].tsx      # Invoice details, breakdown, PDF print
    │   │   └── add.tsx       # Create invoice modal
    │   ├── quotation/
    │   │   ├── [id].tsx      # Quotation details, convert to invoice
    │   │   └── add.tsx       # Create quotation modal
    │   ├── attendance.tsx    # Daily punch & attendance logs
    │   ├── commissions.tsx   # Commissions & performance metrics
    │   └── settings.tsx      # Backend API URL config & session info
    ├── context/
    │   └── AuthContext.tsx   # Auth state, login/logout, role permissions
    ├── services/
    │   └── api.ts            # Centralized API client with Bearer auth
    ├── types/
    │   └── crm.ts            # CRM domain interfaces & models
    └── constants/
        └── theme.ts          # Color tokens, spacing, typography
```
