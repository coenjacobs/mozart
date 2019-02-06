<?php

use CoenJacobs\Mozart\Composer\Autoload\Psr0;
use CoenJacobs\Mozart\Replace\NamespaceReplacer;
use PHPUnit\Framework\TestCase;

class NamespaceReplacerTest extends TestCase
{
    /** @var NamespaceReplacer */
    public $replacer;

    public function setUp()
    {
        $autoloader = new Psr0();
        $autoloader->namespace = 'Test\\Test';

        $replacer = new NamespaceReplacer();
        $replacer->setAutoloader($autoloader);
        $replacer->dep_namespace = 'Prefix\\';
        $this->replacer = $replacer;
    }

    /** @test */
    public function it_replaces_namespace_declarations()
    {
        $contents = 'namespace Test\\Test;';
        $contents = $this->replacer->replace($contents);
        $this->assertEquals('namespace Prefix\\Test\\Test;', $contents);
    }


    /** @test */
    public function it_doesnt_replaces_namespace_inside_namespace()
    {

        $autoloader = new Psr0();
        $autoloader->namespace = 'Test';

        $replacer = new NamespaceReplacer();
        $replacer->setAutoloader($autoloader);
        $replacer->dep_namespace = 'Prefix\\';
        $this->replacer = $replacer;

        $contents ="namespace Test\\Something;\n\nuse Test\\Test;";
        $contents = $this->replacer->replace($contents);
        $this->assertEquals("namespace Prefix\\Test\\Something;\n\nuse Prefix\\Test\\Test;", $contents);
    }

    /** @test */
    public function it_replaces_partial_namespace_declarations()
    {
        $contents = 'namespace Test\\Test\\Another;';
        $contents = $this->replacer->replace($contents);
        $this->assertEquals('namespace Prefix\\Test\\Test\\Another;', $contents);
    }
}
