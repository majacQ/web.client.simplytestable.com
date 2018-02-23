<?php

namespace SimplyTestable\WebClientBundle\Controller;

use SimplyTestable\WebClientBundle\Exception\CoreApplicationRequestException;
use SimplyTestable\WebClientBundle\Exception\InvalidContentTypeException;
use SimplyTestable\WebClientBundle\Exception\InvalidCredentialsException;
use SimplyTestable\WebClientBundle\Interfaces\Controller\Test\RequiresValidOwner;
use SimplyTestable\WebClientBundle\Services\TaskService;
use SimplyTestable\WebClientBundle\Services\TestService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TaskController extends Controller implements RequiresValidOwner
{
    const DEFAULT_UNRETRIEVED_TASKID_LIMIT = 100;
    const MAX_UNRETRIEVED_TASKID_LIMIT = 1000;

    /**
     * @var Response|JsonResponse
     */
    private $response;

    /**
     * @param Response $response
     */
    public function setResponse(Response $response)
    {
        $this->response = $response;
    }

    /**
     * @return bool
     */
    public function hasResponse()
    {
        return !empty($this->response);
    }

    /**
     * {@inheritdoc}
     */
    public function getInvalidOwnerResponse(Request $request)
    {
        return new JsonResponse();
    }

    /**
     * @param string $website
     * @param int $test_id
     *
     * @return JsonResponse
     *
     * @throws CoreApplicationRequestException
     * @throws InvalidContentTypeException
     * @throws InvalidCredentialsException
     */
    public function idCollectionAction($website, $test_id)
    {
        if ($this->hasResponse()) {
            return $this->response;
        }

        $testService = $this->container->get(TestService::class);
        $taskService = $this->container->get(TaskService::class);

        $test = $testService->get($website, $test_id);

        $taskIds = $taskService->getRemoteTaskIds($test);

        return new JsonResponse($taskIds);
    }

    /**
     * @param string $website
     * @param int $test_id
     * @param int|null $limit
     *
     * @return JsonResponse
     *
     * @throws CoreApplicationRequestException
     * @throws InvalidContentTypeException
     * @throws InvalidCredentialsException
     */
    public function unretrievedIdCollectionAction($website, $test_id, $limit = null)
    {
        if ($this->hasResponse()) {
            return $this->response;
        }

        $testService = $this->container->get(TestService::class);
        $taskService = $this->container->get(TaskService::class);

        $test = $testService->get($website, $test_id);

        $limit = filter_var($limit, FILTER_VALIDATE_INT);
        if (false === $limit) {
            $limit = self::DEFAULT_UNRETRIEVED_TASKID_LIMIT;
        }

        if ($limit > self::MAX_UNRETRIEVED_TASKID_LIMIT) {
            $limit = self::MAX_UNRETRIEVED_TASKID_LIMIT;
        }

        $taskIds = $taskService->getUnretrievedRemoteTaskIds($test, $limit);

        return new JsonResponse($taskIds);
    }

    /**
     * @param Request $request
     * @param string $website
     * @param int $test_id
     *
     * @return Response
     *
     * @throws CoreApplicationRequestException
     * @throws InvalidContentTypeException
     * @throws InvalidCredentialsException
     */
    public function retrieveAction(Request $request, $website, $test_id)
    {
        if ($this->hasResponse()) {
            return $this->response;
        }

        $testService = $this->container->get(TestService::class);
        $taskService = $this->container->get(TaskService::class);

        $test = $testService->get($website, $test_id);

        $taskService->getCollection($test, $this->getRequestRemoteTaskIds($request));

        return new Response();
    }

    /**
     * @param Request $request
     *
     * @return array|null
     */
    private function getRequestRemoteTaskIds(Request $request)
    {
        $requestTaskIds = $request->request->get('remoteTaskIds');

        $taskIds = [];

        if (substr_count($requestTaskIds, ':')) {
            $rangeLimits = explode(':', $requestTaskIds);
            $start = (int)$rangeLimits[0];
            $end = (int)$rangeLimits[1];

            for ($i = $start; $i <= $end; $i++) {
                $taskIds[] = $i;
            }
        } else {
            $rawRequestTaskIds = explode(',', $requestTaskIds);

            foreach ($rawRequestTaskIds as $requestTaskId) {
                if (ctype_digit($requestTaskId)) {
                    $taskIds[] = (int)$requestTaskId;
                }
            }
        }

        return (count($taskIds) > 0) ? $taskIds : null;
    }
}
