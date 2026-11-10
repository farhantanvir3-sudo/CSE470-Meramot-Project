# Meramot Frontend (`frontend/`) — Laravel MVC Presentation Layer

This root `frontend/` folder houses **all presentation-layer templates, client-side JavaScript modules, CSS design tokens, and static UI assets** for the Meramot Digital Electronic Repair Marketplace, keeping Frontend and Backend cleanly separated at the repository root while running natively on **Laravel 10 MVC** (`backend/config/view.php`).

## Directory Structure

- **`views/`** — All Laravel Blade templates (`layouts/`, `shops/`, `packages/`, `requests/`, `reviews/`, `ai-consultant/`, `vendor/`, `admin/`, `delivery/`, `auth/`, `checkout/`, `tracking/`, `support/`, `home.blade.php`).
- **`js/`** — Standalone client-side JavaScript modules (served at `/js/*`):
  - `geolocation-helper.js` — Relocated from `backend/public/js/` (**Shanid Sajjatuz Islam `[23201465]`**, Feature R6).
  - `marketplace-client.js` — Relocated & merged from inline Blade `<script>` blocks (**Shanid Sajjatuz Islam `[23201465]`**, Features R8 & R9, enhanced with non-blocking `MeramotToast` notifications).
- **`src/styles/main.css`** — Meramot Commit `8e44218` mint-green & dark-mode CSS custom properties.
- **`src/components/`** — Reference UI component prototypes.
- **`public/images/`** — Brand logos and UI vector icons (`meramot.svg`, `cart.svg`).
