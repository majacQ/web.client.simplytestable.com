<?php
namespace SimplyTestable\WebClientBundle\Services;

use Guzzle\Http\Exception\BadResponseException;
use Guzzle\Http\Exception\CurlException;
use Guzzle\Http\Message\Request;
use Guzzle\Http\Message\Response;
use SimplyTestable\WebClientBundle\Exception\WebResourceException;
use SimplyTestable\WebClientBundle\Model\User;
use SimplyTestable\WebClientBundle\Model\User\Summary as UserSummary;
use SimplyTestable\WebClientBundle\Exception\CoreApplicationAdminRequestException;
use SimplyTestable\WebClientBundle\Model\Team\Invite;
use SimplyTestable\WebClientBundle\Model\Coupon;
use webignition\WebResource\JsonDocument\JsonDocument;

class UserService extends CoreApplicationService
{
    /**
     * @var UserSummary[]
     */
    private $summaries = array();

    /**
     * @var array
     */
    private $existsResultCache = [];

    /**
     * @var array
     */
    private $enabledResultsCache = [];

    /**
     * @var array
     */
    private $confirmationTokenCache = [];

    /**
     * @var HttpClientService
     */
    private $httpClientService;

    /**
     * @var CoreApplicationRouter
     */
    protected $coreApplicationRouter;

    /**
     * @var SystemUserService
     */
    private $systemUserService;

    /**
     * @param WebResourceService $webResourceService
     * @param CoreApplicationRouter $coreApplicationRouter
     * @param SystemUserService $systemUserService
     */
    public function __construct(
        WebResourceService $webResourceService,
        CoreApplicationRouter $coreApplicationRouter,
        SystemUserService $systemUserService
    ) {
        parent::__construct($webResourceService);

        $this->httpClientService = $webResourceService->getHttpClientService();
        $this->coreApplicationRouter = $coreApplicationRouter;
        $this->systemUserService = $systemUserService;
    }

    /**
     * @param string $token
     * @param string $password
     *
     * @return bool|int
     *
     * @throws CoreApplicationAdminRequestException
     */
    public function resetPassword($token, $password)
    {
        $requestUrl = $this->coreApplicationRouter->generate('user_reset_password', ['token' => $token]);

        $request = $this->httpClientService->postRequest($requestUrl, null, [
            'password' => rawurlencode($password)
        ]);

        $this->addAuthorisationToRequest($request);

        try {
            $request->send();
        } catch (BadResponseException $badResponseException) {
            if ($badResponseException->getResponse()->getStatusCode() == 401) {
                throw new CoreApplicationAdminRequestException('Invalid admin user credentials', 401);
            }

            return $badResponseException->getResponse()->getStatusCode();
        } catch (CurlException $curlException) {
            return $curlException->getErrorNo();
        }

        return true;
    }

    /**
     * @param string $password
     *
     * @return bool|int
     *
     * @throws CoreApplicationAdminRequestException
     */
    public function resetLoggedInUserPassword($password)
    {
        $token = $this->getConfirmationToken(parent::getUser()->getUsername());

        return $this->resetPassword($token, $password);
    }

    /**
     * @return bool
     */
    public function authenticate()
    {
        $user = parent::getUser();

        $requestUrl = $this->coreApplicationRouter->generate('user_authenticate', [
            'email' => $user->getUsername(),
        ]);

        $request = $this->httpClientService->getRequest($requestUrl);

        $this->addAuthorisationToRequest($request);

        try {
            $response = $request->send();
        } catch (BadResponseException $badResponseException) {
            $response = $badResponseException->getResponse();
        }

        return $response->isSuccessful();
    }

    /**
     * @param string $email
     * @param string $password
     * @param string $plan
     * @param Coupon|null $coupon
     *
     * @return bool|int|null
     *
     * @throws CoreApplicationAdminRequestException
     */
    public function create($email, $password, $plan, Coupon $coupon = null)
    {
        $requestData = [
            'email' => rawurlencode($email),
            'password' => rawurlencode($password),
            'plan' => $plan
        ];

        if (!is_null($coupon)) {
            $requestData['coupon'] = $coupon->getCode();
        }

        $request = $this->httpClientService->postRequest(
            $this->coreApplicationRouter->generate('user_create'),
            null,
            $requestData
        );

        $this->addAuthorisationToRequest($request);
        $request->getParams()->set('redirect.disable', true);

        try {
            $response = $request->send();
            return $response->getStatusCode() == 200 ? true : $response->getStatusCode();
        } catch (BadResponseException $badResponseException) {
            if ($badResponseException->getResponse()->getStatusCode() == 401) {
                throw new CoreApplicationAdminRequestException('Invalid admin user credentials', 401);
            }

            return $badResponseException->getResponse()->getStatusCode();
        } catch (CurlException $curlException) {
            return $curlException->getErrorNo();
        }
    }

