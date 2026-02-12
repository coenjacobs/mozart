Before committing changes to `src/`, all CI checks must pass. Run the full suite inside the Docker container:

```bash
docker compose run --rm builder composer test
```

This covers coding standards (PSR-12 via phpcs), static analysis (PHPStan level 8), mess detection (phpmd), docblock requirements, and tests. The tool configurations in `phpcs.xml.dist`, `phpstan.neon.dist`, and `composer.json` are the source of truth for what must pass.

If any check fails, fix the issues and re-run until the suite is green. Only then proceed with the commit.
