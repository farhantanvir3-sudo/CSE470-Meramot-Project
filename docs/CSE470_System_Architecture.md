# Meramot — System Architecture & Course Compliance

## 1. Architectural Model

Meramot enforces a strict 3-tier **Model-View-Controller (MVC)** architectural design:

```
+-------------------------------------------------------------+
|                      CLIENT BROWSER                         |
|   Tailwind CSS / Blade Templates / Alpine.js UI Components  |
+------------------------------+------------------------------+
                               | HTTP(S) / AJAX
                               v
+-------------------------------------------------------------+
|                 LARAVEL 10 MVC APPLICATION                 |
|                                                             |
|   [ROUTES]  -->  web.php (Blade Views) & api.php (JSON)     |
|   [MIDDLEWARE] Authenticate, RoleVerification, PasskeyGuard |
|   [CONTROLLERS] Single-CRUD controllers for R1 to R20       |
|   [MODELS]      Eloquent ORM Domain Models                  |
+--------------+-------------------------------+--------------+
               |                               |
       Database Queries                REST Client (HTTP)
               v                               v
+------------------------------+ +----------------------------+
|         POSTGRESQL           | |    PYTHON FASTAPI SERVICE  |
|  Relational Storage Engine   | |   Groq / Gemini AI Engine  |
|  ACID Compliant Transactions | |   Inference Microservice   |
+------------------------------+ +----------------------------+
```

---

## 2. Strict Academic Prohibitions & Rationale

Per faculty guidelines (Nazmul Islam [NZU]):
1. **No Express.js / Node.js Backends**: Express.js is strictly prohibited. The core application logic must reside in Laravel controllers and models.
2. **No Django / Flask as Primary Backend**: FastAPI is utilized strictly as an isolated microservice for AI processing and cannot act as the primary database-handling server.
3. **Equal Feature Granularity**: Every feature (R1 to R20) represents a distinct Single-CRUD entity operation touching a specific Model, View, and Controller.
