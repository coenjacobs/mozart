# Memory Requirements

Mozart uses PHP-Parser for AST-based code transformations. This provides
accurate, syntax-aware replacements but requires more memory than simple
regex-based approaches.

## Memory Usage

Memory usage scales with file size - roughly 5-10x the file size is needed
for AST operations. Most projects work fine with PHP's default 128MB limit.

For context:
- A 100KB PHP file uses ~1MB of memory for AST processing
- A 1MB PHP file uses ~5-10MB of memory for AST processing
- Files are processed one at a time; memory is released between files

## Increasing Memory Limits

If you encounter memory issues with very large PHP files, increase PHP's
memory limit:

### Command line

```bash
php -d memory_limit=256M vendor/bin/mozart compose
```

### In php.ini

```ini
memory_limit = 256M
```

### In Composer scripts

```json
{
    "scripts": {
        "mozart": "php -d memory_limit=256M vendor/bin/mozart compose"
    }
}
```

## Error Messages

When memory is exhausted, Mozart will display a helpful message pointing to
this documentation with instructions for increasing memory limits.
