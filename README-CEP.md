# 🏘️ New Life Fitness — Community Engagement Project (CEP)

> **NEW LIFE FITNESS** — *"Stronger Together, Healthier Community!"*

A community-wellness extension of the New Life Fitness platform, built as a
**college Community Engagement Project (CEP)**. The project extends a gym
management system with a public community platform: free events, volunteering,
feedback, surveys, wellness resources, fitness challenges — plus honest,
transparent AI prototypes for recommendations and feedback analysis.

![New Life Fitness Club Logo](assets/images/logo.jpg)

---

## ⚠️ Relationship to the personal project (READ THIS FIRST)

This CEP is a **separate, standalone copy** of the New Life Fitness codebase
that was extended with the community platform. It was forked specifically so
that the personal project is never at risk:

- The personal project files are **read-only** and were never modified, renamed,
  or deleted while building this CEP.
- The CEP uses its **own database** (`newlife_cep` locally) with its own
  credentials (`config.local.php` is never shipped — see
  `README-DEV-CONFIG.md`).
- The CEP deploys to its **own InfinityFree hosting account** (guide:
  `DEPLOY-CEP.md`).
- No personal credentials, personal demo members, or personal hosting details
  exist anywhere inside this CEP copy.

**Personal project = protected. CEP project = this separate copy.**

---

## ✨ What the CEP adds (the 12 community features)

The CEP keeps every existing gym-admin feature (members, trainers, plans,
offers, attendance, fees, reports, settings, trainer payments, member progress
analytics) and adds the community platform on top:

| # | Feature | Where |
|---|---|---|
| 1 | Community registration + login (separate role, bcrypt, CSRF) | `community/register.php`, `community/login.php` |
| 2 | Community dashboard (announcements, recommended for you, quick stats) | `community/dashboard.php` |
| 3 | Community events — 10 live categories, filters, capacity, deadlines | `community/events.php` |
| 4 | Event registration (duplicate-proof, capacity-aware, cancel/re-register) | `community/events-register.php`, `community/my-events.php` |
| 5 | Event attendance (admin marks Present/Absent, syncs to "Attended") | `admin/community/attendance.php` |
| 6 | Volunteer system — application → approval → assignment → hours | `community/volunteer.php`, `admin/community/volunteers.php` |
| 7 | Volunteer dashboard — assignments, log hours (completed-assignment gated) | `community/volunteer-dashboard.php` |
| 7b | Community feedback — star rating + satisfaction + AI sentiment prototype | `community/event-feedback.php`, `admin/community/feedback.php` |
| 8 | Surveys & polls — single-choice, multi-select, open text; charts | `community/surveys.php`, `admin/community/surveys.php` |
| 9 | Community requests — 6-state workflow (Submit→Under Review→Approved→Scheduled/Declined→Completed) | `community/requests.php`, `admin/community/requests.php` |
| 10 | Wellness resources — curated articles/videos (admin CRUD) | `community/resources.php`, `admin/community/resources.php` |
| 11 | Fitness challenges — join, track progress, completion | `community/challenges.php`, `admin/community/challenges.php` |
| 12 | Community announcements (admin CRUD, dashboard display) | `admin/community/announcements.php` |
| 13 | Community impact dashboard — charts + honest demo-data disclosure | `community/impact.php` |
| — | **AI: Rule-Based Recommendation Prototype** + feedback analysis | `includes/ai_recommendation.php`, `includes/ai_feedback.php` |

*(The event system supports 12 categories; 10 are represented in the demo data.)*

---

## 🧭 Demo workflow (the full journey)

The complete CEP journey is verified by the E2E test (`tests/e2e_demo.sh`),
which passes **48/48 checks** with a fresh user every run:

```
Register → Login → View Events → Register for Event →
Volunteer Application → Admin Approves → Admin Assigns →
Conduct Event (Completed) → Mark Attendance (Present) →
User Feedback (+ AI sentiment prototype) → Survey Vote →
Admin Survey Analysis → Volunteer Logs Hours → Admin Approves Hours →
Impact Dashboard reflects everything
```

### Quick demo logins (demo data — clearly labelled)

| Role | Login | Password |
|---|---|---|
| Admin (gym + community) | `admin` | `admin123` |
| Community user | `priya.sharma@community.demo` | `Community@123` |
| Community user | `rahul.verma@community.demo` | `Community@123` |

All demo community users share `Community@123`. Demo data is marked in the UI
(demo emails end in `@community.demo`; the impact dashboard carries an explicit
"demo data" disclosure; seed comments declare the same).

---

## 🧭 URLs (local XAMPP)

