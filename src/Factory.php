<?php
namespace PharIo\Phive;

use PharIo\Phive\Cli;
use PharIo\Version\VersionConstraintParser;

class Factory {

    /**
     * @var CurlConfig
     */
    private $curlConfig;

    /**
     * @var PhiveVersion
     */
    private $version;

    /**
     * @var Cli\Request
     */
    private $request;

    /**
     * @var Environment
     */
    private $environment;

    /**
     * @param Cli\Request  $request
     * @param PhiveVersion $version
     */
    public function __construct(Cli\Request $request, PhiveVersion $version = null) {
        $this->request = $request;
        $this->version = $version;
    }

    /**
     * @return Cli\Runner
     */
    public function getRunner() {
        return new Cli\Runner(
            $this->getCommandLocator(),
            $this->getOutput(),
            $this->getPhiveVersion(),
            $this->getEnvironment(),
            $this->request
        );
    }

    /**
     * @return VersionCommand
     */
    public function getVersionCommand() {
        return new VersionCommand;
    }

    /**
     * @return HelpCommand
     */
    public function getHelpCommand() {
        return new HelpCommand(
            $this->getEnvironment(),
            $this->getOutput()
        );
    }

    /**
     * @return SkelCommand
     */
    public function getSkelCommand() {
        return new SkelCommand(
            new SkelCommandConfig($this->request->parse(new SkelContext()), getcwd()),
            $this->getPhiveVersion()
        );
    }

    /**
     * @return UpdateRepositoryListCommand
     */
    public function getUpdateRepositoryListCommand() {
        return new UpdateRepositoryListCommand($this->getRemoteSourcesListFileLoader());
    }

    /**
     * @return RemoveCommand
     */
    public function getRemoveCommand() {
        return new RemoveCommand(
            new RemoveCommandConfig($this->request->parse(new RemoveContext()), $this->getTargetDirectoryLocator()),
            $this->getPharRegistry(),
            $this->getOutput(),
            $this->getPhiveXmlConfig()
        );
    }

    /**
     * @return ResetCommand
     */
    public function getResetCommand() {
        return new ResetCommand(
            new ResetCommandConfig($this->request->parse(new ResetContext())),
            $this->getPharRegistry(),
            $this->getEnvironment(),
            $this->getPharInstaller()
        );
    }

    /**
     * @return InstallCommand
     */
    public function getInstallCommand() {
        return new InstallCommand(
            new InstallCommandConfig(
                $this->request->parse(new InstallContext()),
                $this->getPhiveXmlConfig(),
                $this->getTargetDirectoryLocator()
            ),
            $this->getInstallService(),
            $this->getEnvironment(),
            $this->getRequestedPharResolverBuilder()->build($this->getLocalFirstResolvingStrategy())
        );
    }

    /**
     * @return UpdateCommand
     */
    public function getUpdateCommand() {
        $config = new UpdateCommandConfig(
            $this->request->parse(new UpdateContext()),
            $this->getPhiveXmlConfig(),
            $this->getTargetDirectoryLocator()
        );

        $resolvingStrategy = $config->preferOffline() ? $this->getLocalFirstResolvingStrategy() : $this->getRemoteFirstResolvingStrategy();

        return new UpdateCommand(
            $config,
            $this->getInstallService(),
            $this->getRequestedPharResolverBuilder()->build($resolvingStrategy),
            $this->getPhiveXmlConfig()
        );
    }

    /**
     * @return ListCommand
     */
    public function getListCommand() {
        return new ListCommand(
            $this->getSourcesList(),
            $this->getLocalSourcesList(),
            $this->getOutput()
        );
    }

    /**
     * @return PurgeCommand
     */
    public function getPurgeCommand() {
        return new PurgeCommand(
            $this->getPharRegistry(),
            $this->getOutput()
        );
    }

    /**
     * @return ComposerCommand
     */
    public function getComposerCommand() {
        return new ComposerCommand(
            new ComposerCommandConfig(
                $this->request->parse(new ComposerContext()),
                $this->getPhiveXmlConfig(),
                $this->getTargetDirectoryLocator(),
                $this->getEnvironment()->getWorkingDirectory()
            ),
            $this->getComposerService(),
            $this->getInstallService(),
            $this->getPhiveXmlConfig(),
            $this->getEnvironment(),
            $this->getConsoleInput(),
            $this->getRequestedPharResolverBuilder()->build($this->getLocalFirstResolvingStrategy())
        );
    }

