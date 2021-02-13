<?php
declare(strict_types=1);

use CoenJacobs\Mozart\Replace\ClassmapReplacer;
use PHPUnit\Framework\TestCase;

class ClassMapReplacerTest extends TestCase
{
    /** @test */
    public function it_replaces_class_declarations(): void
    {
        $contents = 'class Hello_World {';
        $replacer = new ClassmapReplacer();
        $replacer->classmap_prefix = 'Mozart_';
        $contents = $replacer->replace($contents);
        $this->assertEquals('class Mozart_Hello_World {', $contents);
    }

    /** @test */
    public function it_replaces_abstract_class_declarations(): void
    {
        $contents = 'abstract class Hello_World {';
        $replacer = new ClassmapReplacer();
        $replacer->classmap_prefix = 'Mozart_';
        $contents = $replacer->replace($contents);
        $this->assertEquals('abstract class Mozart_Hello_World {', $contents);
    }

    /** @test */
    public function it_replaces_interface_class_declarations(): void
    {
        $contents = 'interface Hello_World {';
        $replacer = new ClassmapReplacer();
        $replacer->classmap_prefix = 'Mozart_';
        $contents = $replacer->replace($contents);
        $this->assertEquals('interface Mozart_Hello_World {', $contents);
    }

    /** @test */
    public function it_replaces_class_declarations_that_extend_other_classes(): void
    {
        $contents = 'class Hello_World extends Bye_World {';
        $replacer = new ClassmapReplacer();
        $replacer->classmap_prefix = 'Mozart_';
        $contents = $replacer->replace($contents);
        $this->assertEquals('class Mozart_Hello_World extends Bye_World {', $contents);
    }

    /** @test */
    public function it_replaces_class_declarations_that_implement_interfaces(): void
    {
        $contents = 'class Hello_World implements Bye_World {';
        $replacer = new ClassmapReplacer();
        $replacer->classmap_prefix = 'Mozart_';
        $contents = $replacer->replace($contents);
        $this->assertEquals('class Mozart_Hello_World implements Bye_World {', $contents);
    }

    /** @test */
    public function it_stores_replaced_class_names(): void
    {
        $contents = 'class Hello_World {';
        $replacer = new ClassmapReplacer();
        $replacer->classmap_prefix = 'Mozart_';
        $replacer->replace($contents);
        $this->assertArrayHasKey('Hello_World', $replacer->replacedClasses);
    }

    /** @test */
    public function it_replaces_class_declarations_psr2(): void
    {
        $contents = "class Hello_World\n{";
        $replacer = new ClassmapReplacer();
        $replacer->classmap_prefix = 'Mozart_';
        $contents = $replacer->replace($contents);
        $this->assertEquals("class Mozart_Hello_World\n{", $contents);
    }

    /**
     * @see https://github.com/coenjacobs/mozart/issues/81
     *
     * @test
     */
    public function it_replaces_class(): void
    {
        $contents = "class Hello_World";
        $replacer = new ClassmapReplacer();
        $replacer->classmap_prefix = 'Mozart_';
        $contents = $replacer->replace($contents);
        $this->assertEquals("class Mozart_Hello_World", $contents);
    }


    /**
     * @see ClassmapReplacerIntegrationTest::test_it_does_not_make_classname_replacement_inside_namespaced_file()
     * @see https://github.com/coenjacobs/mozart/issues/93
     *
     * @test
     */
    public function it_does_not_replace_inside_namespace_multiline(): void
    {
        $input = "
        namespace Mozart;
        class Hello_World
        ";
        $replacer = new ClassmapReplacer();
        $replacer->classmap_prefix = 'Mozart_';
        $result = $replacer->replace($input);

        $this->assertEquals($input, $result);
    }

    /**
     * @see ClassmapReplacerIntegrationTest::test_it_does_not_make_classname_replacement_inside_namespaced_file()
     * @see https://github.com/coenjacobs/mozart/issues/93
     *
     * @test
     */
    public function it_does_not_replace_inside_namespace_singleline(): void
    {
        $input = "namespace Mozart; class Hello_World";
        $replacer = new ClassmapReplacer();
        $replacer->classmap_prefix = 'Mozart_';
        $result = $replacer->replace($input);

        $this->assertEquals($input, $result);
    }

    /**
     * It's possible to have multiple namespaces inside one file.
     *
     * To have two classes in one file, one in a namespace and the other not, the global namespace needs to be explicit.
     *
     * @test
     */
    public function it_does_not_replace_inside_named_namespace_but_does_inside_explicit_global_namespace(): void
    {

        $input = "
		namespace My_Project {
			class A_Class { }
		}
		namespace {
			class B_Class { }
		}
		";

        $replacer = new ClassmapReplacer();
        $replacer->classmap_prefix = 'Mozart_';
        $result = $replacer->replace($input);

        $this->assertStringNotContainsString('Mozart_A_Class', $result);
        $this->assertStringContainsString('Mozart_B_Class', $result);
    }
}
