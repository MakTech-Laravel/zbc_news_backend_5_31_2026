<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Authenticable\LoginRequest;
use App\Http\Requests\Api\V1\Authenticable\LogoutRequest;
use App\Http\Requests\Api\V1\Authenticable\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Http\Resources\TokenResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class AuthenticableController extends Model
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->assignRole('user');

        $tokenResult = $user->createToken('auth_token');

        return sendResponse(
            true,
            'User registered successfully',
            new TokenResource($tokenResult),
            HttpStatus::HTTP_CREATED,
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (!Auth::attempt($request->only('email', 'password'))) {

            activity()
                ->performedOn(new User())
                ->causedBy($request->user())
                ->withProperties(['email' => $request->email, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()])
                ->log('Login failed');

            return sendResponse(
                false,
                'Invalid credentials',
                null,
                HttpStatus::HTTP_UNAUTHORIZED,
            );

        }

        $user = User::where('email', $request->email)->first();

        if($user->two_factor_secret && $user->two_factor_confirmed_at) {

            return sendResponse(
                false,
                'Two factor authentication required',
                [
                    'requires_2fa' => true,
                    'user' => new UserResource($user),
                ],
                HttpStatus::HTTP_UNAUTHORIZED,
            );
        }


        $tokenResult = $user->createToken('auth_token');

        activity()
            ->performedOn(new User())
            ->causedBy($user)
            ->withProperties(['email' => $request->email, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()])
            ->log('Login successful');

        return sendResponse(
            true,
            'Login successful',
            new TokenResource($tokenResult),
            HttpStatus::HTTP_OK,
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return sendResponse(
            true,
            'Logout successful',
            null,
            HttpStatus::HTTP_OK,
        );
    }
}
