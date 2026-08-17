# API Security

Public endpoints are `/api/health`, client catalog and master-data reads, and
the client/admin register, login, and refresh endpoints. Authentication
endpoints are rate limited.

Every other `/api/v1` endpoint requires its JWT middleware and an explicit
Spatie permission. Admin resources use `<resource>.<action>` (for example,
`products.update`); customer actions use `client.<resource>.manage`.

Default roles are `admin`, `manager`, `staff`, and `user`. The `user` role is
assigned on customer registration; the migration safely assigns it only to
existing users without a role. Existing role assignments are not changed.

Rate limits: client auth (10/minute, 60/15 minutes), admin auth (5/minute,
20/15 minutes), coupon application (30/minute), checkout (10/minute), admin
credential changes (5/minute), stock changes (20/minute), and reports/logs
(60/minute).

To add a protected API, add its permission to `AdminPermissionsSeeder`, grant
it to the intended roles, and apply both authentication and `permission:<key>`
middleware. Use validated request input and allow-lists for dynamic query
columns. API errors use the standard JSON envelope and do not expose internal
details in production.
