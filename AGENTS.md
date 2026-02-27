---
project:
  name: auto-flickr-importer
  type: wordpress-plugin
  language:
    - php
    - javascript
  php: ">=8.2"
  wordpress: ">=6.5"
  scheduler: action-scheduler
agent_config:
  skills_dir: .agents
  default_branch: main
  pr_template: .github/PULL_REQUEST_TEMPLATE.md
---

# AGENTS

This file defines how coding agents should work in this repository.

## Repository Facts

- Plugin bootstrap: `auto-flickr-importer.php`
- Main plugin class: `src/Plugin.php`
- Core services:
  - Settings UI and option registration: `src/Settings.php`
  - Automation entrypoint (admin + cron trigger): `src/Import_Automator.php`
  - Background orchestration: `src/BackgroundTasks.php`
  - Task implementations: `src/Tasks/*.php`
  - Flickr API integration and helpers: `src/API/` and `includes/flickr-helpers.php`
- Import domains:
  - Initial full import (`initial_import`)
  - Hourly latest sync (`fetch_latest_import`)
  - 12-hour comment delta sync (`fetch_comment_delta_import`)

## Commit History Signals

Recent work has focused on:

- More robust failed-task handling and cleanup.
- Guardrails around media path usage for video imports.
- Rate-limit safety and import-timestamp correctness.
- Build/release workflow reliability.

When changing behavior, preserve these stability priorities first.

## Operating Principles

1. Keep changes minimal and localized to the relevant import path.
2. Prefer extending existing task flow hooks over introducing parallel schedulers.
3. Preserve Action Scheduler idempotency checks and run-id safety behavior.
4. Never log or expose Flickr API secrets or site credentials.
5. Preserve translation wrappers (`__`, `esc_html__`, etc.) for user-facing strings.
6. Match existing WordPress coding standards and escaping/sanitization patterns.

## Safe Change Workflow

1. Identify affected flow:
   - settings
   - initial import
   - latest import
   - comment delta import
2. Confirm relevant task flags remain correct (`import_running`, `initial_import_running`, `comment_delta_running`, timestamps).
3. Validate no duplicate scheduling is introduced.
4. Run validation commands (see below).
5. Document test steps in PR format expected by `.github/PULL_REQUEST_TEMPLATE.md`.

## Validation Commands

### PHP / WordPress

- `composer lint:php`
- `composer format:php` (only when intentionally formatting)

### JS / Assets

- `npm run lint`
- `npm run build`

Run only the subset impacted by the change, but ensure PHP linting is always executed for PHP edits.

## High-Risk Areas

- `src/BackgroundTasks.php` (scheduling lifecycle and failure handling)
- `src/Tasks/*.php` (run queue generation and cleanup flags)
- `src/Importers/*.php` (data integrity and API rate limiting)
- `includes/settings.php` and `src/Settings.php` (stored option correctness)

## Definition Of Done

- Behavior change is covered by a reproducible manual test plan.
- No regression in recurring task registration behavior.
- No new PHPCS / lint issues in changed files.
- User-facing admin notices remain escaped and translatable.
- PR notes include risk, rollout, and rollback guidance when task flow changes.

## Skills To Use

Agents should load and follow these repository-local skills from `.agents/`:

- `.agents/repo-triage.md`
- `.agents/background-task-changes.md`
- `.agents/flickr-import-debugging.md`
- `.agents/release-readiness.md`
