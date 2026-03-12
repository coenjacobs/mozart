<?php

namespace CoenJacobs\Mozart\Autoload;

/**
 * Normalizes and relativizes paths for generated autoloader files.
 */
final class PathHelper
{
    /**
     * Normalize a path to forward slashes.
     */
    public function normalize(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $normalized = preg_replace('#/+#', '/', $normalized) ?? $normalized;

        return $normalized;
    }

    /**
     * Normalize a directory path and ensure it ends with a slash.
     */
    public function normalizeDirectory(string $path): string
    {
        $normalized = rtrim($this->normalize($path), '/');

        if ($normalized === '') {
            return '';
        }

        return $normalized . '/';
    }

    /**
     * Split a path into normalized path segments.
     *
     * @return array<string>
     */
    public function split(string $path): array
    {
        [, $segments] = $this->decompose($path);

        return $segments;
    }

    /**
     * Count the normalized path segments.
     */
    public function countSegments(string $path): int
    {
        return count($this->split($path));
    }

    /**
     * Compute a normalized relative path between two paths.
     */
    public function relativePath(string $from, string $target): string
    {
        [$fromRoot, $fromParts] = $this->decompose($from);
        [$targetRoot, $targetParts] = $this->decompose($target);

        if ($fromRoot !== $targetRoot) {
            return $this->normalize($target);
        }

        $commonLength = 0;
        $maxLength = min(count($fromParts), count($targetParts));
        for ($i = 0; $i < $maxLength; $i++) {
            if ($fromParts[$i] !== $targetParts[$i]) {
                break;
            }

            $commonLength++;
        }

        $parts = array_merge(
            array_fill(0, count($fromParts) - $commonLength, '..'),
            array_slice($targetParts, $commonLength)
        );

        return implode('/', $parts);
    }

    /**
     * Relativize a target path against the working directory.
     */
    public function relativizeToWorkingDir(string $workingDir, string $targetPath): string
    {
        $resolved = $this->relativizeResolvedPaths($workingDir, $targetPath);
        if ($resolved !== null) {
            return $resolved;
        }

        $normalizedWorkingDir = $this->normalizeDirectory($workingDir);
        $normalizedTargetPath = $this->normalize($targetPath);
        $relativePath = $this->stripDirectoryPrefix($normalizedWorkingDir, $normalizedTargetPath);
        if ($relativePath !== null) {
            return $relativePath;
        }

        return $this->relativePath($normalizedWorkingDir, $normalizedTargetPath);
    }

    /**
     * Attempt to relativize using resolved filesystem paths when available.
     */
    private function relativizeResolvedPaths(string $workingDir, string $targetPath): ?string
    {
        $resolvedWorkingDir = realpath($workingDir);
        $resolvedTargetPath = realpath($targetPath);

        if (!is_string($resolvedWorkingDir) || !is_string($resolvedTargetPath)) {
            return null;
        }

        $normalizedWorkingDir = $this->normalizeDirectory($resolvedWorkingDir);
        $normalizedTargetPath = $this->normalize($resolvedTargetPath);

        return $this->stripDirectoryPrefix($normalizedWorkingDir, $normalizedTargetPath);
    }

    /**
     * Strip a normalized directory prefix from a normalized path.
     */
    private function stripDirectoryPrefix(string $directory, string $path): ?string
    {
        if (!str_starts_with($path, $directory)) {
            return null;
        }

        return ltrim(substr($path, strlen($directory)), '/');
    }

    /**
     * Decompose a path into a root marker and path segments.
     *
     * @return array{0: string, 1: array<string>}
     */
    private function decompose(string $path): array
    {
        $normalized = $this->normalize($path);
        $root = '';

        if (preg_match('/^[A-Za-z]:/', $normalized, $matches) === 1) {
            $root = strtoupper($matches[0]);
            $normalized = substr($normalized, 2);
        } elseif (str_starts_with($normalized, '/')) {
            $root = '/';
            $normalized = ltrim($normalized, '/');
        }

        $trimmed = trim($normalized, '/');
        if ($trimmed === '') {
            return [$root, []];
        }

        return [$root, explode('/', $trimmed)];
    }
}
