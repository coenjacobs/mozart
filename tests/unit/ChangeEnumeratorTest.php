<?php

namespace BrianHenryIE\Strauss\Tests\Unit;

use BrianHenryIE\Strauss\ChangeEnumerator;
use BrianHenryIE\Strauss\Composer\ComposerPackage;
use BrianHenryIE\Strauss\Composer\Extra\StraussConfig;
use BrianHenryIE\Strauss\Prefixer;
use Composer\Composer;
use PHPUnit\Framework\TestCase;

class ChangeEnumeratorTest extends TestCase
{

    // PREG_BACKTRACK_LIMIT_ERROR

    // Single implied global namespace.
    // Single named namespace.
    // Single explicit global namespace.
    // Multiple namespaces.



    public function testSingleNamespace()
    {

        $validPhp = <<<'EOD'
<?php
namespace MyNamespace;

class MyClass {
}
EOD;

        $config = $this->createMock(StraussConfig::class);
        $config->method('getNamespacePrefix')->willReturn('Prefix');
        $sut = new ChangeEnumerator($config);

        $sut->find($validPhp);

        $this->assertArrayHasKey('MyNamespace', $sut->getDiscoveredNamespaceReplacements());
        $this->assertContains('Prefix\MyNamespace', $sut->getDiscoveredNamespaceReplacements());

        $this->assertNotContains('MyClass', $sut->getDiscoveredClasses());
    }

    public function testGlobalNamespace()
    {

        $validPhp = <<<'EOD'
<?php
namespace {
    class MyClass {
    }
}
EOD;

        $config = $this->createMock(StraussConfig::class);
        $sut = new ChangeEnumerator($config);

        $sut->find($validPhp);

        $this->assertContains('MyClass', $sut->getDiscoveredClasses());
    }

    /**
     *
     */
    public function testMultipleNamespace()
    {

        $validPhp = <<<'EOD'
<?php
namespace MyNamespace {
}
namespace {
    class MyClass {
    }
}
EOD;

        $config = $this->createMock(StraussConfig::class);
        $sut = new ChangeEnumerator($config);

        $sut->find($validPhp);

        $this->assertContains('\MyNamespace', $sut->getDiscoveredNamespaceReplacements());

        $this->assertContains('MyClass', $sut->getDiscoveredClasses());
    }


    /**
     *
     */
    public function testMultipleNamespaceGlobalFirst()
    {

        $validPhp = <<<'EOD'
<?php

namespace {
    class MyClass {
    }
}
namespace MyNamespace {
    class MyOtherClass {
    }
}
EOD;

        $config = $this->createMock(StraussConfig::class);
        $sut = new ChangeEnumerator($config);

        $sut->find($validPhp);

        $this->assertContains('\MyNamespace', $sut->getDiscoveredNamespaceReplacements());

        $this->assertContains('MyClass', $sut->getDiscoveredClasses());
        $this->assertNotContains('MyOtherClass', $sut->getDiscoveredClasses());
    }


    /**
     *
     */
    public function testMultipleClasses()
    {

        $validPhp = <<<'EOD'
<?php
class MyClass {
}
class MyOtherClass {

}
EOD;

        $config = $this->createMock(StraussConfig::class);
        $sut = new ChangeEnumerator($config);

        $sut->find($validPhp);

        $this->assertContains('MyClass', $sut->getDiscoveredClasses());
        $this->assertContains('MyOtherClass', $sut->getDiscoveredClasses());
    }

    /**
     *
     * @author BrianHenryIE
     */
    public function test_it_does_not_treat_comments_as_classes()
    {
        $contents = "
    	// A class as good as any.
    	class Whatever {
    	
    	}
    	";

        $config = $this->createMock(StraussConfig::class);
        $changeEnumerator = new ChangeEnumerator($config);
        $changeEnumerator->find($contents);

        $this->assertNotContains('as', $changeEnumerator->getDiscoveredClasses());
        $this->assertContains('Whatever', $changeEnumerator->getDiscoveredClasses());
    }

    /**
     *
     * @author BrianHenryIE
     */
    public function test_it_does_not_treat_multiline_comments_as_classes()
    {
        $contents = "
    	 /**
    	  * A class as good as any; class as.
    	  */
    	class Whatever {
    	}
    	";

        $config = $this->createMock(StraussConfig::class);
        $changeEnumerator = new ChangeEnumerator($config);
        $changeEnumerator->find($contents);

        $this->assertNotContains('as', $changeEnumerator->getDiscoveredClasses());
        $this->assertContains('Whatever', $changeEnumerator->getDiscoveredClasses());
    }

    /**
     * This worked without adding the expected regex:
     *
     * // \s*\\/?\\*{2,}[^\n]* |                        # Skip multiline comment bodies
     *
     * @author BrianHenryIE
     */
    public function test_it_does_not_treat_multiline_comments_opening_line_as_classes()
    {
        $contents = "
    	 /** A class as good as any; class as.
    	  *
    	  */
    	class Whatever {
    	}
    	";

        $config = $this->createMock(StraussConfig::class);
        $changeEnumerator = new ChangeEnumerator($config);
        $changeEnumerator->find($contents);

        $this->assertNotContains('as', $changeEnumerator->getDiscoveredClasses());
        $this->assertContains('Whatever', $changeEnumerator->getDiscoveredClasses());
    }


