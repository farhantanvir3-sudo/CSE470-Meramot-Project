# CSE470: Software Engineering Project Roadmap

**Project Name**: Meramot — Digital Electronic Repair Marketplace  
**Course & Section**: CSE470 Section 06  
**Faculty**: Nazmul Islam [NZU]  
**Timeline**: November 1 – December 20, 2026 (49 Days / 4 Sprints)

---

## 1. Equal Workload Allocation Matrix (R1 – R20)

| Feature ID | Feature Title | Assigned Developer | Student ID | GitHub | Sprint | MVC Layers Involved |
| :---: | :--- | :--- | :---: | :---: | :---: | :--- |
| **R1** | Delivery Agent Onboarding & Credential Verification | Md. Nafiul Islam | 22299035 | [`Nafiul141`](https://github.com/Nafiul141) | Sprint 1 | **M**: `DeliveryAgent`, `User`<br>**V**: `Admin/VerifyAgent`<br>**C**: `AdminDeliveryController` |
| **R2** | Delivery Job Queue & Status Milestone Synchronization | Md. Nafiul Islam | 22299035 | [`Nafiul141`](https://github.com/Nafiul141) | Sprint 2 | **M**: `DeliveryJob`, `RepairRequest`<br>**V**: `Delivery/JobsQueue`<br>**C**: `DeliveryJobController` |
| **R3** | Delivery Agent Earnings Ledger & Payout Tracker | Md. Nafiul Islam | 22299035 | [`Nafiul141`](https://github.com/Nafiul141) | Sprint 3 | **M**: `RiderEarnings`, `Payout`<br>**V**: `Delivery/Earnings`<br>**C**: `RiderLedgerController` |
| **R4** | Live Delivery Agent GPS Location & Route Sharing | Md. Nafiul Islam | 22299035 | [`Nafiul141`](https://github.com/Nafiul141) | Sprint 3 | **M**: `LocationLog`<br>**V**: `Tracking/LiveMap`<br>**C**: `TrackingController` |
| **R5** | Real-Time Admin-to-Rider WebSocket Support Chat | Md. Nafiul Islam | 22299035 | [`Nafiul141`](https://github.com/Nafiul141) | Sprint 4 | **M**: `RiderChatMessage`<br>**V**: `Support/RiderChat`<br>**C**: `RiderChatController` |
| **R6** | Advanced Repair Shop Search & Multi-Parameter Filtering | Shanid Sajjatuz Islam | 23201465 | [`perashanid`](https://github.com/perashanid) | Sprint 1 | **M**: `RepairShop`, `Category`<br>**V**: `Shops/Explore`<br>**C**: `ShopDiscoveryController` |
| **R7** | Customer Repair Request Posting & Lifecycle Tracking | Shanid Sajjatuz Islam | 23201465 | [`perashanid`](https://github.com/perashanid) | Sprint 1 | **M**: `RepairRequest`, `DeviceBrand`<br>**V**: `Requests/Create`<br>**C**: `RepairRequestController` |
| **R8** | Direct Service Package Catalog & Shopping Cart Flow | Shanid Sajjatuz Islam | 23201465 | [`perashanid`](https://github.com/perashanid) | Sprint 2 | **M**: `ServicePackage`, `Cart`<br>**V**: `Packages/Catalog`<br>**C**: `ServicePackageController` |
| **R9** | Vendor Rating & Verified Repair Review System | Shanid Sajjatuz Islam | 23201465 | [`perashanid`](https://github.com/perashanid) | Sprint 4 | **M**: `ShopReview`, `Order`<br>**V**: `Reviews/WriteReview`<br>**C**: `ReviewController` |
| **R10** | AI-Powered Repair Diagnostic Conversational Consultant | Shanid Sajjatuz Islam | 23201465 | [`perashanid`](https://github.com/perashanid) | Sprint 4 | **M**: `AIChatSession`, `Message`<br>**V**: `AIConsultant/Chat`<br>**C**: `AIChatController` |
| **R11** | Vendor Onboarding & Business Profile Verification Engine | Farhan Tanvir *(Lead)* | 22299179 | [`farhantanvir3-sudo`](https://github.com/farhantanvir3-sudo) | Sprint 1 | **M**: `VendorProfile`, `Document`<br>**V**: `Vendor/Onboarding`<br>**C**: `VendorOnboardingController` |
| **R12** | Live Bidding Engine & Diagnostic Quote Generator | Farhan Tanvir *(Lead)* | 22299179 | [`farhantanvir3-sudo`](https://github.com/farhantanvir3-sudo) | Sprint 2 | **M**: `Bid`, `RepairQuote`, `Item`<br>**V**: `Vendor/BiddingBoard`<br>**C**: `BiddingEngineController` |
| **R13** | FastAPI GROQ/Gemini AI Request Summary Microservice | Farhan Tanvir *(Lead)* | 22299179 | [`farhantanvir3-sudo`](https://github.com/farhantanvir3-sudo) | Sprint 2 | **M**: `AISummaryLog`<br>**V**: `Vendor/RequestDetails`<br>**C**: `AISummaryService` |
| **R14** | Automated Multi-Channel BulkSMS & Email Notification | Farhan Tanvir *(Lead)* | 22299179 | [`farhantanvir3-sudo`](https://github.com/farhantanvir3-sudo) | Sprint 3 | **M**: `NotificationLog`<br>**V**: `Vendor/AlertSettings`<br>**C**: `VendorNotificationController` |
| **R15** | Vendor Financial Analytics & Performance Dashboard | Farhan Tanvir *(Lead)* | 22299179 | [`farhantanvir3-sudo`](https://github.com/farhantanvir3-sudo) | Sprint 4 | **M**: `VendorAnalytics`<br>**V**: `Vendor/Dashboard`<br>**C**: `VendorAnalyticsController` |
| **R16** | Platform Financial Summary & Double-Entry Ledger | Md. Nawshad Zaman Khan | 23301622 | [`Joy-Khan007`](https://github.com/Joy-Khan007) | Sprint 1 | **M**: `FinancialLedger`, `Transaction`<br>**V**: `Admin/FinancialOverview`<br>**C**: `AdminLedgerController` |
| **R17** | SSLCommerz/Stripe Online Payment Gateway & PDF Invoice | Md. Nawshad Zaman Khan | 23301622 | [`Joy-Khan007`](https://github.com/Joy-Khan007) | Sprint 2 | **M**: `PaymentTransaction`, `Invoice`<br>**V**: `Checkout/Payment`<br>**C**: `PaymentGatewayController` |
| **R18** | Automated Vendor Payout & Net Commission Settlement | Md. Nawshad Zaman Khan | 23301622 | [`Joy-Khan007`](https://github.com/Joy-Khan007) | Sprint 3 | **M**: `VendorPayout`, `Settlement`<br>**V**: `Admin/Payouts/Manage`<br>**C**: `VendorPayoutController` |
| **R19** | Sitewide User Moderation & Content Governance Panel | Md. Nawshad Zaman Khan | 23301622 | [`Joy-Khan007`](https://github.com/Joy-Khan007) | Sprint 3 | **M**: `ModerationLog`, `Flagged`<br>**V**: `Admin/Moderation`<br>**C**: `AdminModerationController` |
| **R20** | System Audit Logging & Security Compliance Monitor | Md. Nawshad Zaman Khan | 23301622 | [`Joy-Khan007`](https://github.com/Joy-Khan007) | Sprint 4 | **M**: `AuditLog`<br>**V**: `Admin/AuditLogs`<br>**C**: `AuditLogController` |

---

## 2. Sprint Milestones & Deliverables

### Sprint 1: Foundational Setup & Onboarding
- **Window**: Nov 1 – Nov 12, 2026 (12 Days) | **Official Deadline**: Nov 12, 2026
- **Goal**: Database schema initialization, base MVC layouts, user authentication with role middleware, and initial onboarding CRUDs.
- **Deliverables**:
  - Git repository initialization and teammate branch setup
  - PostgreSQL base migrations (`users`, `roles`, `vendor_profiles`, `delivery_agents`, `repair_requests`)
  - Features R1, R6, R7, R11, R16 functional end-to-end

### Sprint 2: Core Bidding Marketplace, AI Microservice & Payments
- **Window**: Nov 13 – Nov 27, 2026 (15 Days) | **Official Deadline**: Dec 1, 2026
- **Goal**: High-complexity transactional features: vendor bidding mechanics, line-item quote generation, Python FastAPI AI integration, and payment checkout.
- **Deliverables**:
  - Features R2, R8, R12, R13, R17 functional end-to-end
  - Standalone FastAPI microservice running on port 5000 with Groq / Gemini API client
  - SSLCommerz sandbox payment initiation, IPN callback, and PDF invoice generation

### Sprint 3: Logistics Tracking, Communications & Settlement
- **Window**: Nov 28 – Dec 9, 2026 (12 Days) | **Official Deadline**: Dec 14, 2026
- **Goal**: Delivery logistics tracking, external SMS/Email broadcasting, commission accounting, and admin governance.
- **Deliverables**:
  - Features R3, R4, R14, R18, R19 functional end-to-end
  - Leaflet GPS coordinate broadcast for active delivery jobs
  - Automated 10% platform commission calculation & vendor net balance settlement

### Sprint 4: AI Troubleshooting, Analytics, Audit & Defense
- **Window**: Dec 10 – Dec 20, 2026 (11 Days) | **Official Deadline**: Dec 20, 2026
- **Goal**: Real-time communication channels, business analytics, immutable security audits, and final course defense presentation.
- **Deliverables**:
  - Features R5, R9, R10, R15, R20 functional end-to-end
  - Complete integration testing and faculty demo rehearsal
