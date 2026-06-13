# ceylonstudyhub (Prototype)

A minimal PHP prototype for timed, fee-based papers where teachers create questions and students answer within a countdown. Immediate scoring displayed upon submission.

## Design System & UI Architecture
The interface uses a small, composable design system defined in `public/assets/style.css` and shared layout helpers in `src/layout.php`.

### Layout
- `render_header($title)` wraps each page in a flex layout with a sidebar (`.sidebar`) and main content region (`.content`).
- Sidebar links are role-aware (admin, teacher, student) and highlight active route via `.active` class.
- A skip link (`.skip-link`) can be added to jump directly to the main content for keyboard users (add before header if not present).

### Core Components
| Component | Purpose | Classes |
|----------|---------|---------|
| Card | Panel container | `.card` / `.card.alt` |
| Button | Action triggers | `.button`, modifiers: `.outline`, `.danger`, `.small`, `.secondary`, `.button-primary` (alias of primary) |
| Table | Data listing | `.table` inside `.table-wrap` for horizontal scroll |
| Badge | Status labels | `.badge` with modifiers `.success`, `.danger`, `.warn`, `.primary`, `.muted` |
| Alert | Inline feedback | `.alert`, modifiers `.success`, `.error`, `.warning` |
| Timer | Remaining attempt time | `.timer` with state `.warning`, `.expired` |
| Pills/List | Compact list items | `.list-pill` (UL) + `.pill` (optional) |

### Form & Grid Patterns
- Inline search/filter forms use `form.inline` with child `.field` wrappers and a `<span>` label for accessible naming.
- Larger forms may use `.form-grid` or `.stack` (vertical spacing). Inputs receive a focus ring via `outline` for accessibility.
- Ensure every input has a descriptive label or `aria-label` if a visual label is not appropriate.

### Responsive Behavior
- Below 900px the sidebar collapses to a horizontal wrap; cards and grids use fluid widths via `auto-fit` CSS grid.
- Tables remain scrollable horizontally without breaking layout using `.table-wrap { overflow-x:auto; }`.

### Accessibility Conventions
- Landmark roles: `main` region is implied by `<main class="content">`; add `role="main"` if needed.
- Pagination/navigation containers should have `aria-label="Pagination"`.
- Interactive icons (sorting arrows) include textual or code-style hints (`↕`) and can be extended with `aria-label` attributes if using images/SVGs.
- Focus indicators provided globally; avoid removing outlines.
- Skip link pattern:
```html
<a href="#main" class="skip-link">Skip to content</a>
<main id="main" class="content">...</main>
```
Add corresponding CSS:
```css
.skip-link { position:absolute; left:-999px; top:0; background:#000; color:#fff; padding:.5rem 1rem; z-index:1000; }
.skip-link:focus { left:0; }
```

### Recent Accessibility Enhancements (Nov 2025)
- Added hidden captions, labelled table regions, and `aria-sort` indicators to admin teacher/log directories and student tables so assistive tech gets context without visual clutter.
- All alerts now expose the correct `role` (`alert` vs `status`) with matching `aria-live` politeness, and the layout scripts automatically focus the first alert after submissions for quick screen-reader access.
- Global layout/auth shells mirror alert text into polite live regions and provide skip links, theme toggles with `aria-pressed`, and keyboard-accessible table scroll containers.

### Color Palette
| Token | Value | Usage |
|-------|-------|-------|
| `--primary` | `#2563eb` | Primary actions, focus ring |
| `--danger` | `#dc2626` | Destructive actions, error states |
| `--success` | `#16a34a` | Success badges, positive alerts |
| `--warn` | `#d97706` | Warning / timer nearing expiry |
| `--bg` | `#0f172a` | Sidebar background |
| `--bg-alt` | `#1e293b` | Header/table head background |
| `--panel` | `#ffffff` | Card/table surface |
| `--panel-alt` | `#f1f5f9` | Secondary panels |

### Extending / Theming
Override variables with a `.light` class on `<body>` or attach a theme toggle:
```js
document.querySelector('#themeToggle').addEventListener('click',()=>{
	document.body.classList.toggle('light');
});
```
Add new brand colors by introducing `--primary-alt` or update existing tokens; keep contrast ratios (WCAG AA) for text against backgrounds (≥ 4.5:1 for normal text).

### Theme & Contrast Toggles
Two user-toggleable modes are provided (stored in `localStorage`):
1. Light vs Dark: toggles `.light` class on `<body>`.
2. High Contrast: toggles `.hc` class with amplified contrast palette.

Buttons in the header (`#toggleTheme`, `#toggleContrast`) expose `aria-pressed` state for assistive tech.

