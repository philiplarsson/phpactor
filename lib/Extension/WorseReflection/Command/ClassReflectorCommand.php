<?php

namespace Phpactor\Extension\WorseReflection\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Phpactor\Extension\WorseReflection\Application\ClassReflector;
use Symfony\Component\Console\Input\InputArgument;
use Phpactor\Extension\Core\Console\Dumper\DumperRegistry;
use Phpactor\Extension\Core\Console\Handler\FormatHandler;

class ClassReflectorCommand extends Command
{
    /**
     * @var ClassReflector
     */
    private $reflector;

    /**
     * @var DumperRegistry
     */
    private $dumperRegistry;

    public function __construct(
        ClassReflector $reflector,
        DumperRegistry $dumperRegistry
    ) {
        parent::__construct();
        $this->reflector = $reflector;
        $this->dumperRegistry = $dumperRegistry;
    }

    public function configure()
    {
        $this->setName('class:reflect');
        $this->setDescription('Reflect a given class (path or FQN)');
        $this->addArgument('name', InputArgument::REQUIRED, 'Source path or FQN');
        FormatHandler::configure($this);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $reflection = $this->reflector->reflect($input->getArgument('name'));
        $this->dumperRegistry->get($input->getOption('format'))->dump($output, $reflection);
    }
}
