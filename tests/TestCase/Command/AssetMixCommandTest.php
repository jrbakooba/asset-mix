<?php
namespace AssetMix\Test\TestCase\Command;

use Cake\Console\Command;
use AssetMix\StubsPathTrait;
use Cake\TestSuite\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Cake\TestSuite\ConsoleIntegrationTestTrait;

/**
 * Class to test `asset_mix` command
 */
class AssetMixCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;
    use StubsPathTrait;

    /**
     * Filesystem object
     *
     * @var Filesystem
     */
    private $filesystem;

    /**
     * {@inheritDoc}
     */
    public function setUp()
    {
        parent::setUp();

        $this->useCommandRunner();

        $this->filesystem = new Filesystem();
    }

    public function testAssetMixGenerateCommandReturnsSuccessCode()
    {
        $this->exec('asset_mix generate --help');

        $this->assertExitCode(Command::CODE_SUCCESS);
        $this->assertOutputContains('Auto generate configuration files, assets directory');
    }

    public function testGenerateCommandCreatesPackageJsonFileAtProjectRoot()
    {
        $this->exec('asset_mix generate');

        $contents = file_get_contents($this->getVuePackageJsonPath()['to']);

        $this->assertOutputContains('package.json file created successfully.');
        $this->assertContains('"scripts"', $contents);
        $this->assertContains('npm run development', $contents);
        $this->assertContains('axios', $contents);
        $this->assertContains('laravel-mix', $contents);
        $this->assertContains('vue', $contents);
    }


    public function testGenerateCommandCreatesWebpackMixConfigFileAtProjectRoot()
    {
        $this->exec('asset_mix generate');

        $contents = file_get_contents($this->getVueWebpackMixJsPath()['to']);

        $this->assertOutputContains('webpack.mix.js file created successfully.');
        $this->assertContains('mix.setPublicPath', $contents);
        $this->assertContains('assets/js/app.js', $contents);
        $this->assertContains(".setPublicPath('./webroot')", $contents);
    }

    public function testGenerateCommandCreatesResourcesDirectoryAtProjectRoot()
    {
        $paths = $this->getVueAssetsDirPaths();

        $this->exec('asset_mix generate');

        $appJsContents = file_get_contents($paths['to_assets_js_app']);
        $appSassContents = file_get_contents($paths['to_assets_sass_app']);

        $this->assertDirectoryExists($paths['to_assets']);
        $this->assertDirectoryExists($paths['to_assets_css']);
        $this->assertDirectoryExists($paths['to_assets_js']);
        $this->assertDirectoryExists($paths['to_assets_js_components']);
        $this->assertDirectoryExists($paths['to_assets_sass']);
        $this->assertFileExists($paths['to_assets_sass_app']);
        $this->assertContains("import Vue from 'vue';", $appJsContents);
        $this->assertContains('$primary: grey', $appSassContents);
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown()
    {
        parent::tearDown();

        $this->filesystem->remove([
            APP . 'package.json',
            APP . 'webpack.mix.js',
            APP . 'assets'
        ]);
    }
}
