<?php

namespace Phpactor\Extension\Core\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Phpactor\Extension\Core\Application\Status;

class StatusCommand extends Command
{
    /**
     * @var Status
     */
    private $status;

    public function __construct(Status $status)
    {
        parent::__construct();
        $this->status = $status;
    }

    protected function configure()
    {
        $this->setName('status');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $diagnostics = $this->status->check();

        $output->writeln('<info>Version:</info> ' . $diagnostics['phpactor_version']);
        $output->writeln(sprintf(
            '<info>Filesystems:</info> %s',
            implode(', ', $diagnostics['filesystems'])
        ));
        $output->writeln('<info>Working directory:</info> ' . $diagnostics['cwd']);
        $output->write(PHP_EOL);

        $output->writeln('<comment>Config files (missing is not bad):</>');
        $output->write(PHP_EOL);
        foreach ($diagnostics['config_files'] as $configFile => $exists) {
            $check = $exists ? '<info>✔</>' : '<error>✘</>';
            $output->writeln(sprintf('  %s %s', $check, $configFile));
        }

        $output->write(PHP_EOL);

        $output->writeln('<comment>Diagnostics:</comment>');
        $output->write(PHP_EOL);
        foreach ($diagnostics['good'] as $good) {
            $output->writeln('  <info>✔</> ' . $good);
        }

        foreach ($diagnostics['bad'] as $bad) {
            $output->writeln('  <error>✘</> ' . $bad);
        }
        $output->write(PHP_EOL);
    }
}
