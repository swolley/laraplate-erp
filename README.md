<p>&nbsp;</p>
<p align="center">
	<a href="https://github.com/swolley" target="_blank">
		<img src="https://raw.githubusercontent.com/swolley/images/refs/heads/master/logo_laraplate.png?raw=true" width="400" alt="Laraplate Logo" />
    </a>
</p>
<p>&nbsp;</p>

> ⚠️ **Caution**: This package is a **work in progress**. **Don't use this in production or use at your own risk**—no guarantees are provided... or better yet, collaborate with me to create the definitive Laravel boilerplate; that's the right place to introduce your ideas. Let me know your ideas...

## Table of Contents

-   [Description](#description)
-   [Installation](#installation)
-   [Configuration](#configuration)
-   [Features](#features)
-   [Scripts](#scripts)
-   [Contributing](#contributing)
-   [License](#license)

## Description

The Crm Module provides the foundation for **Customer Relationship Management** in Laraplate: contacts, accounts, pipelines, activities, and sales workflows. The module is **optional** and can be enabled or disabled via `modules_statuses.json` like other Laraplate modules.

At this stage the package ships as a **scaffold** (service provider, routes, config, and tooling). Domain models, Filament resources, and migrations will grow alongside product requirements.

## Installation

If you want to add this module to your project, you can use the `joshbrw/laravel-module-installer` package.

In a full Laraplate application you typically depend on **Core** first, then add Crm. Add the repositories to your `composer.json` file (adjust URLs if you use forks or private registries):

```json
"repositories": [
    {
        "type": "composer",
        "url": "https://github.com/swolley/laraplate-core.git"
    },
    {
        "type": "composer",
        "url": "https://github.com/swolley/laraplate-crm.git"
    }
]
```

```bash
composer require joshbrw/laravel-module-installer swolley/laraplate-core swolley/laraplate-crm
```

Then install and activate the modules:

```bash
php artisan module:install Core
php artisan module:install Crm
```

Ensure `modules_statuses.json` lists `Crm` as enabled when you want the module loaded.

## Configuration

When the module is active, configuration is published under the **`crm`** key (file: `Modules/Crm/config/config.php`).

```php
// Example
config('crm.name'); // "Crm"
```

Add environment-driven settings here as features are implemented, for example:

```env
# CRM (examples — extend as the module evolves)
# CRM_DEFAULT_LOCALE=en
# CRM_AUDIT_ENABLED=true
```

## Features

### Requirements

-   PHP >= 8.5
-   Laravel 12.0+
-   **Recommended:** Laraplate **Core** for users, permissions, and shared infrastructure (typical production setup)

### Installed Packages (development)

The Crm module aligns with the same quality toolchain as **Cms** and **Core**:

-   [pestphp/pest](https://github.com/pestphp/pest) and Laravel / type-coverage plugins
-   [laravel/pint](https://github.com/laravel/pint)
-   [larastan/larastan](https://github.com/larastan/larastan)
-   [rector/rector](https://github.com/rectorphp/rector) and [driftingly/rector-laravel](https://github.com/driftingly/rector-laravel)
-   [filament/filament](https://github.com/filamentphp/filament) (v5) for future admin UI
-   [nunomaduro/phpinsights](https://github.com/nunomaduro/phpinsights), [peckphp/peck](https://github.com/peckphp/peck)

### Module metadata

-   **Priority:** `999` in `module.json` (load order consistent with other feature modules)
-   **Namespace:** `Modules\Crm\...`
-   **Web / API routes:** `Modules/Crm/routes/web.php`, `Modules/Crm/routes/api.php`

### Roadmap (planned CRM capabilities)

-   Accounts, contacts, and leads
-   Pipelines, deals, and stages
-   Activities, tasks, and reminders
-   Integration with Core auth, roles, and auditing where applicable
-   Filament panels for back-office CRM

## Scripts

The Crm module exposes the same Composer script conventions as **Cms** and **Core**:

### Code quality and testing

```bash
composer test                 # Full test and quality pipeline
composer test:standalone      # Unit-focused Pest run
composer test:type-coverage   # Type coverage (target: 100%)
composer test:typos           # Peck typo check
composer test:lint            # Pint + Rector dry-run
composer test:types           # PHPStan
composer test:refactor        # Rector
```

### Maintenance

```bash
composer lint                 # Rector, Pint, IDE helpers
composer check                # PHPStan
composer fix                  # PHPStan with fix
composer refactor             # Rector
composer update:requirements  # composer bump + npm-check-updates
```

### Versioning

```bash
composer version:major
composer version:minor
composer version:patch
```

### Hooks

```bash
composer setup:hooks
```

## Contributing

If you want to contribute to this project, follow these steps:

1. Fork the repository.
2. Create a new branch for your feature or fix.
3. Open a pull request.

## License

Crm Module is open-sourced software licensed under the [GNU AGPL v3](https://www.gnu.org/licenses/agpl-3.0.html).

## TODO

High-level items for early development:

- [ ] Domain model design (contacts, companies, deals, activities)
- [ ] Migrations and factories
- [ ] Filament resources and policies
- [ ] API resources and form requests
- [ ] Documentation for integration with Core (users, teams, permissions)
