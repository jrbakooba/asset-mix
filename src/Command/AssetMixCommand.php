<?php
declare(strict_types=1);

namespace AssetMix\Command;

use AssetMix\StubsPathTrait;
use AssetMix\Utility\FileUtility;
use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Exception;

class AssetMixCommand extends Command
{
    use StubsPathTrait;

    /**
     * Filesystem utility object
     *
     * @var \AssetMix\Utility\FileUtility
     */
    private $filesystem;

    /**
     * Preset type provided via argument.
     *
     * @var string
     */
    private $preset;

    /**
     * Directory name where all assets(js, css) files will reside.
     */
    public const ASSETS_DIR_NAME = 'assets';

    /**
     * @inheritDoc
     */
    public function initialize(): void
    {
        $this->filesystem = new FileUtility();
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->setDescription('Auto generate configuration files, assets directory')
            ->addArgument('preset', [
                'help' => __('The preset/scaffolding type (bootstrap, vue, react), default is vue.'),
                'choices' => ['bootstrap', 'vue', 'react'],
            ])
            ->addOption('dir', [
                'short' => 'd',
                'help' => __('Directory name to create'),
                'default' => self::ASSETS_DIR_NAME,
            ]);

        return $parser;
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $this->preset = $args->getArgument('preset');

        if ($this->preset === null) {
            $this->preset = 'vue';
        }

        $this->updatePackageJsonFile($io);
        $this->copyWebpackMixJsFile($args, $io);
        $this->copyAssetsDirectory($args, $io);

        return null;
    }

    /**
     * Update `package.json` file from stubs directory and write into project root.
     *
     * @param \Cake\Console\ConsoleIo $io Console input/output
     * @return void
     */
    private function updatePackageJsonFile($io)
    {
        $path = $this->getPackageJsonPath();

        $packages = $this->getPackageJsonFileContentsAsArray();

        $this->writePackageJsonFile($packages, $path['to']);

        $io->success(__('\'package.json\' file created successfully.'));
    }

    /**
     * Writes `package.json` file.
     *
     * @param  array $packages Content to write into the file.
     * @param  string $to Path to create the file.
     * @return void
     */
    private function writePackageJsonFile($packages, $to)
    {
        $packageConfigKey = 'devDependencies';
        $updatePackagesMethodName = sprintf(
            'update%sPackagesArray',
            ucwords($this->preset)
        );

        $packages[$packageConfigKey] = $this->{$updatePackagesMethodName}($packages[$packageConfigKey]);

        ksort($packages[$packageConfigKey]);

        file_put_contents(
            $to,
            json_encode($packages, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL
        );
    }

    /**
     * Copy `webpack.mix.js` file in project root
     *
     * @param \Cake\Console\Arguments $args Arguments
     * @param \Cake\Console\ConsoleIo $io Console input/output
     * @return void
     */
    private function copyWebpackMixJsFile($args, $io)
    {
        $dirName = $args->getOption('dir');

        if (! is_string($dirName)) {
            throw new Exception('Invalid directory name');
        }

        $path = $this->getWebpackMixJsPath();
        $content = $this->setWebpackMixFileContents($path['from'], $dirName);

        $this->filesystem->write($path['to'], $content);

        $io->success(__('\'webpack.mix.js\' file created successfully.'));
    }

    /**
     * Create, copy `assets` directory to project of the root
     *
     * @param \Cake\Console\Arguments $args Arguments
     * @param \Cake\Console\ConsoleIo $io Console input/output
     * @return void
     */
    private function copyAssetsDirectory($args, $io)
    {
        $dirName = $args->getOption('dir');
        $assetPath = ROOT . DS . $dirName;
        $stubsPaths = $this->getAssetsDirPaths();

        if ($this->filesystem->exists($assetPath)) {
            // Ask if they want to overwrite existing directory with default stubs
        }

        $this->filesystem->mkdir($assetPath);
        $this->filesystem->recursiveCopy($stubsPaths['from_assets'], $assetPath);

        $io->success(__(sprintf('\'%s\' directory created successfully.', $dirName)));
    }

    /**
     * Update `webpack.mix.js` file contents with given directory name.
     *
     * @param string $filePath Path to file.
     * @param string $dirName Directory name.
     * @return string Updated file contents.
     */
    private function setWebpackMixFileContents($filePath, $dirName)
    {
        $currentWebpackContents = file_get_contents($filePath);

        if (! is_string($currentWebpackContents)) {
            throw new Exception('Invalid webpack.mix.js file contents');
        }

        $updatedFileContents = preg_replace(
            '/\b' . self::ASSETS_DIR_NAME . '\b/',
            $dirName,
            $currentWebpackContents
        );

        if (! is_string($updatedFileContents)) {
            throw new Exception('Unable to replace file content');
        }

        return $updatedFileContents;
    }

    /**
     * Get `package.json` file path depending on preset.
     *
     * @return array<string>
     */
    private function getPackageJsonPath()
    {
        $getPackgeJsonPathMethodName = sprintf(
            'get%sPackageJsonPath',
            ucwords($this->preset)
        );

        return $this->{$getPackgeJsonPathMethodName}();
    }

    /**
     * Get `package.json` file contents as array depending on preset.
     *
     * @return array<mixed>
     */
    private function getPackageJsonFileContentsAsArray()
    {
        $getPackgeJsonPathMethodName = sprintf(
            'get%sPackageJsonPath',
            ucwords($this->preset)
        );
        $path = $this->{$getPackgeJsonPathMethodName}();

        return json_decode(file_get_contents($path['from']), true);
    }

    /**
     * Returns `webpack.mix.js` file path depending on preset.
     *
     * @return array<string>
     */
    private function getWebpackMixJsPath()
    {
        $webpackMixJsPathMethodName = sprintf(
            'get%sWebpackMixJsPath',
            ucwords($this->preset)
        );

        return $this->{$webpackMixJsPathMethodName}();
    }

    /**
     * Returns paths of `assets` directory files depending on preset.
     *
     * @return array<string>
     */
    private function getAssetsDirPaths()
    {
        $assetsDirPathMethodName = sprintf(
            'get%sAssetsDirPaths',
            ucwords($this->preset)
        );

        return $this->{$assetsDirPathMethodName}();
    }

    /**
     * Update packages array for vue.
     *
     * @param  array $packages Existing packages array to update.
     * @return array
     */
    private function updateVuePackagesArray($packages)
    {
        return [
            'resolve-url-loader' => '^2.3.1',
            'sass' => '^1.20.1',
            'sass-loader' => '^8.0.0',
            'vue' => '^2.5.18',
            'vue-template-compiler' => '^2.6.10',
        ] + $packages;
    }

    /**
     * Update packages array for bootstrap.
     *
     * @param  array $packages Existing packages array to update.
     * @return array
     */
    private function updateBootstrapPackagesArray($packages)
    {
        return [
            'bootstrap' => '^4.0.0',
            'jquery' => '^3.2',
            'popper.js' => '^1.12',
        ] + $packages;
    }
}
