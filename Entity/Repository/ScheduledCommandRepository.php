<?php

namespace JMose\CommandSchedulerBundle\Entity\Repository;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\TransactionRequiredException;
use JMose\CommandSchedulerBundle\Entity\ScheduledCommand;

/**
 * Class ScheduledCommandRepository.
 *
 * @author  Julien Guyon <julienguyon@hotmail.com>
 */
class ScheduledCommandRepository extends EntityRepository
{
    /**
     * Find all enabled command ordered by priority.
     *
     * @return ScheduledCommand[]
     */
    public function findEnabledCommand(): array
    {
        return $this->findBy(['disabled' => false, 'locked' => false], ['priority' => 'DESC']);
    }

    /**
     * findAll override to implement the default orderBy clause.
     *
     * @return ScheduledCommand[]
     */
    public function findAll(): array
    {
        return $this->findBy([], ['priority' => 'DESC']);
    }

    /**
     * Find all locked commands.
     *
     * @return ScheduledCommand[]
     */
    public function findLockedCommand(): array
    {
        return $this->findBy(['disabled' => false, 'locked' => true], ['priority' => 'DESC']);
    }

    /**
     * Find all failed command.
     *
     * @return ScheduledCommand[]
     */
    public function findFailedCommand(): array
    {
        return $this->createQueryBuilder('command')
            ->where('command.disabled = false')
            ->andWhere('command.lastReturnCode != 0')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int|bool $lockTimeout
     *
     * @return array|ScheduledCommand[]
     */
    public function findFailedAndTimeoutCommands($lockTimeout = false): array
    {
        // Fist, get all failed commands (return != 0)
        $failedCommands = $this->findFailedCommand();

        // Then, si a timeout value is set, get locked commands and check timeout
        if (false !== $lockTimeout) {
            $lockedCommands = $this->findLockedCommand();
            foreach ($lockedCommands as $lockedCommand) {
                $now = time();
                if ($lockedCommand->getLastExecution()->getTimestamp() + $lockTimeout < $now) {
                    $failedCommands[] = $lockedCommand;
                }
            }
        }

        return $failedCommands;
    }

    /**
     * @param ScheduledCommand $command
     *
     * @return mixed
     *
     * @throws NonUniqueResultException
     * @throws TransactionRequiredException
     */
    public function getNotLockedCommand(ScheduledCommand $command): mixed
    {
        $query = $this->createQueryBuilder('command')
            ->where('command.locked = false')
            ->andWhere('command.id = :id')
            ->setParameter('id', $command->getId())
            ->getQuery();

        $query->setLockMode(LockMode::PESSIMISTIC_WRITE);

        return $query->getOneOrNullResult();
    }
}
