<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Task\ValidateTaskUpdate;
use App\Services\Task\TaskService;
use App\Http\Resources\Task\TaskResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UnassignMember extends BaseController
{
    protected $service;

    public function __construct(TaskService $service)
    {
        $this->service = $service;
    }

    public function __invoke(Request $request, int $id): JsonResponse
    {
        $model = $this->service->unassignMember($id, $request->input('member'));

        return $this->successResponse(new TaskResource($model), 'Timers updated successfully.', Response::HTTP_OK);
    }
}
