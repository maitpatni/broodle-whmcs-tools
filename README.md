# Broodle WHMCS Tools

A comprehensive WHMCS addon module providing various tweaks and enhancements for your WHMCS installation.

**Author:** [Broodle](https://broodle.host)
**Version:** 1.0.0
**Compatibility:** WHMCS 9.x, PHP 8.1+, Lagom Theme

## Features

### Nameservers Tab
Adds a "Nameservers" tab to the cPanel product details page in the client area. Displays the service's assigned nameservers in a clean, modern UI with copy-to-clipboard functionality. Fully compatible with the Lagom theme including dark mode.

### Auto Update
Built-in update system that checks for new releases from the GitHub repository. Check for updates and apply them directly from the WHMCS admin panel.

## Installation

1. Upload the `modules/addons/broodle_whmcs_tools` folder to your WHMCS `modules/addons/` directory.
2. Go to **WHMCS Admin → Setup → Addon Modules**.
3. Find **Broodle WHMCS Tools** and click **Activate**.
4. Configure access control as needed.
5. Navigate to **Addons → Broodle WHMCS Tools** to manage settings.

## Settings

| Tweak | Description |
|-------|-------------|
| Nameservers Tab | Adds a Nameservers tab on cPanel product details pages |
| Auto Update | Enable automatic update checking from GitHub |

## File Structure

```
modules/addons/broodle_whmcs_tools/
├── broodle_whmcs_tools.php   # Main module file
├── hooks.php                  # WHMCS hooks (nameservers tab injection)
├── lang/
│   └── english.php            # Language strings
└── README.md
```

## Requirements

- WHMCS 9.0 or later
- PHP 8.1 or later
- cURL extension enabled
- ZipArchive extension (for auto-updates)

## License

Proprietary — © 2026 Broodle. All rights reserved.

## Support

Visit [https://broodle.host](https://broodle.host) for support.
