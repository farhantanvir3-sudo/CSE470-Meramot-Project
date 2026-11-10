# Sprint 1 Implementation Notes & Developer Handover Guide

**Target Milestone:** Sprint 1 Defense Demo (November 12, 2026)  
**Maintainer / Team Lead:** Farhan Tanvir (`22299179` — `@farhantanvir3-sudo`)  
**Team Roster:**
- **Md. Nafiul Islam** (`22299035` — `@Nafiul141`) — Features `R1 – R5`
- **Shanid Sajjatuz Islam** (`23201465` — `@perashanid`) — Features `R6 – R10`
- **Farhan Tanvir** (`22299179` — `@farhantanvir3-sudo`) — Features `R11 – R15`
- **Md. Nawshad Zaman Khan** (`23301622` — `@Joy-Khan007`) — Features `R16 – R20`

---

## 1. Summary of Completed Sprint 1 Architecture

All Sprint 1 requirements (`R1`, `R6`, `R7`, `R11`, `R16`) plus role-aware authentication, the unified Quickmatch / Direct-Order Cart (`R8`), Multi-Branch Vendor Expansion, Dual Shop Addressing, and nationwide Bangladesh OpenStreetMap seeding (`1,297` shops) are live and verified by **68 automated PHPUnit tests (320 assertions)**.

### Folder Separation Reminder
- **`frontend/views/`**: All Laravel Blade templates (configured in `backend/config/view.php`).
- **`frontend/js/`**: Client-side JavaScript helpers (`geolocation-helper.js`, `marketplace-client.js`), served via `FrontendAssetController`.
- **`backend/`**: Controllers, Eloquent Models, Services, Migrations, Seeders, Routes, and PHPUnit Feature Tests.

---

## 2. Module-by-Module Implementation Details

### A. Delivery & Rider Logistics (`R1 – R5` • Md. Nafiul Islam `[22299035]`)
- **Feature `R1` (Completed — Live in Sprint 1)**:
  - Public application portal at `GET /delivery-agent/register` and `POST /delivery-agent/register` (`AdminDeliveryController@create`, `store`).
  - Admin audit desk at `GET /admin/delivery-agents/pending` (`AdminDeliveryController@index`, `approve`, `reject`, `block`) with BRTA license / NID verification, mandatory `rejection_reason` on rejection, and shared Admin Console navigation (`frontend/views/admin/partials/nav.blade.php`).
  - One-click demo logins: `/auth/demo?role=delivery` (`rider.demo@meramot.test`) and `/auth/demo?role=delivery_admin` (`delivery.admin.demo@meeramoot.test`).
- **Features `R2 – R5` (Sprint 2–4 Handover)**:
  - `DeliveryJobController` (`R2`), `RiderLedgerController` (`R3`), `TrackingController` (`R4`), and `RiderChatController` (`R5`) are null-safe for unauthenticated visitors and aligned with `2026_11_01_000005_create_delivery_and_support_tables.php`.
  - Unfinished Sprint 2–4 form actions (`claimJob`, `updateMilestone`, `requestPayout`, `sendMessage`) temporarily delegate to `ComingSoonController` so zero buttons fail during the Sprint 1 demo.
  - **Sprint 2 Action Item (`nafiul-sprint2` branch)**: Replace the `ComingSoonController` return in `DeliveryJobController@claimJob` and `updateMilestone` with atomic rider assignment and 4-digit OTP verification.

### B. Discovery, Repair Requests, Cart, Reviews & AI (`R6 – R10` • Shanid Sajjatuz Islam `[23201465]`)
- **Feature `R6` (Completed — Live in Sprint 1)**:
  - Nationwide Bangladesh Haversine search (`ShopDiscoveryController` + `BangladeshLocationResolver`) and OpenStreetMap + Leaflet interactive map location picker (`frontend/js/geolocation-helper.js`).
  - Seeded **1,297 repair shops** across all 8 divisions of Bangladesh (`ShopSeeder` + `SprintOneDemoSeeder`).
  - **Dual Shop Addresses**: Private `gps_address` + coordinates for distance sorting (hidden from public JSON/views) vs. public vendor-provided `address`, with optional `same_as_gps_location` sync.
  - **Multi-Branch Tags**: Multi-branch brands display exact branch badges (`$shop->display_branch_tag`) across homepage cards, `/shops` search tiles, and `/shops/{slug}` profiles.
- **Feature `R7` (Completed — Live in Sprint 1)**:
  - Customer Request & Order History Hub at `GET /requests` & `GET /orders` (`RepairRequestController@index` -> `frontend/views/requests/index.blade.php`).
  - Pre-selected shop & issue support on `GET /requests/create?shop_id=...&issue=...`.
  - Full 16-state Dual-Quote lifecycle (`StatusTransitionController`) on `GET /requests/{id}/track`: Customer Quote 1 bid comparison & acceptance (`POST /requests/{id}/accept-bid`) + Bench Quote 2 approval/decline (`POST /requests/{id}/respond-final-quote`).
- **Features `R8, R9, R10`**:
  - `R8` (`ServicePackageController`): Full Quickmatch & locked-shop Direct-Order Cart (`/packages/catalog` -> `/cart` -> `/cart/checkout`).
  - `R9` (`ReviewController`): Null-safe `customer_id` fallback and automatic recalculation of both `rating` and `review_count` on `RepairShop`.
  - `R10` (`frontend/views/ai-consultant/chat.blade.php`): Preserves `AIChatController` API contract while providing client-side symptom-aware hardware diagnostic triage when the FastAPI microservice on port `5000` is offline.