### Live Regions
Layout injects two off-screen regions:
```html
<div class="sr-live" aria-live="polite" aria-atomic="true"></div>
<div class="sr-live-assertive" aria-live="assertive" aria-atomic="true"></div>
```
Current implementation mirrors `.alert` text into the polite region on load; extend for dynamic runtime updates when AJAX interactions are added.

### Keyboard Navigation (Attempt Page)
Question navigation shortcuts (while focus is inside the attempt form):
- `Alt + ArrowRight` or `Alt + N`: Next question
- `Alt + ArrowLeft` or `Alt + P`: Previous question

Focus moves to first radio of target question; if none, the fieldset itself.


### Custom Component Example
Simple progress bar:
```html
<div class="progress-bar"><span style="width:40%"></span></div>
```
Update width dynamically in JS to reflect attempt progress or question completion.

## Changelog (UI Pass)
- Converted all major pages to unified layout (`layout.php`).
- Added cards, badges, button variants, timer styling.
- Implemented accessible pagination and search forms on admin directories.
- Added marketing landing content to `public/index.php`.
- Prepared structure for skip link & additional ARIA roles.

## Remaining UI / Accessibility Ideas
- Add `aria-live="polite"` region for dynamic success/error alerts.
- Implement keyboard shortcuts (e.g., jump to next question) with proper instructions.
- Provide high-contrast mode toggle (additional palette set).


## Features Implemented
- Teacher registration & paper creation (title, fee, time limit, questions, options, correct answers).
- Student registration & teacher assignment (manual DB insert for now via `teacher_student`).
- Fee simulation (no real gateway) with payment completion enabling access.
- Timed attempt page with JS countdown; disables answering after time.
- Immediate scoring after final submit.

## Not Yet Implemented / Next Steps
- Real payment gateway integration.
- Teacher UI to publish papers (`is_published` toggle) and assign students.
- Better security (CSRF tokens, input validation, rate limiting).
- Multi-question progress autosave & resume handling after timeout.
- Rich text / images for questions.
 - Admin UI improvements (bulk import CSV for preapproved students, audit logs).

## Setup
1. Create MySQL database:
```sql
CREATE DATABASE ceylonstudyhub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```
2. Import schema:
```sql
SOURCE c:/xampp/htdocs/claz/src/db_schema.sql;
```
	(If you already imported previous version without admin role, run:)
```sql
ALTER TABLE users MODIFY user_type ENUM('teacher','student','admin') NOT NULL;
ALTER TABLE preapproved_students ADD COLUMN is_deleted TINYINT(1) DEFAULT 0;
CREATE INDEX idx_pre_students_deleted_created ON preapproved_students(is_deleted, created_at);
```
3. Adjust credentials in `src/config.php` if needed.
4. Start Apache/MySQL via XAMPP and navigate to `http://localhost/claz/public/`.

## Assigning Students to Teachers
Insert into `teacher_student` after both accounts exist:
```sql
INSERT INTO teacher_student (teacher_id, student_id) VALUES (TEACHER_ID, STUDENT_ID);
```

## Preapproved Student Workflow
1. Admin creates an `admin` user manually:
```sql
INSERT INTO users (user_type,name,email,password_hash) VALUES ('admin','Admin','admin@example.com', '$2y$...');
```
2. Login as admin and visit `/admin/index.php` to add preapproved students.
3. Map teachers to each student via "Manage Teachers" link.
4. Student registration will only succeed if their `student_id` exists and they select allowed teachers.

## Admin Pages
	- Supports `?q=` search, `?page=` pagination, `perPage=` (25/50/100), and sorting via `sort=` (student_id|name|created_at) with `dir=` (asc|desc) on `/admin/index.php`.
	- CSV export of filtered list ignores pagination but applies search filters (sorting not applied to export).
	- `/admin/teachers.php`: teacher directory search (name or code) with pagination & sorting.
	- `/admin/logs.php`: audit log viewer with filters (action substring, actor user_id) & pagination.
## JSON API Endpoints
All authenticated endpoints now support either existing session or `Authorization: Bearer <JWT>` header.

Issue token (session required): `GET /api/auth_token.php?ttl=3600&include_refresh=1`
Response: `{"token":"...","expires_in":3600,"refresh_token":"..."}`
Refresh token (POST JSON): `/api/auth_refresh.php` body `{"refresh_token":"..."}` returns new rotated pair.

Admin endpoints:
- `/api/preapproved_students.php`: `q,page,perPage(1-100),sort(student_id|name|created_at),dir(asc|desc)` list excluding soft-deleted.
- `/api/teachers.php`: `q,page,perPage,sort(name|teacher_code|created_at),dir` teacher directory.
- `/api/audit_logs.php`: `action,user_id,from(YYYY-MM-DD),to(YYYY-MM-DD),page,perPage` filtered audit trail.

