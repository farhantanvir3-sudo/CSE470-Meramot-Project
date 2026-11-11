# Meramot Project — Context & Knowledge Base (Living Dossier)

> **IMPORTANT NOTICE FOR AI ASSISTANTS & DEVELOPERS**:
> This file is a **persistent living context dossier** designed to survive context window limitations.
> Whenever major design decisions, architectural pivots, schema updates, team allocations, or deadline adjustments are made, **YOU MUST UPDATE THIS FILE** immediately.

---

## 1. Project Overview & Evolution

**Meramot** (মেরামত) is an end-to-end digital electronic device repair marketplace connecting customers, verified repair vendors, doorstep delivery agents, and system administrators.

### Architectural Evolution:
1. **Version 1.0 (Legacy / Historical Prototype)**:
   - Built with Next.js 16 + React 19 + Express.js REST API + Prisma ORM (435 commits by Farhan Tanvir).
   - Prohibited under current academic course regulations due to Express/MERN restriction.
2. **Version 2.0 (Active CSE 470 Project — Launched Nov 1, 2026)**:
   - Built strictly adhering to BRAC University CSE 470 (Software Engineering, Section 06) guidelines under **Faculty Nazmul Islam [NZU]**.
   - **Primary Backend**: Laravel 10 (PHP 8.2) following strict Model-View-Controller (MVC) architecture with PostgreSQL.
   - **Dedicated AI Microservice**: Python 3.11+ / FastAPI on port 5000 utilizing Groq / Google Gemini APIs for device diagnostics (R10) and hardware fault summarization (R13).
   - **Frontend / Client Layer**: Laravel Blade Views, Tailwind CSS, Alpine.js, and Lucide Icons.
   - **Prohibition Enforced**: Express.js, MERN stack, Flask/Django (as main backend) are strictly prohibited.

---

## 2. Active Repository & Team Roster (Version 2.0)

