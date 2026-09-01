# Architecture

Core Blueprint Content Migrator is a **standalone-first** WordPress utility. Its migration engine depends only on public WordPress APIs.

## Runtime layers

- `Admin/Page.php` — WordPress-native Tools screen and UI-as-manual workflow.
- `Admin/Controller.php` — capability/nonce gates and state transitions.
- `Migration/PostAnalyzer.php` — read-only post migration discovery.
- `Migration/PostRunner.php` — post copy, verification, rollback and finalize.
- `Migration/TaxonomyAnalyzer.php` — read-only taxonomy discovery.
- `Migration/TaxonomyRunner.php` — term copy, hierarchy, term meta, relationships, verification and rollback.
- `Migration/PlanStore.php` — per-user analyzed plan.
- `Migration/JobStore.php` — one active site-level migration job.
- `Integration/Suite.php` — optional Core Blueprint Extension Registry/health integration.
- `Governance/Events.php` — optional best-effort Governance audit events.

## Safety invariants

1. Analysis never mutates content.
2. Mappings are explicit; unknown fields/taxonomies are not guessed.
3. Source posts are never permanently deleted by RC1.
4. Source taxonomy terms are never deleted by RC1.
5. Post rollback deletes only target posts carrying the active job marker and any target taxonomy terms created and marked by that post migration.
6. Taxonomy rollback removes only relationships tracked as newly added by the job.
7. Taxonomy rollback deletes only newly created target terms carrying the active job marker.
8. Existing target terms matched by slug are reused without mutation.
9. Target lifecycle hooks remain enabled during post insertion.
10. Core Blueprint Base is an optional enhancement, not a runtime dependency.

## Taxonomy conflicts

A matching target slug is treated as an existing canonical target term. Content Migrator maps the source term to it but does not overwrite its name, description, hierarchy or meta. Term-meta mapping applies only to terms created by the migration job.

## Finalization

Finalization removes rollback markers and deletes the stored job. Post mode can optionally move sources to WordPress Trash. Taxonomy mode keeps the source because WordPress has no reversible term trash mechanism.
