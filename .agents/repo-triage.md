---
name: repo-triage
description: Fast repository triage for Auto Flickr Importer before any non-trivial change.
version: 1
owners:
  - wpcomspecialprojects
applies_to:
  - plugin-bootstrap
  - settings
  - background-tasks
  - importers
---

# Repo Triage Skill

## Use When

- Starting a new task without full context.
- Touching scheduling, importer flow, or settings.

## Steps

1. Read `auto-flickr-importer.php`, `functions.php`, and `src/Plugin.php` to map boot sequence.
2. Determine affected domain:
   - settings (`src/Settings.php`, `includes/settings.php`)
   - task orchestration (`src/BackgroundTasks.php`, `src/Tasks/*`)
   - Flickr API/integration (`src/API/*`, `includes/flickr-helpers.php`, `src/Importers/*`)
3. Check recent git history for similar changes and regressions.
4. Identify the minimum files needed for a safe implementation.
5. Produce a short risk list before editing.

## Output Checklist

- Entry points identified.
- Primary affected flow identified.
- Risk areas listed.
- Validation commands selected.
