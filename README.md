# Meramot — Digital Electronic Repair Marketplace

[![BRAC University](https://img.shields.io/badge/BRAC_University-CSE470_Software_Engineering-0056b3?style=for-the-badge)](https://www.bracu.ac.bd/)
[![Course Faculty](https://img.shields.io/badge/Faculty-Nazmul_Islam_[NZU]-blueviolet?style=for-the-badge)](#)
[![Section](https://img.shields.io/badge/Section-06-orange?style=for-the-badge)](#)
[![Group](https://img.shields.io/badge/Group-06-green?style=for-the-badge)](#)

---

## 1. Project Overview

**Meramot** (মেরামত) is an end-to-end, multi-actor digital marketplace and lifecycle management platform for electronic device repair services. The platform connects consumers facing hardware/software device faults with verified local repair vendors, automated AI diagnostics, transparent reverse-bidding quotes, doorstep logistics delivery agents, and secure payment settlements.

### Academic Context
- **Course**: CSE 470: Software Engineering
- **Faculty**: Nazmul Islam [NZU] | Section 06
- **Project Window**: November 1, 2026 – December 20, 2026 (49 Days / 4 Sprints)

---

## 2. Team Roster & Equal Workload Distribution

The development workload is partitioned equally among all 4 group members, with exactly **5 single-CRUD functional features per member (20 features total)** following faculty guidelines:

| Member Name | Student ID | GitHub Username | Assigned Feature IDs | Core Modules |
| :--- | :---: | :---: | :---: | :--- |
| **Farhan Tanvir** *(Team Lead)* | **22299179** | [`farhantanvir3-sudo`](https://github.com/farhantanvir3-sudo) | **R11, R12, R13, R14, R15** | Vendor Onboarding, Live Bidding Engine, FastAPI AI Summary Microservice, Multi-Channel BulkSMS, Vendor Analytics |
| **Md. Nafiul Islam** | **22299035** | [`Nafiul141`](https://github.com/Nafiul141) | **R1, R2, R3, R4, R5** | Delivery Onboarding, Delivery Jobs Queue, Rider Earnings Ledger, Live GPS Tracking, Pusher Rider Support Chat |
| **Shanid Sajjatuz Islam** | **23201465** | [`perashanid`](https://github.com/perashanid) | **R6, R7, R8, R9, R10** | Repair Shop Search & Filters, Customer Repair Requests, Service Package Cart, Verified Shop Reviews, AI Diagnostic Chat Consultant |
| **Md. Nawshad Zaman Khan** | **23301622** | [`Joy-Khan007`](https://github.com/Joy-Khan007) | **R16, R17, R18, R19, R20** | Platform Double-Entry Ledger, SSLCommerz/Stripe Gateway & Invoice, Automated Vendor Payouts, Content Moderation, Immutable Audit Logs |

---

## 3. Architecture & Strict Course Constraints

In strict accordance with CSE470 course guidelines and faculty requirements:
- **Primary Backend**: Built using **Laravel 10 (PHP 8.2)** adhering strictly to the **Model-View-Controller (MVC)** architectural pattern.
- **Dedicated AI Microservice**: Implemented via an isolated **Python 3.11+ / FastAPI** microservice for LLM diagnostic inference (Groq / Google Gemini APIs).
- **Frontend / Client Layer**: **Laravel Blade Views**, **Tailwind CSS**, and modern reactive components (Alpine.js / ES6 Modules).
- **Database**: **PostgreSQL** relational database with Eloquent ORM migrations and relationships.
- **Strict Prohibition Notice**: MERN stack and Express.js backend architectures are strictly prohibited and not utilized anywhere in this system.

---

## 4. Repository Structure

```text
CSE470-Meramot-Project/
├── backend/                  # Laravel 10 MVC Backend API & Web Application
│   ├── app/
│   │   ├── Http/Controllers/ # Feature Controllers (R1-R20)
│   │   ├── Http/Middleware/  # Auth, Passkey, Role-based access control
│   │   ├── Models/           # Eloquent Models (User, VendorProfile, RepairRequest, etc.)
│   │   └── Services/         # Gateway, SMS, AI client abstractions
│   ├── config/               # Database, auth, mail, and service configs
│   ├── database/
│   │   ├── migrations/       # PostgreSQL schema migrations
│   │   └── seeders/          # Role, permission, and test data seeders
│   ├── routes/
│   │   ├── api.php           # REST API routes for clients and microservice integration
│   │   └── web.php           # Web routes for Blade MVC views
│   ├── ai-microservice/      # Python FastAPI Microservice for AI Diagnostic & Summary (R10, R13)
│   │   ├── app/
│   │   │   ├── routers/      # AI endpoints (diagnose, summarize, consult)
│   │   │   └── services/     # Groq & Gemini API clients
│   │   ├── main.py           # FastAPI entrypoint
│   │   └── requirements.txt
│   ├── composer.json
│   └── .env.example
│
├── frontend/                 # Frontend Views, Assets & Reactive UI Components
│   ├── src/
│   │   ├── components/       # Reusable UI components (Bidding cards, Navbars, Modals)
│   │   ├── views/            # Customer, Vendor, Rider & Admin screens
│   │   └── styles/           # Tailwind CSS directives & tokens
│   ├── public/               # Logos, icons, and static images
│   ├── package.json
│   └── tailwind.config.js
│
├── docs/                     # Course Documentation & Reports
│   ├── CSE470_Sprint_Roadmap.md
│   └── CSE470_System_Architecture.md
│
├── .gitignore
└── README.md
```

---

## 5. 4-Sprint Development Schedule

| Sprint | Timeline | Module & Feature Scope | Deadline |
| :---: | :---: | :--- | :---: |
| **Sprint 1** | Nov 1 – Nov 12 *(1.5 Wks)* | **Foundational Setup & Onboarding**: DB schema initialization, R1 (Delivery Agent Onboarding), R6 (Shop Search), R7 (Repair Request Posting), R11 (Vendor Profile Verification), R16 (Platform Ledger) | Nov 12, 2026 |
| **Sprint 2** | Nov 13 – Nov 27 *(2.0 Wks)* | **Core Bidding Marketplace, AI Microservice & Payments**: R2 (Delivery Jobs Queue), R8 (Package Cart), R12 (Live Bidding Engine), R13 (FastAPI Request Summary Microservice), R17 (SSLCommerz/Stripe Gateway & Invoicing) | Dec 1, 2026 |
| **Sprint 3** | Nov 28 – Dec 9 *(1.5 Wks)* | **Logistics Tracking, Communications & Settlement**: R3 (Rider Earnings), R4 (Live GPS Location Tracking), R14 (BulkSMS BD Broadcast Engine), R18 (Automated Vendor Commission Settlement), R19 (Sitewide Governance & Moderation) | Dec 14, 2026 |
| **Sprint 4** | Dec 10 – Dec 20 *(1.5 Wks)* | **AI Troubleshooting, Analytics & Defense Prep**: R5 (Pusher Rider Support Chat), R9 (Shop Reviews), R10 (AI Diagnostic Chat Assistant), R15 (Vendor Financial Analytics), R20 (Immutable Audit Logs), Integration Testing | Dec 20, 2026 |

---

## 6. Getting Started Locally

### Prerequisites
- PHP `>= 8.2` (via XAMPP or native binary)
- Composer `>= 2.5`
- Node.js `>= 18.0` and npm
- Python `>= 3.10`
- PostgreSQL `>= 14`

### Backend Setup (Laravel 10)
```bash
cd backend
cp .env.example .env
# Update DB credentials in .env (DB_DATABASE=meramot_db, DB_USERNAME, DB_PASSWORD)
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve --port=8000
```

### AI Microservice Setup (FastAPI)
```bash
cd backend/ai-microservice
python -m venv venv
# On Windows:
.\venv\Scripts\activate
# On Linux/macOS:
# source venv/bin/activate
pip install -r requirements.txt
cp .env.example .env
# Set GROQ_API_KEY and GEMINI_API_KEY
uvicorn main:app --reload --port=5000
```

### Frontend Asset Compilation
```bash
cd frontend
npm install
npm run build # or npm run dev
```

---

## 7. License & Academic Integrity

Developed strictly for academic evaluation in **CSE 470: Software Engineering** at **BRAC University**.
All rights reserved by Group 6. Unauthorized commercial distribution or plagiarism is strictly prohibited.
