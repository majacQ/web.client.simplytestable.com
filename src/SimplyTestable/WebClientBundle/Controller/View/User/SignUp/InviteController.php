<?php

namespace SimplyTestable\WebClientBundle\Controller\View\User\SignUp;

use SimplyTestable\WebClientBundle\Controller\BaseViewController;
use SimplyTestable\WebClientBundle\Exception\InvalidAdminCredentialsException;
use SimplyTestable\WebClientBundle\Exception\InvalidContentTypeException;
use SimplyTestable\WebClientBundle\Interfaces\Controller\IEFiltered;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use SimplyTestable\WebClientBundle\Controller\Action\SignUp\Team\InviteController as ActionInviteController;

class InviteController extends BaseViewController implements IEFiltered
{
    /**
     * @param Request $request
     * @param string $token
     *
     * @return Response
     *
     * @throws InvalidAdminCredentialsException
     * @throws InvalidContentTypeException
     */
    public function indexAction(Request $request, $token)
    {
        $teamInviteService = $this->container->get('simplytestable.services.teaminviteservice');
        $flashBagValuesService = $this->container->get('simplytestable.services.flashbagvalues');
        $cacheValidatorService = $this->container->get('simplytestable.services.cachevalidator');
        $templating = $this->container->get('templating');

        $staySignedIn = $request->query->get('stay-signed-in');

        $invite = $teamInviteService->getForToken($token);
        $flashBagValues = $flashBagValuesService->get([
            ActionInviteController::FLASH_BAG_INVITE_ACCEPT_ERROR_KEY,
            ActionInviteController::FLASH_BAG_INVITE_ACCEPT_FAILURE_KEY,
        ]);

        $response = $cacheValidatorService->createResponse($request, array_merge(
            [
                'token' => $token,
                'invite' => json_encode($invite),
                'has_invite' => json_encode(!empty($invite)),
                'stay_signed_in' => $staySignedIn
            ],
            $flashBagValues
        ));

        if ($cacheValidatorService->isNotModified($response)) {
            return $response;
        }

        $viewData = array_merge(
            [
                'token' => $token,
                'invite' => $invite,
                'has_invite' => !empty($invite),
                'stay_signed_in' => $staySignedIn,
            ],
            $flashBagValues
        );

        $content = $templating->render(
            'SimplyTestableWebClientBundle:bs3/User/SignUp/Invite:index.html.twig',
            array_merge($this->getDefaultViewParameters(), $viewData)
        );

        $response->setContent($content);

        return $response;
    }
}
