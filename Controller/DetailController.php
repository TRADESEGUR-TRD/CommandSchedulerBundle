<?php

namespace JMose\CommandSchedulerBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use JMose\CommandSchedulerBundle\Entity\ScheduledCommand;
use JMose\CommandSchedulerBundle\Form\Type\ScheduledCommandType;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class DetailController.
 *
 * @author  Julien Guyon <julienguyon@hotmail.com>
 */
class DetailController extends BaseController
{
    public function __construct(EntityManagerInterface $entityManager, TranslatorInterface $translator, RequestStack $requestStack)
    {
        parent::__construct($entityManager, $translator, $requestStack);
    }

    /**
     * Handle display of new/existing ScheduledCommand object.
     * This action should not be invoked directly.
     *
     * @param ScheduledCommand $scheduledCommand
     * @param Form|null $scheduledCommandForm
     *
     * @return Response
     */
    public function indexAction(ScheduledCommand $scheduledCommand, Form $scheduledCommandForm = null): ?Response
    {
        if (null === $scheduledCommandForm) {
            $scheduledCommandForm = $this->createForm(ScheduledCommandType::class, $scheduledCommand);
        }

        return $this->render(
            '@JMoseCommandScheduler/Detail/index.html.twig',
            [
                'scheduledCommandForm' => $scheduledCommandForm->createView(),
            ]
        );
    }

    /**
     * Initialize a new ScheduledCommand object and forward to the index action (view).
     *
     * @return Response
     */
    public function initNewScheduledCommandAction(): ?Response
    {
        $scheduledCommand = new ScheduledCommand();

        return $this->forward(
            self::class.'::indexAction',
            [
                'scheduledCommand' => $scheduledCommand,
            ]
        );
    }

    /**
     * Get a ScheduledCommand object with its id and forward it to the index action (view).
     *
     * @param $scheduledCommandId
     *
     * @return Response
     */
    public function initEditScheduledCommandAction($scheduledCommandId): ?Response
    {
        $scheduledCommand = $this->entityManager->getRepository(ScheduledCommand::class)
            ->find($scheduledCommandId);

        return $this->forward(
            self::class.'::indexAction',
            [
                'scheduledCommand' => $scheduledCommand,
            ]
        );
    }

    /**
     * Handle save after form is submit and forward to the index action (view).
     *
     * @param Request $request
     *
     * @return Response
     */
    public function saveAction(Request $request): ?Response
    {
        // Init and populate form object
        $commandDetail = $request->request->get('command_scheduler_detail');
        if ('' != $commandDetail['id']) {
            $scheduledCommand = $this->entityManager->getRepository(ScheduledCommand::class)
                ->find($commandDetail['id']);
        } else {
            $scheduledCommand = new ScheduledCommand();
        }

        $scheduledCommandForm = $this->createForm(ScheduledCommandType::class, $scheduledCommand);
        $scheduledCommandForm->handleRequest($request);

        if ($scheduledCommandForm->isSubmitted() && $scheduledCommandForm->isValid()) {
            // Handle save to the database
            if (null === $scheduledCommand->getId()) {
                $this->entityManager->persist($scheduledCommand);
            }
            $this->entityManager->flush();

            // Add a flash message and do a redirect to the list
            $this->requestStack->getSession()->getFlashBag()
                ->add('success', $this->translator->trans('flash.success', [], 'JMoseCommandScheduler'));

            return $this->redirectToRoute('jmose_command_scheduler_list');
        }

        // Redirect to indexAction with the form object that has validation errors
        return $this->forward(
            self::class.'::indexAction',
            [
                'scheduledCommand' => $scheduledCommand,
                'scheduledCommandForm' => $scheduledCommandForm,
            ]
        );
    }
}
