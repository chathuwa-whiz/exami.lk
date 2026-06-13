# Accessibility Validation Plan

This checklist will guide the final sweep once every page uses the new UI shell. It focuses on semantic structure, screen-reader hints, focus management, and responsive behavior.

## 1. Global layout (`src/layout.php`)
- Verify the `<header>`, `<main>`, and `<footer>` landmarks are unique per page. Add `role="navigation"` labels with `aria-label="Primary"` if multiple navs exist.
- Ensure the skip link remains the first focusable element and visibly styled when focused.
- Confirm the theme toggle/button icons have `aria-pressed` and descriptive labels.

## 2. Navigation + tables
- For data tables (`public/admin/teachers.php`, `public/admin/logs.php`, `public/student/papers.php`), confirm `<caption>` or `aria-describedby` explains context. Current `aria-describedby"` references need real IDs.
- Check sortable headers: add `aria-sort` attributes tied to `$sort/$dir` to announce ordering.

## 3. Forms and dialogs
- Student/teacher forms (e.g., `public/teacher/create_paper.php`, `public/admin/import.php`, `public/student/pay.php`) need consistent `<label for>` pairing. Add helper text via `aria-describedby` instead of plain `<p>` when giving instructions.
- Validate CSRF hidden inputs are positioned near other inputs so screen readers announce them only once (no action required, but keep forms linear).

## 4. Alerts and status feedback
- Ensure `.alert` components use appropriate `role` attributes (e.g., `role="status"` for success/info, `role="alert"` for errors) and include `aria-live="polite"` if content updates dynamically.
- Payment/result pages should move focus to the first alert after actions (JS hook or `tabindex="-1"` + `focus()` after load).

## 5. Focus management & keyboard shortcuts
- Student attempt page: confirm keyboard shortcuts are documented and can be disabled if they conflict. Wrap listener so it ignores inputs inside textareas.
- When modals or dynamic sections appear (e.g., question builder), trap focus inside until dismissed.

## 6. Color and contrast
- Re-run contrast checks on the primary palette defined in `public/assets/style.css` (primary, secondary, badge backgrounds). Adjust token values if any text/background combo falls below WCAG AA (4.5:1 for text, 3:1 for large text/icons).
- Verify disabled or muted text still meets contrast requirements.

## 7. Responsive verification
- Test breakpoint behavior (≥1400px, 992px, 768px, 576px, <480px) for core flows: landing, student papers/attempt/result, teacher manage/create/assign, admin dashboard/import/edit.
- Ensure tables provide horizontal scrolling with focusable containers (`tabindex="0"`) and visible focus outlines.

## 8. Testing workflow
1. Use Lighthouse/axe to run audits on each major route (student: papers/attempt/result/pay, teacher: manage/create/assign, admin: index/teachers/logs/import/edit_student).
2. Capture any violations, fix iteratively, and rerun until clean.
3. Document outcomes in README with a short accessibility section summarizing coverage and any known limitations.
