<?php

namespace JMose\CommandSchedulerBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use JMose\CommandSchedulerBundle\Entity\ScheduledCommand;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class ListController.
 *
 * @author  Julien Guyon <julienguyon@hotmail.com>
 */
class ListController extends BaseController
{
    /**
     * @var string
     */
    private string $lockTimeout;

    public function __construct(EntityManagerInterface $entityManager, TranslatorInterface $translator, RequestStack $requestStack)
    {
        parent::__construct($entityManager, $translator, $requestStack);
    }

    /**
     * @param $lockTimeout string
     */
    public function setLockTimeout(string $lockTimeout): void
    {
        $this->lockTimeout = $lockTimeout;
    }

    /**
     * @return Response
     */
    public function indexAction(): ?Response
    {
        $scheduledCommands = $this->entityManager->getRepository(
            'JMoseCommandSchedulerBundle:ScheduledCommand'
        )->findAll();

        return $this->render(
            '@JMoseCommandScheduler/List/index.html.twig',
            ['scheduledCommands' => $scheduledCommands]
        );
    }

    /**
     * @param $id
     *
     * @return Response
     */
    public function removeAction($id): ?Response
    {
        $scheduledCommand = $this->entityManager->getRepository(ScheduledCommand::class)->find($id);

        $this->entityManager->remove($scheduledCommand);
        $this->entityManager->flush();

        // Add a flash message and do a redirect to the list
        $this->requestStack->getSession()->getFlashBag()
            ->add('success', $this->translator->trans('flash.deleted', [], 'JMoseCommandScheduler'));

        return $this->redirectToRoute('jmose_command_scheduler_list');
    }

    /**
     * @param $id
     *
     * @return Response
     */
    public function toggleAction($id): ?Response
    {
        $scheduledCommand =  $this->entityManager->getRepository(ScheduledCommand::class)->find($id);
        $scheduledCommand->setDisabled(!$scheduledCommand->isDisabled());
        $this->entityManager->flush();

        return $this->redirectToRoute('jmose_command_scheduler_list');
    }

    /**
     * @param $id
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function executeAction($id, Request $request): ?Response
    {
        $scheduledCommand =  $this->entityManager->getRepository(ScheduledCommand::class)->find($id);
        $scheduledCommand->setExecuteImmediately(true);
        $this->entityManager->flush();

        // Add a flash message and do a redirect to the list
        $this->requestStack->getSession()->getFlashBag()
            ->add('success', $this->translator->trans('flash.execute', [], 'JMoseCommandScheduler'));

        if ($request->query->has('referer')) {
            return $this->redirect($request->getSchemeAndHttpHost().urldecode($request->query->get('referer')));
        }

        return $this->redirectToRoute('jmose_command_scheduler_list');
    }

    /**
     * @param $id
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function unlockAction($id, Request $request): ?Response
    {
        $scheduledCommand =  $this->entityManager->getRepository(ScheduledCommand::class)->find($id);
        $scheduledCommand->setLocked(false);
        $this->entityManager->flush();

        // Add a flash message and do a redirect to the list
        $this->requestStack->getSession()->getFlashBag()
            ->add('success', $this->translator->trans('flash.unlocked', [], 'JMoseCommandScheduler'));

        if ($request->query->has('referer')) {
            return $this->redirect($request->getSchemeAndHttpHost().urldecode($request->query->get('referer')));
        }

        return $this->redirectToRoute('jmose_command_scheduler_list');
    }

    /**
     * method checks if there are jobs which are enabled but did not return 0 on last execution or are locked.<br>
     * if a match is found, HTTP status 417 is sent along with an array which contains name, return code and locked-state.
     * if no matches found, HTTP status 200 is sent with an empty array.
     *
     * @return JsonResponse
     */
    public function monitorAction(): ?JsonResponse
    {
        $failedCommands = $this->entityManager
            ->getRepository(ScheduledCommand::class)
            ->findFailedAndTimeoutCommands($this->lockTimeout);

        $jsonArray = [];
        foreach ($failedCommands as $command) {
            $jsonArray[$command->getName()] = [
                'LAST_RETURN_CODE' => $command->getLastReturnCode(),
                'B_LOCKED' => $command->getLocked() ? 'true' : 'false',
                'DH_LAST_EXECUTION' => $command->getLastExecution(),
            ];
        }

        $response = new JsonResponse();
        $response->setContent(json_encode($jsonArray));
        $response->setStatusCode(count($jsonArray) > 0 ? Response::HTTP_EXPECTATION_FAILED : Response::HTTP_OK);

        return $response;
    }
}
