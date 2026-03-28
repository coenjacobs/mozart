<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Integration\AliasQualifiedNamespace;

use CoenJacobs\Mozart\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Integration tests for guzzlehttp/guzzle package processing.
 *
 * Guzzle and its dependencies share the GuzzleHttp namespace prefix:
 * - guzzlehttp/guzzle: GuzzleHttp\
 * - guzzlehttp/psr7: GuzzleHttp\Psr7\
 * - guzzlehttp/promises: GuzzleHttp\Promise\
 *
 * Because the Replacer scans dep_directory/GuzzleHttp/ recursively for
 * the parent package, sub-package files (Psr7, Promise) are processed
 * by the parent autoloader first, then again by their own autoloader.
 * This can cause a recurring (double) prefix replacement where alias-
 * qualified names like P\Create or Psr7\Utils are expanded to the full
 * prefixed path without being made fully qualified, so PHP resolves
 * them relative to the current namespace — doubling the path.
 */
class AliasQualifiedNamespaceTest extends IntegrationTestCase
{
    /**
     * Verifies that Mozart successfully processes the guzzlehttp/guzzle package.
     */
    #[Test]
    public function it_processes_guzzle_package_successfully(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        $this->assertEquals(0, $result);

        $this->assertDirectoryExists($this->testsWorkingDir . '/src/dependencies/GuzzleHttp');
    }

    /**
     * Verifies that alias-qualified names are not expanded into non-fully-qualified
     * prefixed paths, which would cause PHP to resolve them relative to the current
     * namespace — effectively doubling the namespace prefix.
     *
     * Original Guzzle code uses patterns like:
     *   use GuzzleHttp\Promise as P;
     *   return P\Create::promiseFor(...);
     *
     * After Mozart, the use statement is correctly prefixed. But if P\Create is
     * expanded to Mozart\TestProject\Dependencies\GuzzleHttp\Promise\Create (without
     * leading backslash), PHP resolves it relative to the current namespace
     * Mozart\TestProject\Dependencies\GuzzleHttp, producing:
     *   Mozart\TestProject\Dependencies\GuzzleHttp\Mozart\TestProject\Dependencies\GuzzleHttp\Promise\Create
     *
     * This is the "recurring replacement" bug.
     */
    #[Test]
    public function it_does_not_produce_recurring_replacement_in_client(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        $this->assertEquals(0, $result);

        $clientPath = $this->testsWorkingDir . '/src/dependencies/GuzzleHttp/Client.php';
        $this->assertFileExists($clientPath);

        $content = file_get_contents($clientPath);

        // The file is in namespace Mozart\TestProject\Dependencies\GuzzleHttp.
        // Any inline reference to Mozart\TestProject\Dependencies\GuzzleHttp\Promise\Create
        // WITHOUT a leading backslash would be resolved by PHP as:
        //   CurrentNamespace + Reference = doubled path
        //
        // The reference should either stay as the alias (P\Create) or be
        // fully qualified (\Mozart\TestProject\Dependencies\...).
        $this->assertDoesNotMatchRegularExpression(
            '/[^\\\\]Mozart\\\\TestProject\\\\Dependencies\\\\GuzzleHttp\\\\Promise\\\\Create::/',
            $content,
            'Client.php contains a non-fully-qualified prefixed reference to Promise\Create, '
            . 'which PHP would resolve relative to the current namespace, doubling the path'
        );
    }

    /**
     * Verifies that relative qualified names (like Psr7\Utils) in StreamHandler
     * are not expanded into non-fully-qualified prefixed paths.
     *
     * StreamHandler.php uses patterns like:
     *   Psr7\Utils::streamFor($stream)
     *   new Psr7\Response(...)
     *   new Psr7\LazyOpenStream(...)
     *
     * These relative names resolve correctly through the current namespace.
     * Expanding them to the full prefixed path without a leading backslash
     * would cause the same recurring replacement as in Client.php.
     */
    #[Test]
    public function it_does_not_produce_recurring_replacement_in_stream_handler(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        $this->assertEquals(0, $result);

        $handlerPath = $this->testsWorkingDir . '/src/dependencies/GuzzleHttp/Handler/StreamHandler.php';
        $this->assertFileExists($handlerPath);

        $content = file_get_contents($handlerPath);

        // Inline references should not be non-fully-qualified prefixed paths
        $this->assertDoesNotMatchRegularExpression(
            '/[^\\\\]Mozart\\\\TestProject\\\\Dependencies\\\\GuzzleHttp\\\\Psr7\\\\Utils::/',
            $content,
            'StreamHandler.php contains a non-fully-qualified prefixed reference to Psr7\Utils, '
            . 'which PHP would resolve relative to the current namespace, doubling the path'
        );
    }

    /**
     * Broad check: no PHP file in the dependency output should contain a
     * non-fully-qualified prefixed namespace reference that would cause
     * PHP to double the path at runtime.
     *
     * Matches lines where the prefix appears as an inline reference without
     * being a namespace declaration, use statement, or fully-qualified name.
     */
    #[Test]
    public function it_does_not_produce_recurring_replacement_in_any_file(): void
    {
        $this->copyFixtures();
        $this->runComposerInstall();
        $result = $this->runMozart();

        $this->assertEquals(0, $result);

        $dependencyDir = $this->testsWorkingDir . '/src/dependencies/GuzzleHttp';
        $this->assertDirectoryExists($dependencyDir);

        $phpFiles = $this->findPhpFiles($dependencyDir);
        $this->assertNotEmpty($phpFiles, 'Should find PHP files in the dependency directory');

        $prefix = 'Mozart\\TestProject\\Dependencies\\';
        $failedFiles = [];

        foreach ($phpFiles as $filePath) {
            $content = file_get_contents($filePath);
            $lines = explode("\n", $content);
            $relativePath = str_replace($this->testsWorkingDir . '/', '', $filePath);

            foreach ($lines as $lineNum => $line) {
                $trimmed = ltrim($line);

                // Skip namespace declarations and use statements
                if (str_starts_with($trimmed, 'namespace ') || str_starts_with($trimmed, 'use ')) {
                    continue;
                }

                // Skip comment-only lines
                if (str_starts_with($trimmed, '*') || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '/*')) {
                    continue;
                }

                // Find non-fully-qualified prefixed references in code lines.
                // A fully-qualified reference starts with \ before the prefix.
                // A non-qualified inline reference would have a non-backslash
                // character (or start of line) before the prefix.
                // Exclude string literals (e.g., function_exists('...')) since those
                // correctly use the full namespace as a string value.
                $pattern = '/(?<![\\\\a-zA-Z])' . preg_quote($prefix, '/') . 'GuzzleHttp\\\\/';
                $stringPattern = "/['\"]" . preg_quote($prefix, '/') . "/";
                if (preg_match($pattern, $line) && !preg_match($stringPattern, $line)) {
                    $failedFiles[] = sprintf('%s:%d: %s', $relativePath, $lineNum + 1, trim($line));
                }
            }
        }

        $this->assertEmpty(
            $failedFiles,
            "Found non-fully-qualified prefixed references that would cause recurring replacement at runtime:\n"
            . implode("\n", array_slice($failedFiles, 0, 20))
        );
    }

    /**
     * Find all PHP files recursively in a directory.
     *
     * @return array<string>
     */
    private function findPhpFiles(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                $files[] = $file->getRealPath();
            }
        }

        return $files;
    }
}