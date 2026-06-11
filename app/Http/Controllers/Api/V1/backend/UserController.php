<?php

namespace App\Http\Controllers\Api\V1\backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\v1\UserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\TwoFactorAuthenticationProvider;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService
    ) {}


    public function twoFactorEnable(Request $request): JsonResponse
    {
        $user = $request->user();


        app(EnableTwoFactorAuthentication::class)($user);


        $user->refresh();


        $qrSvg = $user->twoFactorQrCodeSvg();



        $secret = decrypt($user->two_factor_secret);

        return sendResponse(
            true,
            '2FA enabled successfully',
            [
                'secret' => $secret,
                'qr_svg' => $qrSvg,
            ],
            HttpStatus::HTTP_OK,
        );
    }

    public function index()
    {
        $users = $this->userService->getAllUsers();

        return sendResponse(
            true,
            'Users retrieved successfully',
            UserResource::collection($users),
            HttpStatus::HTTP_OK,
        );
    }

    public function store(UserRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar');
        }
        $user = $this->userService->create($data);

        return sendResponse(
            true,
            'User created successfully',
            new UserResource($user),
            HttpStatus::HTTP_CREATED,
        );
    }

    public function update(UserRequest $request, $id): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar');
        }

        $user = $this->userService->updateUser($id, $data);

        return sendResponse(
            true,
            'User updated successfully',
            new UserResource($user),
            HttpStatus::HTTP_OK,
        );
    }

    public function show($id): JsonResponse
    {
        $user = $this->userService->getUserById($id);

        return sendResponse(
            true,
            'User retrieved successfully',
            new UserResource($user),
            HttpStatus::HTTP_OK,
        );
    }

    public function destroy($id): JsonResponse
    {
        try {
            $this->userService->deleteUser($id);

            return sendResponse(
                true,
                'User deleted successfully',
                null,
                HttpStatus::HTTP_OK,
            );
        } catch (\Exception $e) {
            return sendResponse(
                false,
                $e->getMessage(),
                null,
                $e->getCode() ?: HttpStatus::HTTP_FORBIDDEN,
            );
        }
    }

    public function articleActivities(int $userId)
    {
        $activities = $this->userService->getUserArticleActivities($userId);

        return sendResponse(
            true,
            'User article activities retrieved successfully',
            $activities,
            HttpStatus::HTTP_OK,
        );
    }
}
