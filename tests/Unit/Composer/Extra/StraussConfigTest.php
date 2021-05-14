<?php
/**
 * Should accept Nannarl config and Mozart config.
 *
 * Should have sensible defaults.
 */

namespace BrianHenryIE\Strauss\Composer\Extra;

use Composer\Factory;
use Composer\IO\NullIO;
use PHPUnit\Framework\TestCase;

class StraussConfigTest extends TestCase
{

    /**
     * With a full (at time of writing) config, test the getters.
     */
    public function testGetters()
    {

        $composerExtraStraussJson = <<<'EOD'
{
  "name": "brianhenryie/strauss-config-test",
  "require": {
    "league/container": "*"
  },
  "extra": {
    "strauss": {
      "target_directory": "/target_directory/",
      "namespace_prefix": "BrianHenryIE\\Strauss\\",
      "classmap_prefix": "BrianHenryIE_Strauss_",
      "packages": [
        "pimple/pimple"
      ],
      "exclude_prefix_packages": [
        "psr/container"
      ],
      "override_autoload": {
        "clancats/container": {
          "classmap": [
            "src/"
          ]
        }
      },
      "delete_vendor_files": false
    }
  }
}
EOD;

        $tmpfname = tempnam(sys_get_temp_dir(), 'strauss-test-');
        file_put_contents($tmpfname, $composerExtraStraussJson);

        $composer = Factory::create(new NullIO(), $tmpfname);

        $sut = new StraussConfig($composer);

        $this->assertContains('pimple/pimple', $sut->getPackages());

        $this->assertEquals('target_directory' . DIRECTORY_SEPARATOR, $sut->getTargetDirectory());

        $this->assertEquals("BrianHenryIE\\Strauss", $sut->getNamespacePrefix());

        $this->assertEquals('BrianHenryIE_Strauss_', $sut->getClassmapPrefix());

        // @see https://github.com/BrianHenryIE/strauss/issues/14
        $this->assertContains('/^psr.*$/', $sut->getExcludeFilePatternsFromPrefixing());

        $this->assertArrayHasKey('clancats/container', $sut->getOverrideAutoload());

        $this->assertFalse($sut->isDeleteVendorFiles());
    }

    /**
     * Test how it handles an extra key.
     *
     * Turns out it just ignores it... good!
     */
    public function testExtraKey()
    {

        $composerExtraStraussJson = <<<'EOD'
{
  "name": "brianhenryie/strauss-config-test",
  "require": {
    "league/container": "*"
  },
  "extra": {
    "strauss": {
      "target_directory": "/target_directory/",
      "namespace_prefix": "BrianHenryIE\\Strauss\\",
      "classmap_prefix": "BrianHenryIE_Strauss_",
      "packages": [
        "pimple/pimple"
      ],
      "exclude_prefix_packages": [
        "psr/container"
      ],
      "override_autoload": {
        "clancats/container": {
          "classmap": [
            "src/"
          ]
        }
      },
      "delete_vendor_files": false,
      "unexpected_key": "here"
    }
  }
}
EOD;

        $tmpfname = tempnam(sys_get_temp_dir(), 'strauss-test-');
        file_put_contents($tmpfname, $composerExtraStraussJson);

        $composer = Factory::create(new NullIO(), $tmpfname);

        $exception = null;

        try {
            $sut = new StraussConfig($composer);
        } catch (\Exception $e) {
            $exception = $e;
        }

        $this->assertNull($exception);
    }

    /**
     * straussconfig-test-3.json has no target_dir key.
     *
     * If no target_dir is specified, used "strauss/"
     */
    public function testDefaultTargetDir()
    {

        $composerExtraStraussJson = <<<'EOD'
{
  "name": "brianhenryie/strauss-config-test",
  "require": {
    "league/container": "*"
  },
  "extra": {
    "strauss": {
      "namespace_prefix": "BrianHenryIE\\Strauss\\",
      "classmap_prefix": "BrianHenryIE_Strauss_",
      "exclude_prefix_packages": [
        "psr/container"
      ],
      "override_autoload": {
        "clancats/container": {
          "classmap": [
            "src/"
          ]
        }
      },
      "delete_vendor_files": false,
      "unexpected_key": "here"
    }
  }
}
EOD;

        $tmpfname = tempnam(sys_get_temp_dir(), 'strauss-test-');
        file_put_contents($tmpfname, $composerExtraStraussJson);

        $composer = Factory::create(new NullIO(), $tmpfname);

        $sut = new StraussConfig($composer);

        $this->assertEquals('strauss'. DIRECTORY_SEPARATOR, $sut->getTargetDirectory());
    }