### C. Vendor Onboarding, Multi-Branch, Bidding & Workbench (`R11 – R15` • Farhan Tanvir `[22299179]`)
- **Feature `R11` (Completed — Live in Sprint 1)**:
  - Guest-safe `/vendor/onboarding` (`VendorOnboardingController@show`, `store`) with Dual-Address picker, Multi-Branch toggle, and live application status banners (`pending` / `approved` / `rejected` + `rejection_reason`).
  - Unverified shops remain `is_public = false` until approved by Admin on `GET /admin/vendors/pending` (`VendorOnboardingController@adminIndex`, `approve`, `reject`, `toggleFeatured`).
- **Real Bidding & Workbench Sync (`R12` / `R15` Preview)**:
  - Vendors submit real PostgreSQL Quote 1 bids via `POST /vendor/requests/{id}/bid` (`VendorBranchController@submitBid`), accept/decline Direct Orders via `POST /vendor/direct-orders/{id}/respond`, expand branches via `POST /vendor/branches`, and update bench job statuses (`AT_SHOP`, `DIAGNOSING`, `WAITING_APPROVAL` Quote 2, `REPAIRING`, `READY_TO_RETURN`, `COMPLETED`) via `POST /vendor/jobs/{jobId}/status`.
  - `R13` (`/vendor/request-details/{id}`) and `R14` (`/vendor/alert-settings`) render Mint Sprint 2/3 previews connected to `ComingSoonController`.

### D. Financial Ledger, Payment Gateway & Governance (`R16 – R20` • Md. Nawshad Zaman Khan `[23301622]`)
- **Feature `R16` (Completed — Live in Sprint 1)**:
  - Immutable Double-Entry Financial Ledger at `GET /admin/financial-overview` (`AdminLedgerController`) with PostgreSQL-safe `CAST(id AS TEXT) ILIKE` search, Fund Distribution BI charts, Manual Settlement, and Batch Auto-Settlement.
  - **End-to-End Marketplace Coupling**: Marking any `RepairJob` as `COMPLETED` (`VendorBranchController@updateJobStatus`) automatically records a `PAID` `PaymentTransaction` and calls `AdminLedgerController::settleOrderWithCommission()` to post the `100%` Customer Credit, `10%` Platform Commission Credit, and `90%` Vendor Net Earning Debit entries.
- **Features `R17 – R20` (PostgreSQL Schema Alignment & Sprint 2–4 Handover)**:
  - Reconciled `PaymentTransaction`, `VendorPayout` (`shop_id`, `amount`, `status`, `paid_at`), `ModerationLog` (`admin_id`, `target_type`, `target_id`, `violation_type`, `action_taken`, `reason`), and `AuditLog` (`actor_id`, `action`, `target_resource`, `payload_before`, `payload_after`, `ip_address`) with `2026_11_01_000006_create_financial_review_and_audit_tables.php`.
  - `GET /checkout/payment/{requestId}` (`R17`) displays real order totals and routes `POST /payment/initiate` to `ComingSoonController` (`sslcommerz-payment`).
  - **Sprint 2 Action Item (`nawshad-sprint2` branch)**: Implement live SSLCommerz session initiation inside `PaymentGatewayController@initiatePayment` using the aligned `payment_transactions` columns.

---

## 3. Universal `"Coming Soon"` Placeholder System

- **Controller**: `backend/app/Http/Controllers/ComingSoonController.php`
- **View**: `frontend/views/coming-soon.blade.php`
- **Route**: `Route::match(['get', 'post'], '/coming-soon/{feature?}', [ComingSoonController::class, 'show'])` + `Route::fallback([ComingSoonController::class, 'fallback'])` in `backend/routes/web.php`.
- Any link or form action scheduled for Sprint 2, Sprint 3, or Sprint 4 resolves with HTTP `200` to a Mint-styled roadmap page displaying its target sprint window and navigation back to active Sprint 1 workflows.

---

## 4. Seeded Demo Scenarios (`SprintOneDemoSeeder`)

Run `php artisan migrate:fresh --seed` inside `backend/` to load:
1. **Pending Vendor Audits (`R11`)**: `ElectroBench Diagnostics` (Dhanmondi, distinct GPS vs. storefront address) and `Karnaphuli MicroSolder Lab` (Chattogram, `same_as_gps_location = true`), plus 1 rejected audit trail (`QuickPatch Kiosk`).
2. **Pending Courier Audits (`R1`)**: `Kamal Hossain` and `Tarek Mahmud` ready to Approve/Reject on `/admin/delivery-agents/pending`.
3. **Open Marketplace Bidding Request (`R7` / `R12`)**: *Apple MacBook Pro 14 M2 — Liquid Spill & No Backlight* (`status = BIDDING`) with 2 competing bids — ready for `ProTech Solutions` to submit a 3rd bid or for `customer@meramot.demo` to accept a bid.
4. **Bench Quote 2 Approval Job (`R7`)**: *Samsung Galaxy S23 Ultra — Green Line & Touch Flicker* (`status = WAITING_APPROVAL`, `৳8,900`) — ready for 1-click customer approval and vendor completion -> ledger settlement (`R16`).
5. **Incoming Direct Service Order (`R8`)**: *Direct Service Order (2 items) — ProTech Solutions* (`status = PENDING`, `৳3,000`) — ready to Accept or Decline on `/vendor/dashboard?tab=direct_orders`.
