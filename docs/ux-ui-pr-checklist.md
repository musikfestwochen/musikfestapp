# UX/UI PR Checklist

## Scope Rule

- Keep fixes simple and local.
- If item needs larger refactor, create GitHub issue and defer.

## Requested Fixes

- [x] `/login` page title is `Musikfestapp` only.
- [x] `/` / organization selection page title is `Musikfestapp` only.
- [x] Password reset logs user in directly after new password is set.
- [x] Organization selection `Log out` button shows pointer cursor.
- [x] Dashboard placeholder widgets removed.
- [x] Widget `Last updated` info sticks to bottom of widget.
- [x] Area count history shows `Last updated` info.
- [x] Most Active Sensors time range selectors show pointer cursor.
- [x] Area count history legend uses Grafana-like behavior: click isolates area, second click restores all, shift-click toggles one area.
- [x] Area count history legend area names show pointer cursor on hover.
- [x] Area count history display type button shows pointer cursor.
- [x] Data table buttons show pointer cursor on hover.
- [x] Data table destructive actions show confirmation dialog.
- [x] Data table row click opens edit page for that row.
- [x] Peoplecount sensors `New Token` action warns first.
- [x] Token copied notification auto-dismisses.
- [x] Sensors table subtitle wraps normally.
- [x] Controls hidden when user lacks matching permission, including create buttons.

## Check Other Places

- [x] Check same title pattern on auth and organization-selection pages.
- [x] Check pointer cursor on shared button/link primitives before fixing individual buttons.
- [x] Check all data tables using shared `DataTable` and row action components.
- [x] Check all dashboard widgets using `Last updated` footer.
- [x] Check all create/action controls against `usePermissions()` conventions.

## Possible Deferrals

- [x] No GitHub issue needed for permission-aware UI.
- [x] No GitHub issue needed for row-click edit.
- [x] No GitHub issue needed for destructive confirmations.