    /**
     * When the namespace prefix isn't provided, use the PSR-4 autoload key name.
     */
    public function testDefaultNamespacePrefixFromAutoloaderPsr4()
    {

        $composerExtraStraussJson = <<<'EOD'
{
  "name": "brianhenryie/strauss-config-test",
  "require": {
    "league/container": "*"
  },

  "autoload": {
    "psr-4": {
      "BrianHenryIE\\Strauss\\": "src"
    }
  }
}
EOD;

        $tmpfname = tempnam(sys_get_temp_dir(), 'strauss-test-');
        file_put_contents($tmpfname, $composerExtraStraussJson);

        $composer = Factory::create(new NullIO(), $tmpfname);

        $sut = new StraussConfig($composer);

        $this->assertEquals("BrianHenryIE\\Strauss", $sut->getNamespacePrefix());
    }

    /**
     * When the namespace prefix isn't provided, use the PSR-0 autoload key name.
     */
    public function testDefaultNamespacePrefixFromAutoloaderPsr0()
    {

        $composerExtraStraussJson = <<<'EOD'
{
  "name": "brianhenryie/strauss-config-test",
  "require": {
    "league/container": "*"
  },

  "autoload": {
    "psr-0": {
      "BrianHenryIE\\Strauss\\": "lib/"
    }
  }
}
EOD;
        $tmpfname = tempnam(sys_get_temp_dir(), 'strauss-test-');
        file_put_contents($tmpfname, $composerExtraStraussJson);

        $composer = Factory::create(new NullIO(), $tmpfname);

        $sut = new StraussConfig($composer);

        $this->assertEquals("BrianHenryIE\\Strauss", $sut->getNamespacePrefix());
    }

    /**
     * When the namespace prefix isn't provided, and there's no PSR-0 or PSR-4 autoloader to figure it from...
     *
     * brianhenryie/strauss-config-test
     */
    public function testDefaultNamespacePrefixWithNoAutoloader()
    {

        $composerExtraStraussJson = <<<'EOD'
{
  "name": "brianhenryie/strauss-config-test",
  "require": {
    "league/container": "*"
  }
}
EOD;

        $tmpfname = tempnam(sys_get_temp_dir(), 'strauss-test-');
        file_put_contents($tmpfname, $composerExtraStraussJson);

        $composer = Factory::create(new NullIO(), $tmpfname);

        $sut = new StraussConfig($composer);

        $this->assertEquals("Brianhenryie\\Strauss_Config_Test", $sut->getNamespacePrefix());
    }

    /**
     * When the classmap prefix isn't provided, use the PSR-4 autoload key name.
     */
    public function testDefaultClassmapPrefixFromAutoloaderPsr4()
    {

        $composerExtraStraussJson = <<<'EOD'
{
  "name": "brianhenryie/strauss-config-test",
  "require": {
    "league/container": "*"
  },

  "autoload": {
    "psr-4": {
      "BrianHenryIE\\Strauss\\": "src"
    }
  }
}
EOD;

        $tmpfname = tempnam(sys_get_temp_dir(), 'strauss-test-');
        file_put_contents($tmpfname, $composerExtraStraussJson);

        $composer = Factory::create(new NullIO(), $tmpfname);

        $sut = new StraussConfig($composer);

        $this->assertEquals("BrianHenryIE_Strauss_", $sut->getClassmapPrefix());
    }

    /**
     * When the classmap prefix isn't provided, use the PSR-0 autoload key name.
     */
    public function testDefaultClassmapPrefixFromAutoloaderPsr0()
    {

        $composerExtraStraussJson = <<<'EOD'
{
  "name": "brianhenryie/strauss-config-test",
  "require": {
    "league/container": "*"
  },

  "autoload": {
    "psr-0": {
      "BrianHenryIE\\Strauss\\": "lib/"
    }
  }
}
EOD;
        $tmpfname = tempnam(sys_get_temp_dir(), 'strauss-test-');
        file_put_contents($tmpfname, $composerExtraStraussJson);

        $composer = Factory::create(new NullIO(), $tmpfname);


        $sut = new StraussConfig($composer);

        $this->assertEquals("BrianHenryIE_Strauss_", $sut->getClassmapPrefix());
    }

    /**
     * When the classmap prefix isn't provided, and there's no PSR-0 or PSR-4 autoloader to figure it from...
     *
     * brianhenryie/strauss-config-test
     */
    public function testDefaultClassmapPrefixWithNoAutoloader()
    {

        $composerExtraStraussJson = <<<'EOD'
{
  "name": "brianhenryie/strauss-config-test",
  "require": {
    "league/container": "*"
  }

}
EOD;
        $tmpfname = tempnam(sys_get_temp_dir(), 'strauss-test-');
        file_put_contents($tmpfname, $composerExtraStraussJson);

        $composer = Factory::create(new NullIO(), $tmpfname);

        $sut = new StraussConfig($composer);

        $this->assertEquals("Brianhenryie_Strauss_Config_Test", $sut->getClassmapPrefix());
    }

