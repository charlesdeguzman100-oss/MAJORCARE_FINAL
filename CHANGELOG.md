# MajorCare — Change / Handoff Log

> **Purpose:** This is a living record of changes made to this project. It is meant so that
> work can continue in a fresh context window: just tell the new session to **read this file
> first** and it will know what has been done, what is in progress, and what is left to do.

## How to use this file

- When starting a new context window, say: _"Read `CHANGELOG.md` in the project root before we start."_
- After making any meaningful change, append a new entry to **Changelog entries** below (newest on top).
- Keep entries short but concrete: what changed, which files, and why.
- If something is only partially done, put it under **In progress / TODO** so it isn't lost.

---

## Project snapshot

- **Project:** MajorCare — a student clinic management web app (PHP + MySQL/XAMPP).
- **Web root:** `C:\xampp\htdocs\MAJORCARE`
- **Repo:** git repository; last commit at time of writing: `5243b77 ALL PHASES DONE AND TESTED`.
- **Docs:** `docs/01_xampp_setup.md`, `02_student_guide.md`, `03_staff_guide.md`, `04_troubleshooting.md`.
- **Auth:** roles such as `student` / `staff`; CSRF protection via `csrf_field()` / `csrf_is_valid()`.
- **Layout:** shared `templates/header.php` and `templates/footer.php`; page helpers like `e()`, `url()`, `redirect()`, `set_flash()`.

> When updating this section, run `git status --short` and `git log --oneline -5` and record the results.

---

## In progress / TODO

- [x] **Official logo installed:** `assets/img/majorcare-logo.png` (owl mark only). The user saved a
      newer version as `majorcare_logo.png`; it was renamed into the active filename. Verified it
      renders on the login page (owl + "MajorCare" wordmark) and is served as `image/png`
      (HTTP 200, 137 KB). The optional `majorcare-logo-full.png` slot remains available for a
      complete lockup.
- [ ] **Duplicate file to review:** `assets/css/js/password.js` appears to be an accidental copy of
      `assets/js/password.js`. The footer loads `assets/js/password.js`; the `assets/css/js/` copy is
      unused. Confirm before deleting (it is untracked and may be user work).
- [ ] Commit the working tree once the logo is in place (see changelog entries below).
- [ ] Optional follow-up: add an `empty-state` class to empty cards if richer empty states are wanted.

---

## Changelog entries

### 2026-11-22 — Troubleshooting note: database connection refused
- Symptom: `Fatal error: Uncaught RuntimeException: Database connection failed: SQLSTATE[HY000]
  [2002] No connection could be made because the target machine actively refused it` on
  `public/register.php` (and any page that calls `getDatabase()`).
- Cause: XAMPP's MySQL service was not running; nothing was listening on port 3306. **Not a code
  bug** — no files changed.
- Fix: start MySQL in the XAMPP control panel (or `C:\xampp\mysql_start.bat`). Verified a single
  `mysqld` process listening on port 3306 afterwards.
- If it recurs, check the service/port before editing `config/database.php`.

### 2026-11-18 — Favicon added
- Added `assets/img/favicon.png` using the new owl image supplied by the user (saved as
  `majorcare_favicon.png`, renamed to `favicon.png`).
- `templates/header.php` now outputs `rel="icon"` and `rel="apple-touch-icon"` links in `<head>`,
  so the favicon applies to every page.
- Verified: `php -l` passes, both link tags render in the login page HTML, the file is served as
  `image/png` (HTTP 200, 116 KB), and the reloaded page has no console errors.
- Note: this favicon artwork is a different owl style/colour treatment than the main sidebar logo,
  per the user's separate image.

### 2026-11-18 — Logo replaced with newer file
- Replaced `assets/img/majorcare-logo.png` with the newer 137 KB version supplied by the user
  (arrived as `majorcare_logo.png`, renamed to the expected filename).
- Reloaded the app: owl + "MajorCare" wordmark renders correctly on the login page, no console
  errors, image served as `image/png` (HTTP 200).

### 2026-11-18 — Official owl logo installed
- Added `assets/img/majorcare-logo.png` (renamed from the user-saved `majorcare_logo.png`).
- Verified in the browser: owl mark + "MajorCare" wordmark renders in the sidebar on the student
  dashboard, with no console errors.
- Verified the login page HTML references the owl image plus the text wordmark, and the image is
  served correctly (HTTP 200, `image/png`, 126 KB).

### 2026-11-18 — Logo asset support (owl-only vs full lockup)
- `templates/header.php` now supports two logo filenames: `majorcare-logo.png` (owl mark only,
  paired with an automatic text wordmark) and `majorcare-logo-full.png` (complete lockup, used
  alone). Falls back to "MC" text when neither exists.
- `assets/css/style.css` — added `.brand-mark-img` and `.auth-logo-mark` styling.
- `assets/img/README.md` — updated with both filename options.
- Verified `php -l` and the login page still render with the fallback.

### 2026-11-18 — Complete UI/UX redesign (sidebar shell + shared design system)

Implemented the full redesign against the supplied reference screenshot and written brief.
**No backend logic, queries, schema, validation, auth, or CSRF behaviour was changed.** The redesign
is driven by the shared templates and one design-system stylesheet, so all existing Student, Staff
and public pages inherit it automatically.