| Area | URL |
|---|---|
| Public CEP homepage | `http://localhost/new-life-fitness/` |
| Community login | `http://localhost/new-life-fitness/community/login.php` |
| Community register | `http://localhost/new-life-fitness/community/register.php` |
| Impact dashboard | `http://localhost/new-life-fitness/community/impact.php` |
| Admin (gym + community) | `http://localhost/new-life-fitness/login.php` |
| Community admin modules | `http://localhost/new-life-fitness/admin/community/` |

---

## 🏗️ Tech stack

| Layer | Technology |
|---|---|
| Frontend | HTML, CSS, vanilla JS + Chart.js (community charts) |
| Backend | PHP 8.x, mysqli prepared statements everywhere |
| Database | MySQL / MariaDB (36 tables) — `cep_install.sql` |
| Auth | bcrypt (password_hash), sessions, role isolation, CSRF tokens |
| Deployment | InfinityFree (separate account) or XAMPP local |

---

## 🔐 Security highlights

- **bcrypt** password hashing for admin, gym members, and community users.
- **Prepared statements** on every query (no string-built SQL).
- **CSRF tokens** on every state-changing POST form (19 community pages + all admin modules).
- **Role isolation**: admin / member / community roles use separate session keys (`admin_id`, `member_id`, `community_user_id`) — a community session cannot open admin pages and vice-versa.
- **Session hardening**: `session_regenerate_id()` on login, session-IP binding for community sessions, secure logout (destroys session).
- **Server-side authorization**: hours logging requires a Completed assignment (UI hiding is never trusted); attendance marking requires the admin role.
- **Uploads hardening**: `.htaccess` in `uploads/` blocks PHP execution.
- **Sensitive files**: root `.htaccess` blocks `*.sql`, `*.md`, `*.py`, `config.local.php`, `includes/`, `docs/`, `tests/` from web access.

---

## 📁 Project structure

```
NewLifeFitness-CEP/
├── index.php               # public CEP homepage (hero, benefits, events,
│                           #   challenges, announcements, impact, CTA)
├── login.php / logout.php  # admin entry point
├── db_connect.php          # env vars → config.local.php → XAMPP fallback
├── config.example.php      # template for hosting credentials (safe to ship)
├── .htaccess               # blocks .sql/.md/.py, config, includes/, docs/, tests/
├── .user.ini               # PHP limits for shared hosting
├── cep_install.sql         # ★ ONE-FILE DB installer (36 tables)
├── cep_schema.sql          # community tables schema (build source)
├── cep_seed.sql            # demo seed data (build source)
├── database.sql            # legacy gym schema (build source)
├── offers_schema.sql       # legacy offers schema (build source)
├── build_cep_install.py    # build-time combiner (local dev only)
├── community/              # 17 public community pages (+3 partials)
├── admin/                  # full gym admin + 12 admin/community/* modules
├── member/                 # gym member portal
├── includes/               # PHPMailer, auth, helpers, csrf, mailer, ai_*.php
├── uploads/                # demo images (PHP execution blocked)
├── docs/                   # AI_DOCUMENTATION.md
├── tests/                  # smoke + E2E scripts (dev tooling)
├── DEPLOY-CEP.md           # ★ deployment guide (separate InfinityFree)
├── README-CEP.md           # this file
└── README-DEV-CONFIG.md    # why config.local.php is not shipped
```

## 🔧 Local setup (XAMPP, 5 minutes)

1. Copy this folder to `C:\xampp\htdocs\new-life-fitness`.
2. Start Apache + MySQL, open phpMyAdmin.
3. Create DB `newlife_cep` with collation `utf8mb4_general_ci`.
4. Import `cep_install.sql` (36 tables), then optionally `cep_seed.sql` (demo data).
5. Visit `http://localhost/new-life-fitness/` — register a community account or log in as admin (`admin` / `admin123`).

See `DEPLOY-CEP.md` for hosting deployment (separate InfinityFree account).

## 🧪 Testing

Three automated curl+SQL test scripts live in `tests/` (dev tooling, not needed on hosting):

| Script | What it covers | Result |
|---|---|---|
| `smoke_community.sh` | 11 community pages render, auth-gated correctly | 11/11 |
| `smoke_admin_community.sh` | 11 admin/community pages + login flow | 11/11 |
| `e2e_demo.sh` | The full 15-step demo workflow with a fresh user, verifying HTTP redirects, flash messages, and database rows at every step | 48/48 |

Run them locally with the dev server running on `127.0.0.1:8081` (see `tests/README` or the script headers for the exact commands).
