<?php

namespace JMose\CommandSchedulerBundle\Command;

use DateInterval;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use JMose\CommandSchedulerBundle\Entity\ScheduledCommand;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to unlock one or all scheduled commands that have surpassed the lock timeout.
 *
 * @author  Marcel Pfeiffer <m.pfeiffer@strucnamics.de>
 */
class UnlockCommand extends Command
{
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var int
     */
    private int $defaultLockTimeout;

    /**
     * @var int Number of seconds after a command is considered as timeout
     */
    private int $lockTimeout;

    /**
     * @var bool true if all locked commands should be unlocked
     */
    private bool $unlockAll;

    /**
     * @var string name of the command to be unlocked
     */
    private string $scheduledCommandName = '';

    /**
     * UnlockCommand constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param int $lockTimeout
     */
    public function __construct(EntityManagerInterface $entityManager, int $lockTimeout)
    {
        $this->entityManager = $entityManager;
        $this->defaultLockTimeout = $lockTimeout;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('scheduler:unlock')
            ->setDescription('Unlock one or all scheduled commands that have surpassed the lock timeout.')
            ->addArgument('name', InputArgument::OPTIONAL, 'Name of the command to unlock')
            ->addOption('all', 'A', InputOption::VALUE_NONE, 'Unlock all scheduled commands')
            ->addOption(
                'lock-timeout',
                null,
                InputOption::VALUE_REQUIRED,
                'Use this lock timeout value instead of the configured one (in seconds, optional)'
            );
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
        $this->unlockAll = $input->getOption('all');
        $this->scheduledCommandName = $input->getArgument('name');

        $this->lockTimeout = $input->hasOption('lock-timeout') ? $input->getOption('lock-timeout', null) : $this->defaultLockTimeout;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (false === $this->unlockAll) {
            $output->writeln('Either the name of a scheduled command or the --all option must be set.');

            return Command::FAILURE;
        }

        $repository = $this->entityManager->getRepository(ScheduledCommand::class);

        if (true === $this->unlockAll) {
            $failedCommands = $repository->findLockedCommand();
            foreach ($failedCommands as $failedCommand) {
                $this->unlock($failedCommand, $output);
            }
        } else {
            $scheduledCommand = $repository->findOneBy(['name' => $this->scheduledCommandName, 'disabled' => false]);
            if (null === $scheduledCommand) {
                $output->writeln(
                    sprintf(
                        'Error: Scheduled Command with name "%s" not found or is disabled.',
                        $this->scheduledCommandName
                    )
                );

                return Command::FAILURE;
            }
            $this->unlock($scheduledCommand, $output);
        }

        $this->entityManager->flush();

        return Command::SUCCESS;
    }

    /**
     * @param ScheduledCommand $command command to be unlocked
     *
     * @return bool true if unlock happened
     * @throws Exception
     */
    protected function unlock(ScheduledCommand $command, OutputInterface $output): bool
    {
        if (false === $command->isLocked()) {
            $output->writeln(sprintf('Skipping: Scheduled Command "%s" is not locked.', $command->getName()));

            return false;
        }

        if (null !== $command->getLastExecution() &&
            $command->getLastExecution() >= (new \DateTime())->sub(
                new DateInterval(sprintf('PT%dS', $this->lockTimeout))
            )
        ) {
            $output->writeln(
                sprintf('Skipping: Timout for scheduled Command "%s" has not run out.', $command->getName())
            );

            return false;
        }
        $command->setLocked(false);
        $output->writeln(sprintf('Scheduled Command "%s" has been unlocked.', $command->getName()));

        return true;
    }
}
