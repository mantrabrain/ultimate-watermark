# Ultimate Watermark Plugin

A professional WordPress image watermarking plugin built with modern PHP and clean architecture.

## Features

- **PSR-4 Autoloading**: Modern PHP namespace structure
- **Clean Architecture**: Separation of concerns with interfaces and traits
- **Modern Admin UI**: Beautiful, responsive admin interface
- **Dashboard**: Overview of plugin status and quick actions
- **Settings Page**: Comprehensive configuration options
- **Asset Management**: Proper CSS/JS organization
- **WordPress Standards**: Follows WordPress coding standards

## Plugin Structure

```
ultimate-watermark/
├── src/
│   ├── Core/
│   │   ├── Plugin.php
│   │   ├── Activator.php
│   │   ├── Deactivator.php
│   │   ├── Interfaces/
│   │   └── Traits/
│   ├── Admin/
│   │   ├── AdminManager.php
│   │   └── Pages/
│   ├── Assets/
│   └── Helpers/
├── assets/
│   ├── css/
│   └── js/
├── languages/
├── composer.json
└── ultimate-watermark.php
```

## Installation

1. Upload the plugin to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu
3. Navigate to 'Ultimate Watermark' in your admin menu
4. Configure your settings

## Development

### Requirements
- PHP 7.4+
- WordPress 5.0+
- Composer (for development)

### Setup
```bash
composer install
```

### Namespace
All classes use the `MantraBrain\UltimateWatermark` namespace.

## Admin Pages

### Dashboard
- Plugin overview
- Quick stats
- Quick actions

### Settings
- General settings
- Watermark configuration
- Protection options

## License

GPL v3 or later
