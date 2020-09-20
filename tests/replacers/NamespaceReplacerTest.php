<?php
declare(strict_types=1);

use CoenJacobs\Mozart\Composer\Autoload\Psr0;
use CoenJacobs\Mozart\Replace\NamespaceReplacer;
use PHPUnit\Framework\TestCase;

class NamespaceReplacerTest extends TestCase
{
    const PREFIX = 'My\\Mozart\\Prefix\\';

    /** @var NamespaceReplacer */
    public $replacer;

    protected function setUp(): void
    {
        $this->replacer = self::createReplacer('Test\\Test');
    }

    /**
     * Creates a NamespaceReplacer, given a namespace and a prefix.
     *
     * @param string $namespace
     * @param string $prefix
     *
     * @return Psr0
     */
    protected static function createReplacer(string $namespace, string $prefix = self::PREFIX) : NamespaceReplacer
    {
        $autoloader = new Psr0;
        $autoloader->namespace = $namespace;
        $replacer = new NamespaceReplacer();
        $replacer->setAutoloader($autoloader);
        $replacer->dep_namespace = $prefix;

        return $replacer;
    }

    /** @test */
    public function it_replaces_namespace_declarations(): void
    {
        $contents = 'namespace Test\\Test;';
        $contents = $this->replacer->replace($contents);

        $this->assertEquals('namespace My\\Mozart\\Prefix\\Test\\Test;', $contents);
    }


    /** @test */
    public function it_doesnt_replaces_namespace_inside_namespace(): void
    {
        $replacer = self::createReplacer('Test');

        $contents = "namespace Test\\Something;\n\nuse Test\\Test;";
        $contents = $replacer->replace($contents);

        $this->assertEquals("namespace My\\Mozart\\Prefix\\Test\\Something;\n\nuse My\\Mozart\\Prefix\\Test\\Test;", $contents);
    }

    /** @test */
    public function it_replaces_partial_namespace_declarations(): void
    {
        $contents = 'namespace Test\\Test\\Another;';
        $contents = $this->replacer->replace($contents);

        $this->assertEquals('namespace My\\Mozart\\Prefix\\Test\\Test\\Another;', $contents);
    }

	/** @test */
    public function it_doesnt_prefix_already_prefixed_namespace(): void
    {
        $replacer = self::createReplacer('Test\\Another');

        $contents = 'namespace My\\Mozart\\Prefix\\Test\\Another;';
        $contents = $replacer->replace($contents);

        $this->assertEquals('namespace My\\Mozart\\Prefix\\Test\\Another;', $contents);
    }
}
