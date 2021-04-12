<?php

namespace CoenJacobs\Mozart;

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

        $sut = new ChangeEnumerator();

        $sut->find($validPhp);

        $this->assertContains('MyNamespace', $sut->getDiscoveredNamespaces());

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

        $sut = new ChangeEnumerator();

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

        $sut = new ChangeEnumerator();

        $sut->find($validPhp);

        $this->assertContains('MyNamespace', $sut->getDiscoveredNamespaces());

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

        $sut = new ChangeEnumerator();

        $sut->find($validPhp);

        $this->assertContains('MyNamespace', $sut->getDiscoveredNamespaces());

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

        $sut = new ChangeEnumerator();

        $sut->find($validPhp);

        $this->assertContains('MyClass', $sut->getDiscoveredClasses());
        $this->assertContains('MyOtherClass', $sut->getDiscoveredClasses());
    }
}
