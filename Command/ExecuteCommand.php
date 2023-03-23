<?php

namespace JMose\CommandSchedulerBundle\Command;

use Cron\CronExpression;
use DateTime;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use JMose\CommandSchedulerBundle\Entity\ScheduledCommand;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * Class ExecuteCommand : This class is the entry point to execute all scheduled command.
 *
 * @author  Julien Guyon <julienguyon@hotmail.com>
 */
class ExecuteCommand extends Command
{
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var string
     */
    private string $logPath;

    /**
     * @var bool
     */
    private bool $dumpMode;

    /**
     * @var int
     */
    private int $commandsVerbosity;

    /**
     * ExecuteCommand constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param string $logPath
     */
    public function __construct(EntityManagerInterface $entityManager, string $logPath)
    {
        $this->entityManager = $entityManager;
        $this->logPath = $logPath;

        // If logpath is not set to false, append the directory separator to it
        if ($this->logPath) {
            $this->logPath = rtrim($this->logPath, '/\\').DIRECTORY_SEPARATOR;
        }

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('scheduler:execute')
            ->setDescription('Execute scheduled commands')
            ->addOption('dump', null, InputOption::VALUE_NONE, 'Display next execution')
            ->addOption('no-output', null, InputOption::VALUE_NONE, 'Disable output message from scheduler')
            ->setHelp('This class is the entry point to execute all scheduled command');
    }

    /**
     * Initialize parameters and services used in execute function.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return void
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->dumpMode = $input->getOption('dump');

        // Store the original verbosity before apply the quiet parameter
        $this->commandsVerbosity = $output->getVerbosity();

        if (true === $input->getOption('no-output')) {
            $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Start : '.($this->dumpMode ? 'Dump' : 'Execute').' all scheduled command</info>');

        // Before continue, we check that the output file is valid and writable (except for gaufrette)
        if (false !== $this->logPath && 0 !== strpos($this->logPath, 'gaufrette:') && false === is_writable(
                $this->logPath
            )
        ) {
            $output->writeln(
                '<error>'.$this->logPath.
                ' not found or not writable. You should override `log_path` in your config.yml'.'</error>'
            );

            return Command::FAILURE;
        }

        $commands = $this->entityManager->getRepository(ScheduledCommand::class)->findEnabledCommand();

        $noneExecution = true;
        foreach ($commands as $command) {
            $this->entityManager->refresh($this->entityManager->find(ScheduledCommand::class, $command));
            if ($command->isDisabled() || $command->isLocked()) {
                continue;
            }

            /** @var ScheduledCommand $command */
            $cron = new CronExpression($command->getCronExpression());
            $nextRunDate = $cron->getNextRunDate($command->getLastExecution());
            $now = new DateTime();

            if ($command->isExecuteImmediately()) {
                $noneExecution = false;
                $output->writeln(
                    'Immediately execution asked for : <comment>'.$command->getCommand().'</comment>'
                );

                if (!$input->getOption('dump')) {
                    $this->executeCommand($command, $output, $input);
                }
            } elseif ($nextRunDate < $now) {
                $noneExecution = false;
                $output->writeln(
                    'Command <comment>'.$command->getCommand().
                    '</comment> should be executed - last execution : <comment>'.
                    $command->getLastExecution()->format(\DateTimeInterface::ATOM).'.</comment>'
                );

                if (!$input->getOption('dump')) {
                    $this->executeCommand($command, $output, $input);
                }
            }
        }

        if (true === $noneExecution) {
            $output->writeln('Nothing to do.');
        }

        return Command::SUCCESS;
    }

    /**
     * @param ScheduledCommand $scheduledCommand
     * @param OutputInterface $output
     * @param InputInterface $input
     * @return void
     * @throws ExceptionInterface
     * @throws Exception
     */
    private function executeCommand(ScheduledCommand $scheduledCommand, OutputInterface $output, InputInterface $input): void
    {
        //reload command from database before every execution to avoid parallel execution
        $this->entityManager->getConnection()->beginTransaction();
        try {
            $notLockedCommand = $this
                ->entityManager
                ->getRepository(ScheduledCommand::class)
                ->getNotLockedCommand($scheduledCommand);

            $scheduledCommand = $notLockedCommand;
            $scheduledCommand->setLastExecution(new DateTime());
            $scheduledCommand->setLocked(true);
            $this->entityManager->persist($scheduledCommand);
            $this->entityManager->flush();
            $this->entityManager->getConnection()->commit();
        } catch (\Exception $e) {
            $this->entityManager->getConnection()->rollBack();
            $output->writeln(
                sprintf(
                    '<error>Command %s is locked %s</error>',
                    $scheduledCommand->getCommand(),
                    (!empty($e->getMessage()) ? sprintf('(%s)', $e->getMessage()) : '')
                )
            );

            return;
        }

        $scheduledCommand = $this->entityManager->find(ScheduledCommand::class, $scheduledCommand);

        try {
            $command = $this->getApplication()->find($scheduledCommand->getCommand());
        } catch (\InvalidArgumentException $e) {
            $scheduledCommand->setLastReturnCode(-1);
            $output->writeln('<error>Cannot find '.$scheduledCommand->getCommand().'</error>');

            return;
        }

        $input = new StringInput(
            $scheduledCommand->getCommand().' '.$scheduledCommand->getArguments().' --env='.$input->getOption('env')
        );

        $input->bind($command->getDefinition());

        // Disable interactive mode if the current command has no-interaction flag
        if (true === $input->hasParameterOption(['--no-interaction', '-n'])) {
            $input->setInteractive(false);
        }

        // Use a StreamOutput or NullOutput to redirect write() and writeln() in a log file
        if (empty($scheduledCommand->getLogFile())) {
            $logOutput = new NullOutput();
        } else {
            $logOutput = new StreamOutput(
                fopen(
                    $this->logPath.$scheduledCommand->getLogFile(),
                    'a',
                    false
                ), $this->commandsVerbosity
            );
        }

        // Execute command and get return code
        try {
            $output->writeln(
                '<info>Execute</info> : <comment>'.$scheduledCommand->getCommand()
                .' '.$scheduledCommand->getArguments().'</comment>'
            );
            $result = $command->run($input, $logOutput);
        } catch (\Exception $e) {
            $logOutput->writeln($e->getMessage());
            $logOutput->writeln($e->getTraceAsString());
            $result = -1;
        }

        if (false === $this->entityManager->isOpen()) {
            $output->writeln('<comment>Entity manager closed by the last command.</comment>');
        }

        $scheduledCommand->setLastReturnCode($result);
        $scheduledCommand->setLocked(false);
        $scheduledCommand->setExecuteImmediately(false);
        $this->entityManager->persist($scheduledCommand);
        $this->entityManager->flush();

        /*
         * This clear() is necessary to avoid conflict between commands and to be sure that none entity are managed
         * before entering a new command
         */
        $this->entityManager->clear();

        unset($command);
        gc_collect_cycles();
    }
}
