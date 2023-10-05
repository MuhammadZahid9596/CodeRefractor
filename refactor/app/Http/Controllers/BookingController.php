<?php

namespace DTApi\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Http\Requests;
use DTApi\Models\Distance;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;
use Illuminate\Http\JsonResponse;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{

    /**
     * @var BookingRepository
     */
    protected $repository;

    /**
     * BookingController constructor.
     * @param BookingRepository $bookingRepository
     */
    public function __construct(BookingRepository $bookingRepository)
    {
        $this->repository = $bookingRepository;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        $user = $request->__authenticatedUser;

        if ($user_id = $request->user_id) {
            $response = $this->repository->getUsersJobs($user_id);
        } elseif ($this->isAdminOrSuperadmin($user)) {
            $response = $this->repository->getAll($request);
        } else {
            // Handle other cases or return an error response if needed
            $response = ['message' => 'Unauthorized'];
        }

        return response($response);
    }

    protected function isAdminOrSuperadmin($user)
    {
        $users = Users::whereIn('role',['admin','super_admin'])->get();
        return in_array($user->user_type, $users);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function show($id)
    {
        $job = $this->repository->with('translatorJobRel.user')->findOrFail($id);
        return new JsonResponse($job);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            // Define your validation rules here.
        ]);
    
        $user = $request->user();
    
        // Use a try-catch block to handle exceptions and provide appropriate responses.
        try {
            // Call the repository's store method and pass in the user and validated data.
            $response = $this->repository->store($user, $validatedData);
            
            // Return a success JSON response with a 201 (Created) status code.
            return new JsonResponse($response, 201);
        } catch (\Exception $e) {
            // Handle any exceptions that may occur during the store operation.
            // You can log the error, return a custom error message, or use a 500 status code.
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function update($id, Request $request)
    {
        $data = $request->all();
        $cuser = $request->__authenticatedUser;
        $response = $this->repository->updateJob($id, array_except($data, ['_token', 'submit']), $cuser);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function immediateJobEmail(Request $request)
    {
        $adminSenderEmail = config('app.adminemail');
        $data = $request->all();

        $response = $this->repository->storeJobEmail($data);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getHistory(Request $request)
    {
        $user_id = $request->input('user_id');

        if (!$user_id) {
            // If 'user_id' is not provided in the request, return a 400 (Bad Request) response.
            return new JsonResponse(['error' => 'Missing user_id parameter.'], 400);
        }

        try {
            $response = $this->repository->getUsersJobsHistory($user_id, $request);
            // Check if the response is empty, and return a 404 (Not Found) response if needed.
            if (empty($response)) {
                return new JsonResponse(['error' => 'No job history found for the specified user.'], 404);
            }
            // Return a successful JSON response with the job history data.
            return new JsonResponse($response);
        } catch (\Exception $e) {
            // Handle any exceptions that may occur during the retrieval process.
            // You can log the error, return a custom error message, or use a 500 status code.
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function acceptJob(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->acceptJob($data, $user);

        return response($response);
    }

    public function acceptJobWithId(Request $request)
    {
        $data = $request->get('job_id');
        $user = $request->__authenticatedUser;

        $response = $this->repository->acceptJobWithId($data, $user);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function cancelJob(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->cancelJobAjax($data, $user);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function endJob(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->endJob($data);

        return response($response);

    }

    public function customerNotCall(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->customerNotCall($data);

        return response($response);

    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getPotentialJobs(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->getPotentialJobs($user);

        return response($response);
    }

    public function distanceFeed(Request $request)
    {
        $data = $request->all();
        $jobid = $data['jobid'] ?? null;

        $updates = [];

        // Check for distance and time updates.
        if (isset($data['distance'])) {
            $updates['distance'] = $data['distance'];
        }

        if (isset($data['time'])) {
            $updates['time'] = $data['time'];
        }

        // Check for flagged, manually_handled, and by_admin updates.
        $updates['flagged'] = $data['flagged'] === 'true' ? 'yes' : 'no';
        $updates['manually_handled'] = $data['manually_handled'] === 'true' ? 'yes' : 'no';
        $updates['by_admin'] = $data['by_admin'] === 'true' ? 'yes' : 'no';

        // Check for admincomment and session_time updates.
        $updates['admin_comments'] = isset($data['admincomment']) ? $data['admincomment'] : '';
        $updates['session_time'] = isset($data['session_time']) ? $data['session_time'] : '';

        // Check if flagged is 'true' and admincomment is empty.
        if ($updates['flagged'] === 'yes' && empty($updates['admin_comments'])) {
            return new JsonResponse(['error' => 'Please, add comment'], 400);
        }

        // Update the Distance and Job models with the merged updates if jobid is provided.
        if ($jobid) {
            $affectedRows = Distance::where('job_id', '=', $jobid)->update($updates);
            $affectedRows1 = Job::where('id', '=', $jobid)->update($updates);
        }

        return new JsonResponse(['message' => 'Record updated!']);
    }

    public function reopen(Request $request)
    {
        $data = $request->all();
        $response = $this->repository->reopen($data);

        return response($response);
    }

    public function resendNotifications(Request $request)
    {
        $data = $request->all();
        $jobid = $data['jobid'] ?? null;
    
        if (!$jobid) {
            return new JsonResponse(['error' => 'Missing jobid parameter.'], 400);
        }
    
        // Use try-catch to handle potential exceptions when finding the job.
        try {
            $job = $this->repository->find($jobid);
            if (!$job) {
                return new JsonResponse(['error' => 'Job not found.'], 404);
            }
    
            $job_data = $this->repository->jobToData($job);
            $this->repository->sendNotificationTranslator($job, $job_data, '*');
    
            return new JsonResponse(['success' => 'Push sent']);
        } catch (\Exception $e) {
            // Handle any exceptions that may occur during the process.
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Sends SMS to Translator
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function resendSMSNotifications(Request $request)
    {
        $data = $request->all();
        $jobid = $data['jobid'] ?? null;

        if (!$jobid) {
            return new JsonResponse(['error' => 'Missing jobid parameter.'], 400);
        }

        // Use try-catch to handle potential exceptions when finding the job.
        try {
            $job = $this->repository->find($jobid);
            if (!$job) {
                return new JsonResponse(['error' => 'Job not found.'], 404);
            }

            $job_data = $this->repository->jobToData($job);
            $this->repository->sendSMSNotificationToTranslator($job, $job_data, '*');

            return new JsonResponse(['success' => 'SMS sent']);
        } catch (\Exception $e) {
            // Handle any exceptions that may occur during the process.
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

}
