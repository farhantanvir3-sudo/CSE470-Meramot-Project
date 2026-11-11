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

All Sprint 1 requirements (`R1`, `R6`, `R7`, `R11`, `R16`) plus role-aware authentication, the unified Quickmatch / Direct-Order Cart (`R8`), Multi-Branch Vendor Expansion, Dual Shop Addressing, nationwide Bangladesh OpenStreetMap seeding (`1,297` shops), and 100% end-to-end relational interconnection across all PostgreSQL domain tables are live and verified by **69 automated PHPUnit tests (388 assertions)**.

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
  - One-click demo logins: `/auth/demo?role=delivery` (`rider.demo@meramot.test`), `/auth/demo?role=delivery_admin` (`delivery.admin@meramot.demo`), and `/auth/demo?role=admin` (`admin@meramot.demo` — `Meramot`).
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
  - `R8` (`ServicePackageController`): Full Quickmatch & locked-shop Direct-Order Cart (`/packages/catalog` -> `/cart` -> `/cart/checkout`), restricted strictly to active public shops (`is_active = true`, `is_public = true`) and linking `requested_service_id` on `RepairRequest`.
  - `R9` (`ReviewController`): Auto-links `repair_request_id` to the customer's latest `COMPLETED` `RepairRequest` at that shop and recalculates `RepairShop.rating`, `RepairShop.review_count`, and `VendorProfile.rating` strictly from visible (`is_hidden = false`) reviews.
  - `R10` (`AIChatController` + `frontend/views/ai-consultant/chat.blade.php`): Persists authenticated `AIChatSession` & `AIChatMessage` threads while providing client-side symptom-aware hardware diagnostic triage when the FastAPI microservice on port `5000` is offline.

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
  - **End-to-End Marketplace Coupling**: Marking any `RepairJob` as `COMPLETED` (`VendorBranchController@updateJobStatus`) automatically generates a `PAID` `Invoice` + `invoice_line_items`, a 90-day `Warranty`, a `PAID` `PaymentTransaction` (`invoice_id` linked), `EscrowLedger` (`HOLD` + `RELEASE`), and calls `AdminLedgerController::settleOrderWithCommission()` to post the `100%` Customer Credit, `10%` Platform Commission Credit, and `90%` Vendor Net Earning Debit entries.
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

## 4. End-to-End Interconnected Seed Dataset (`DatabaseSeeder`)

Run `php artisan migrate:fresh --seed` inside `backend/` to load a 100% referentially synced marketplace across all PostgreSQL tables:
1. **50 Historical `COMPLETED` Order Chains (`FinancialLedgerSeeder` + `ReviewSeeder`)**: Every single `ShopReview` (`49` visible + `1` hidden/moderated spam review) links `1:1` via `repair_request_id` to a `COMPLETED` `RepairRequest` with its full `recently_viewed` -> (`Cart`/`CartItem`/`ServicePackage.order_count` or `Bid`s) -> `RepairJob` -> 90-day `Warranty` -> round-trip `DeliveryJob`s + `LocationLog`s + `RiderEarnings` -> `Invoice` + `InvoiceLineItem`s -> `PaymentTransaction` -> `EscrowLedger` (`HOLD`/`RELEASE`) -> `FinancialLedger` settlement chain, plus 1 `DisputeCase` -> `DisputeNote` -> `Refund` (`REFUND` ledger) workflow.
2. **Pending & Rejected Vendor Audits (`R11`)**: `ElectroBench Diagnostics` (Dhanmondi, distinct GPS vs. storefront address) and `Karnaphuli MicroSolder Lab` (Chattogram, `same_as_gps_location = true`) with `0.00` rating / `0` reviews, plus 1 rejected audit trail (`QuickPatch Kiosk`) linked to `ModerationLog` and `AuditLog`.
3. **Pending & Rejected Courier Audits (`R1`)**: `Kamal Hossain` and `Tarek Mahmud` (`PENDING`) plus `Sohag Mia` (`REJECTED`, logged in `audit_logs`) alongside approved Dhaka & Chattogram riders (`Rahim Uddin`, `Tariqul Islam`) mapped to 8 `coverage_zones` via `rider_coverages`.
4. **Open Marketplace Bidding Request (`R7` / `R10` / `R12`)**: *Apple MacBook Pro 14 M2 — Liquid Spill & No Backlight* (`status = BIDDING`), preceded by an `AIChatSession` + `AIChatMessage`s and backed by 2 competing bids + `notification_logs`.
5. **Bench Quote 2 Approval + Support Thread (`R7`)**: *Samsung Galaxy S23 Ultra — Green Line & Touch Flicker* (`status = WAITING_APPROVAL`, `৳8,900`), backed by inbound `DELIVERED` courier job `MRM-DLV-2001`, SMS `notification_logs`, and an active `support_tickets` + `support_messages` thread.
6. **Incoming Direct Service Order (`R8` / `R2`)**: *Direct Service Order — Laptop High-Capacity Battery Replacement* (`status = PENDING`, `৳3,200`), synced with `ServicePackage.order_count`, `CHECKED_OUT` `Cart` + `CartItem`, `PENDING` `PaymentTransaction`, and unclaimed `DeliveryJob` `MRM-DLV-2002`.
7. **Live Courier Transit & Rider Chat (`R2` / `R4` / `R5`)**: *Apple iPad Pro 11 M2 — Cracked Digitizer & Touch Drift* (`status = PICKED_UP`) with active `IN_TRANSIT` `DeliveryJob` `MRM-DLV-2003`, 3 GPS `LocationLog` pings rendered live on `/tracking/{jobId}`, and `RiderChatMessage` dispatches on `/support/rider-chat/{jobId}`.
