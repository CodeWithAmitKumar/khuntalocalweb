# reporter/

Reporter area. **Phase 2 — implemented.**

- `index.php` — reporter dashboard: submission stat cards (total, published,
  pending, under review, needs info, rejected) + a full submissions table with
  verification status, reviewer notes, review time and per-item actions.
- `edit.php` — edit an eligible submission (`draft`, `pending`,
  `needs_information`). Saving a *needs-information* item resubmits it for review
  and notifies the verification team. Ownership and status are enforced
  server-side.

Reporters can also submit new stories at `/submit-news.php` and view a quick
"My submissions" list on `/profile.php`.
