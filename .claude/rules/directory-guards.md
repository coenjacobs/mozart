`is_dir()` checks are required before any file discovery operation (glob, directory iteration, recursive scanning). Without them, operations on missing directories cause warnings or errors.

Locations that depend on these guards:
- `Replacer` — before scanning the target directory for files to process, and before calling into `FilesHandler` for files autoloader entries
- Autoloader handlers (PSR-4, PSR-0, classmap) — before discovering source files in package directories
