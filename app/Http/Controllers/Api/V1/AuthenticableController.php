<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Authenticable\LoginRequest;
use App\Http\Requests\Api\V1\Authenticable\LogoutRequest;
use App\Http\Requests\Api\V1\Authenticable\RegisterRequest;
use App\Http\Requests\Api\V1\Authenticable\TwoFactorChallengeRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Http\Resources\TokenResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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

        $user->load(['roles', 'permissions']);
        if ($user->two_factor_secret && $user->two_factor_confirmed_at) {
            $attemptToken = Str::random(60);
            session()->put($attemptToken, ['user_id' => $user->id, 'expires_at' => now()->addMinutes(5)]);

            return sendResponse(
                false,
                'Two factor authentication required',
                [
                    'requires_2fa' => true,
                    'attempt_token' => $attemptToken,
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
            [
                'access_token' => $tokenResult->accessToken,
                'token_type' => 'Bearer',
                'expires_in' => $tokenResult->token->expires_at,
                'user' => new UserResource($user)
            ],
            HttpStatus::HTTP_OK,
        );
    }

    public function twoFactorChallenge(TwoFactorChallengeRequest $request): JsonResponse
    {
        $attemptToken = session()->get($request->attempt_token);
        if (!$attemptToken) {
            return sendResponse(
                false,
                'Invalid attempt token',
                null,
                HttpStatus::HTTP_UNAUTHORIZED,
            );
        }
        if ($attemptToken['expires_at'] < now()) {
            session()->forget($request->attempt_token);
            return sendResponse(
                false,
                'Time Expired.',
                null,
                HttpStatus::HTTP_REQUEST_TIMEOUT,
            );
        }
        $user = User::find($attemptToken['user_id']);

        if (!$user->validateTwoFactorCode($request->code)) {
            return sendResponse(
                false,
                'Invalid code',
                null,
                HttpStatus::HTTP_UNAUTHORIZED,
            );
        }

        $tokenResult = $user->createToken('auth_token');

        activity()
            ->performedOn(new User())
            ->causedBy($user)
            ->withProperties(['email' => $request->email, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()])
            ->log('Login successful');
        session()->forget($request->attempt_token);

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
