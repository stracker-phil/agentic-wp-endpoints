# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Build and Test Commands

This project uses ddev for local development. Start ddev if not running: `ddev start`

```bash
# Install dependencies
ddev composer install

# Run all tests
ddev exec phpunit

# Run tests with readable output
ddev exec phpunit --testdox

# Run specific test file
ddev exec phpunit tests/Unit/Converter/MarkdownToBlocksTest.php

# Run tests matching a pattern
ddev exec phpunit --filter "it_converts_headings"

# Regenerate autoloader after adding classes
ddev composer dump-autoload
```

## Architecture Overview

This is a WordPress plugin that provides REST API endpoints for converting between Markdown and Gutenberg blocks.

### Dependency Injection Pattern

The plugin uses WPDI (WordPress Dependency Injection) with a composition root pattern:

- **`src/Plugin.php`** - Extends `WPDI\Scope`, the only place where service resolution happens
- **`wpdi-config.php`** - Container configuration binding interfaces to implementations
- All classes receive dependencies via constructor injection (no service locator calls)

### Core Components

**Converters** (`src/Converter/`):
- `MarkdownToBlocks` - Parses Markdown line-by-line and creates Gutenberg block arrays
- `BlocksToMarkdown` - Converts Gutenberg blocks back to Markdown syntax

**REST Endpoints** (`src/Endpoints/`):
- `AbstractEndpoint` - Base class with shared functionality (permission checks, response helpers)
- `ToBlocksEndpoint` - POST `/wp-json/agentic/v1/convert/to-blocks`
- `ToMarkdownEndpoint` - GET `/wp-json/agentic/v1/convert/to-markdown`

**Application** (`src/Application.php`):
- Receives endpoint instances via constructor
- Registers all endpoints on `rest_api_init` hook

### Request Flow

1. WordPress calls `rest_api_init`
2. `Application::register_rest_routes()` registers each endpoint
3. Endpoint receives request, validates parameters
4. Endpoint calls converter with input data
5. Converter returns structured data
6. Endpoint returns JSON response

### Test Structure

Tests use PHPUnit 10 with WordPress function stubs defined in `tests/bootstrap.php`:

- `tests/Unit/Converter/` - Converter unit tests
- `tests/Unit/Endpoints/` - Endpoint unit tests
- `tests/Integration/` - Application integration tests

Global variables (`$mock_posts`, `$mock_parsed_blocks`, etc.) control WordPress function behavior in tests.

## Code Style

WordPress coding standards with strict types. Key rules:

- **Spacing**: Spaces inside parentheses `function( $param )`, `if ( $condition )`, `array( $item )`
- **Arrays**: Spaces after `=>` aligned for readability, trailing commas
- **Concatenation**: Spaces around `.` operator: `$var . 'string'`
- **Declaration**: `declare( strict_types = 1 );` with spaces
- **Naming**: `snake_case` for methods and variables, `PascalCase` for classes
- **Braces**: Opening brace on same line, blank line after class opening brace
- **Indentation**: Tabs, not spaces
- **PHPDoc**: Required for all classes, methods, and properties with `@param`, `@return`, `@var`
- **PHP 8.1**: Syntax supports `match`, `switch`, `mixed`, `enum`, etc.
