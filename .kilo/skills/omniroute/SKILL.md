---
name: omniroute
description: Use when adding or changing CodeIgniter 4 routes, authentication filters, role access, or diagnosing unauthorized route access in SIDAK TEJO.
---

# Omniroute — SIDAK TEJO route and access-control guide

Use the framework and conventions present in this repository. SIDAK TEJO uses CodeIgniter 4, with routes in `app/Config/Routes.php` and filters configured in `app/Config/Filters.php`.

## Before changing a route

1. Read the relevant route group and inspect the matching filter implementation and controller action.
2. Search for the same route pattern and role names elsewhere in the repository; reuse existing identifiers rather than inventing roles.
3. Check resource-level access rules such as ULP scope, ownership, and workflow status before deciding whether a route filter alone is sufficient.

## Route and authorization rules

- Put authentication and broad role access on the route with the project's existing CodeIgniter filters. The protected route group already uses `auth`; role restrictions use the `role:` filter argument.
- Keep controller or service checks for resource-specific authorization and business rules. A route role filter does not prove that the user may access every record or ULP identifier supplied in a request.
- Follow the project's existing CSRF and HTTP-method conventions for state-changing routes.
- Fail closed when the required filter, role, or scope cannot be established. Do not remove an existing authorization layer as a shortcut.
- Do not copy Laravel examples such as `routes/web.php`, `Route::middleware()`, or `Auth::user()->role` into this CodeIgniter 4 project.

## Existing CodeIgniter pattern

Inside the existing authenticated group in `app/Config/Routes.php`, a role-restricted route follows this form:

```php
$routes->get('reports', 'ReportController::index', [
    'filter' => 'role:administrator,admin_pusat',
]);
```

For a group of routes, use the same `$routes->group(..., ['filter' => 'role:...'], ...)` style found in the surrounding file. Preserve the surrounding auth group instead of adding a parallel framework-style route system.

## Verify

- Run `php spark routes` and confirm the route has the expected auth and role filters.
- Check the matching filter and existing authorization tests for denied and allowed roles; use the repository's actual test command.
- Do not treat a route listing as proof of resource-level authorization or claim access behavior was tested unless it was exercised.
