# Meramot (Meeramoot) Project — Context & Knowledge Base (Living Dossier)

> **IMPORTANT NOTICE FOR AI ASSISTANTS & DEVELOPERS**:
> This file is a **persistent living context dossier** designed to survive context window limitations.
> Whenever major design decisions, architectural pivots, schema updates, team allocations, or deadline adjustments are made, **YOU MUST UPDATE THIS FILE** immediately.

---

## 1. Project Overview & Evolution

**Meramot** (মেরামত) is an end-to-end digital electronic device repair marketplace connecting customers, verified repair vendors, doorstep delivery agents, and system administrators.

### Architectural Evolution:
1. **Version 1.0 (Legacy / Historical Prototype)**:
   - Built with Next.js 16 + React 19 + Express.js REST API + Prisma ORM.
   - 435 commits by Farhan Tanvir in [`AsirMahmud/Meramot-Electiontric-Item-reparing-management-system-`](https://github.com/AsirMahmud/Meramot-Electiontric-Item-reparing-management-system-).
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
│   │       ├── User.php, VendorProfile.php, RepairShop.php, RepairRequest.php, Bid.php
│   │       ├── DeliveryAgent.php, DeliveryJob.php, RiderEarnings.php, LocationLog.php
│   │       ├── RiderChatMessage.php, ServicePackage.php, ShopReview.php, AIChatSession.php
│   │       ├── FinancialLedger.php, PaymentTransaction.php, VendorPayout.php
│   │       ├── ModerationLog.php, AuditLog.php
│   ├── database/
│   │   ├── migrations/                   # 6 Schema migration files covering all 20 features
│   │   └── seeders/
│   │       ├── DatabaseSeeder.php        # Master seeder entrypoint
│   │       ├── ShopSeeder.php            # 32 original shops from legacy Meramot with lat/lng
│   │       └── UserSeeder.php            # Admin, Customer, Vendor & Rider baseline accounts
│   ├── resources/views/                  # Blade MVC View Templates
│   │   ├── layouts/app.blade.php         # Master layout with Meramot header & role switcher
│   │   ├── admin/                        # verify-agent, financial-overview, payouts, moderation, audit-logs
│   │   ├── customer/ & requests/         # create-request, track-request
│   │   ├── delivery/ & tracking/         # jobs-queue, earnings, live-map
│   │   ├── shops/ & reviews/             # explore, show, write-review
│   │   ├── vendor/                       # onboarding, bidding-board, request-details, alert-settings, dashboard
│   │   └── ai-consultant/                # chat
│   ├── routes/
│   │   ├── api.php                       # REST API endpoints
│   │   └── web.php                       # Blade view routes
│   ├── ai-microservice/                  # Dedicated Python FastAPI AI Service (Port 5000)
│   │   ├── main.py                       # FastAPI entrypoint (/api/ai/summarize-request, /api/ai/chat)
│   │   ├── requirements.txt              # fastapi, uvicorn, groq, google-genai
│   │   └── .env.example
│   ├── public/images/                    # meramot.svg, cart.svg
│   ├── composer.json
│   └── .env.example                      # PostgreSQL, SSLCommerz, BulkSMS, Pusher config
│
├── frontend/                             # Frontend views & asset compilation pipeline
│   ├── public/                           # meramot.svg, cart.svg, icons, index.html preview
│   ├── src/components/                   # Navbar.js, BiddingBoardCard.js
│   ├── src/styles/main.css               # Tailwind CSS tokens
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

- **Legacy Shared Repo**: [`AsirMahmud/Meramot-Electiontric-Item-reparing-management-system-`](https://github.com/AsirMahmud/Meramot-Electiontric-Item-reparing-management-system-) (Public, 435 commits by Farhan)
- **Legacy Frontend Standalone**: [`farhantanvir3-sudo/meramot-frontend`](https://github.com/farhantanvir3-sudo/meramot-frontend) (Private)
- **Legacy Course Group Repo**: [`Nafiul141/Meramot_CSE470_G-6`](https://github.com/Nafiul141/Meramot_CSE470_G-6) (Historical submission)
- **Legacy Local Clone**: `C:\Users\USER\Desktop\Meeramoot-Electiontric-Item-reparing-management-system-`
- **Legacy Zip Archives**:
  - `C:\Users\USER\Desktop\Meeramoot-Electiontric-Item-reparing-management-system-.zip`
  - `C:\Users\USER\Desktop\Meeramoot-Electiontric-Item-reparing-management-system- (2).zip`
  - `C:\Users\USER\Desktop\Meeramoot-Electiontric-Item-reparing-management-system- (3).zip`

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
