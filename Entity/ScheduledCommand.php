<?php

namespace JMose\CommandSchedulerBundle\Entity;

use DateTime;

/**
 * Entity ScheduledCommand.
 *
 * @author  Julien Guyon <julienguyon@hotmail.com>
 */
class ScheduledCommand
{
    /**
     * @var int
     */
    private int $id;

    /**
     * @var string
     */
    private string $name;

    /**
     * @var string
     */
    private string $command;

    /**
     * @var string
     */
    private string $arguments;

    /**
     * @see http://www.abunchofutils.com/utils/developer/cron-expression-helper/
     *
     * @var string
     */
    private string $cronExpression;

    /**
     * @var DateTime
     */
    private DateTime $lastExecution;

    /**
     * @var int
     */
    private int $lastReturnCode;

    /**
     * Log's file name (without path).
     *
     * @var string
     */
    private string $logFile;

    /**
     * @var int
     */
    private int $priority;

    /**
     * If true, command will be execute next time regardless cron expression.
     *
     * @var bool
     */
    private bool $executeImmediately;

    /**
     * @var bool
     */
    private bool $disabled;

    /**
     * @var bool
     */
    private bool $locked;

    /**
     * Init new ScheduledCommand.
     */
    public function __construct()
    {
        $this->setLastExecution(new DateTime());
        $this->setLocked(false);
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Set id.
     *
     * @param $id
     *
     * @return ScheduledCommand
     */
    public function setId($id): ScheduledCommand
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName():string
    {
        return $this->name;
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return ScheduledCommand
     */
    public function setName(string $name): ScheduledCommand
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get command.
     *
     * @return string
     */
    public function getCommand(): string
    {
        return $this->command;
    }

    /**
     * Set command.
     *
     * @param string $command
     *
     * @return ScheduledCommand
     */
    public function setCommand(string $command): ScheduledCommand
    {
        $this->command = $command;

        return $this;
    }

    /**
     * Get arguments.
     *
     * @return string
     */
    public function getArguments(): string
    {
        return $this->arguments;
    }

    /**
     * Set arguments.
     *
     * @param string $arguments
     *
     * @return ScheduledCommand
     */
    public function setArguments(string $arguments): ScheduledCommand
    {
        $this->arguments = $arguments;

        return $this;
    }

    /**
     * Get cronExpression.
     *
     * @return string
     */
    public function getCronExpression(): string
    {
        return $this->cronExpression;
    }

    /**
     * Set cronExpression.
     *
     * @param string $cronExpression
     *
     * @return ScheduledCommand
     */
    public function setCronExpression(string $cronExpression): ScheduledCommand
    {
        $this->cronExpression = $cronExpression;

        return $this;
    }

    /**
     * Get lastExecution.
     *
     * @return DateTime
     */
    public function getLastExecution(): DateTime
    {
        return $this->lastExecution;
    }

    /**
     * Set lastExecution.
     *
     * @param DateTime $lastExecution
     *
     * @return ScheduledCommand
     */
    public function setLastExecution(DateTime $lastExecution): ScheduledCommand
    {
        $this->lastExecution = $lastExecution;

        return $this;
    }

    /**
     * Get logFile.
     *
     * @return string
     */
    public function getLogFile(): string
    {
        return $this->logFile;
    }

    /**
     * Set logFile.
     *
     * @param string $logFile
     *
     * @return ScheduledCommand
     */
    public function setLogFile(string $logFile): ScheduledCommand
    {
        $this->logFile = $logFile;

        return $this;
    }

    /**
     * Get lastReturnCode.
     *
     * @return int
     */
    public function getLastReturnCode(): int
    {
        return $this->lastReturnCode;
    }

    /**
     * Set lastReturnCode.
     *
     * @param int $lastReturnCode
     *
     * @return ScheduledCommand
     */
    public function setLastReturnCode(int $lastReturnCode): ScheduledCommand
    {
        $this->lastReturnCode = $lastReturnCode;

        return $this;
    }

    /**
     * Get priority.
     *
     * @return int
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Set priority.
     *
     * @param int $priority
     *
     * @return ScheduledCommand
     */
    public function setPriority(int $priority): ScheduledCommand
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * Get executeImmediately.
     *
     * @return bool
     */
    public function isExecuteImmediately(): bool
    {
        return $this->executeImmediately;
    }

    /**
     * Get executeImmediately.
     *
     * @return bool
     */
    public function getExecuteImmediately(): bool
    {
        return $this->executeImmediately;
    }

    /**
     * Set executeImmediately.
     *
     * @param $executeImmediately
     *
     * @return ScheduledCommand
     */
    public function setExecuteImmediately($executeImmediately): ScheduledCommand
    {
        $this->executeImmediately = $executeImmediately;

        return $this;
    }

    /**
     * Get disabled.
     *
     * @return bool
     */
    public function isDisabled(): bool
    {
        return $this->disabled;
    }

    /**
     * Get disabled.
     *
     * @return bool
     */
    public function getDisabled(): bool
    {
        return $this->disabled;
    }

    /**
     * Set disabled.
     *
     * @param bool $disabled
     *
     * @return ScheduledCommand
     */
    public function setDisabled(bool $disabled): ScheduledCommand
    {
        $this->disabled = $disabled;

        return $this;
    }

    /**
     * Locked Getter.
     *
     * @return bool
     */
    public function isLocked(): bool
    {
        return $this->locked;
    }

    /**
     * locked Getter.
     *
     * @return bool
     */
    public function getLocked(): bool
    {
        return $this->locked;
    }

    /**
     * locked Setter.
     *
     * @param bool $locked
     *
     * @return ScheduledCommand
     */
    public function setLocked(bool $locked): ScheduledCommand
    {
        $this->locked = $locked;

        return $this;
    }
}