### Primary Repository:
- **GitHub URL**: [`farhantanvir3-sudo/CSE470-Meramot-Project`](https://github.com/farhantanvir3-sudo/CSE470-Meramot-Project)
- **Visibility**: Public
- **Local Synchronized Path**: `C:\Users\USER\Desktop\CSE 470\CSE470-Meramot-Project`
- **Default Branch**: `main`

### Team Group 6 Workload Allocation (20 Granular Single-CRUD Features):
Workload is strictly partitioned into 5 features per developer across 4 Sprints:

| Developer | Student ID | GitHub Handle | Assigned Features | Core Modules | Notification Issue |
| :--- | :---: | :---: | :---: | :--- | :---: |
| **Farhan Tanvir** *(Team Lead)* | **22299179** | [`farhantanvir3-sudo`](https://github.com/farhantanvir3-sudo) | **R11, R12, R13, R14, R15** | Vendor Onboarding, Live Bidding Engine, FastAPI AI Summary Microservice, Multi-Channel BulkSMS, Vendor Analytics | Project Lead |
| **Md. Nafiul Islam** | **22299035** | [`Nafiul141`](https://github.com/Nafiul141) | **R1, R2, R3, R4, R5** | Delivery Agent Onboarding, Delivery Jobs Queue, Rider Earnings Ledger, Live GPS Tracking, Pusher Rider Support Chat | [Issue #1](https://github.com/farhantanvir3-sudo/CSE470-Meramot-Project/issues/1) |
| **Shanid Sajjatuz Islam** | **23201465** | [`perashanid`](https://github.com/perashanid) | **R6, R7, R8, R9, R10** | Repair Shop Search & Filters, Customer Repair Requests, Service Package Cart, Verified Shop Reviews, AI Diagnostic Chat Consultant | [Issue #2](https://github.com/farhantanvir3-sudo/CSE470-Meramot-Project/issues/2) |
| **Md. Nawshad Zaman Khan** | **23301622** | [`Joy-Khan007`](https://github.com/Joy-Khan007) | **R16, R17, R18, R19, R20** | Platform Double-Entry Ledger, SSLCommerz/Stripe Gateway & Invoice, Automated Vendor Payouts, Content Moderation, Immutable Audit Logs | [Issue #3](https://github.com/farhantanvir3-sudo/CSE470-Meramot-Project/issues/3) |

---

## 3. Official 4-Sprint Schedule & Deadlines

| Sprint | Development Window | Scope & Core Deliverables | Official Deadline |
| :---: | :---: | :--- | :---: |
| **Sprint 1** | Nov 1 – Nov 12 *(1.5 Wks)* | **Foundational Setup & Onboarding**: DB schema initialization, R1 (Delivery Agent Onboarding), R6 (Shop Search), R7 (Repair Request Posting), R11 (Vendor Profile Verification), R16 (Platform Ledger) | **Nov 12, 2026** |
| **Sprint 2** | Nov 13 – Dec 1 *(2.5 Wks)* | **Core Bidding Marketplace, AI Microservice & Payments**: R2 (Delivery Jobs Queue), R8 (Package Cart), R12 (Live Bidding Engine), R13 (FastAPI Request Summary Microservice), R17 (SSLCommerz/Stripe Gateway & Invoicing) | **Dec 1, 2026** |
| **Sprint 3** | Dec 2 – Dec 14 *(1.8 Wks)* | **Logistics Tracking, Communications & Settlement**: R3 (Rider Earnings), R4 (Live GPS Location Tracking), R14 (BulkSMS BD Broadcast Engine), R18 (Automated Vendor Commission Settlement), R19 (Sitewide Governance & Moderation) | **Dec 14, 2026** |
| **Sprint 4** | Dec 15 – Dec 20 *(1.0 Wk)* | **AI Troubleshooting, Analytics & Defense Prep**: R5 (Pusher Rider Support Chat), R9 (Shop Reviews), R10 (AI Diagnostic Chat Assistant), R15 (Vendor Financial Analytics), R20 (Immutable Audit Logs), Integration Testing | **Dec 20, 2026** |

---

## 4. System Architecture & Project Layout (Version 2.0)

```text
C:\Users\USER\Desktop\CSE 470\CSE470-Meramot-Project\
├── backend/                              # Laravel 10 MVC Backend API & Web App
│   ├── app/
│   │   ├── Http/Controllers/             # Isolated single-CRUD controllers for R1 to R20
│   │   │   ├── AdminDeliveryController.php   # R1 (Nafiul)
│   │   │   ├── DeliveryJobController.php     # R2 (Nafiul)
│   │   │   ├── RiderLedgerController.php     # R3 (Nafiul)
│   │   │   ├── TrackingController.php        # R4 (Nafiul)
│   │   │   ├── RiderChatController.php       # R5 (Nafiul)
│   │   │   ├── ShopDiscoveryController.php   # R6 (Shanid)
│   │   │   ├── RepairRequestController.php   # R7 (Shanid)
│   │   │   ├── ServicePackageController.php  # R8 (Shanid)
│   │   │   ├── ReviewController.php          # R9 (Shanid)
│   │   │   ├── AIChatController.php          # R10 (Shanid)
│   │   │   ├── VendorOnboardingController.php# R11 (Farhan)
│   │   │   ├── BiddingEngineController.php   # R12 (Farhan)
│   │   │   ├── AISummaryController.php       # R13 (Farhan)
│   │   │   ├── VendorNotificationController.php # R14 (Farhan)
│   │   │   ├── VendorAnalyticsController.php # R15 (Farhan)
│   │   │   ├── AdminLedgerController.php     # R16 (Nawshad)
│   │   │   ├── PaymentGatewayController.php  # R17 (Nawshad)
│   │   │   ├── VendorPayoutController.php    # R18 (Nawshad)
│   │   │   ├── AdminModerationController.php # R19 (Nawshad)
│   │   │   └── AuditLogController.php        # R20 (Nawshad)
│   │   └── Models/                       # Eloquent ORM Models
│   │       ├── User.php, VendorProfile.php, RepairShop.php, ShopService.php, RepairRequest.php, Bid.php, RepairJob.php
│   │       ├── DeliveryAgent.php, DeliveryJob.php, RiderEarnings.php, LocationLog.php
│   │       ├── RiderChatMessage.php, ServicePackage.php, Cart.php, CartItem.php, ShopReview.php
│   │       ├── AIChatSession.php, AIChatMessage.php, FinancialLedger.php, PaymentTransaction.php
│   │       ├── VendorPayout.php, ModerationLog.php, AuditLog.php
│   ├── database/
│   │   ├── factories/                    # Eloquent Model Factories for PostgreSQL Feature Tests
│   │   ├── migrations/                   # Schema migration files covering all 39 PostgreSQL tables
│   │   └── seeders/
│   │       ├── DatabaseSeeder.php        # Master seeder entrypoint
│   │       ├── UserSeeder.php            # System Admin 'Meramot', Logistics Admin, 11 customers, riders & coverage zones
│   │       ├── ShopSeeder.php            # 1,294 verified shops across all 8 Bangladesh divisions + shop_services & shop_staff
│   │       ├── ServicePackageSeeder.php  # 6 fixed-price service packages + active offer_banners
│   │       ├── FinancialLedgerSeeder.php # 50 end-to-end COMPLETED order chains, invoices, warranties, courier legs & dispute refund
│   │       ├── ReviewSeeder.php          # 50 1:1 verified ShopReviews derived from COMPLETED RepairRequests + rating sync
│   │       └── SprintOneDemoSeeder.php   # Pending/rejected audits, live AI session, open bids, Quote 2, support, live GPS & audit logs
│   ├── resources/views/README.md         # Signpost pointing to root frontend/views/
│   ├── routes/
│   │   ├── api.php                       # REST API endpoints
│   │   └── web.php                       # Blade view, Direct-Order Cart & Frontend asset routes
│   ├── tests/Feature/                    # PostgreSQL PHPUnit Feature Suites (R6, R7, R8, R9, R10)
│   ├── ai-microservice/                  # Dedicated Python FastAPI AI Service (Port 5000)
│   │   ├── main.py                       # FastAPI entrypoint (/api/ai/summarize-request, /api/ai/chat)
│   │   ├── requirements.txt              # fastapi, uvicorn, groq, google-genai
│   │   └── .env.example
│   ├── public/images/                    # meramot.svg, cart.svg
│   ├── composer.json
│   ├── phpunit.xml                       # PostgreSQL testing configuration
│   └── .env.example                      # PostgreSQL, SSLCommerz, BulkSMS, Pusher config
│
├── frontend/                             # Separated Frontend Root Folder (Pure Laravel 10 MVC)
│   ├── views/                            # All Blade MVC View Templates (configured via backend/config/view.php)
│   │   ├── layouts/app.blade.php         # Master layout with Meramot header, toast banner & role switcher
│   │   ├── admin/                        # verify-agent, financial-overview, payouts, moderation, audit-logs
│   │   ├── packages/                     # catalog, cart (Unified Direct-Order + Quickmatch), checkout
│   │   ├── requests/                     # create, track (16-state Dual-Quote timeline)
│   │   ├── delivery/ & tracking/         # register, jobs-queue, earnings, live-map
│   │   ├── shops/ & reviews/             # explore, show, write-review
│   │   ├── vendor/                       # onboarding, bidding-board, request-details, alert-settings, dashboard
│   │   └── ai-consultant/                # chat (Alpine reactive chat + quick symptom prompts)
│   ├── js/                               # Modular Client Scripts (served via FrontendAssetController)
│   │   ├── geolocation-helper.js         # Browser GPS helper
│   │   └── marketplace-client.js         # Non-blocking Cart, Geolocation & AI Chat client module
│   ├── public/                           # meramot.svg, cart.svg, icons
│   ├── src/styles/main.css               # Tailwind CSS & Mint/Forest-Green CSS variable tokens
│   ├── package.json
│   └── tailwind.config.js
│
├── docs/                                 # CSE470 deliverables & architecture documentation
│   ├── CSE470_Sprint_Roadmap.md
│   └── CSE470_System_Architecture.md
│
├── .gitignore
└── README.md
```

---

## 5. Historical Repositories & Archives (Version 1.0)

- **Legacy Frontend Standalone**: [`farhantanvir3-sudo/meramot-frontend`](https://github.com/farhantanvir3-sudo/meramot-frontend) (Private)
- **Legacy Course Group Repo**: [`Nafiul141/Meramot_CSE470_G-6`](https://github.com/Nafiul141/Meramot_CSE470_G-6) (Historical submission)

---

## 6. Core Business Logic & Invariants

1. **Platform Commission**: Fixed at **10.00%** on all accepted repair jobs, deducted automatically during vendor payout settlement.
2. **Reverse Bidding Mechanics**: Technicians bid itemized quotes (parts cost + labor fee + turnaround days + warranty days) on customer repair requests.
3. **Escrow & Payment Flow**: Customers pay online via SSLCommerz sandbox; funds are held in platform escrow until the customer signs off delivery via a 4-digit OTP.
4. **Logistics & Milestone Synchronization**:
   - `pickup_from_customer` -> `deliver_to_shop` -> `return_to_customer`
   - Verified by dual OTPs (pickup OTP and dropoff OTP) and real-time GPS coordinate tracking via Leaflet and Pusher.
5. **AI Diagnostic Integration**: Unstructured user symptom text is parsed by the Python FastAPI microservice into structured hardware fault categories before technicians submit quotes.

---

## 7. Frontend Design System & UI/UX Tokens (Faithful Port from V1)

The Meramot frontend is built with an unmistakable, fresh mint and deep forest green visual aesthetic ported directly from legacy Meramot:

### Color Palette Tokens:
```css
:root {
  --background: #E4FCD5;       /* Primary page mint background */
  --foreground: #203126;       /* Deep forest green typography */
  --card: #c8e7c5;             /* Soft pastel mint card background */
  --card-foreground: #203126;  /* Card text */
  --muted: #b1c9aa;            /* Subtle borders & divider lines */
  --muted-foreground: #5e6d64; /* Secondary captions & timestamps */
  --border: #556c527a;         /* Card & input borders */
  --mint-50: #b5d4ad;          /* Hover highlight */
  --mint-100: #e4f3df;         /* Badge backgrounds & avatar boxes */
  --mint-200: #c4ddbd;         /* Input hover / active states */
  --mint-300: #b8d9b5;         /* Search inputs & form fields */
  --mint-500: #7eae77;         /* Accent highlights */
  --mint-700: #436440;         /* Deep green accents */
  --accent-dark: #244229;      /* Primary action buttons & headers */
  --accent-foreground: #ffffff;
}
```

### Component Styling & Design Choices:
1. **Cards & Badges**: Distinctive rounded pill curvature (`rounded-[1.6rem]`, `rounded-2xl`, `rounded-full`).
2. **Master Header (`layouts/app.blade.php`)**:
   - Original `meramot.svg` logo linked to `/`.
   - Metro Location Selector dropdown ("Banani, Dhaka", "Gulshan", "Dhanmondi", "All Dhaka").
   - Category navigation tabs: `Courier Pickup`, `In-shop Repair`, `Spare Parts`, and `Packages`.
   - Live Search bar with `--mint-300` background and search icon.
   - Quick action pills: AI Diagnostic Consultant, New Request, Shopping Cart (`cart.svg`), and Role Switcher.
3. **Public Unauthenticated Views Built**:
   - **Homepage (`/`)**: Sidebar filters, promo carousel cards, popular categories with demand trend tags (📱, 💻, 🧾, ⌚, 🎮, 🖨️), featured repair shops grid, and hot search pills.
   - **Explore Shops (`/shops` & `/shops/explore`)**: Full multi-filter search, metro area selector pills, 4 sort modes (Top Rated, Lowest Price, Nearest Distance, Best Match), promo toggles (Vouchers, Free delivery, Deals), and rich shop result cards with ratings, review counts, ETA, inspection fees, and specialties.
   - **Shop Profile (`/shops/{slug}`)**: Comprehensive shop header, inspection/labor fee transparency, technical specialties list, common repair estimates, verified customer reviews (Feature R9), and related neighborhood shops.
   - **Service Packages Catalog (`/packages/catalog`)**: 6 pre-configured repair packages with warranty, turnaround hours, inclusions, and session cart.
   - **Create Repair Request (`/requests/create`)**: Multi-device intake form with AI microservice triage link.
   - **AI Diagnostic Consultant (`/ai-consultant/chat`)**: Interactive chat interface querying FastAPI microservice.

---

## 8. Database Seeds & Baseline Dataset (Version 2.0)

The PostgreSQL database is seeded with authentic records ported from the historical prototype:
- **Exact Schema Implementation (39 Tables)**: Fully ported from the original `schema.prisma` into native Laravel 10 migrations, ensuring 100% column-for-column and relation-for-relation compatibility without using Node/Express or Prisma:
  - `users`: Core authentication with roles (`customer`, `vendor`, `admin`, `delivery`, `delivery_admin`), status, geocoordinates.
  - `vendor_profiles`: Business registration matching `VendorApplication` (trade license, specialties, shop coordinates, verification state).
  - `repair_shops`: 32 authentic shops matching `Shop` (inspection fees, base labor fees, opening hours, vouchers, free delivery, categories).
  - `shop_services` & `shop_staff`: Specific service catalog items and multi-technician shop membership.
  - `repair_requests`: Customer problem descriptions, symptom checklists, diagnostic fees, and delivery preferences.
  - `bids` & `repair_jobs`: Reverse-bidding quotes (parts + labor + turnaround) and repair workflow state machine.
  - `delivery_agents`, `coverage_zones`, `rider_coverages`, `delivery_jobs`, `rider_chat_messages`: Full logistics ecosystem with OTP verification and GPS coordinate logging.
  - `invoices`, `invoice_line_items`, `payment_transactions`, `financial_ledgers`, `vendor_payouts`, `escrow_ledgers`, `refunds`: Double-entry accounting with 10% platform commission deductions.
  - `shop_reviews`, `service_packages`, `carts`, `cart_items`, `ai_chat_sessions`, `ai_chat_messages`, `support_tickets`, `support_messages`, `warranties`, `recently_viewed`, `notification_logs`, `offer_banners`, `dispute_cases`, `dispute_notes`, `moderation_logs`, `audit_logs`.
- **32 Repair Shops**: All 12 specialty/featured repair hubs from `mock-data.ts` (ProTech Solutions, The Gadget Den, Budget Fixer, MacMaster Repairs, etc.) plus 20 core Dhaka metro shops spanning Banani, Gulshan, Dhanmondi, Mirpur, Uttara, Bashundhara, Mohammadpur, Farmgate, Tejgaon, Wari, Rampura, and New Market.
- **32 Vendor Profiles**: Synchronized with `repair_shops` table to satisfy Feature R11.
- **6 Fixed Service Packages**: Seeded with real turnaround hours and warranty terms (Feature R8).
- **Verified Customer Reviews**: Authentic reviews seeded for top shops (Feature R9).
- **Core User Accounts**: Admin, Delivery Admin, Customer, and Vendor demo credentials.

---

## 9. Local Execution & AI Microservice Port Management

- **Laravel 10 Backend**: Runs on `http://127.0.0.1:8000` via `run-backend.bat`.
- **FastAPI AI Microservice**: Runs on port `5000` via `run-ai.bat`.
  - **Socket Error 10013 Resolution**: If port 5000 is occupied by a previously running process or Hyper-V reservation, `run-ai.bat` automatically notifies the user and launches on fallback port `5001`.
- **Database**: PostgreSQL 18 service (`postgresql-x64-18`), database `meramot`, user `meramot_user`.

---

## 10. Developer & Academic Defense Mode Architecture

To preserve a 100% clean, professional consumer-facing product while simultaneously supporting academic grading defense for CSE 470, the system features a hidden **Academic Defense / Dev Mode**:

1. **Default Production View (`dev=0` / normal access)**:
   - Completely hides all academic banners, university names, faculty tags, and internal feature codes (`Feature R... • Lead: [Student Name & ID]`).
   - Browser tab titles render clean without feature codes (e.g., `Explore Verified Repair Shops in Dhaka | Meramot`).
   - The top navigation ribbon is completely omitted from the layout.
   - The footer displays authentic customer service links (`Post Repair Request`, `AI Fault Diagnostics`, `Standard Fixed Packages`, `Register as Partner Shop`).
2. **Academic Defense Mode (`dev=1`)**:
   - Displays the dark green top ribbon: `[CSE 470 Group 6 • Dev Mode] Faculty: Nazmul Islam [NZU]` with one-click role switcher links (`Customer`, `Vendor Portal`, `Rider Board`, `Admin Console`) and `Exit Dev Mode`.
   - Surfaces individual feature code tags and student ownership badges (`.dev-only-badge`) next to each section header for immediate grading traceability during vivas.
   - Restores the academic course information column in the footer.
3. **How to Toggle**:
   - **URL Query Parameter**: Append `?dev=1` to any page URL to activate (persists in session), or `?dev=0` to deactivate.
   - **Keyboard Shortcut**: Press `Ctrl + Shift + D` on any page to toggle instantaneously.
   - **Footer Button**: Click the discreet `Dev` toggle link in the footer copyright row.

---

## 11. Design Fidelity to Commit `8e44218a3c09c9d2e79907e507dcbbdc72767d4c` (Farhan's Original Vision)

- **Context**: In the legacy project, merge conflicts and groupmate PRs caused the deployed frontend to deviate from the intended vision. Commit `8e44218a3c09c9d2e79907e507dcbbdc72767d4c` (and preceding commits by Farhan) represents the true intended UX/UI design.
- **Strict MVC Rule**: The system retains strict Laravel 10 (PHP 8.2) MVC architecture + PostgreSQL 15 + FastAPI microservice. The legacy repository remains completely untouched.
- **Ported UI / UX Elements**:
  1. **AuthCard & Google Button (`auth/login.blade.php`, `auth/register.blade.php`)**:
     - Signature green gradient shimmer on Google button (`hover:border-[#214c34]/40 hover:shadow-[0_0_20px_-5px_rgba(33,76,52,0.3)]` with `bg-gradient-to-r from-transparent via-[#214c34]/[0.07] to-transparent`).
     - Real-time password strength checklist (8+ chars, uppercase, lowercase, number, special char).
     - Delivery partner login link in deep navy (`text-[#1b365d]`) on sign-in only (Commit `d9490a9`).
     - Vendor and rider registration links displayed on sign-up only (Commit `1eb676e`).
     - Full authentication controller (`AuthController.php`) with session auth and 1-click demo login chips.
  2. **Master Layout & Header (`layouts/app.blade.php`)**:
     - Exact branding logo scale: `h-[4.62rem] md:h-[5.1975rem] lg:h-[6.3525rem]`.
     - Center Location button (`NavbarLocationButton`) with Dhaka neighborhood selector modal.
     - Cart button with `cart.svg` (`h-[45px] w-[45px]`).
     - Category tabs with active `border-b-[3px]` indicator (`Courier Pickup`, `In-shop Repair`, `Spare Parts`).
     - Mobile drawer (`mobile-sidebar-panel`) with `drawerSlideIn` animation and dark blur backdrop.
     - Logout confirmation modal ("Are you sure? You will be logged out...").
  3. **Homepage (`home.blade.php`)**:
     - `OfferCarousel`: 2-column cards grid (`grid grid-cols-2 gap-3 md:gap-4`) with "Offer available" uppercase tracking, bold typography, and `Explore shops` pill buttons.
     - `FeaturedShops`: Desktop 3-column grid + Mobile 2-column compact natural-height tiles with starting-from price and voucher/free delivery badges.
     - `PopularCategories`: 3-column grid with device sprites (📱, 💻, 🧾, ⌚, 🎮, 🖨️).
     - `RecentlyViewed`: Pill chips linking to past search queries.
  4. **Explore Directory (`shops/explore.blade.php`)**:
     - Two-column desktop layout with sticky `SidebarFilters` (Sort radio dots, Distance range slider, Offers & savings checkboxes).
     - Area selector pills (All, Banani, Gulshan, Dhanmondi, Mirpur, Uttara, etc.).
     - Rich `ShopResultCard` with the 3-column stats box (`INSPECTION FEE | LABOR FEE | ETA`).
  5. **Customer Request Wizard (`requests/create.blade.php`)**:
     - All 29 electronic device types and 12 issue categories.
     - Distinguishing specs fields (chipset, display size, release year).
  6. **Vendor Dashboard (`vendor/dashboard.blade.php`)**:
     - Tabbed UI (`Overview`, `Marketplace Bids`, `Direct Orders`, `My Repair Jobs`) ported from Farhan's commit `aff5aff`.
   7. **Explore Directory Desktop Fixes (`shops/explore.blade.php`)**:
      - Resolved desktop right-side cutoff by setting CSS grid track to `lg:grid-cols-[240px_minmax(0,1fr)]` matching `shops-results-client.tsx`.
      - Enforced `min-w-0 max-w-full` on the main content column and chip containers to eliminate horizontal blowout.
      - Styled horizontal area chip lists with `.no-scrollbar` to suppress intrusive native OS scrollbars.
   8. **Shop Profile & Reviews (`shops/show.blade.php`)**:
      - Ported `frontend/src/app/shops/[slug]/page.tsx` from commit `8e44218`.
      - Hero banner with avatar/logo, address, contact, and quick booking box.
      - Services catalog with starting price estimates and repair booking actions.
      - Exact two-card reviews layout: Left score card with 5★–1★ breakdown progress bars; Right card with verified customer feedback cards and area chips.

---

## 12. Sprint 1 Teammate Merge & Unified Direct-Order Architecture

1. **Two-Root-Folder Separation (`frontend/` & `backend/`) on Pure Laravel 10 MVC**:
   - All Blade views live in `frontend/views/` (loaded via `backend/config/view.php`).
   - All client-side JavaScript modules live in `frontend/js/` (`geolocation-helper.js`, `marketplace-client.js`), served cleanly via `FrontendAssetController` (`/js/{filename}`, `/styles/{filename}`).
   - `backend/` strictly houses PHP controllers, Eloquent models, services, PostgreSQL migrations, factories, and PHPUnit feature tests.
2. **PostgreSQL Enforced Everywhere**:
   - Reverted accidental SQLite config in `backend/.env.example` back to `DB_CONNECTION=pgsql`.
   - Fixed PostgreSQL Haversine distance queries in `ShopDiscoveryController.php` using clamped `LEAST(1.0, GREATEST(-1.0, ...))` and `whereRaw`.
3. **Unified Direct-Order + Quickmatch Cart (`ServicePackageController` & `StatusTransitionController`)**:
   - Customers can add shop-specific services from `/shops/{slug}` (locking that `RepairShop`) or fixed-price packages from `/packages/catalog` (with Quickmatch or manual verified shop selection).
   - Checking out `/cart` (`POST /cart/checkout/place`) configures Pickup Schedule (`NOW` / `LATER`), Courier Tier (`REGULAR` ৳80 / `EXPRESS` ৳150), 5% min ৳30 escrow protection fee, Dhaka address + GPS coordinates, and Payment Method (`CASH` / `SSLCOMMERZ`), atomically creating a `RepairRequest` (`status = PENDING`, `source = DIRECT_SERVICE`), pre-linked `RepairJob` (`status = CREATED`), and `PaymentTransaction`.
   - Vendors review incoming orders in `/vendor/dashboard` (**Direct Orders** tab) to **Accept** (`PENDING -> ASSIGNED`) or **Decline** (`PENDING -> REJECTED` with automatic escrow refund).
4. **16-State Dual-Quote Lifecycle (`RepairRequest` & `requests/track.blade.php`)**:
   - Supports both Marketplace Quote 1 (`BIDDING -> ASSIGNED`) and Bench Diagnosis Quote 2 (`WAITING_APPROVAL -> REPAIRING` on customer approval, or `RETURN_SCHEDULED` on customer decline).

---

## 13. Nationwide Bangladesh OSM Directory, Multi-Branch Architecture & Sprint 1 Demo Readiness (Nov 12, 2026)

1. **Nationwide Bangladesh OpenStreetMap Shop Seeding & Dual Shop Addresses**:
   - Seeded **1,297 Repair Shops** across all 8 divisions of Bangladesh (`ShopSeeder` + `SprintOneDemoSeeder`), sourced from real OpenStreetMap tech/electronics/mobile/computer storefronts plus curated multi-branch flagship partners.
   - Every shop maintains **Dual Addresses**:
     1. **GPS Approximate Location** (`latitude`, `longitude`, `gps_address` — used server-side for Haversine distance sorting and kept hidden from public JSON/views).
     2. **Vendor-Provided Storefront Location** (`address`, `city`, `area` — displayed publicly).
     3. Optional **`same_as_gps_location`** toggle during onboarding (`/vendor/onboarding`) or later in the Vendor Dashboard (`/vendor/dashboard?tab=branches`).
2. **Multi-Branch Vendor Expansion (`is_branched` / `is_branch` / `branch_name`)**:
   - Vendors can operate multiple shops across Bangladesh under one brand name (`ProTech Solutions`, `Apple Gadgets Care`, `Miraz Telecom`, etc.), distinguished by exact **Branch Tags** (`[Banani DOHS Flagship]`, `[Uttara Sector 7]`, `[Agrabad, Chattogram]`) on shop tiles, profiles, bids, and ledger records.
3. **Complete Sprint 1 End-to-End Workflows**:
   - **Unified Admin Console (`/admin`)** with shared sub-navigation (`frontend/views/admin/partials/nav.blade.php`) connecting Vendor Verification (`R11`), Courier Verification (`R1`), and Platform Financial Double-Entry Ledger (`R16`).
   - **Feature R11 Completed End-to-End**: Guest-safe `/vendor/onboarding` with status banners (`approved` / `pending` / `rejected` + `rejection_reason`), paired with `/admin/vendors/pending` (`Approve`, `Reject` with mandatory reason, and `Featured` toggle).
   - **Feature R7 / R12 / R15 / R16 Live Sync**: Customer Request History Hub (`/requests` & `/orders`), real Vendor Quote 1 bidding (`POST /vendor/requests/{id}/bid`), Quote 2 bench updates (`WAITING_APPROVAL`), and automatic 10% platform commission / 90% vendor net double-entry `FinancialLedger` settlement when a vendor marks a `RepairJob` `COMPLETED`.
   - **Zero Dead Links / Universal `"Coming Soon"` Landing (`ComingSoonController` & `frontend/views/coming-soon.blade.php`)**: Every button, form, and link scheduled for Sprint 2, Sprint 3, or Sprint 4 routes cleanly to a Mint-styled `"Coming Soon"` roadmap page with zero `href="#"`, zero 404s, and zero 500s.
4. **100% End-to-End Relational Seed Interconnection & E-Commerce Integrity**:
   - **System Admin Identity**: Official System Admin account is **`Meramot`** (`admin@meramot.demo`), alongside **`Meramot Logistics`** (`delivery.admin@meramot.demo`).
   - **Zero Pre-Onboarding Ratings/Reviews**: Pending (`ElectroBench Diagnostics`, `Karnaphuli MicroSolder Lab`) and rejected (`QuickPatch Kiosk`) vendor applications strictly carry `0.00` rating, `0` reviews, `is_public = false`, and `accepts_direct_orders = false` until approved by Admin.
   - **Full 38-Domain-Table Referential Graph**:
     - Every `ShopReview` (`50` total: `49` visible + `1` hidden/moderated spam review) links `1:1` via `repair_request_id` to a `COMPLETED` `RepairRequest` by that exact customer at that exact shop; `RepairShop.rating`, `RepairShop.review_count`, and `VendorProfile.rating` are mathematically aggregated strictly from visible reviews.
     - Every `COMPLETED` `RepairRequest` (`50` orders across flagship multi-branch hubs, curated metro shops, and OpenStreetMap multi-branch storefronts) connects upstream & downstream across `recently_viewed` -> (`Cart` `CHECKED_OUT` + `CartItem` + `ServicePackage.order_count` or `Bid`s) -> `RepairJob` (`COMPLETED`) -> `warranties` (`90`-day) -> round-trip `DeliveryJob`s + `LocationLog`s + `RiderEarnings` (city-matched approved couriers & `coverage_zones` when `supports_pickup = true`) -> `invoices` (`PAID`) + `invoice_line_items` -> `payment_transactions` (`PAID`) -> `escrow_ledgers` (`HOLD` & `RELEASE`) -> `financial_ledgers` (`CUSTOMER_PAYMENT`, `PLATFORM_COMMISSION`, `VENDOR_EARNING`, `DELIVERY_FEE`, `DELIVERY_PAYOUT`).
     - Governance, dispute, AI triage, support, and live tracking tables (`vendor_profiles.reviewed_by_admin_id`, `shop_staff`, `rider_coverages`, `rider_chat_messages`, `dispute_cases` -> `dispute_notes` -> `refunds` -> `REFUND` ledger, `support_tickets` -> `support_messages`, `ai_chat_sessions` -> `ai_chat_messages`, `notification_logs`, `offer_banners`, `moderation_logs`, `audit_logs`, `vendor_payouts`) are 100% referentially synced, and runtime controllers (`VendorBranchController`, `VendorOnboardingController`, `AdminDeliveryController`, `ServicePackageController`, `ReviewController`, `AIChatController`) preserve these exact links on live actions.
   - **Automated Verification**: **69 PHPUnit tests (388 assertions)** passing 100% against PostgreSQL.
