# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A lightweight, single-file PHP MVC micro-framework (no Composer, no vendor directory). Chinese-language codebase — comments, documentation, and variable names are in Chinese. The framework uses traditional URL query parameter routing (`?c=ControllerName&do=methodName`) instead of URL rewriting.

## Running the Project

Serve with any PHP-capable web server:
```
php -S localhost:8000
```

No test framework. CLI test runner validates core modules:
```
php run_tests.php            # Run all tests
php run_tests.php db         # Database tests (requires MySQL)
php run_tests.php http       # HTTP client tests (requires internet)
php run_tests.php route      # Routing/bootstrap tests (no dependencies)
```

Web-based tests via browser: `?c=DatabaseTest&do=view`, `?c=HttpTest&do=view`, `?c=RouteTest&do=view`.

## Architecture

**Entry point:** `index.php` — accepts `c` (controller name) and `do` (method name) via `$_REQUEST`. Input is sanitized to alphanumeric + underscore only. Defaults to `TestController::home()`.

**Service container:** `system/bootstrap.php` — the `bootstrap` class is the service container, router, and autoloader. All framework services are accessed through static methods on this class or through global shorthand functions.

**Core libraries in `system/core/`:**
- `database.php` — PDO wrapper with CRUD helpers (`fetch`, `fetchAll`, `insert`, `update`, `delete`), transaction support, and parameterized queries. Auto-detects MySQL vs SQL Server from DSN prefix.
- `template.php` — HTML template compiler. Compiles `.html` templates to cached `.php` files. Supports `{$var}`, `{foreach}`, `{if}/{else}/{elseif}`, `{for}`, `{include()}`, `{php}...{/php}` blocks, and dot-to-arrow (`.` → `->`) conversion for object access.
- `http.php` — cURL-based HTTP client with fluent builder pattern: `http::post($url)->withForm($data)->onReady($callback)->submit()`.

**Application directory `app/`:**
- `config/` — PHP files returning associative arrays. Database config uses sectioned format: `["section_name" => ["dsn"=>..., "user"=>..., "passwd"=>...]]`.
- `controllers/` — Classes suffixed with `Controller`. Auto-loaded by `bootstrap::controller()`.
- `models/` — Classes suffixed with `Model`. Auto-loaded by `bootstrap::model()`.
- `views/` — `.html` template files. Compiled output cached in `app/cache/`.
- `libs/` — Autoloaded via `ww_import()` or `spl_autoload_register`.

## Key Conventions

- **Controller routing:** `?c=Test` maps to `TestController`. The `c` param omits the "Controller" suffix. Passing the full name (`?c=TestController`) also works.
- **Model naming:** `XxxModel`, accessed via `bootstrap::model("Xxx")`.
- **Database access:** `bootstrap::dao("section_name", "config_file")` returns a `database` instance. Config files return sectioned arrays keyed by section name.
- **View rendering:** `ww_view("/path", $params)` — path maps to `app/views/path.html`.
- **DEBUG mode:** Enabled via `define("DEBUG", true)` before including bootstrap. Forces template recompilation on every request, verbose error display, and human-readable cache filenames.
- **Template security:** `RENDERER_HEAD` guard (`ALLOW_ACCESS` check) is prepended to all compiled templates to prevent direct access.
- **Global functions:** `ww_route`, `ww_view`, `ww_model`, `ww_import`, `ww_config`, `ww_dao`, `ww_setVar`, `ww_getVar` — all delegate to `bootstrap` class methods.
- **DAO caching:** Controller and DAO instances are singleton-cached per request by the bootstrap class.
- **No models directory exists yet** — it would be at `app/models/` when needed.
- **No libs directory exists yet** — it would be at `app/libs/` when needed.
