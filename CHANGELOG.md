# Changelog

## 1.0.0-rc1

- Promoted the validated feature set to the first v1 release candidate.
- Added owner-bound active migration jobs and an atomic site-wide mutation lock.
- Added per-item recovery checkpoints for post, term, parent and relationship writes.
- Added recovery of marked target posts after interrupted requests.
- Added relationship write-ahead journaling for taxonomy migrations.
- Added strict WordPress object, post type, taxonomy, meta and destructive-action capability checks.
- Added a hard safety gate that refuses source removal when WordPress Trash is disabled.
- Expanded verification to cover the complete promised post contract, including slug, author, dates, parent and featured image.
- Made unresolved migration errors block verification.
- Added fresh verification immediately before finalization.
- Hardened rollback so externally-used job-created terms are preserved.
- Added explicit destructive-action confirmations and clearer in-context safety guidance.
- Added paged analysis for large post/term sets and chunked relationship discovery.
- Made optional Core Blueprint Extension Registry and Governance integration non-fatal and i18n-safe.
- Added committed runtime smoke tests and GitHub CI for PHP 8.4.

## 0.1.0-rc1

- Renamed the utility to Core Blueprint Content Migrator.
- Made the migration engine fully standalone; Core Blueprint Base is optional.
- Added WordPress-native `Tools → Content Migrator` administration.
- Added safe post-type to post-type migrations with taxonomy and post-meta mapping.
- Added taxonomy-to-taxonomy migrations with hierarchy, term-meta mapping and optional relationship remapping.
- Added conflict-safe reuse of existing target terms by slug without overwriting their data.
- Added batch processing, verification, rollback and finalize workflows for both modes.
- Added optional Core Blueprint Extension Registry and Governance integration when Base is available.
