---
name: background-task-changes
description: Guardrails for changing Action Scheduler-based task lifecycle and queue behavior.
version: 1
depends_on:
  - .agents/repo-triage.md
targets:
  - src/BackgroundTasks.php
  - src/Tasks
---

# Background Task Changes Skill

## Use When

- Modifying `src/BackgroundTasks.php`.
- Changing queue generation or chunk processing in `src/Tasks/*.php`.
- Adjusting task cadence, start args, or cleanup behavior.

## Required Guardrails

1. Preserve run-id consistency checks against latest run.
2. Keep cleanup paths for both success and failure.
3. Avoid duplicate scheduling by reusing existing scheduler wrappers.
4. Keep option key naming patterns consistent (`wpcomsp_bg-task_*`).
5. Do not break state flags:
   - `import_running`
   - `initial_import_running`
   - `comment_delta_running`

## Verification

- Trigger or inspect all affected transitions:
  - start -> run -> continue -> cleanup
  - start -> run -> cleanup_failed
- Confirm no stale error option remains on successful cleanup.
- Confirm recurring tasks remain singular after registration.

## Anti-Patterns

- Scheduling actions directly outside wrappers without group/data checks.
- Skipping failure cleanup while adding new error paths.
- Introducing new option keys when existing task-key patterns suffice.
