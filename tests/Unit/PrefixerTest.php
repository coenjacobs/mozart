<?php
/**
 * @author https://github.com/coenjacobs
 * @author https://github.com/BrianHenryIE
 * @author https://github.com/markjaquith
 * @author https://github.com/stephenharris
 */


namespace BrianHenryIE\Strauss\Tests\Unit;

use BrianHenryIE\Strauss\Composer\Extra\StraussConfig;
use BrianHenryIE\Strauss\Prefixer;
use Composer\Composer;
use Composer\Config;
use PHPUnit\Framework\TestCase;

/**
 * Class ReplacerTest
 * @package BrianHenryIE\Strauss
 * @covers \BrianHenryIE\Strauss\Prefixer
 */
class PrefixerTest extends TestCase
{

    protected StraussConfig $config;

    protected function aaasetUp(): void
    {
        parent::setUp();

        $composerJson = <<<'EOD'
{
"name": "brianhenryie/strauss-replacer-test",
"extra": {

}
}
EOD;

        $composerConfig = new Config(false);
        $composerConfig->merge(json_decode($composerJson, true));
        $composer = new Composer();
        $composer->setConfig($composerConfig);

        $this->config = new StraussConfig($composer);
    }

    public function testNamespaceReplacer()
    {

        $contents = <<<'EOD'
<?php
/*
 * Copyright 2010 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Google;

use Google\Http\Batch;
use TypeError;

class Service
{
  public $batchPath;
  public $rootUrl;
  public $version;
  public $servicePath;
  public $availableScopes;
  public $resource;
  private $client;

  public function __construct($clientOrConfig = [])
  {
    if ($clientOrConfig instanceof Client) {
      $this->client = $clientOrConfig;
    } elseif (is_array($clientOrConfig)) {
      $this->client = new Client($clientOrConfig ?: []);
    } else {
      $errorMessage = 'constructor must be array or instance of Google\Client';
      if (class_exists('TypeError')) {
        throw new TypeError($errorMessage);
      }
      trigger_error($errorMessage, E_USER_ERROR);
    }
  }

  /**
   * Return the associated Google\Client class.
   * @return \Google\Client
   */
  public function getClient()
  {
    return $this->client;
  }

  /**
   * Create a new HTTP Batch handler for this service
   *
   * @return Batch
   */
  public function createBatch()
  {
    return new Batch(
        $this->client,
        false,
        $this->rootUrl,
        $this->batchPath
    );
  }
}
EOD;
        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);

        $originalNamespace = 'Google\\Http';
        $replacement = 'BrianHenryIE\\Strauss\\Google\\Http';

        $result = $replacer->replaceNamespace($contents, $originalNamespace, $replacement);

        $expected = 'use BrianHenryIE\\Strauss\\Google\\Http\\Batch;';

        $this->assertStringContainsString($expected, $result);
    }


    public function testClassnameReplacer()
    {

        $contents = <<<'EOD'
<?php
/*******************************************************************************
* FPDF                                                                         *
*                                                                              *
* Version: 1.82                                                                *
* Date:    2019-12-07                                                          *
* Author:  Olivier PLATHEY                                                     *
*******************************************************************************/

define('FPDF_VERSION','1.82');

class FPDF
{
protected $page;               // current page number
protected $n;                  // current object number
protected $offsets;            // array of object offsets
protected $buffer;             // buffer holding in-memory PDF
}
EOD;

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);

        $original = "FPDF";
        $classnamePrefix = "BrianHenryIE_Strauss_";

        $result = $replacer->replaceClassname($contents, $original, $classnamePrefix);

        $expected = "class BrianHenryIE_Strauss_FPDF";

        $this->assertStringContainsString($expected, $result);
    }

    /**
     * PHP 7.4 typed parameters were being prefixed.
     */
    public function testTypeFunctionParameter()
    {
        $this->markTestIncomplete();
    }

    /**
     * @author CoenJacobs
     */
    public function test_it_replaces_class_declarations(): void
    {
        $contents = 'class Hello_World {';
        $originalClassname = 'Hello_World';
        $classnamePrefix = 'Mozart_';

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);

        $result = $replacer->replaceClassname($contents, $originalClassname, $classnamePrefix);

        $this->assertEquals('class Mozart_Hello_World {', $result);
    }

    /**
     * @author CoenJacobs
     */
    public function test_it_replaces_abstract_class_declarations(): void
    {
        $contents = 'abstract class Hello_World {';

        $originalClassname = 'Hello_World';
        $classnamePrefix = 'Mozart_';

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);

        $result = $replacer->replaceClassname($contents, $originalClassname, $classnamePrefix);

        $this->assertEquals('abstract class Mozart_Hello_World {', $result);
    }

    /**
     * @author CoenJacobs
     */
    public function test_it_replaces_interface_class_declarations(): void
    {
        $contents = 'interface Hello_World {';

        $originalClassname = 'Hello_World';
        $classnamePrefix = 'Mozart_';

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);

        $result = $replacer->replaceClassname($contents, $originalClassname, $classnamePrefix);

        $this->assertEquals('interface Mozart_Hello_World {', $result);
    }

    /**
     * @author CoenJacobs
     */
    public function test_it_replaces_class_declarations_that_extend_other_classes(): void
    {
        $contents = 'class Hello_World extends Bye_World {';

        $originalClassname = 'Hello_World';
        $classnamePrefix = 'Mozart_';

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);

        $result = $replacer->replaceClassname($contents, $originalClassname, $classnamePrefix);

        $this->assertEquals('class Mozart_Hello_World extends Bye_World {', $result);
    }

    /**
     * @author CoenJacobs
     */
    public function test_it_replaces_class_declarations_that_implement_interfaces(): void
    {
        $contents = 'class Hello_World implements Bye_World {';

        $originalClassname = 'Hello_World';
        $classnamePrefix = 'Mozart_';

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);

        $result = $replacer->replaceClassname($contents, $originalClassname, $classnamePrefix);

        $this->assertEquals('class Mozart_Hello_World implements Bye_World {', $result);
    }


    /**
     * @author BrianHenryIE
     */
    public function testItReplacesNamespacesInInterface(): void
    {
        $contents = 'class Hello_World implements \Strauss\Bye_World {';

        $originalNamespace = 'Strauss';
        $replacement = 'Prefix\Strauss';

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);

        $result = $replacer->replaceNamespace($contents, $originalNamespace, $replacement);

        $this->assertEquals('class Hello_World implements \Prefix\Strauss\Bye_World {', $result);
    }

    /**
     * @author CoenJacobs
     */
    public function test_it_stores_replaced_class_names(): void
    {
        $this->markTestIncomplete('TODO Delete/move');

        $contents = 'class Hello_World {';
        $replacer = new Prefixer($config, __DIR__);
        $replacer->setClassmapPrefix('Mozart_');
        $replacer->replace($contents);
        $this->assertArrayHasKey('Hello_World', $replacer->getReplacedClasses());
    }

    /**
     * @author https://github.com/stephenharris
     * @see https://github.com/coenjacobs/mozart/commit/fd7906943396c9a17110d1bfaf9d778f3b1f322a#diff-87828794e62b55ce8d7263e3ab1a918d1370e283ac750cd44e3ac61db5daee54
     */
    public function test_it_replaces_class_declarations_psr2(): void
    {
        $contents = "class Hello_World\n{";

        $originalClassname = 'Hello_World';
        $classnamePrefix = 'Mozart_';

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);

        $result = $replacer->replaceClassname($contents, $originalClassname, $classnamePrefix);

        $this->assertEquals("class Mozart_Hello_World\n{", $result);
    }

    /**
     * @see https://github.com/coenjacobs/mozart/issues/81
     * @author BrianHenryIE
     *
     */
    public function test_it_replaces_class(): void
    {
        $contents = "class Hello_World {";

        $originalClassname = 'Hello_World';
        $classnamePrefix = 'Mozart_';

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);

        $result = $replacer->replaceClassname($contents, $originalClassname, $classnamePrefix);

        $this->assertEquals("class Mozart_Hello_World {", $result);
    }


    /**
     * @see ClassmapReplacerIntegrationTest::test_it_does_not_make_classname_replacement_inside_namespaced_file()
     * @see https://github.com/coenjacobs/mozart/issues/93
     *
     * @author BrianHenryIE
     *
     * @test
     */
    public function it_does_not_replace_inside_namespace_multiline(): void
    {
        $contents = "
        namespace Mozart;
        class Hello_World
        ";

        $originalClassname = 'Hello_World';
        $classnamePrefix = 'Mozart_';

        $config = $this->createMock(StraussConfig::class);
        $config->method("getClassmapPrefix")->willReturn($classnamePrefix);

        $replacer = new Prefixer($config, __DIR__);

        $result = $replacer->replaceInString([$originalClassname], [], [], $contents);

        $this->assertEquals($contents, $result);
    }

    /**
     * @see ClassmapReplacerIntegrationTest::test_it_does_not_make_classname_replacement_inside_namespaced_file()
     * @see https://github.com/coenjacobs/mozart/issues/93
     *
     * @author BrianHenryIE
     */
    public function test_it_does_not_replace_inside_namespace_singleline(): void
    {
        $contents = "namespace Mozart; class Hello_World";

        $originalClassname = 'Hello_World';
        $classnamePrefix = 'Mozart_';

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);

        $result = $replacer->replaceClassname($contents, $originalClassname, $classnamePrefix);

        $this->assertEquals($contents, $result);
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
    public function it_does_not_replace_inside_named_namespace_but_does_inside_explicit_global_namespace_b(): void
    {

        $contents = "
		namespace My_Project {
			class A_Class { }
		}
		namespace {
			class B_Class { }
		}
		";

        $classnamePrefix = 'Mozart_';

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);

        $result = $replacer->replaceClassname($contents, 'B_Class', $classnamePrefix);

        $this->assertStringContainsString('Mozart_B_Class', $result);
    }

    /** @test */
    public function it_replaces_namespace_declarations(): void
    {
        $contents = 'namespace Test\\Test;';

        $namespace = "Test\\Test";
        $replacement = "My\\Mozart\\Prefix\\Test\\Test";

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);

        $result = $replacer->replaceNamespace($contents, $namespace, $replacement);

        $this->assertEquals('namespace My\\Mozart\\Prefix\\Test\\Test;', $result);
    }


    /**
     * This test doesn't seem to match its name.
     */
    public function test_it_doesnt_replaces_namespace_inside_namespace(): void
    {
        $contents = "namespace Test\\Something;\n\nuse Test\\Test;";

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);
        $result = $replacer->replaceNamespace($contents, "Test\\Something", "My\\Mozart\\Prefix\\Test\\Something");
        $result = $replacer->replaceNamespace($result, "Test\\Test", "My\\Mozart\\Prefix\\Test\\Test");

        $this->assertEquals("namespace My\\Mozart\\Prefix\\Test\\Something;\n\nuse My\\Mozart\\Prefix\\Test\\Test;", $result);
    }

    /**
     *
     */
    public function test_it_does_notreplaces_partial_namespace_declarations(): void
    {
        $contents = 'namespace Test\\Test\\Another;';

        $namespace = 'Test\\Another';
        $replacement = 'My\\Mozart\\Prefix\\'.$namespace;

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);
        $result = $replacer->replaceNamespace($contents, $namespace, $replacement);

        $this->assertEquals('namespace Test\\Test\\Another;', $result);
    }


    public function test_it_doesnt_prefix_already_prefixed_namespace(): void
    {

        $contents = 'namespace My\\Mozart\\Prefix\\Test\\Another;';

        $namespace = "Test\\Another";
        $prefix = "My\\Mozart\\Prefix";

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);
        $result = $replacer->replaceNamespace($contents, $namespace, $prefix);

        $this->assertEquals('namespace My\\Mozart\\Prefix\\Test\\Another;', $result);
    }

    /**
     * @author markjaquith
     */
    public function test_it_doesnt_double_replace_namespaces_that_also_exist_inside_another_namespace(): void
    {

        // This is a tricky situation. We are referencing Chicken\Egg,
        // but Egg *also* exists as a separate top level class.
        $contents = 'use Chicken\\Egg;';
        $expected = 'use My\\Mozart\\Prefix\\Chicken\\Egg;';

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);

        $result = $replacer->replaceNamespace($contents, 'Chicken', 'My\\Mozart\\Prefix\\Chicken');
        $result = $replacer->replaceNamespace($result, 'Egg', 'My\\Mozart\\Prefix\\Egg');

        $this->assertEquals($expected, $result);
    }

    /**
     * @see https://github.com/coenjacobs/mozart/issues/75
     *
     * @test
     */
    public function it_replaces_namespace_use_as_declarations(): void
    {
        $namespace = 'Symfony\\Polyfill\\';
        $replacement = "MBViews\\Dependencies\\Symfony\\Polyfill\\";

        $contents = "use Symfony\Polyfill\Mbstring as p;";

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);
        $result = $replacer->replaceNamespace($contents, $namespace, $replacement);

        $expected = "use MBViews\\Dependencies\\Symfony\\Polyfill\\Mbstring as p;";

        $this->assertEquals($expected, $result);
    }

    /**
     * @author BrianHenryIE
     */
    public function test_it_doesnt_prefix_function_types_that_happen_to_match_the_namespace()
    {
        $namespace = 'Mpdf';
        $prefix = "Mozart";
        $contents = 'public function getServices( Mpdf $mpdf, LoggerInterface $logger, $config, )';

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);
        $result = $replacer->replaceNamespace($contents, $namespace, $prefix);

        $expected = 'public function getServices( Mpdf $mpdf, LoggerInterface $logger, $config, )';

        $this->assertEquals($expected, $result);
    }

    public function testLeadingSlashInString()
    {
        $originalNamespace = "Strauss\\Test";
        $replacement = "Prefix\\Strauss\\Test";
        $contents = '$mentionedClass = "\\Strauss\\Test\\Classname";';

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);
        $result = $replacer->replaceNamespace($contents, $originalNamespace, $replacement);

        $expected = '$mentionedClass = "\\Prefix\\Strauss\\Test\\Classname";';

        $this->assertEquals($expected, $result);
    }

    public function testDoubleLeadingSlashInString()
    {
        $originalNamespace = 'Strauss\\Test';
        $replacement = 'Prefix\\Strauss\\Test';
        $contents = '$mentionedClass = "\\\\Strauss\\\\Test\\\\Classname";';

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);
        $result = $replacer->replaceNamespace($contents, $originalNamespace, $replacement);

        $expected = '$mentionedClass = "\\\\Prefix\\\\Strauss\\\\Test\\\\Classname";';

        $this->assertEquals($expected, $result);
    }

    public function testItReplacesSlashedNamespaceInFunctionParameter()
    {

        $originalNamespace = "net\\authorize\\api\\contract\\v1";
        $replacement = "Prefix\\net\\authorize\\api\\contract\\v1";
        $contents = "public function __construct(\\net\\authorize\\api\\contract\\v1\\AnetApiRequestType \$request, \$responseType)";

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);
        $result = $replacer->replaceNamespace($contents, $originalNamespace, $replacement);

        $expected = "public function __construct(\\Prefix\\net\\authorize\\api\\contract\\v1\\AnetApiRequestType \$request, \$responseType)";

        $this->assertEquals($expected, $result);
    }


    public function testItReplacesNamespaceInFunctionParameterDefaultAgumentValue()
    {

        $originalNamespace = "net\\authorize\\api\constants";
        $replacement = "Prefix\\net\\authorize\\api\constants";
        $contents = "public function executeWithApiResponse(\$endPoint = \\net\\authorize\\api\\constants\\ANetEnvironment::CUSTOM)";

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);
        $result = $replacer->replaceNamespace($contents, $originalNamespace, $replacement);

        $expected = "public function executeWithApiResponse(\$endPoint = \\Prefix\\net\\authorize\\api\\constants\\ANetEnvironment::CUSTOM)";

        $this->assertEquals($expected, $result);
    }


    public function testItReplacesNamespaceConcatenatedStringConst()
    {

        $originalNamespace = "net\\authorize\\api\\constants";
        $replacement = "Prefix\\net\\authorize\\api\\constants";
        $contents = "\$this->apiRequest->setClientId(\"sdk-php-\" . \\net\\authorize\\api\\constants\\ANetEnvironment::VERSION);";

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);
        $result = $replacer->replaceNamespace($contents, $originalNamespace, $replacement);

        $expected = "\$this->apiRequest->setClientId(\"sdk-php-\" . \\Prefix\\net\\authorize\\api\\constants\\ANetEnvironment::VERSION);";


        $this->assertEquals($expected, $result);
    }

    /**
     * Another mpdf issue where the class "Mpdf" is in the namespace "Mpdf" and incorrect replacements are being made.
     */
    public function testClassnameNotConfusedWithNamespace()
    {

        $contents = '$default_font_size = $mmsize * (Mpdf::SCALE);';
        $expected = $contents;

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);
        $result = $replacer->replaceNamespace($contents, 'Mpdf', 'BrianHenryIE\Strauss\Mpdf');

        $this->assertEquals($expected, $result);
    }

    public function testClassExtendsNamspacedClassIsPrefixed()
    {

        $contents = 'class BarcodeException extends \Mpdf\MpdfException';
        $expected = 'class BarcodeException extends \BrianHenryIE\Strauss\Mpdf\MpdfException';

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);
        $result = $replacer->replaceNamespace($contents, 'Mpdf', 'BrianHenryIE\Strauss\Mpdf');

        $this->assertEquals($expected, $result);
    }

    /**
     * Prefix namespaced classnames after `new` keyword.
     *
     * @see https://github.com/BrianHenryIE/strauss/issues/11
     */
    public function testNewNamespacedClassIsPrefixed()
    {

        $contents = '$ioc->register( new \Carbon_Fields\Provider\Container_Condition_Provider() );';
        $expected = '$ioc->register( new \BrianHenryIE\Strauss\Carbon_Fields\Provider\Container_Condition_Provider() );';

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);
        $result = $replacer->replaceNamespace($contents, 'Carbon_Fields\Provider', 'BrianHenryIE\Strauss\Carbon_Fields\Provider');

        $this->assertEquals($expected, $result);
    }



    /**
     * Prefix namespaced classnames after `static` keyword.
     *
     * @see https://github.com/BrianHenryIE/strauss/issues/11
     */
    public function testStaticNamespacedClassIsPrefixed()
    {

        $contents = '@method static \Carbon_Fields\Container\Comment_Meta_Container';
        $expected = '@method static \BrianHenryIE\Strauss\Carbon_Fields\Container\Comment_Meta_Container';

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);
        $result = $replacer->replaceNamespace($contents, 'Carbon_Fields\Container', 'BrianHenryIE\Strauss\Carbon_Fields\Container');

        $this->assertEquals($expected, $result);
    }

    /**
     * Prefix namespaced classnames after return statement.
     *
     * @see https://github.com/BrianHenryIE/strauss/issues/11
     */
    public function testReturnedNamespacedClassIsPrefixed()
    {

        $contents = 'return \Carbon_Fields\Carbon_Fields::resolve';
        $expected = 'return \BrianHenryIE\Strauss\Carbon_Fields\Carbon_Fields::resolve';

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);
        $result = $replacer->replaceNamespace($contents, 'Carbon_Fields', 'BrianHenryIE\Strauss\Carbon_Fields');

        $this->assertEquals($expected, $result);
    }

    /**
     * Prefix namespaced classnames between two tabs and colon.
     *
     * @see https://github.com/BrianHenryIE/strauss/issues/11
     */
    public function testNamespacedStaticIsPrefixed()
    {

        $contents = '		\\Carbon_Fields\\Carbon_Fields::service( \'legacy_storage\' )->enable()';
        $expected = '		\\BrianHenryIE\\Strauss\\Carbon_Fields\\Carbon_Fields::service( \'legacy_storage\' )->enable()';

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);
        $result = $replacer->replaceNamespace(
            $contents,
            'Carbon_Fields',
            'BrianHenryIE\\Strauss\\Carbon_Fields'
        );

        $this->assertEquals($expected, $result);
    }

    /**
     * Sometimes the namespace in a string should be replaced, but sometimes not.
     *
     * @see https://github.com/BrianHenryIE/strauss/issues/15
     */
    public function testDoNotReplaceInStringThatIsNotCode()
    {
        $originalNamespace = "TrustedLogin";
        $replacement = "Prefix\\TrustedLogin";
        $contents = "esc_html__( 'Learn about TrustedLogin', 'trustedlogin' )";

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);
        $result = $replacer->replaceNamespace($contents, $originalNamespace, $replacement);

        $expected = "esc_html__( 'Learn about TrustedLogin', 'trustedlogin' )";

        $this->assertEquals($expected, $result);
    }


    /**
     *
     *
     * @see https://github.com/BrianHenryIE/strauss/issues/19
     *
     */
    public function testDoNotReplaceInVariableNames()
    {
        $originalClassname = 'object';
        $classnamePrefix = 'Strauss_Issue19_';
        $contents = "public static function objclone(\$object) {";

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);
        $result = $replacer->replaceClassname($contents, $originalClassname, $classnamePrefix);

        // NOT public static function objclone($Strauss_Issue19_object) {
        $expected = "public static function objclone(\$object) {";

        $this->assertEquals($expected, $result);
    }

    public function testReplaceConstants()
    {

        $contents = <<<'EOD'
/*******************************************************************************
 * FPDF                                                                         *
 *                                                                              *
 * Version: 1.83                                                                *
 * Date:    2021-04-18                                                          *
 * Author:  Olivier PLATHEY                                                     *
 *******************************************************************************
 */

define('FPDF_VERSION', '1.83');

define('ANOTHER_CONSTANT', '1.83');

class FPDF
{
EOD;

        $config = $this->createMock(StraussConfig::class);
        $config->method('getConstantsPrefix')->willReturn('BHMP_');
        $replacer = new Prefixer($config, __DIR__);

        $namespacesChanges = array();
        $classes = array();
        $constants = array('FPDF_VERSION','ANOTHER_CONSTANT');

        $result = $replacer->replaceInString($namespacesChanges, $classes, $constants, $contents);

        $this->assertStringContainsString("define('BHMP_ANOTHER_CONSTANT', '1.83');", $result);
        $this->assertStringContainsString("define('BHMP_ANOTHER_CONSTANT', '1.83');", $result);
    }

    public function testStaticFunctionCallOfNamespacedClassIsPrefixed()
    {

        $contents = <<<'EOD'
public function __construct() {
    new \ST\StraussTestPackage2();
    \ST\StraussTestPackage2::hello();
    new \ST\StraussTestPackage2();
}
EOD;
        $expected = <<<'EOD'
public function __construct() {
    new \StraussTest\ST\StraussTestPackage2();
    \StraussTest\ST\StraussTestPackage2::hello();
    new \StraussTest\ST\StraussTestPackage2();
}
EOD;
        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);
        $result = $replacer->replaceNamespace($contents, 'ST', 'StraussTest\ST');

        $this->assertEquals($expected, $result);
    }


    public function testItPrefixesGroupedNamespacedClasses()
    {

        $contents = 'use chillerlan\\QRCode\\{QRCode, QRCodeException};';
        $expected = 'use BrianHenryIE\\Strauss\\chillerlan\\QRCode\\{QRCode, QRCodeException};';

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);
        $result = $replacer->replaceNamespace($contents, 'chillerlan\\QRCode', 'BrianHenryIE\\Strauss\\chillerlan\\QRCode');

        $this->assertEquals($expected, $result);
    }

    /**
     * @see https://github.com/BrianHenryIE/strauss/issues/25
     * @see https://gist.github.com/adrianstaffen/e1df25cd62c17d3f1a4697db6c449034
     */
    public function testStaticSimpleCall()
    {

        $config = $this->createMock(StraussConfig::class);
        $replacer = new Prefixer($config, __DIR__);

        // Simple call.

        $contents = '\ST\StraussTestPackage2::hello();';
        $expected = '\StraussTest\ST\StraussTestPackage2::hello();';

        $result = $replacer->replaceNamespace($contents, 'ST', 'StraussTest\\ST');
        $this->assertEquals($expected, $result);

        $contents = '! \ST\StraussTestPackage2::hello();';
        $expected = '! \StraussTest\ST\StraussTestPackage2::hello();';

        $result = $replacer->replaceNamespace($contents, 'ST', 'StraussTest\\ST');
        $this->assertEquals($expected, $result);
    }


    /**
     * @see https://github.com/BrianHenryIE/strauss/issues/25
     * @see https://gist.github.com/adrianstaffen/e1df25cd62c17d3f1a4697db6c449034
     */
    public function testStaticVariableAssignment()
    {

        $config = $this->createMock(StraussConfig::class);
        $replacer = new Prefixer($config, __DIR__);

        // Variable assignment.
        $contents = '$test1 = \ST\StraussTestPackage2::hello();';
        $expected = '$test1 = \StraussTest\ST\StraussTestPackage2::hello();';

        $result = $replacer->replaceNamespace($contents, 'ST', 'StraussTest\\ST');
        $this->assertEquals($expected, $result);

        $contents = '$test2 = ! \ST\StraussTestPackage2::hello();';
        $expected = '$test2 = ! \StraussTest\ST\StraussTestPackage2::hello();';

        $result = $replacer->replaceNamespace($contents, 'ST', 'StraussTest\\ST');
        $this->assertEquals($expected, $result);
    }


    /**
     * @see https://github.com/BrianHenryIE/strauss/issues/25
     * @see https://gist.github.com/adrianstaffen/e1df25cd62c17d3f1a4697db6c449034
     */
    public function testStaticIfConditionSingle()
    {

        $config = $this->createMock(StraussConfig::class);
        $replacer = new Prefixer($config, __DIR__);

        // If condition: Single.
        $contents = <<<'EOD'
if ( \ST\StraussTestPackage2::hello() ) {
    echo 'hello world';
}
EOD;
        $expected = <<<'EOD'
if ( \StraussTest\ST\StraussTestPackage2::hello() ) {
    echo 'hello world';
}
EOD;

        $result = $replacer->replaceNamespace($contents, 'ST', 'StraussTest\\ST');
        $this->assertEquals($expected, $result);

        $contents = <<<'EOD'
if ( ! \ST\StraussTestPackage2::hello() ) {
    echo 'hello world';
}
EOD;
        $expected = <<<'EOD'
if ( ! \StraussTest\ST\StraussTestPackage2::hello() ) {
    echo 'hello world';
}
EOD;

        $result = $replacer->replaceNamespace($contents, 'ST', 'StraussTest\\ST');
        $this->assertEquals($expected, $result);
    }


    /**
     * @see https://github.com/BrianHenryIE/strauss/issues/25
     * @see https://gist.github.com/adrianstaffen/e1df25cd62c17d3f1a4697db6c449034
     */
    public function testStaticIfConditionMultipleAND()
    {

        $config = $this->createMock(StraussConfig::class);
        $replacer = new Prefixer($config, __DIR__);

// If condition: Multiple (AND).
        $contents = <<<'EOD'
if ( \ST\StraussTestPackage2::hello() && ! \ST\StraussTestPackage2::hello() ) {
    echo 'hello world';
}
EOD;
        $expected = <<<'EOD'
if ( \StraussTest\ST\StraussTestPackage2::hello() && ! \StraussTest\ST\StraussTestPackage2::hello() ) {
    echo 'hello world';
}
EOD;
        $result = $replacer->replaceNamespace($contents, 'ST', 'StraussTest\\ST');
        $this->assertEquals($expected, $result);

        $contents = <<<'EOD'
if ( ! \ST\StraussTestPackage2::hello() && \ST\StraussTestPackage2::hello() ) {
    echo 'hello world';
}
EOD;
        $expected = <<<'EOD'
if ( ! \StraussTest\ST\StraussTestPackage2::hello() && \StraussTest\ST\StraussTestPackage2::hello() ) {
    echo 'hello world';
}
EOD;

        $result = $replacer->replaceNamespace($contents, 'ST', 'StraussTest\\ST');
        $this->assertEquals($expected, $result);
    }


    /**
     * @see https://github.com/BrianHenryIE/strauss/issues/25
     * @see https://gist.github.com/adrianstaffen/e1df25cd62c17d3f1a4697db6c449034
     */
    public function testStaticIfConditionMultipleOR()
    {

        $config = $this->createMock(StraussConfig::class);
        $replacer = new Prefixer($config, __DIR__);

// If condition: Multiple (OR).
        $contents = <<<'EOD'
if ( \ST\StraussTestPackage2::hello() || ! \ST\StraussTestPackage2::hello() ) {
    echo 'hello world';
}
EOD;
        $expected = <<<'EOD'
if ( \StraussTest\ST\StraussTestPackage2::hello() || ! \StraussTest\ST\StraussTestPackage2::hello() ) {
    echo 'hello world';
}
EOD;
        $result = $replacer->replaceNamespace($contents, 'ST', 'StraussTest\\ST');
        $this->assertEquals($expected, $result);

        $contents = <<<'EOD'
if ( ! \ST\StraussTestPackage2::hello() || \ST\StraussTestPackage2::hello() ) {
    echo 'hello world';
}
EOD;
        $expected = <<<'EOD'
if ( ! \StraussTest\ST\StraussTestPackage2::hello() || \StraussTest\ST\StraussTestPackage2::hello() ) {
    echo 'hello world';
}
EOD;

        $result = $replacer->replaceNamespace($contents, 'ST', 'StraussTest\\ST');
        $this->assertEquals($expected, $result);
    }


    /**
     * @see https://github.com/BrianHenryIE/strauss/issues/25
     * @see https://gist.github.com/adrianstaffen/e1df25cd62c17d3f1a4697db6c449034
     */
    public function testStaticArrayNonAssociativeSingle()
    {

        $config = $this->createMock(StraussConfig::class);
        $replacer = new Prefixer($config, __DIR__);

// Array: Non-associative: Single.
        $contents = <<<'EOD'
$arr1 = array(
    \ST\StraussTestPackage2::hello(),
    ! \ST\StraussTestPackage2::hello(),
);
EOD;
        $expected = <<<'EOD'
$arr1 = array(
    \StraussTest\ST\StraussTestPackage2::hello(),
    ! \StraussTest\ST\StraussTestPackage2::hello(),
);
EOD;

        $result = $replacer->replaceNamespace($contents, 'ST', 'StraussTest\\ST');
        $this->assertEquals($expected, $result);
    }


    /**
     * @see https://github.com/BrianHenryIE/strauss/issues/25
     * @see https://gist.github.com/adrianstaffen/e1df25cd62c17d3f1a4697db6c449034
     */
    public function testStaticArrayNonAssociativeMultipleAND()
    {

        $config = $this->createMock(StraussConfig::class);
        $replacer = new Prefixer($config, __DIR__);

// Array: Non-associative: Multiple (AND).
        $contents = <<<'EOD'
$arr2 = array(
    \ST\StraussTestPackage2::hello() && ! \ST\StraussTestPackage2::hello(),
    ! \ST\StraussTestPackage2::hello() && \ST\StraussTestPackage2::hello(),
);
EOD;
        $expected = <<<'EOD'
$arr2 = array(
    \StraussTest\ST\StraussTestPackage2::hello() && ! \StraussTest\ST\StraussTestPackage2::hello(),
    ! \StraussTest\ST\StraussTestPackage2::hello() && \StraussTest\ST\StraussTestPackage2::hello(),
);
EOD;

        $result = $replacer->replaceNamespace($contents, 'ST', 'StraussTest\\ST');
        $this->assertEquals($expected, $result);
    }


    /**
     * @see https://github.com/BrianHenryIE/strauss/issues/25
     * @see https://gist.github.com/adrianstaffen/e1df25cd62c17d3f1a4697db6c449034
     */
    public function testStaticArrayNonAssociationMultipleOR()
    {

        $config = $this->createMock(StraussConfig::class);
        $replacer = new Prefixer($config, __DIR__);

// Array: Non-associative: Multiple (OR).
        $contents = <<<'EOD'
$arr3 = array(
    \ST\StraussTestPackage2::hello() || ! \ST\StraussTestPackage2::hello(),
    ! \ST\StraussTestPackage2::hello() || \ST\StraussTestPackage2::hello(),
);
EOD;
        $expected = <<<'EOD'
$arr3 = array(
    \StraussTest\ST\StraussTestPackage2::hello() || ! \StraussTest\ST\StraussTestPackage2::hello(),
    ! \StraussTest\ST\StraussTestPackage2::hello() || \StraussTest\ST\StraussTestPackage2::hello(),
);
EOD;

        $result = $replacer->replaceNamespace($contents, 'ST', 'StraussTest\\ST');
        $this->assertEquals($expected, $result);
    }


    /**
     * @see https://github.com/BrianHenryIE/strauss/issues/25
     * @see https://gist.github.com/adrianstaffen/e1df25cd62c17d3f1a4697db6c449034
     */
    public function testStaticArrayAssociativeSingle()
    {

        $config = $this->createMock(StraussConfig::class);
        $replacer = new Prefixer($config, __DIR__);

// Array: Associative: Single.
        $contents = <<<'EOD'
$assoc_arr1 = array(
    'one' => \ST\StraussTestPackage2::hello(),
    'two' => ! \ST\StraussTestPackage2::hello(),
);
EOD;
        $expected = <<<'EOD'
$assoc_arr1 = array(
    'one' => \StraussTest\ST\StraussTestPackage2::hello(),
    'two' => ! \StraussTest\ST\StraussTestPackage2::hello(),
);
EOD;

        $result = $replacer->replaceNamespace($contents, 'ST', 'StraussTest\\ST');
        $this->assertEquals($expected, $result);
    }


    /**
     * @see https://github.com/BrianHenryIE/strauss/issues/25
     * @see https://gist.github.com/adrianstaffen/e1df25cd62c17d3f1a4697db6c449034
     */
    public function testStaticArrayAssociativeMultipleAND()
    {

        $config = $this->createMock(StraussConfig::class);
        $replacer = new Prefixer($config, __DIR__);

// Array: Associative: Multiple (AND).
        $contents = <<<'EOD'
$assoc_arr1 = array(
    'one' => \ST\StraussTestPackage2::hello() && ! \ST\StraussTestPackage2::hello(),
    'two' => ! \ST\StraussTestPackage2::hello() && \ST\StraussTestPackage2::hello(),
);
EOD;
        $expected = <<<'EOD'
$assoc_arr1 = array(
    'one' => \StraussTest\ST\StraussTestPackage2::hello() && ! \StraussTest\ST\StraussTestPackage2::hello(),
    'two' => ! \StraussTest\ST\StraussTestPackage2::hello() && \StraussTest\ST\StraussTestPackage2::hello(),
);
EOD;
        $result = $replacer->replaceNamespace($contents, 'ST', 'StraussTest\\ST');
        $this->assertEquals($expected, $result);
    }


    /**
     * @see https://github.com/BrianHenryIE/strauss/issues/25
     * @see https://gist.github.com/adrianstaffen/e1df25cd62c17d3f1a4697db6c449034
     */
    public function testStaticArrayAssociativeMultipleOR()
    {

        $config = $this->createMock(StraussConfig::class);
        $replacer = new Prefixer($config, __DIR__);

// Array: Associative: Multiple (OR).
        $contents = <<<'EOD'
$assoc_arr1 = array(
    'one' => \ST\StraussTestPackage2::hello() || ! \ST\StraussTestPackage2::hello(),
    'two' => ! \ST\StraussTestPackage2::hello() || \ST\StraussTestPackage2::hello(),
);
EOD;
        $expected = <<<'EOD'
$assoc_arr1 = array(
    'one' => \StraussTest\ST\StraussTestPackage2::hello() || ! \StraussTest\ST\StraussTestPackage2::hello(),
    'two' => ! \StraussTest\ST\StraussTestPackage2::hello() || \StraussTest\ST\StraussTestPackage2::hello(),
);
EOD;

        $result = $replacer->replaceNamespace($contents, 'ST', 'StraussTest\\ST');
        $this->assertEquals($expected, $result);
    }


    /**
     * @see https://github.com/BrianHenryIE/strauss/issues/26
     */
    public function testDoublePrefixBug()
    {

        $config = $this->createMock(StraussConfig::class);
        $replacer = new Prefixer($config, __DIR__);

        $contents = <<<'EOD'
namespace ST;
class StraussTestPackage {
	public function __construct() {
	}
}
EOD;
        $expected = <<<'EOD'
namespace StraussTest\ST;
class StraussTestPackage {
	public function __construct() {
	}
}
EOD;
        $result = $replacer->replaceNamespace($contents, 'ST', 'StraussTest\\ST');
        $this->assertEquals($expected, $result);

        $contents = <<<'EOD'
namespace ST\Namespace;
class StraussTestPackage2
{
    public function __construct()
    {
        $one = '\ST\Namespace';
        $two = '\ST\Namespace\StraussTestPackage2';
    }
}
EOD;
        $expected = <<<'EOD'
namespace StraussTest\ST\Namespace;
class StraussTestPackage2
{
    public function __construct()
    {
        $one = '\StraussTest\ST\Namespace';
        $two = '\StraussTest\ST\Namespace\StraussTestPackage2';
    }
}
EOD;

        $result = $replacer->replaceNamespace($contents, 'ST\\Namespace', 'StraussTest\\ST\\Namespace');
        $result = $replacer->replaceNamespace($result, 'ST', 'StraussTest\\ST');
        $this->assertEquals($expected, $result);
    }

    /**
     * A prefixed classname was being replaced inside a namespace.
     *
     * namespace Symfony\Polyfill\Intl\Normalizer_Test_Normalizer;
     *
     * @see https://github.com/BrianHenryIE/strauss/issues/27
     *
     * @author BrianHenryIE
     */
    public function testItDoesNotPrefixClassnameInsideNamespaceName(): void
    {

        $contents = <<<'EOD'
namespace Symfony\Polyfill\Intl\Normalizer;
class NA
{

}
EOD;

        $originalClassname = 'Normalizer';
        $classnamePrefix = 'Normalizer_Test_';

        $config = $this->createMock(StraussConfig::class);

        $replacer = new Prefixer($config, __DIR__);

        $result = $replacer->replaceClassname($contents, $originalClassname, $classnamePrefix);

        $this->assertEquals($contents, $result);
    }
}
