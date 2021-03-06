<?php
namespace PharIo\Phive;

class ResetCommandConfig {

    /**
     * @var Cli\Options
     */
    private $cliOptions;

    /**
     * @param Cli\Options $cliOptions
     */
    public function __construct(Cli\Options $cliOptions) {
        $this->cliOptions = $cliOptions;
    }

    /**
     * @return bool
     */
    public function hasAliases() {
        return $this->cliOptions->getArgumentCount() > 0;
    }

    /**
     * @return array
     * @throws Cli\CommandOptionsException
     */
    public function getAliases() {
        return $this->cliOptions->getArguments();
    }

}
