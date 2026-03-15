---
inclusion: auto
---

# Broodle WHMCS Tools — Module Steering Rules

## Module Identity

- **Name**: Broodle WHMCS Tools
- **Author**: Broodle (https://broodle.host)
- **GitHub Repo**: `maitpatni/broodle-whmcs-tools`
- **Current Version**: defined in `BROODLE_TOOLS_VERSION` constant in `broodle_whmcs_tools.php`
- **License**: Proprietary
- **WHMCS Addon Type**: Addon Module (lives in `modules/addons/broodle_whmcs_tools/`)

## Architecture Overview

### File Roles

| File | Purpose |
|------|---------|
| `broodle_whmcs_tools.php` | Main module: config, activate/deactivate, admin output, update system, DB helpers |
| `hooks.php` | Client-area hooks: Nameservers tab, Email section, WordPress Toolkit tab, all CSS/JS |
| `ajax.php` | AJAX handler for email actions (create, password, delete, webmail login, get domains) |
| `ajax_wordpress.php` | AJAX handler for WordPress actions (instances, plugins, themes, security, WP-CLI) |
| `lang/english.php` | Language strings (`$_ADDONLANG` array) |

### Function Naming Conventions

- Module lifecycle: `broodle_whmcs_tools_config()`, `broodle_whmcs_tools_activate()`, `broodle_whmcs_tools_deactivate()`, `broodle_whmcs_tools_output()`
- Internal helpers: `broodle_tools_*` prefix (e.g., `broodle_tools_get_setting()`, `broodle_tools_ns_enabled()`)
- AJAX helpers in `ajax.php`: `broodle_ajax_whm_call()`
- AJAX helpers in `ajax_wordpress.php`: `broodle_wp_whm_call()`, `broodle_wp_uapi_call()`, `broodle_wp_exec_wpcli()`
- Output builders: `broodle_tools_build_ns_output()`, `broodle_tools_build_email_output()`, `broodle_tools_build_wp_output()`

## Database Schema

### Table: `mod_broodle_tools_settings`

Created on activation, dropped on deactivation.

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT AUTO_INCREMENT | Primary key |
| `setting_key` | VARCHAR(255) UNIQUE | Setting identifier |
| `setting_value` | TEXT NULLABLE | Setting value |
| `created_at` | TIMESTAMP | |
| `updated_at` | TIMESTAMP | |

### Setting Keys

| Key | Default | Description |
|-----|---------|-------------|
| `tweak_nameservers_tab` | `1` | Show Nameservers tab on cPanel service details |
| `tweak_email_list` | `1` | Show Email Accounts section on cPanel service details |
| `tweak_wordpress_toolkit` | `0` | Show WordPress Toolkit tab on cPanel service details |
| `auto_update_enabled` | `0` | Enable auto-update checks from GitHub |

Use `broodle_tools_get_setting($key, $default)` to read settings. Use `broodle_tools_setting_enabled($key)` for boolean checks.

## cPanel/WHM API Integration

### Authentication Pattern

The access hash stored in `tblservers.accesshash` may be:
1. A **plaintext API token** (short alphanumeric string, 10-64 chars) — use directly
2. An **encrypted value** — call `decrypt()` first, then validate

**CRITICAL**: Always detect plaintext tokens first with regex `/^[A-Za-z0-9]{10,64}$/`. If it matches, do NOT call `decrypt()` — it will return garbage.

```php
$raw = trim($server->accesshash);
if (preg_match('/^[A-Za-z0-9]{10,64}$/', $raw)) {
    $accessHash = $raw; // Plaintext API token — use directly
} else {
    $accessHash = trim(decrypt($raw)); // Encrypted — decrypt first
    if (empty($accessHash) || !preg_match('/^[A-Za-z0-9]{10,64}$/', $accessHash)) {
        $accessHash = ''; // Decryption failed, fall back to password
    }
}
```

**Auth header format**: `Authorization: whm {serverUser}:{token}`

**Fallback**: If no access hash, use password auth via `CURLOPT_USERPWD`.

### UAPI via WHM Proxy

All cPanel UAPI calls go through the WHM proxy (port 2087):

```
{protocol}://{hostname}:{port}/json-api/cpanel
  ?cpanel_jsonapi_user={cpUsername}
  &cpanel_jsonapi_apiversion=3
  &cpanel_jsonapi_module={Module}
  &cpanel_jsonapi_func={function}
  &{params}
```

### API Endpoints Used

**Email (ajax.php)**:
- `Email::add_pop` — Create email account
- `Email::passwd_pop` — Change email password
- `Email::delete_pop` — Delete email account
- `Email::list_pops` — List email accounts (primary)
- `Email::list_pops_with_disk` — List emails with disk info (fallback)
- `DomainInfo::list_domains` — Get domains for account
- `create_user_session` (WHM API, service=webmaild) — Webmail auto-login

**Email list fallback chain** (in `broodle_tools_get_emails()`):
1. UAPI `Email::list_pops`
2. WHM `list_pops_for`
3. UAPI `Email::list_pops_with_disk`

**WordPress (ajax_wordpress.php)**:
- `WordPressInstanceManager::get_instances` — List WP installations
- `WordPressInstanceManager::get_instance_by_id` — Get WP instance details
- `WordPressInstanceManager::start_scan` — Scan for new WP installations
- `Shell::exec` — Execute WP-CLI commands
- `create_user_session` (WHM API, service=cpaneld) — cPanel auto-login for WP admin

### cURL Settings

All WHM API calls use:
- `CURLOPT_SSL_VERIFYPEER => false`
- `CURLOPT_SSL_VERIFYHOST => false`
- `CURLOPT_TIMEOUT => 20` (email) or `30` (WordPress)

## UI Conventions

### Accent Color

Primary accent: `#0a5ed3` — used everywhere for icons, buttons, toggles, badges, links, focus rings.

Secondary colors:
- WordPress sections: `#21759b` (WordPress brand blue)
- Success/OK: `#059669`
- Warning: `#d97706`
- Danger/Delete: `#ef4444`

### CSS Class Prefixes

| Prefix | Feature |
|--------|---------|
| `bt-` | Admin settings page |
| `bns-` | Nameservers card (shared card styles used by email too) |
| `bem-` | Email management (rows, buttons, modals, fields) |
| `bwp-` | WordPress Toolkit (cards, sites, detail panel, tabs) |

### Theme Compatibility

- **Primary target**: Lagom WHMCS theme
- Tab navigation: `ul.panel-tabs.nav.nav-tabs` (Lagom), fallback to `.section-body ul.nav.nav-tabs`
- Dark mode: CSS variables with `[data-theme="dark"]` and `.dark-mode` selectors
- Uses CSS custom properties: `--card-bg`, `--border-color`, `--heading-color`, `--text-muted`, `--input-bg`

### Layout Rules

- **Nameservers**: Injected as a tab in the bottom panel-tabs nav (alongside Overview, etc.)
- **Email Accounts**: Injected as a tab in the bottom panel-tabs nav (after Nameservers, before WordPress Manager)
- **WordPress Manager**: Injected as a tab in the bottom panel-tabs nav
- **Default "Quick Create Email Account"**: Hidden via CSS (`.quick-create-email`) and JS (scans headings for "quick create email" text)

### Modal Pattern

All modals use the `bem-overlay` / `bem-modal` pattern:
- Fixed overlay with `rgba(0,0,0,.45)` background
- Centered modal with `border-radius: 14px`
- Head / Body / Foot sections
- Close via `[data-close]` attribute, overlay click, or X button
- Message area with `.bem-modal-msg` (`.success` or `.error` class)

## AJAX Handler Pattern

Both `ajax.php` and `ajax_wordpress.php` follow the same structure:

1. Define `CLIENTAREA` constant, require WHMCS init
2. Verify logged-in client via `WHMCS\ClientArea`
3. Get `action` and `service_id` from POST
4. Verify service belongs to client (`tblhosting.userid === clientId`)
5. Check feature toggle is enabled in `mod_broodle_tools_settings`
6. Get server info from `tblservers`
7. Resolve auth (access hash detection → decrypt fallback → password fallback)
8. Route via `switch ($action)` block
9. Return JSON: `{ success: bool, message: string, ...data }`

### Client-Side AJAX

Uses vanilla `XMLHttpRequest` with `FormData`. No jQuery dependency.

```javascript
var ajaxUrl = "modules/addons/broodle_whmcs_tools/ajax.php";
var wpAjaxUrl = "modules/addons/broodle_whmcs_tools/ajax_wordpress.php";
```

## Admin Settings Page

Located in `broodle_tools_render_admin()`. Features:
- Header with logo icon, version, and broodle.host link
- Tweaks section with toggle switches for each feature
- Updates section with auto-update toggle and manual check button
- Save button posts to `?action=save_settings`
- Update check/apply via `?action=check_update` / `?action=apply_update`

## Auto-Update System

- Checks GitHub API: `https://api.github.com/repos/{repo}/releases/latest`
- Compares `tag_name` (stripped of `v` prefix) against `BROODLE_TOOLS_VERSION`
- Downloads zipball, extracts, copies files over module directory
- Uses `broodle_tools_copy_directory()` and `broodle_tools_delete_directory()` helpers

## Version Bumping & Release Workflow

### Steps to release a new version:

1. Update `BROODLE_TOOLS_VERSION` constant in `broodle_whmcs_tools.php`
2. Commit changes
3. Create and push a git tag: `git tag v{X.Y.Z} && git push origin v{X.Y.Z}`
4. GitHub Actions workflow (`.github/workflows/release.yml`) auto-creates the release with a zip
5. **IMPORTANT**: The Actions workflow creates the release automatically. To update release notes, use GitHub API `PATCH` (not `POST`, which fails with `already_exists`)

### Semver Guidelines

- MAJOR: Breaking changes to settings schema or hook behavior
- MINOR: New features (new tweaks, new tabs, new AJAX actions)
- PATCH: Bug fixes, UI tweaks, release note updates

### Git Config

```
user.email = hello@broodle.host
user.name = Broodle
```

## Development Environment

- **OS**: Windows
- **PHP**: `C:\aapanel\BtSoft\php\83\php.exe` (NOT in PATH — always use full path)
- **WHMCS Path**: `C:\aapanel\BtSoft\wwwroot\whmcs\`
- **MySQL**: `C:\aapanel\BtSoft\mysql\MySQL5.7\bin\mysql.exe` (user=`whmcs`, db=`whmcs`)
- **PowerShell TLS is broken** — use PHP for any API testing
- **Shell**: bash

## Security Rules

- Never expose server credentials, API tokens, or database passwords in client-facing output
- Always verify client ownership of service before any AJAX action
- Always check feature toggle before processing AJAX requests
- Sanitize all user input (email usernames, domains, WP paths)
- Use `escapeshellarg()` for any shell commands passed to WP-CLI
- SSL verification is disabled for WHM API calls (self-signed certs on cPanel servers)

## Adding New Features

When adding a new tweak/feature:

1. Add setting key to `$defaults` array in `broodle_whmcs_tools_activate()`
2. Add toggle row in `broodle_tools_render_admin()` (follow existing pattern)
3. Add to `$tweaks` array in the save handler
4. Add helper function: `broodle_tools_{feature}_enabled()`
5. Add conditional block in `ClientAreaProductDetailsOutput` hook
6. Create output builder: `broodle_tools_build_{feature}_output()`
7. If AJAX needed, add cases to existing `ajax.php` or create new handler
8. Add language strings to `lang/english.php`
9. Add CSS with appropriate prefix to `broodle_tools_shared_script()`
10. Bump version and release
