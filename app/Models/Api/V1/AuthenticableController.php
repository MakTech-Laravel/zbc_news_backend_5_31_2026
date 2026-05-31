<?php

namespace App\Models\Api\V1;

use App\Http\Requests\Api\V1\Authenticable\LoginRequest;
use App\Http\Resources\TokenResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class AuthenticableController extends Model
{
    //

    public function login(LoginRequest $request): JsonResponse
    {


        if (!Auth::attempt($request->only('email', 'password'))) {
            return sendResponse(
                false,
                'Invalid credentials',
                null,
                HttpStatus::HTTP_UNAUTHORIZED,
            );
        }

        $user = User::where('email', $request->email)->first();
        $tokenResult = $user->createToken('auth_token');

        return sendResponse(
            true,
            'Login successful',
            new TokenResource($tokenResult),
            HttpStatus::HTTP_OK,
        );
    }
}
