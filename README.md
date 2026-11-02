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
| **Sprint 2** | Nov 13 – Dec 1 *(2.5 Wks)* | **Core Bidding Marketplace, AI Microservice & Payments**: R2 (Delivery Jobs Queue), R8 (Package Cart), R12 (Live Bidding Engine), R13 (FastAPI Request Summary Microservice), R17 (SSLCommerz/Stripe Gateway & Invoicing) | Dec 1, 2026 |
| **Sprint 3** | Dec 2 – Dec 14 *(1.8 Wks)* | **Logistics Tracking, Communications & Settlement**: R3 (Rider Earnings), R4 (Live GPS Location Tracking), R14 (BulkSMS BD Broadcast Engine), R18 (Automated Vendor Commission Settlement), R19 (Sitewide Governance & Moderation) | Dec 14, 2026 |
| **Sprint 4** | Dec 15 – Dec 20 *(1.0 Wk)* | **AI Troubleshooting, Analytics & Defense Prep**: R5 (Pusher Rider Support Chat), R9 (Shop Reviews), R10 (AI Diagnostic Chat Assistant), R15 (Vendor Financial Analytics), R20 (Immutable Audit Logs), Integration Testing | Dec 20, 2026 |

---

## 6. Getting Started Locally (Teammate Setup Guide)

### ⚡ Quickest Setup (Windows — One-Click)

If you are on Windows, you don't need to run complex terminal commands manually:
1. **Clone the repository**:
   ```bash
   git clone https://github.com/farhantanvir3-sudo/CSE470-Meramot-Project.git
   cd CSE470-Meramot-Project
   ```
2. **One-Click Automated Setup**:
   Double-click `setup.bat` (or run `./setup.bat` in Command Prompt / PowerShell).
   - Automatically prepares `.env` files for both backend and AI microservice.
   - Installs Composer packages from `composer.lock`.
   - Generates the Laravel encryption key.
   - Configures the Python virtual environment and installs FastAPI dependencies.
   - Attempts to auto-create the `meramot` PostgreSQL database and run migrations.

3. **Database Configuration**:
   Make sure PostgreSQL is running on port `5432`. In `backend/.env`, set your local PostgreSQL user & password:
   ```env
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=meramot
   DB_USERNAME=postgres
   DB_PASSWORD=your_local_postgres_password
   ```
   *(Or uncomment `DATABASE_URL=postgresql://postgres:your_password@127.0.0.1:5432/meramot` if you prefer a single connection string URL!)*

4. **One-Click Database Migration & Seeding**:
   Double-click `setup-db.bat`. It will create the database `meramot` if missing, run all 39 table migrations, and seed mock shops, users, and packages!

5. **Launch Development Servers**:
   - Double-click `run-backend.bat` (Starts Laravel on **http://127.0.0.1:8000**)
   - Double-click `run-ai.bat` (Starts Python FastAPI on **http://127.0.0.1:5000**)

---

### 🛠️ Manual Terminal Setup (Linux, macOS, or Windows CLI)

#### Step 1: System Prerequisites
- **PHP** `>= 8.2` (via XAMPP or native binary)
  > **Important PHP.ini Check**: In your `php.ini`, ensure the PostgreSQL extensions are enabled by removing the semicolon `;` in front of:
  > ```ini
  > extension=pdo_pgsql
  > extension=pgsql
  > ```
- **Composer** `>= 2.5` ([getcomposer.org](https://getcomposer.org/))
- **PostgreSQL** `>= 14` (Ensure service is running on port `5432`)
- **Python** `>= 3.10` & **Node.js** `>= 18.0`

#### Step 2: Database Configuration
1. Open pgAdmin or `psql` and ensure the database exists:
   ```sql
   CREATE DATABASE meramot;
   ```
2. In `backend/.env`, verify database credentials:
   ```env
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=meramot
   DB_USERNAME=postgres       # or your local postgres username
   DB_PASSWORD=your_password  # your postgres password
   ```

#### Step 3: Install Backend & Run Migrations
```bash
cd backend
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve --port=8000
```
*The database will seed all 32 original repair shops with Dhaka coordinates, baseline admin accounts, customer demo accounts, and test vendors.*

#### Step 4: Start Python AI Microservice (FastAPI)
```bash
cd backend/ai-microservice
python -m venv venv

# Windows:
.\venv\Scripts\activate
# Linux/macOS:
source venv/bin/activate

pip install -r requirements.txt
cp .env.example .env
uvicorn main:app --reload --port 5000
```

---

### 🌐 Local URLs to Access Features

| Portal | URL | Assigned Features |
| :--- | :--- | :--- |
| **Customer Hub** | [http://127.0.0.1:8000/shops/explore](http://127.0.0.1:8000/shops/explore) | R6 (Search), R7 (Request), R8 (Cart) |
| **Vendor Portal** | [http://127.0.0.1:8000/vendor/bidding-board](http://127.0.0.1:8000/vendor/bidding-board) | R11 (Onboarding), R12 (Bidding), R15 (Analytics) |
| **Rider Logistics** | [http://127.0.0.1:8000/delivery/jobs-queue](http://127.0.0.1:8000/delivery/jobs-queue) | R1 (Verify), R2 (Queue), R3 (Earnings), R4 (GPS) |
| **Admin Governance** | [http://127.0.0.1:8000/admin/financial-overview](http://127.0.0.1:8000/admin/financial-overview) | R16 (Ledger), R18 (Payouts), R19 (Moderation), R20 (Audit) |
| **AI Swagger Docs** | [http://127.0.0.1:5000/docs](http://127.0.0.1:5000/docs) | Interactive testing for R10 & R13 endpoints |

---

### ❓ Common Gotchas & Troubleshooting

1. **`could not find driver (SQLSTATE[08006])`**:
   Open `php.ini` in your PHP/XAMPP directory, search for `pdo_pgsql`, remove the leading semicolon (`;extension=pdo_pgsql` &rarr; `extension=pdo_pgsql`), save, and restart Apache / CLI.
2. **`Connection refused on port 5432`**:
   Make sure PostgreSQL is running (in Windows Services or Docker).
3. **`Composer install security warning`**:
   A `composer.lock` file is committed to the repo. Running `composer install` will install exact, verified package versions without prompting advisory blockers.

---

## 7. License & Academic Integrity

Developed strictly for academic evaluation in **CSE 470: Software Engineering** at **BRAC University**.
All rights reserved by Group 6. Unauthorized commercial distribution or plagiarism is strictly prohibited.