**New / rewritten shared files**
- `templates/header.php` — completely restructured:
  - Signed-in users get a fixed navy sidebar (brand, grouped nav sections with SVG icons, distinct
    active state, unread badge, user card, CSRF logout) plus a sticky topbar (eyebrow, page title,
    role pill, notification icon) and a scrolling `.content` area.
  - Guests get a focused `.auth-page` layout with centred brand + card.
  - Sidebar links mirror the original role-based links exactly; staff-only items remain staff-only.
  - Student detail pages highlight "Students"; `message_thread.php` highlights "Messages".
  - Uses the official logo when `assets/img/majorcare-logo.png` exists, otherwise a clean "MC" fallback.
- `templates/footer.php` — closes the new shell correctly for both layouts; keeps the disclaimer;
  loads `app.js` and `password.js`.
- `assets/css/style.css` — replaced with a full design system (tokens, app shell, cards, buttons,
  forms, tables, badges, alerts, stats, filters, messages, FAQ, auth, responsive breakpoints at
  1100/900/640px). Palette: navy primary, teal active/interactive, cyan accent, warm-white content.
- `includes/icons.php` — new dependency-free inline SVG icon helper (`mc_icon()`).
- `assets/js/app.js` — new mobile drawer behaviour (open/close, overlay, Escape, close on link,
  reset on resize).
- `assets/img/README.md` — documents where to drop the official logo.

**Small markup-only page edits (no logic changes)**
- `student/health.php`, `staff/students.php`, `staff/slots.php`, `staff/student_health.php` —
  wrapped existing tables in `.table-wrap` for horizontal scrolling on small screens.
- `staff/students.php` — replaced `<br><br>` action-cell spacing with a `.table-actions` flex group
  and smaller reset button.

**Pages inheriting the redesign (all existing pages)**
- Public: login, register, change password, notifications, document/result file endpoints (no UI).
- Student: dashboard, profile, health, appointments, book, visits, results, documents, messages,
  consent, clinic, availability.
- Staff: dashboard, registrations, students, appointments, slots, documents, messages, message
  thread, availability, clinic info, FAQs, announcements, student profile/health/visits/
  assessments/results, visit edit, assessment edit.

**Verification performed**
- `php -l` passes on every PHP file in the project.
- Browser smoke test of all 19 staff pages and 13 student/public pages: no console errors, no PHP
  warnings/fatals. Expected 302s confirmed for `change_password.php` (not required) and a published
  assessment edit (draft-only rule).
- Visual checks: login, staff appointments, staff students table, staff dashboard, student dashboard.
- Confirmed role restrictions still redirect staff away from student pages.

### 2026-11-18 — Baseline: uncommitted work captured when this log was created

This entry records the working-tree state at the time the log was first written, so a new
context window has the full picture. **These changes were not committed yet.**

**1. Staff registration review — undo rejection + view rejected (`staff/registrations.php`)**
- Added an `undo` action: a rejected registration can be returned to `pending`.
  - Undo requires the account to currently be `rejected`; approve/reject still require `pending`.
  - Undo clears `rejection_reason` (sets it to `null`) and notifies the student that their
    registration is "under review" again.
- Added a **Rejected** section listing rejected registrations (most recent first, ordered by
  `users.updated_at`) with school ID, rejection date, reason, and an "Undo rejection" button
  (with a JS `confirm()` prompt).
- Rejection reason validation (required, max 400 chars) moved out of the `reject` branch and
  applied up front.
- Hardened POST handling: whitelist of actions (`approve`/`reject`/`undo`), explicit "not found"
  vs "wrong status" errors, and guard on `rollBack()` with `inTransaction()`.
- Reformatted the "Submitted" timestamp for pending rows into a human-readable date.
- Page heading changed from "Pending registrations" to "Registrations"; pending list now under an
  `<h2>Pending</h2>` heading.

**2. Password field UX (`assets/js/password.js`, `assets/css/style.css`, `templates/footer.php`)**
- New `assets/js/password.js` (loaded via `defer` in `templates/footer.php`):
  - Adds a **Show/Hide** button to every `input[type="password"]` (wraps it in `.password-wrap`,
    toggles `type` between `password` and `text`, maintains `aria-label` / `aria-pressed`).
  - Adds a live "Passwords match / do not match" hint under `input[name="confirm_password"]`
    for forms containing `password` or `new_password`. This is client-side convenience only;
    the server still validates.
- CSS added in `assets/css/style.css`: `.password-wrap`, `.toggle-password`, `.match-hint`,
  `.match-ok`, `.match-bad`.
- Footer now includes `<script src=".../assets/js/password.js" defer></script>`.

**Working tree at capture time (`git status --short`):**
```
 M assets/css/style.css
 M staff/registrations.php
 M templates/footer.php
?? assets/css/js/
?? assets/js/
```

---

<!--
Append new entries above this comment using this template:

### YYYY-MM-DD — Short title
- What changed and why.
- Files touched: `path/to/file.php`, ...
- Verification: how it was tested / what still needs testing.
-->
