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

The **ERP** module provides Laraplate’s **accounting and operations** domain: multi-company, chart of accounts, journal entries, fiscal calendar, tax codes, commercial scaffolding (customers, quotations, projects), and related Filament admin resources. The module is **optional** and can be enabled or disabled via `modules_statuses.json` like other Laraplate modules.

The package evolves with product requirements; treat public APIs as unstable until a stable release is declared.

## Installation

If you want to add this module to your project, you can use the `joshbrw/laravel-module-installer` package.

In a full Laraplate application you typically depend on **Core** first, then add **ERP**. Add the repositories to your `composer.json` file (adjust URLs if you use forks or private registries):

```json
"repositories": [
    {
        "type": "composer",
        "url": "https://github.com/swolley/laraplate-core.git"
    },
    {
        "type": "composer",
        "url": "https://github.com/swolley/laraplate-erp.git"
    }
]
```

```bash
composer require joshbrw/laravel-module-installer swolley/laraplate-core swolley/laraplate-erp
```

Then install and activate the modules:

```bash
php artisan module:install Core
php artisan module:install ERP
```

Ensure `modules_statuses.json` lists `ERP` as enabled when you want the module loaded.

## Configuration

When the module is active, configuration is published under the **`erp`** key (file: `Modules/ERP/config/config.php`).

```php
// Example
config('erp.name'); // "ERP"
```

Add environment-driven settings here as features are implemented.

## Features

### Requirements

-   PHP >= 8.5
-   Laravel 12.0+
-   **Recommended:** Laraplate **Core** for users, permissions, and shared infrastructure (typical production setup)

### Installed Packages (development)

The ERP module aligns with the same quality toolchain as **Cms** and **Core**:

-   [pestphp/pest](https://github.com/pestphp/pest) and Laravel / type-coverage plugins
-   [laravel/pint](https://github.com/laravel/pint)
-   [larastan/larastan](https://github.com/larastan/larastan)
-   [rector/rector](https://github.com/rectorphp/rector) and [driftingly/rector-laravel](https://github.com/driftingly/rector-laravel)
-   [filament/filament](https://github.com/filamentphp/filament) (v5) for admin UI
-   [nunomaduro/phpinsights](https://github.com/nunomaduro/phpinsights), [peckphp/peck](https://github.com/peckphp/peck)

### Module metadata

-   **Priority:** `999` in `module.json` (load order consistent with other feature modules)
-   **Namespace:** `Modules\ERP\...`
-   **Composer package:** `swolley/laraplate-erp`
-   **Web / API routes:** `Modules/ERP/routes/web.php`, `Modules/ERP/routes/api.php`

### Roadmap

-   Full CRM, inventory, and invoicing UI where not yet covered
-   E-invoice provider contracts (implementations as separate packages)
-   Deeper integration with Core auth, roles, and auditing

## Scripts

The ERP module exposes the same Composer script conventions as **Cms** and **Core**:

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

## Repository rename (from laraplate-business)

If you still have a clone using the old remote:

```bash
git remote set-url origin git@github.com:swolley/laraplate-erp.git
# or HTTPS: https://github.com/swolley/laraplate-erp.git
```

In the parent Laraplate app, update `.gitmodules` to `path = Modules/ERP` and `url` / `pushurl` for `laraplate-erp`, then run `git submodule sync` and `composer update`.

## Contributing

If you want to contribute to this project, follow these steps:

1. Fork the repository.
2. Create a new branch for your feature or fix.
3. Open a pull request.

## License

ERP module is open-sourced software licensed under the [GNU AGPL v3](https://www.gnu.org/licenses/agpl-3.0.html).

## TODO

High-level items for early development:

- [ ] Expand domain coverage (CRM, stock, billing) per roadmap
- [ ] API resources and form requests where needed
- [ ] Documentation for integration with Core (users, teams, permissions)
