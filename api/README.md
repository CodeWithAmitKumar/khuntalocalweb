# api/

REST-style JSON endpoints for the future Android application.

**Status: planned for Phase 5.** The `api_tokens` table (Phase 1 schema) and the
consistent `json_response()` helper (`includes/helpers.php`) are already in place
so the API layer can be added without reworking the core.

Planned endpoints include:

```
/api/auth/login.php      /api/auth/register.php     /api/auth/logout.php
/api/news/list.php       /api/news/detail.php       /api/news/create.php
/api/news/update.php     /api/news/search.php
/api/categories/list.php /api/locations/list.php
/api/comments/create.php /api/comments/list.php
/api/notifications/list.php
```
