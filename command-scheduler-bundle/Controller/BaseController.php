<?php

namespace JMose\CommandSchedulerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Translation\TranslatorInterface as ComponentTranslatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface as ContractsTranslatorInterface;

/**
 * Class BaseController.
 *
 * @author  Julien Guyon <julienguyon@hotmail.com>
 */
abstract class BaseController extends AbstractController
{
    /**
     * @var string
     */
    private string $managerName;

    /**
     * @var ContractsTranslatorInterface|ComponentTranslatorInterface
     */
    protected TranslatorInterface $translator;

    /**
     * @param $managerName string
     * @return void
     */
    public function setManagerName($managerName): string
    {
        $this->managerName = $managerName;
    }

    /**
     * @param ContractsTranslatorInterface|ComponentTranslatorInterface $translator
     */
    public function setTranslator($translator): TranslatorInterface
    {
        $this->translator = $translator;
    }

    /**
     * @return \Doctrine\Common\Persistence\ObjectManager
     */
    protected function getDoctrineManager(): ObjectManager
    {
        return $this->getDoctrine()->getManager($this->managerName);
    }
}
