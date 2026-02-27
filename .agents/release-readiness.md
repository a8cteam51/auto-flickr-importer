---
name: release-readiness
description: Pre-release checklist for quality, documentation, and operational safety.
version: 1
depends_on:
  - .agents/repo-triage.md
scope:
  - changelog
  - lint
  - build
  - pr-notes
---

# Release Readiness Skill

## Use When

- Preparing a release PR.
- Finishing a bug fix that affects import behavior.
- Updating plugin version or deployment workflow.

## Checklist

1. Run quality gates for touched surfaces:
   - `composer lint:php`
   - `npm run lint` (if JS/CSS touched)
   - `npm run build` (if assets changed)
2. Confirm `auto-flickr-importer.php` plugin header version aligns with release intent.
3. Ensure `README.md` user-facing behavior notes are still accurate.
4. Write testing instructions in the repository PR template style.
5. Include rollback notes when background-task behavior changed.

## PR Content Expectations

- Clear user impact statement.
- Risk assessment (especially scheduling/import impact).
- Reproduction and verification steps.
- Explicit mention of any data migration or re-import implications.
