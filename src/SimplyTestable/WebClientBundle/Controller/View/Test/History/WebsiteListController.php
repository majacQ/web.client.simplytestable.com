<?php

namespace SimplyTestable\WebClientBundle\Controller\View\Test\History;

use SimplyTestable\WebClientBundle\Controller\BaseViewController;
use SimplyTestable\WebClientBundle\Interfaces\Controller\RequiresValidUser;
use Symfony\Component\HttpFoundation\JsonResponse;

class WebsiteListController extends BaseViewController implements RequiresValidUser
{
    /**
     * @return JsonResponse
     */
    public function indexAction()
    {
        $remoteTestService = $this->container->get('simplytestable.services.remotetestservice');
        $userService = $this->container->get('simplytestable.services.userservice');

        $user = $userService->getUser();

        $remoteTestService->setUser($user);
        $finishedWebsites = $remoteTestService->getFinishedWebsites();

        return new JsonResponse($finishedWebsites);
    }
}
