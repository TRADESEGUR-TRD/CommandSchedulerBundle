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
use Symfony\Component\Console\Input\ArrayInput;

/**
 * Class ExecuteCommand : This class is the entry point to execute all scheduled commands.
 *
 * @author  Julien Guyon <julienguyon@hotmail.com>
 */
class ExecuteCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private string $logPath;
    private bool $dumpMode;
    private int $commandsVerbosity;

    public function __construct(EntityManagerInterface $entityManager, string $logPath)
    {
        $this->entityManager = $entityManager;
        $this->logPath = rtrim($logPath, '/\\') . DIRECTORY_SEPARATOR;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('scheduler:execute')
            ->setDescription('Execute scheduled commands')
            ->addOption('dump', null, InputOption::VALUE_NONE, 'Display next execution')
            ->addOption('no-output', null, InputOption::VALUE_NONE, 'Disable output message from scheduler')
            ->setHelp('This class is the entry point to execute all scheduled commands');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->dumpMode = $input->getOption('dump');
        $this->commandsVerbosity = $output->getVerbosity();

        if ($input->getOption('no-output')) {
            $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Start: ' . ($this->dumpMode ? 'Dump' : 'Execute') . ' all scheduled commands</info>');

        if (!is_writable($this->logPath)) {
            $output->writeln('<error>' . $this->logPath . ' is not writable. Please check your configuration.</error>');
            return Command::FAILURE;
        }

        $commands = $this->entityManager->getRepository(ScheduledCommand::class)->findEnabledCommand();
        $noneExecution = true;

        foreach ($commands as $command) {
            $this->entityManager->refresh($command);
            if ($command->isDisabled() || $command->isLocked()) {
                continue;
            }

            $cron = new CronExpression($command->getCronExpression());
            $nextRunDate = $cron->getNextRunDate($command->getLastExecution());
            $now = new DateTime();

            if ($command->isExecuteImmediately() || $nextRunDate < $now) {
                $noneExecution = false;
                $output->writeln('Executing command: <comment>' . $command->getCommand() . '</comment>');
                if (!$input->getOption('dump')) {
                    $this->executeCommand($command, $output);
                }
            }
        }

        if ($noneExecution) {
            $output->writeln('Nothing to do.');
        }

        return Command::SUCCESS;
    }

    private function executeCommand(ScheduledCommand $scheduledCommand, OutputInterface $output): void
    {
        try {
            $this->entityManager->getConnection()->beginTransaction();
            $scheduledCommand->setLastExecution(new DateTime());
            $scheduledCommand->setLocked(true);
            $this->entityManager->persist($scheduledCommand);
            $this->entityManager->flush();
            $this->entityManager->getConnection()->commit();

            $command = $this->getApplication()->find($scheduledCommand->getCommand());

            // Preparar los argumentos correctamente
            $arguments = [];
            if (!empty($scheduledCommand->getArguments())) {
                $argsArray = explode(' ', $scheduledCommand->getArguments());

                for ($i = 0; $i < count($argsArray); $i++) {
                    $arg = $argsArray[$i];
                    if (strpos($arg, '-') === 0) {
                        if (isset($argsArray[$i + 1]) && strpos($argsArray[$i + 1], '-') !== 0) {
                            $arguments[$arg] = $argsArray[$i + 1];
                            $i++;
                        } else {
                            $arguments[$arg] = null;
                        }
                    }
                }
            }

            $input = new ArrayInput($arguments);
            $input->bind($command->getDefinition());

            if ($input->hasParameterOption(['--no-interaction', '-n'])) {
                $input->setInteractive(false);
            }

            $logOutput = new StreamOutput(fopen($this->logPath . 'scheduler.log', 'a', false), $this->commandsVerbosity);

            $output->writeln('<info>Executing:</info> ' . $scheduledCommand->getCommand() . ' ' . $scheduledCommand->getArguments());
            $result = $command->run($input, $logOutput);

            $scheduledCommand->setLastReturnCode($result);
            $scheduledCommand->setLocked(false);
            $scheduledCommand->setExecuteImmediately(false);
            $this->entityManager->persist($scheduledCommand);
            $this->entityManager->flush();
            $this->entityManager->clear();

        } catch (\Exception $e) {
            $this->entityManager->getConnection()->rollBack();
            $output->writeln('<error>Failed to execute command: ' . $scheduledCommand->getCommand() . '</error>');
        }
    }
}

