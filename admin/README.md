# admin/

Back-office console. **Phase 2 — implemented.**

- `index.php` — dashboard metrics (total news, pending verification, published
  today, breaking, open reports, users) + verification queue preview.
- `verification.php` — full verification queue with review timers and risk flags,
  filterable by status, paginated.
- `verify.php` — split review screen: original submission on the left; verification
  assistant on the right (reporter history, automated checks, possible duplicates,
  internal notes, audit history). Actions: Approve & Publish, Request more
  information, Reject (reason required), Schedule.

Every action is protected by role-based access control (`includes/authz.php`),
runs in a transaction, is idempotent, and is written to the audit and
verification logs (`includes/audit.php`).

Still to come: category/homepage/banner/language management, user & report
moderation screens, and the settings UI (later phases).
