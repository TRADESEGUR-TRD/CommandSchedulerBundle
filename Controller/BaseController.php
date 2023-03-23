<?php

namespace JMose\CommandSchedulerBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class BaseController.
 *
 * @author  Julien Guyon <julienguyon@hotmail.com>
 */
abstract class BaseController extends AbstractController
{
    /**
     * @var EntityManagerInterface
     */
    protected EntityManagerInterface $entityManager;

    /**
     * @var TranslatorInterface
     */
    protected TranslatorInterface $translator;

    /**
     * @var RequestStack
     */
    protected RequestStack $requestStack;

    public function __construct(EntityManagerInterface $entityManager, TranslatorInterface $translator, RequestStack $requestStack)
    {
        $this->entityManager = $entityManager;
        $this->translator = $translator;
        $this->requestStack = $requestStack;
    }
}