    /**
     * @param string $token
     *
     * @return bool|int
     *
     * @throws CoreApplicationAdminRequestException
     */
    public function activate($token)
    {
        parent::setUser($this->systemUserService->getAdminUser());

        $request = $this->httpClientService->postRequest(
            $this->coreApplicationRouter->generate('user_activate', ['token' => $token])
        );

        $this->addAuthorisationToRequest($request);

        try {
            $response = $request->send();
        } catch (BadResponseException $badResponseException) {
            $response = $badResponseException->getResponse();
        } catch (CurlException $curlException) {
            return $curlException->getErrorNo();
        }

        parent::setUser(SystemUserService::getPublicUser());

        if ($response->getStatusCode() == 401) {
            throw new CoreApplicationAdminRequestException('Invalid admin user credentials', 401);
        }

        if ($response->getStatusCode() == 400) {
            return false;
        }

        return $response->getStatusCode() == 200 ? true : $response->getStatusCode();
    }

    /**
     * @param Invite $invite
     * @param string $password
     *
     * @return bool|int
     */
    public function activateAndAccept(Invite $invite, $password)
    {
        $request = $this->httpClientService->postRequest(
            $this->coreApplicationRouter->generate('teaminvite_activateandaccept'),
            null,
            [
                'token' => $invite->getToken(),
                'password' => rawurlencode($password)
            ]
        );

        try {
            $request->send();
        } catch (BadResponseException $badResponseException) {
            return $badResponseException->getResponse()->getStatusCode();
        } catch (CurlException $curlException) {
            return $curlException->getErrorNo();
        }

        return true;
    }

    /**
     * @param string $email
     * @return bool|null
     *
     * @throws CoreApplicationAdminRequestException
     */
    public function exists($email = null)
    {
        $email = empty($email)
            ? parent::getUser()->getUsername()
            : $email;

        if (!isset($this->existsResultCache[$email])) {
            $requestUrl = $this->coreApplicationRouter->generate('user_exists', [
                'email' => $email,
            ]);

            $existsResult = $this->getAdminBooleanResponse($this->httpClientService->postRequest($requestUrl));

            $this->existsResultCache[$email] = $existsResult;
        }

        return $this->existsResultCache[$email];
    }

    /**
     * @param Request $request
     *
     * @return bool
     *
     * @throws CoreApplicationAdminRequestException
     */
    private function getAdminBooleanResponse(Request $request)
    {
        return $this->getAdminResponse($request)->getStatusCode() === 200;
    }

    /**
     * @param Request $request
     *
     * @return Response|null
     *
     * @throws CoreApplicationAdminRequestException
     */
    protected function getAdminResponse(Request $request)
    {
        $currentUser = parent::getUser();

        parent::setUser($this->systemUserService->getAdminUser());
        $this->addAuthorisationToRequest($request);

        try {
            $response = $request->send();
        } catch (BadResponseException $badResponseException) {
            $response = $badResponseException->getResponse();
        }

        if (!is_null($currentUser)) {
            parent::setUser($currentUser);
        }

        if ($response->getStatusCode() == 401) {
            throw new CoreApplicationAdminRequestException('Invalid admin user credentials', 401);
        }

        return $response;
    }

    /**
     * @param string $email
     *
     * @return bool|null
     *
     * @throws CoreApplicationAdminRequestException
     */
    public function isEnabled($email = null)
    {
        if (!$this->exists($email)) {
            return false;
        }

        $email = (is_null($email)) ? parent::getUser()->getUsername() : $email;

        if (!isset($this->enabledResultsCache[$email])) {
            $requestUrl = $this->coreApplicationRouter->generate('user_is_enabled', [
                'email' => $email,
            ]);

            $existsResult = $this->getAdminBooleanResponse($this->httpClientService->postRequest($requestUrl));

            $this->enabledResultsCache[$email] = $existsResult;
        }

        return $this->enabledResultsCache[$email];
    }

    /**
     * @param string $email
     *
     * @return string
     *
     * @throws CoreApplicationAdminRequestException
     */
    public function getConfirmationToken($email)
    {
        if (!isset($this->confirmationTokenCache[$email])) {
            $requestUrl = $this->coreApplicationRouter->generate('user_get_token', [
                'email' => $email
            ]);

            $request = $this->httpClientService->getRequest($requestUrl);

            $this->confirmationTokenCache[$email] = json_decode($this->getAdminResponse($request)->getBody());
        }

        return $this->confirmationTokenCache[$email];
    }

    /**
     * @param User|null $user
     *
     * @return UserSummary
     *
     * @throws WebResourceException
     * @throws CurlException
     */
    public function getSummary(User $user = null)
    {
        if (is_null($user)) {
            $user = parent::getUser();
        }

        $username = $user->getUsername();

        if (!isset($this->summaries[$username])) {
            $request = $this->httpClientService->getRequest(
                $this->coreApplicationRouter->generate('user', [
                    'email' => $username,
                ])
            );

            $this->addAuthorisationToRequest($request);

            /* @var JsonDocument $jsonDocument */
            $jsonDocument = $this->webResourceService->get($request);

            $this->summaries[$username] = new UserSummary($jsonDocument->getContentObject());
        }

        return $this->summaries[$username];
    }
}
