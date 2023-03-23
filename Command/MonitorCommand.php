<?php

namespace JMose\CommandSchedulerBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Class MonitorCommand : This class is used for monitoring scheduled commands if they run for too long or failed to execute.
 *
 * @author  Daniel Fischer <dfischer000@gmail.com>
 */
class MonitorCommand extends Command
{
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var int|bool Number of seconds after a command is considered as timeout
     */
    private bool $lockTimeout;

    /**
     * @var string|array receiver for statusmail if an error occured
     */
    private array $receiver;

    /**
     * @var string mailSubject subject to be used when sending a mail
     */
    private string $mailSubject;

    /**
     * @var bool if true, current command will send mail even if all is ok.
     */
    private bool $sendMailIfNoError;

    /**
     * MonitorCommand constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param bool $lockTimeout
     * @param array $receiver
     * @param string $mailSubject
     * @param bool $sendMailIfNoError
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        bool $lockTimeout,
        array $receiver,
        string $mailSubject,
        bool $sendMailIfNoError
    ) {
        $this->entityManager = $entityManager;
        $this->lockTimeout = $lockTimeout;
        $this->receiver = $receiver;
        $this->mailSubject = $mailSubject;
        $this->sendMailIfNoError = $sendMailIfNoError;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('scheduler:monitor')
            ->setDescription('Monitor scheduled commands')
            ->addOption('dump', null, InputOption::VALUE_NONE, 'Display result instead of send mail')
            ->setHelp('This class is for monitoring all active commands.');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // If not in dump mode and none receiver is set, exit.
        $dumpMode = $input->getOption('dump');
        if (!$dumpMode && 0 === count($this->receiver)) {
            $output->writeln('Please add receiver in configuration');

            return Command::FAILURE;
        }

        // Fist, get all failed or potential timeout
        $failedCommands = $this->entityManager->getRepository('JMoseCommandSchedulerBundle:ScheduledCommand')
            ->findFailedAndTimeoutCommands($this->lockTimeout);

        // Commands in error
        if (count($failedCommands) > 0) {
            $message = '';

            foreach ($failedCommands as $command) {
                $message .= sprintf(
                    "%s: returncode %s, locked: %s, last execution: %s\n",
                    $command->getName(),
                    $command->getLastReturnCode(),
                    $command->getLocked(),
                    $command->getLastExecution()->format(\DateTimeInterface::ATOM)
                );
            }

            // if --dump option, don't send mail
            if ($dumpMode) {
                $output->writeln($message);
            } else {
                $this->sendMails($message);
            }
        } else {
            if ($dumpMode) {
                $output->writeln('No errors found.');
            } elseif ($this->sendMailIfNoError) {
                $this->sendMails('No errors found.');
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Send message to email receivers.
     *
     * @param string $message message to be sent
     * @return bool
     */
    private function sendMails(string $message): bool
    {
        try {
            // prepare email constants
            $hostname = gethostname();
            $subject = $this->getMailSubject();
            $headers = 'From: cron-monitor@' . $hostname . "\r\n" .
                'X-Mailer: PHP/' . phpversion();

            foreach ($this->receiver as $rcv) {
                mail(trim($rcv), $subject, $message, $headers);
            }
            $success = true;
        }catch (Throwable $throwable)
        {
            $success = false;
        }
        return $success;
    }

    /**
     * get the subject for monitor mails.
     *
     * @return string subject
     */
    private function getMailSubject(): string
    {
        $hostname = gethostname();

        return sprintf($this->mailSubject, $hostname, date('Y-m-d H:i:s'));
    }
}
