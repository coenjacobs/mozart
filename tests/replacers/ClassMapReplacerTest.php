<?php
declare(strict_types=1);

use CoenJacobs\Mozart\Exceptions\FileOperationException;
use CoenJacobs\Mozart\Replace\ClassmapReplacer;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ClassMapReplacerTest extends TestCase
{
    #[Test]
    public function it_replaces_class_declarations(): void
    {
        $contents = '<?php class Hello_World {}';
        $replacer = new ClassmapReplacer('Mozart_');
        $result = $replacer->replace($contents);
        $this->assertStringContainsString('class Mozart_Hello_World', $result);
    }

    #[Test]
    public function it_replaces_abstract_class_declarations(): void
    {
        $contents = '<?php abstract class Hello_World {}';
        $replacer = new ClassmapReplacer('Mozart_');
        $result = $replacer->replace($contents);
        $this->assertStringContainsString('abstract class Mozart_Hello_World', $result);
    }

    #[Test]
    public function it_replaces_interface_class_declarations(): void
    {
        $contents = '<?php interface Hello_World {}';
        $replacer = new ClassmapReplacer('Mozart_');
        $result = $replacer->replace($contents);
        $this->assertStringContainsString('interface Mozart_Hello_World', $result);
    }

    #[Test]
    public function it_replaces_class_declarations_that_extend_other_classes(): void
    {
        $contents = '<?php class Hello_World extends Bye_World {}';
        $replacer = new ClassmapReplacer('Mozart_');
        $result = $replacer->replace($contents);
        $this->assertStringContainsString('class Mozart_Hello_World extends Bye_World', $result);
    }

    #[Test]
    public function it_replaces_class_declarations_that_implement_interfaces(): void
    {
        $contents = '<?php class Hello_World implements Bye_World {}';
        $replacer = new ClassmapReplacer('Mozart_');
        $result = $replacer->replace($contents);
        $this->assertStringContainsString('class Mozart_Hello_World implements Bye_World', $result);
    }

    #[Test]
    public function it_stores_replaced_class_names(): void
    {
        $contents = '<?php class Hello_World {}';
        $replacer = new ClassmapReplacer('Mozart_');
        $replacer->replace($contents);
        $this->assertArrayHasKey('Hello_World', $replacer->getReplacedClasses());
    }

    #[Test]
    public function it_replaces_class_declarations_psr2(): void
    {
        $contents = "<?php\nclass Hello_World\n{}";
        $replacer = new ClassmapReplacer('Mozart_');
        $result = $replacer->replace($contents);
        $this->assertStringContainsString('class Mozart_Hello_World', $result);
    }

    /**
     * @see https://github.com/coenjacobs/mozart/issues/81
     */
    #[Test]
    public function it_replaces_class(): void
    {
        $contents = "<?php class Hello_World {}";
        $replacer = new ClassmapReplacer('Mozart_');
        $result = $replacer->replace($contents);
        $this->assertStringContainsString('class Mozart_Hello_World', $result);
    }


    /**
     * @see ClassmapReplacerIntegrationTest::test_it_does_not_make_classname_replacement_inside_namespaced_file()
     * @see https://github.com/coenjacobs/mozart/issues/93
     */
    #[Test]
    public function it_does_not_replace_inside_namespace_multiline(): void
    {
        $input = "<?php
        namespace Mozart;
        class Hello_World {}
        ";
        $replacer = new ClassmapReplacer('Mozart_');
        $result = $replacer->replace($input);

        $this->assertStringNotContainsString('Mozart_Hello_World', $result);
    }

    /**
     * @see ClassmapReplacerIntegrationTest::test_it_does_not_make_classname_replacement_inside_namespaced_file()
     * @see https://github.com/coenjacobs/mozart/issues/93
     */
    #[Test]
    public function it_does_not_replace_inside_namespace_singleline(): void
    {
        $input = "<?php namespace Mozart; class Hello_World {}";
        $replacer = new ClassmapReplacer('Mozart_');
        $result = $replacer->replace($input);

        $this->assertStringNotContainsString('Mozart_Hello_World', $result);
    }

    /**
     * It's possible to have multiple namespaces inside one file.
     *
     * To have two classes in one file, one in a namespace and the other not,
     * the global namespace needs to be explicit.
     */
    #[Test]
    public function it_does_not_replace_inside_named_namespace_but_does_inside_explicit_global_namespace(): void
    {
        $input = "<?php
		namespace My_Project {
			class A_Class { }
		}
		namespace {
			class B_Class { }
		}
		";

        $replacer = new ClassmapReplacer('Mozart_');
        $result = $replacer->replace($input);

        $this->assertStringNotContainsString('Mozart_A_Class', $result);
        $this->assertStringContainsString('Mozart_B_Class', $result);
    }

    #[Test]
    public function it_returns_non_php_content_unmodified(): void
    {
        $contents = 'This is not PHP code';
        $replacer = new ClassmapReplacer('Mozart_');
        $result = $replacer->replace($contents);

        $this->assertEquals($contents, $result);
    }

    #[Test]
    public function it_throws_exception_on_syntax_error(): void
    {
        $contents = '<?php class Hello_World { syntax error here';
        $replacer = new ClassmapReplacer('Mozart_');

        $this->expectException(FileOperationException::class);
        $this->expectExceptionMessageMatches('/Failed to parse PHP code:/');
        $replacer->replace($contents);
    }

    #[Test]
    public function it_returns_empty_content_unchanged(): void
    {
        $replacer = new ClassmapReplacer('Mozart_');
        $result = $replacer->replace('');

        $this->assertEquals('', $result);
    }

    #[Test]
    public function it_returns_content_unchanged_when_no_prefix(): void
    {
        $contents = '<?php class Hello_World {}';
        $replacer = new ClassmapReplacer('');
        $result = $replacer->replace($contents);

        $this->assertEquals($contents, $result);
    }
}
