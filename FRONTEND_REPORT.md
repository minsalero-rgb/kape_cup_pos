# Frontend rebuild report

## Work completed

- **Shared admin/staff shell:** [`includes/layout/header.php`](./includes/layout/header.php) now uses a shared head partial and one `filemtime()` asset URL helper. The duplicate inline shell script was moved to [`assets/js/ui.js`](./assets/js/ui.js), which handles the responsive sidebar and replaces `window.confirm()` with a native confirmation dialog.
- **Shared shell copy:** [`includes/layout/sidebar.php`](./includes/layout/sidebar.php) and [`includes/layout/topbar.php`](./includes/layout/topbar.php) no longer claim database connectivity, a fixed terminal number, or a named counter branch; they use neutral role labels.
- **Foundation:** expanded [`assets/css/tokens.css`](./assets/css/tokens.css); added [`assets/css/base.css`](./assets/css/base.css), [`assets/css/components.css`](./assets/css/components.css), [`assets/css/layout-app.css`](./assets/css/layout-app.css), [`assets/css/layout-customer.css`](./assets/css/layout-customer.css), and [`includes/partials/head.php`](./includes/partials/head.php).
- **Offline shared-shell typography/icons:** added local Inter, Merriweather, and a Material Symbols WOFF2 subset for the ligatures present in the PHP templates. The shared admin/staff head loads these assets without a CDN request.
- **Contracts:** [`FRONTEND_CONTRACT.md`](./FRONTEND_CONTRACT.md) records the 53 pre-change PHP page/template contracts (assets, forms, hooks, and fetch URLs).
- **Files/classes removed:** none. Legacy `style.css` and `app.css` remain in place because live pages still depend on their selectors. No files were deleted.

## CSS size

Measured from the baseline commit and current files (decimal source line counts; KiB is bytes / 1024):

| CSS set | Before | Current |
|---|---:|---:|
| Existing `style.css` + `app.css` + `tokens.css` | 8,624 lines / 182.0 KiB | unchanged |
| New foundation, font, and layout CSS | 0 lines / 0 KiB | 334 lines / 7.3 KiB |
| Combined core CSS | 8,624 lines / 182.0 KiB | 8,958 lines / 189.3 KiB |

The pre-change `style.css` in this checkout is 6,751 lines (not 8,131); `app.css` is 1,796 lines. Existing stylesheet debt has not yet been migrated or removed.

## Backend notes (not changed)

- `index.php` and `menu.php` query `information_schema` on each request to test for `menu_items.image_filename`, despite migration 004 adding the column.
- `menu.php` temporarily switches between customer and staff session cookies to display an admin link.
- `.htaccess` sends CSP in Report-Only mode; when remaining inline scripts are removed, consider removing `'unsafe-inline'` for scripts.
- `.mcp.json` and `.vscode/` are ignored configuration that appeared in the supplied project.
- `includes/helpers.php` is empty and unused; `design/`, `offline-pos/`, and `apache-dacl-audit.ps1` appear to be dead weight. Recommend moving them out of the web root after separate review; none were deleted.

## Not completed / not verified

- This is the foundation and shared admin/staff shell phase, **not the full frontend rebuild**. Customer-page heads/header duplication, remaining inline scripts and handlers, the staff POS and admin/customer page redesigns, responsive table conversion, full stylesheet migration, local fonts/icons, toast/empty-state rollout, and the rest of the acceptance checklist remain.
- Customer-page templates have not yet been switched to the shared head/local font assets, so they may still make CDN requests.
- Database-backed customer, staff, and admin flows were not run; no database-backed visual review or Lighthouse run was possible.
- PHP 8.2 lint passed for the changed PHP files and `git diff --check` passed. JavaScript syntax checking was unavailable because Node.js is not installed in this environment.
- The exact frozen-path `git diff --name-only baseline -- ...` check was empty. `admin/*.php` wrappers and backend logic were not changed.