    /**
     * @return StatusCommand
     */
    public function getStatusCommand() {
        return new StatusCommand(
            $this->getPhiveXmlConfig(),
            $this->getOutput()
        );
    }

    /**
     * @return SelfupdateCommand
     */
    public function getSelfupdateCommand() {
        return new SelfupdateCommand(
            $this->getPharDownloader(),
            $this->getGithubAliasResolver(),
            $this->getEnvironment(),
            $this->getPhiveVersion(),
            $this->getOutput()
        );
    }

    /**
     * @return TargetDirectoryLocator
     */
    private function getTargetDirectoryLocator() {
        return new TargetDirectoryLocator($this->getConfig(), $this->getPhiveXmlConfig(), $this->request->getOptions());
    }

    /**
     * @return CommandLocator
     */
    private function getCommandLocator() {
        return new CommandLocator($this);
    }

    /**
     * @return Cli\Output
     */
    public function getOutput() {
        return (new Cli\OutputLocator(new Cli\OutputFactory()))->getOutput($this->getEnvironment());
    }

    /**
     * @return PhiveVersion
     */
    private function getPhiveVersion() {
        if (!$this->version) {
            $this->version = new GitAwarePhiveVersion($this->getGit());
        }

        return $this->version;
    }

    /**
     * @return InstallService
     */
    private function getInstallService() {
        return new InstallService(
            $this->getPhiveXmlConfig(),
            $this->getPharInstaller(),
            $this->getPharRegistry(),
            $this->getPharService()
        );
    }

    /**
     * @return Git
     */
    private function getGit() {
        return new Git($this->getEnvironment()->getWorkingDirectory());
    }

    /**
     * @return Environment
     */
    private function getEnvironment() {
        if (null === $this->environment) {
            $locator = new EnvironmentLocator();
            $this->environment = $locator->getEnvironment(PHP_OS);
        }

        return $this->environment;
    }

    /**
     * @return RemoteSourcesListFileLoader
     */
    public function getRemoteSourcesListFileLoader() {
        return new RemoteSourcesListFileLoader(
            $this->getConfig()->getSourcesListUrl(),
            $this->getConfig()->getHomeDirectory()->file('repositories.xml'),
            $this->getFileDownloader(),
            $this->getOutput()
        );
    }

    /**
     * @return LocalSourcesListFileLoader
     */
    public function getLocalSourcesListFileLoader() {
        return new LocalSourcesListFileLoader(
            $this->getConfig()->getHomeDirectory()->file('local.xml')
        );
    }

    /**
     * @return Config
     */
    public function getConfig() {
        return new Config(
            $this->getEnvironment(),
            $this->request->getOptions()
        );
    }

    /**
     * @return FileDownloader
     */
    public function getFileDownloader() {
        return new FileDownloader(
            $this->getCurl(),
            $this->getFileStorageCacheBackend()
        );
    }

    /**
     * @return HttpClient
     */
    private function getCurl() {
        if (null === $this->curlConfig) {
            $this->curlConfig = new CurlConfig('Phive ' . $this->getPhiveVersion()->getVersion());
            $this->curlConfig->addLocalSslCertificate(
                new LocalSslCertificate(
                    'hkps.pool.sks-keyservers.net',
                    __DIR__ . '/../conf/ssl/ca_certs/sks-keyservers.netCA.pem'
                )
            );
            $environment = $this->getEnvironment();
            if ($environment->hasProxy()) {
                $this->curlConfig->setProxy($environment->getProxy());
            }
        }

        return new Curl($this->curlConfig, $this->getHttpProgressRenderer());
    }

    /**
     * @return HttpProgressRenderer
     */
    private function getHttpProgressRenderer() {
        return new HttpProgressRenderer($this->getOutput());
    }

    /**
     * @return PharRegistry
     */
    public function getPharRegistry() {
        return new PharRegistry(
            new XmlFile(
                $this->getConfig()->getHomeDirectory()->file('/phars.xml'),
                'https://phar.io/phive/installdb',
                'phars'
            ),
            $this->getConfig()->getHomeDirectory()->child('phars')
        );
    }

    /**
     * @return PharService
     */
    private function getPharService() {
        return new PharService(
            $this->getPharRegistry(),
            $this->getPharDownloader()
        );
    }

    /**
     * @return PharDownloader
     */
    private function getPharDownloader() {
        return new PharDownloader(
            $this->getCurl(),
            $this->getGnupgSignatureVerifier(),
            $this->getChecksumService(),
            $this->getPharRegistry()
        );
    }

