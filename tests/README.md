# Runtime smoke tests

Run:

```bash
php tests/runtime-smoke.php
php tests/trash-disabled-smoke.php
```

The tests use a small in-memory WordPress contract stub so the safety state machine can be exercised in CI without a database. They cover job ownership and locking, post and taxonomy checkpoints, verification drift, crash recovery, relationship journaling, rollback preservation of externally-used terms, and the disabled-Trash safety gate.
