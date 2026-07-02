# UX/UI PR Checklist

## Scope Rule

- Keep fixes simple and local.
- If item needs larger refactor, create GitHub issue and defer.

## Requested Fixes

- [ ] `/login` page title is `Musikfestapp` only.
- [ ] `/` / organization selection page title is `Musikfestapp` only.
- [ ] Password reset logs user in directly after new password is set.
- [ ] Organization selection `Log out` button shows pointer cursor.
- [ ] Dashboard placeholder widgets removed.
- [ ] Widget `Last updated` info sticks to bottom of widget.
- [ ] Area count history shows `Last updated` info.
- [ ] Most Active Sensors time range selectors show pointer cursor.
- [ ] Area count history legend uses Grafana-like behavior: click isolates area, second click restores all, shift-click toggles one area.
- [ ] Area count history legend area names show pointer cursor on hover.
- [ ] Area count history display type button shows pointer cursor.
- [ ] Data table buttons show pointer cursor on hover.
- [ ] Data table destructive actions show confirmation dialog.
- [ ] Data table row click opens edit page for that row.
- [ ] Peoplecount sensors `New Token` action warns first.
- [ ] Token copied notification auto-dismisses.
- [ ] Sensors table subtitle wraps normally.
- [ ] Controls hidden when user lacks matching permission, including create buttons.

## Check Other Places

- [ ] Check same title pattern on auth and organization-selection pages.
- [ ] Check pointer cursor on shared button/link primitives before fixing individual buttons.
- [ ] Check all data tables using shared `DataTable` and row action components.
- [ ] Check all dashboard widgets using `Last updated` footer.
- [ ] Check all create/action controls against `usePermissions()` conventions.

## Possible Deferrals

- [ ] Create GitHub issue if permission-aware UI requires changing shared backend Inertia props.
- [ ] Create GitHub issue if row-click edit conflicts with row action buttons, selection, or future bulk actions.
- [ ] Create GitHub issue if destructive confirmations need broad shared action API refactor.
