Before committing changes to `src/`, run the lint suite to catch style violations and missing docblocks early:

```bash
docker compose run --rm builder composer test:lint
```

This catches the same issues that CI would reject, avoiding failed builds from formatting or docblock problems.