    /**
     *
     * @author BrianHenryIE
     */
    public function test_it_does_not_treat_multiline_comments_on_one_line_as_classes()
    {
        $contents = "
    	 /** A class as good as any; class as. */ class Whatever_Trevor {
    	}
    	";

        $config = $this->createMock(StraussConfig::class);
        $changeEnumerator = new ChangeEnumerator($config);
        $changeEnumerator->find($contents);

        $this->assertNotContains('as', $changeEnumerator->getDiscoveredClasses());
        $this->assertContains('Whatever_Trevor', $changeEnumerator->getDiscoveredClasses());
    }

    /**
     * If someone were to put a semicolon in the comment it would mess with the previous fix.
     *
     * @author BrianHenryIE
     *
     * @test
     */
    public function test_it_does_not_treat_comments_with_semicolons_as_classes()
    {
        $contents = "
    	// A class as good as any; class as versatile as any.
    	class Whatever_Ever {
    	
    	}
    	";

        $config = $this->createMock(StraussConfig::class);
        $changeEnumerator = new ChangeEnumerator($config);
        $changeEnumerator->find($contents);

        $this->assertNotContains('as', $changeEnumerator->getDiscoveredClasses());
        $this->assertContains('Whatever_Ever', $changeEnumerator->getDiscoveredClasses());
    }

    /**
     * @author BrianHenryIE
     */
    public function test_it_parses_classes_after_semicolon()
    {

        $contents = "
	    myvar = 123; class Pear { };
	    ";

        $config = $this->createMock(StraussConfig::class);
        $changeEnumerator = new ChangeEnumerator($config);
        $changeEnumerator->find($contents);

        $this->assertContains('Pear', $changeEnumerator->getDiscoveredClasses());
    }


    /**
     * @author BrianHenryIE
     */
    public function test_it_parses_classes_followed_by_comment()
    {

        $contents = <<<'EOD'
	class WP_Dependency_Installer {
		/**
		 *
		 */
EOD;

        $config = $this->createMock(StraussConfig::class);
        $changeEnumerator = new ChangeEnumerator($config);
        $changeEnumerator->find($contents);

        $this->assertContains('WP_Dependency_Installer', $changeEnumerator->getDiscoveredClasses());
    }


    /**
     * It's possible to have multiple namespaces inside one file.
     *
     * To have two classes in one file, one in a namespace and the other not, the global namespace needs to be explicit.
     *
     * @author BrianHenryIE
     *
     * @test
     */
    public function it_does_not_replace_inside_named_namespace_but_does_inside_explicit_global_namespace_a(): void
    {

        $contents = "
		namespace My_Project {
			class A_Class { }
		}
		namespace {
			class B_Class { }
		}
		";

        $config = $this->createMock(StraussConfig::class);
        $changeEnumerator = new ChangeEnumerator($config);
        $changeEnumerator->find($contents);

        $this->assertNotContains('A_Class', $changeEnumerator->getDiscoveredClasses());
        $this->assertContains('B_Class', $changeEnumerator->getDiscoveredClasses());
    }

    public function testExcludePackagesFromPrefix()
    {

        $config = $this->createMock(StraussConfig::class);
        $config->method('getExcludePackagesFromPrefixing')->willReturn(
            array('brianhenryie/pdfhelpers')
        );

        $dir = '';
        $composerPackage = $this->createMock(ComposerPackage::class);
        $composerPackage->method('getName')->willReturn('brianhenryie/pdfhelpers');
        $relativeFilepaths = array( 'irrelevent' => $composerPackage);

        $changeEnumerator = new ChangeEnumerator($config);
        $changeEnumerator->findInFiles($dir, $relativeFilepaths);

        $this->assertEmpty($changeEnumerator->getDiscoveredNamespaceReplacements());
    }


    public function testExcludeFilePatternsFromPrefix()
    {
        $config = $this->createMock(StraussConfig::class);
        $config->method('getExcludeFilePatternsFromPrefixing')->willReturn(
            array('/to/')
        );

        $dir = '';
        $composerPackage = $this->createMock(ComposerPackage::class);
        $composerPackage->method('getName')->willReturn('brianhenryie/pdfhelpers');
        $relativeFilepaths = array( 'path/to/file' => $composerPackage);

        $changeEnumerator = new ChangeEnumerator($config);
        $changeEnumerator->findInFiles($dir, $relativeFilepaths);

        $this->assertEmpty($changeEnumerator->getDiscoveredNamespaceReplacements());
    }

    /**
     * Test custom replacements
     */
    public function testNamespaceReplacementPatterns()
    {

        $contents = "
		namespace BrianHenryIE\PdfHelpers {
			class A_Class { }
		}
		";

        $config = $this->createMock(StraussConfig::class);
        $config->method('getNamespacePrefix')->willReturn('BrianHenryIE\Prefix');
        $config->method('getNamespaceReplacementPatterns')->willReturn(
            array('/BrianHenryIE\\\\(PdfHelpers)/'=>'BrianHenryIE\\Prefix\\\\$1')
        );

        $changeEnumerator = new ChangeEnumerator($config);
        $changeEnumerator->find($contents);

        $this->assertArrayHasKey('BrianHenryIE\PdfHelpers', $changeEnumerator->getDiscoveredNamespaceReplacements());
        $this->assertContains('BrianHenryIE\Prefix\PdfHelpers', $changeEnumerator->getDiscoveredNamespaceReplacements());
        $this->assertNotContains('BrianHenryIE\Prefix\BrianHenryIE\PdfHelpers', $changeEnumerator->getDiscoveredNamespaceReplacements());
    }
}
