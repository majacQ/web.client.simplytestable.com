<?php

namespace SimplyTestable\WebClientBundle\Resque\Job;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

abstract class CommandJob extends Job
{
    /**
     * @return Command
     */
    abstract protected function getCommand();

    /**
     * Get the arguments required by the to-be-run command.
     *
     * This may differ from the arguments passed to this job, specifically when being run via resque as some additional
     * container-relevant args will be added to the job that are not relevant to the command.
     *
     * return array
     */
    abstract protected function getCommandArgs();

    public function run($args)
    {
        $command = $this->getCommand();

        $input = new ArrayInput($this->getCommandArgs());
        $output = new BufferedOutput();

        $returnCode = ($this->isTestEnvironment()) ? $this->args['returnCode'] : $command->run($input, $output);

        if ($returnCode === 0) {
            return true;
        }

        $logger = $this->getContainer()->get('logger');

        $logger->error(get_class($this) . ': task [' . $args['id'] . '] returned ' . $returnCode);
        $logger->error(get_class($this) . ': task [' . $args['id'] . '] output ' . trim($output->fetch()));
    }

    /**
     * @return bool
     */
    private function isTestEnvironment()
    {
        if (!isset($this->args['kernel.environment'])) {
            return false;
        }

        return $this->args['kernel.environment'] == 'test';
    }
}
