<?php

declare(strict_types=1);

namespace CoenJacobs\Mozart\Tests\Unit\Replace\Namespace;

use CoenJacobs\Mozart\Config\Psr0;
use CoenJacobs\Mozart\Replace\Namespace\NamespaceReplacer;
use CoenJacobs\Mozart\Tests\Support\AstProcessingTestTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class NamespaceReplacerTest extends TestCase
{
    use AstProcessingTestTrait;

    const PREFIX = 'My\\Mozart\\Prefix\\';

    /** @var NamespaceReplacer */
    public $replacer;

    protected function setUp(): void
    {
        $this->replacer = self::createReplacer('Test\\Test');
    }

    /**
     * Creates a NamespaceReplacer, given a namespace and a prefix.
     */
    protected static function createReplacer(string $namespace, string $prefix = self::PREFIX): NamespaceReplacer
    {
        $autoloader = new Psr0;
        $autoloader->setNamespace($namespace);

        $replacer = new NamespaceReplacer($prefix);
        $replacer->setAutoloader($autoloader);

        return $replacer;
    }

    #[Test]
    public function it_replaces_namespace_declarations(): void
    {
        $contents = $this->wrapCode('namespace Test\\Test;');
        $contents = $this->replacer->replace($contents);
        $extracted = $this->extractCode($contents);

        $this->assertEquals('namespace My\\Mozart\\Prefix\\Test\\Test;', $extracted);
    }

    #[Test]
    public function it_does_not_replace_namespace_inside_namespace(): void
    {
        $replacer = self::createReplacer('Test');

        $contents = $this->wrapCode("namespace Test\\Something;\n\nuse Test\\Test;");
        $contents = $replacer->replace($contents);
        $extracted = $this->extractCode($contents);

        $this->assertStringContainsString('namespace My\\Mozart\\Prefix\\Test\\Something;', $extracted);
        $this->assertStringContainsString('use My\\Mozart\\Prefix\\Test\\Test;', $extracted);
    }

    #[Test]
    public function it_replaces_partial_namespace_declarations(): void
    {
        $contents = $this->wrapCode('namespace Test\\Test\\Another;');
        $contents = $this->replacer->replace($contents);
        $extracted = $this->extractCode($contents);

        $this->assertEquals('namespace My\\Mozart\\Prefix\\Test\\Test\\Another;', $extracted);
    }

    #[Test]
    public function it_does_not_prefix_already_prefixed_namespace(): void
    {
        $replacer = self::createReplacer('Test\\Another');

        $contents = $this->wrapCode('namespace My\\Mozart\\Prefix\\Test\\Another;');
        $contents = $replacer->replace($contents);
        $extracted = $this->extractCode($contents);

        $this->assertEquals('namespace My\\Mozart\\Prefix\\Test\\Another;', $extracted);
    }

    #[Test]
    public function it_does_not_double_replace_namespaces_that_also_exist_inside_another_namespace(): void
    {
        $chickenReplacer = self::createReplacer('Chicken');
        $eggReplacer = self::createReplacer('Egg');

        /**
         * This is a tricky situation. We are referencing Chicken\Egg, but Egg
         * *also* exists as a separate top level class.
         */
        $contents = $this->wrapCode('use Chicken\\Egg;');

        // First, we test that eggReplacer(chickenReplacer()) yields the expected result.
        $result = $eggReplacer->replace($chickenReplacer->replace($contents));
        $extracted = $this->extractCode($result);
        $this->assertEquals('use My\\Mozart\\Prefix\\Chicken\\Egg;', $extracted);

        // Then, we test that chickenReplacer(eggReplacer()) yields the expected result.
        $result = $chickenReplacer->replace($eggReplacer->replace($contents));
        $extracted = $this->extractCode($result);
        $this->assertEquals('use My\\Mozart\\Prefix\\Chicken\\Egg;', $extracted);

        // Now we do the same thing, but with root-relative references.
        $contents = $this->wrapCode('use \\Chicken\\Egg;');

        // First, we test that eggReplacer(chickenReplacer()) yields the expected result.
        $result = $eggReplacer->replace($chickenReplacer->replace($contents));
        $extracted = $this->extractCode($result);
        $this->assertEquals('use My\\Mozart\\Prefix\\Chicken\\Egg;', $extracted);

        // Then, we test that chickenReplacer(eggReplacer()) yields the expected result.
        $result = $chickenReplacer->replace($eggReplacer->replace($contents));
        $extracted = $this->extractCode($result);
        $this->assertEquals('use My\\Mozart\\Prefix\\Chicken\\Egg;', $extracted);
    }

    /**
     * @see https://github.com/coenjacobs/mozart/issues/75
     */
    #[Test]
    public function it_replaces_namespace_use_as_declarations(): void
    {
        $namespace = 'Symfony\\Polyfill\\Mbstring';
        $prefix = "MBViews\\Dependencies\\";

        $replacer = self::createReplacer($namespace, $prefix);

        $contents = $this->wrapCode("use Symfony\\Polyfill\\Mbstring as p;");
        $result = $replacer->replace($contents);
        $extracted = $this->extractCode($result);

        $this->assertEquals("use MBViews\\Dependencies\\Symfony\\Polyfill\\Mbstring as p;", $extracted);
    }
}