Teacher endpoints:
- `/api/papers_teacher.php`: `q,page,perPage,sort(created_at|title|is_published),dir` list teacher (or all if admin) papers.
- `/api/paper_create.php` (POST JSON): `{title,description,fee_cents,time_limit_seconds,is_published}` creates paper.
- `/api/questions_paper.php?paper_id=ID` list questions with options.
- `/api/question_create.php` POST `{paper_id,question_text,marks,position?,options:[{option_text,is_correct}]}`.
- `/api/question_update.php` POST `{question_id,question_text?,marks?,position?,options?}` (options replaces entire set).
- `/api/question_delete.php` POST `{question_id}` hard delete.

Student endpoints:
- `/api/papers_student.php`: `q,page,perPage,sort(created_at|title),dir` published papers accessible via `paper_access`.

Public (rate-limited) endpoint:
- `/api/preapproved_teachers.php?student_id=STUDENT_ID` returns mapped teachers used during registration.

### JWT Details
- HS256 signed; secret defined as `JWT_SECRET` in `src/config.php` (change for production).
- Claims include `uid` (user id), `ut` (user type), `iat`, `exp`.
- Default TTL clamp: min 300s, max 86400s.

### Rate Limiting
- Fixed window counters via `rate_limits` table.
- Hybrid limits (user + IP) on mutation endpoints (paper/question create/update/delete).
- Sample limits: listing 120/min, audit logs 60/min, token issuance 5/min per user, paper creation 10/hour per user & 30/hour per IP, public preapproved teachers 30/min per IP, question create 100/min per user.

### Restore & Soft Delete
- Admin can toggle "Show Deleted" on `/admin/index.php` and restore individually or bulk.
- Deleted rows highlighted red; counts note inclusion when showing deleted.

### New Migrations (if upgrading existing install)
```sql
CREATE TABLE rate_limits (
	id INT AUTO_INCREMENT PRIMARY KEY,
	rl_key VARCHAR(200) NOT NULL,
	window_start INT NOT NULL,
	count INT NOT NULL,
	INDEX idx_rl_key_window (rl_key, window_start)
) ENGINE=InnoDB;
```

### Example Bearer Usage
### Refresh Flow
1. Client gets access + refresh via `auth_token.php?include_refresh=1`.
2. Stores refresh token securely (httpOnly cookie or encrypted storage).
3. Calls `/api/auth_refresh.php` before access expiry; receives new access + rotated refresh.
4. Old refresh becomes invalid (revoked); logout flow can revoke current refresh.
```bash
curl -H "Authorization: Bearer YOUR_TOKEN" "http://localhost/claz/public/api/papers_teacher.php?page=1"
```

## Soft Delete Behavior
- Deleting preapproved students sets `is_deleted=1` (data retained; mappings untouched).
- Export and listing exclude soft-deleted records; restore flow can be added by changing `is_deleted` back to 0.

## CSRF Rotation
- Tokens rotate automatically after successful POST reducing replay window.
  - Supports `?q=` search, `?page=` pagination, `perPage=` (25/50/100), sorting `sort=` (student_id|name|created_at) with `dir=` (asc|desc), bulk delete, and preference persistence in session on `/admin/index.php`.
  - CSV export of filtered list applies search & sorting (`sort`, `dir`) and includes deterministic secondary sort.
`/admin/export_preapproved.php?q=SEARCH&perPage=50`
Exports all filtered rows (ignores pagination) to CSV.

## Payment Simulation
Use link on admin index or direct URL:
`/admin/export_preapproved.php?q=SEARCH&sort=created_at&dir=desc`
Exports all filtered rows (ignores pagination) to CSV with applied sorting; deterministic secondary sort by student_id.

## Bulk Delete
Select multiple rows via checkboxes on `/admin/index.php` and click "Bulk Delete Selected"; each deletion logged individually in `audit_logs`.

## Audit Logs
View `/admin/logs.php?action=add_preapproved&user_id=1&page=2` to filter by action substring and actor. Indexed on `user_id`, `created_at`, and `action` for performance.
Click "Pay Now"; system marks payment completed and grants access. Replace logic in `student/pay.php` with real gateway callback.

## Security Notes
- Passwords hashed with `password_hash`.
- Add HTTPS in production.
- Implement CSRF protection (e.g., token in session).
- Validate/escape all dynamic output beyond current minimal escaping.
 - Restrict admin pages by `user_type='admin'`.
 - Audit trail in `audit_logs` for administrative changes.

## License
Internal prototype. No distribution license added.