    /**
     * When Strauss config has packages specified, obviously use them.
     */
    public function testGetPackagesFromConfig()
    {

        $composerExtraStraussJson = <<<'EOD'
{
  "name": "brianhenryie/strauss-config-test",
  "require": {
    "league/container": "*"
  },
  "extra": {
    "strauss": {
      "target_directory": "/target_directory/",
      "namespace_prefix": "BrianHenryIE\\Strauss\\",
      "classmap_prefix": "BrianHenryIE_Strauss_",
      "packages": [
        "pimple/pimple"
      ],
      "exclude_prefix_packages": [
        "psr/container"
      ],
      "override_autoload": {
        "clancats/container": {
          "classmap": [
            "src/"
          ]
        }
      },
      "delete_vendor_files": false
    }
  }
}

EOD;
        $tmpfname = tempnam(sys_get_temp_dir(), 'strauss-test-');
        file_put_contents($tmpfname, $composerExtraStraussJson);

        $composer = Factory::create(new NullIO(), $tmpfname);

        $sut = new StraussConfig($composer);

        $this->assertContains('pimple/pimple', $sut->getPackages());
    }

    /**
     * When Strauss config has no packages specified, use composer.json's require list.
     */
    public function testGetPackagesNoConfig()
    {

        $composerExtraStraussJson = <<<'EOD'
{
  "name": "brianhenryie/strauss-config-test",
  "require": {
    "league/container": "*"
  },
  "extra": {
    "strauss": {
      "namespace_prefix": "BrianHenryIE\\Strauss\\",
      "classmap_prefix": "BrianHenryIE_Strauss_",
      "exclude_prefix_packages": [
        "psr/container"
      ],
      "override_autoload": {
        "clancats/container": {
          "classmap": [
            "src/"
          ]
        }
      },
      "delete_vendor_files": false,
      "unexpected_key": "here"
    }
  }
}
EOD;
        $tmpfname = tempnam(sys_get_temp_dir(), 'strauss-test-');
        file_put_contents($tmpfname, $composerExtraStraussJson);

        $composer = Factory::create(new NullIO(), $tmpfname);

        $sut = new StraussConfig($composer);

        $this->assertContains('league/container', $sut->getPackages());
    }

    /**
     * For backwards compatibility, if a Mozart config is present, use it.
     */
    public function testMapMozartConfig()
    {

        $composerExtraStraussJson = <<<'EOD'
{
  "extra": {
    "mozart": {
      "dep_namespace": "My_Mozart_Config\\",
      "dep_directory": "/dep_directory/",
      "classmap_prefix": "My_Mozart_Config_",
      "classmap_directory": "/classmap_directory/",
      "packages": [
        "pimple/pimple"
      ],
      "exclude_packages": [
        "psr/container"
      ],
      "override_autoload": {
        "clancats/container": {
          "classmap": [
            "src/"
          ]
        }
      }
    }
  }
}
EOD;
        $tmpfname = tempnam(sys_get_temp_dir(), 'strauss-test-');
        file_put_contents($tmpfname, $composerExtraStraussJson);

        $composer = Factory::create(new NullIO(), $tmpfname);

        $sut = new StraussConfig($composer);

        $this->assertContains('pimple/pimple', $sut->getPackages());

        $this->assertEquals('dep_directory' . DIRECTORY_SEPARATOR, $sut->getTargetDirectory());

        $this->assertEquals("My_Mozart_Config", $sut->getNamespacePrefix());

        $this->assertEquals('My_Mozart_Config_', $sut->getClassmapPrefix());

        $this->assertContains('psr/container', $sut->getExcludePackagesFromPrefixing());

        $this->assertArrayHasKey('clancats/container', $sut->getOverrideAutoload());

        // Mozart default was true.
        $this->assertTrue($sut->isDeleteVendorFiles());
    }

    /**
     * Since sometimes the namespace we're prefixing will already have a leading backslash, sometimes
     * the namespace_prefix will want its slash at the beginning, sometimes at the end.
     *
     * @see Prefixer::replaceNamespace()
     *
     * @covers \BrianHenryIE\Strauss\Composer\Extra\StraussConfig::getNamespacePrefix
     */
    public function testNamespacePrefixHasNoSlash()
    {

        $composerExtraStraussJson = <<<'EOD'
{
  "extra": {
    "mozart": {
      "dep_namespace": "My_Mozart_Config\\"
    }
  }
}
EOD;
        $tmpfname = tempnam(sys_get_temp_dir(), 'strauss-test-');
        file_put_contents($tmpfname, $composerExtraStraussJson);

        $composer = Factory::create(new NullIO(), $tmpfname);

        $sut = new StraussConfig($composer);

        $this->assertEquals("My_Mozart_Config", $sut->getNamespacePrefix());
    }
}
