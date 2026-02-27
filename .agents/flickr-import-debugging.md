---
name: flickr-import-debugging
description: Debug import failures, rate-limit issues, and partial sync behavior safely.
version: 1
depends_on:
  - .agents/repo-triage.md
focus:
  - flickr-api
  - importers
  - task-errors
---

# Flickr Import Debugging Skill

## Use When

- Initial import stalls or fails.
- Hourly/12-hour jobs run but data is missing.
- Errors appear on the settings page notice.

## Debug Flow

1. Identify failing task name (`initial_import`, `fetch_latest_import`, `fetch_comment_delta_import`).
2. Inspect latest run id and persisted error option (`wpcomsp_bg-task_{task}_run-{id}_error`).
3. Confirm prerequisite settings exist:
   - API key/secret
   - Flickr username
   - site author username
4. Trace importer path through:
   - `src/Tasks/*.php`
   - `src/Importers/*.php`
   - `includes/flickr-helpers.php`
5. Verify rate-limit protections are not bypassed (especially around comment delta vs latest import concurrency).

## Remediation Guidance

- Prefer recovering via existing cleanup and retry behavior.
- Keep fixes idempotent; avoid re-import loops that create duplicates.
- Avoid broad retry intervals that can amplify Flickr API pressure.

## Completion Criteria

- Root cause identified and mapped to one task stage.
- Fix includes regression-safe handling for both success and error paths.
- Manual verification includes at least one successful follow-up task run.
