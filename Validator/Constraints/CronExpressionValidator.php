<?php

namespace JMose\CommandSchedulerBundle\Validator\Constraints;

use Cron\CronExpression as CronExpressionLib;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Class CronExpressionValidator.
 */
class CronExpressionValidator extends ConstraintValidator
{
    /**
     * Validate method for CronExpression constraint.
     *
     * @param mixed      $value
     * @param Constraint $constraint
     */
    public function validate($value, Constraint $constraint): void
    {
        $value = (string) $value;

        if ('' === $value) {
            return;
        }

        try {
            $cronExpression = new CronExpressionLib($value);
        } catch (InvalidArgumentException $e) {
            $this->context->addViolation($constraint->message, [], $value);
        }
    }
}