    /**
     * @return SignatureVerifier
     */
    private function getGnupgSignatureVerifier() {
        return new GnupgSignatureVerifier($this->getGnupg(), $this->getKeyService());
    }

    /**
     * @return \Gnupg|GnuPG
     */
    private function getGnupg() {
        $home = $this->getConfig()->getHomeDirectory()->child('gpg');
        if (extension_loaded('gnupg')) {
            putenv('GNUPGHOME=' . $home);
            $gpg = new \Gnupg();
            $gpg->seterrormode(\Gnupg::ERROR_EXCEPTION);
        } else {
            $gpg = new GnuPG(
                new Executor(),
                $this->getConfig()->getGPGBinaryPath(),
                $home->child('temp'),
                $home
            );
            if (!class_exists('\Gnupg')) {
                class_alias(GnuPG::class, '\Gnupg');
            }
        }
        return $gpg;
    }

    /**
     * @return KeyService
     */
    private function getKeyService() {
        return new KeyService(
            $this->getPgpKeyDownloader(),
            $this->getGnupgKeyImporter(),
            $this->getConfig()->getTrustedKeyIds(),
            $this->getOutput(),
            $this->getConsoleInput()
        );
    }

    /**
     * @return GnupgKeyDownloader
     */
    private function getPgpKeyDownloader() {
        return new GnupgKeyDownloader(
            $this->getCurl(),
            include __DIR__ . '/../conf/pgp-keyservers.php',
            $this->getOutput()
        );
    }

    /**
     * @return KeyImporter
     */
    private function getGnupgKeyImporter() {
        return new GnupgKeyImporter($this->getGnupg());
    }

    /**
     * @return Cli\ConsoleInput
     */
    private function getConsoleInput() {
        return new Cli\ConsoleInput($this->getOutput());
    }

    /**
     * @return ChecksumService
     */
    private function getChecksumService() {
        return new ChecksumService();
    }

    /**
     * @return PharInstaller
     */
    private function getPharInstaller() {
        return new PharInstaller(
            $this->getConfig()->getHomeDirectory()->child('phars'),
            $this->getOutput(),
            $this->getFileLinker()
        );
    }



    /**
     * @return PhiveXmlConfig
     */
    private function getPhiveXmlConfig() {
        return new PhiveXmlConfig(
            new XmlFile(
                $this->getEnvironment()->getWorkingDirectory()->file('phive.xml'),
                'https://phar.io/phive',
                'phive'
            ),
            new VersionConstraintParser()
        );
    }

    /**
     * @return ComposerService
     */
    private function getComposerService() {
        return new ComposerService($this->getSourcesList());
    }

    /**
     * @return RequestedPharResolverService
     */
    public function getRequestedPharResolverService() {
        return new RequestedPharResolverService();
    }

    /**
     * @return GithubAliasResolver
     */
    public function getGithubAliasResolver() {
        return new GithubAliasResolver($this->getFileDownloader());
    }

    /**
     * @return PharActivator
     */
    private function getFileLinker() {
        return $this->getFileLinkerLocator()->getPharActivator($this->getEnvironment());
    }

    /**
     * @return PharActivatorLocator
     */
    private function getFileLinkerLocator() {
        return new PharActivatorLocator(new PharActivatorFactory());
    }

    /**
     * @return FileStorageCacheBackend
     */
    private function getFileStorageCacheBackend() {
        return new FileStorageCacheBackend($this->getConfig()->getHomeDirectory()->child('http-cache'));
    }

    /**
     * @return RequestedPharResolverServiceBuilder
     */
    private function getRequestedPharResolverBuilder() {
        return new RequestedPharResolverServiceBuilder($this);
    }

    /**
     * @return SourcesList
     */
    private function getSourcesList() {
        return $this->getRemoteSourcesListFileLoader()->load();
    }

    /**
     * @return SourcesList
     */
    private function getLocalSourcesList() {
        return $this->getLocalSourcesListFileLoader()->load();
    }

    /**
     * @return LocalFirstResolvingStrategy
     */
    private function getLocalFirstResolvingStrategy() {
        return new LocalFirstResolvingStrategy($this->getRequestedPharResolverFactory());
    }

    /**
     * @return RemoteFirstResolvingStrategy
     */
    private function getRemoteFirstResolvingStrategy() {
        return new RemoteFirstResolvingStrategy($this->getRequestedPharResolverFactory());
    }

    /**
     * @return RequestedPharResolverFactory
     */
    private function getRequestedPharResolverFactory() {
        return new RequestedPharResolverFactory($this);
    }
}
