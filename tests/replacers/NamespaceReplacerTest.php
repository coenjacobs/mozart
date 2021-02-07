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

    /** @test */
    public function it_doesnt_double_replace_namespaces_that_also_exist_inside_another_namespace(): void
    {
        $chickenReplacer = self::createReplacer('Chicken');
        $eggReplacer = self::createReplacer('Egg');

        // This is a tricky situation. We are referencing Chicken\Egg,
        // but Egg *also* exists as a separate top level class.
        $contents = 'use Chicken\\Egg;';
        $expected = 'use My\\Mozart\\Prefix\\Chicken\\Egg;';

        // First, we test that eggReplacer(chickenReplacer()) yields the expected result.
        $this->assertEquals($expected, $eggReplacer->replace($chickenReplacer->replace($contents)));

        // Then, we test that chickenReplacer(eggReplacer()) yields the expected result.
        $this->assertEquals($expected, $chickenReplacer->replace($eggReplacer->replace($contents)));

        // Now we do the same thing, but with root-relative references.
        $contents = 'use \\Chicken\\Egg;';
        $expected = 'use \\My\\Mozart\\Prefix\\Chicken\\Egg;';

        // First, we test that eggReplacer(chickenReplacer()) yields the expected result.
        $this->assertEquals($expected, $eggReplacer->replace($chickenReplacer->replace($contents)));

        // Then, we test that chickenReplacer(eggReplacer()) yields the expected result.
        $this->assertEquals($expected, $chickenReplacer->replace($eggReplacer->replace($contents)));
    }

    /**
     * @see https://github.com/coenjacobs/mozart/issues/75
     *
     * @test
     */
    public function it_replaces_namespace_use_as_declarations(): void
    {

        $namespace = 'Symfony\Polyfill\Mbstring';
        $prefix = "MBViews\\Dependencies\\";

        $replacer = self::createReplacer($namespace, $prefix);

        $contents = "use Symfony\Polyfill\Mbstring as p;";
        $expected = "use MBViews\Dependencies\Symfony\Polyfill\Mbstring as p;";

        $this->assertEquals($expected, $replacer->replace($contents));
    }
}
