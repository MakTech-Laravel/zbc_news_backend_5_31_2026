<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Authenticable\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Authenticable\LoginRequest;
use App\Http\Requests\Api\V1\Authenticable\LogoutRequest;
use App\Http\Requests\Api\V1\Authenticable\RegisterRequest;
use App\Http\Requests\Api\V1\Authenticable\ResetPasswordRequest;
use App\Http\Requests\Api\V1\Authenticable\TwoFactorChallengeRequest;
use App\Http\Requests\Api\V1\Authenticable\VerifyOtpRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Http\Resources\TokenResource;
use App\Models\User;
use App\Services\AuthOtpService;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class AuthenticableController extends Controller
{
    public function __construct(
        private readonly AuthOtpService $authOtpService,
        private readonly NotificationPreferenceService $notificationPreferenceService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->resolvedName(),
            'email' => strtolower($request->string('email')->toString()),
            'password' => Hash::make($request->string('password')->toString()),
            'slug' => User::generateUniqueSlug($request->resolvedName()),
            'email_verified_at' => now(),
        ]);

        $user->assignRole('user');

        $this->notificationPreferenceService->getOrCreate($user);

        $tokenResult = $user->createToken('auth_token');

        $payload = [
            'access_token' => $tokenResult->accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $tokenResult->token->expires_at,
            'user' => new UserResource($user),
        ];

        return sendResponse(
            true,
            'User registered successfully.',
            $payload,
            HttpStatus::HTTP_CREATED,
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
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

        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

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
                'user' => new UserResource($user),
            ],
            HttpStatus::HTTP_OK,
        );
    }

    public function twoFactorChallenge(TwoFactorChallengeRequest $request): JsonResponse
    {
        $attemptToken = session()->get($request->attempt_token);
        if (! $attemptToken) {
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

        if (! $user->validateTwoFactorCode($request->code)) {
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

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $email = strtolower($request->string('email')->toString());
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            return sendResponse(
                false,
                'No account found with this email address.',
                null,
                HttpStatus::HTTP_NOT_FOUND,
            );
        }

        $otp = $this->authOtpService->issue($email, AuthOtpService::PURPOSE_PASSWORD_RESET);

        $payload = null;
        if (config('app.debug')) {
            $payload = ['otp' => $otp, 'verification_code' => $otp];
        }

        return sendResponse(
            true,
            'A reset code has been sent to your email.',
            $payload,
            HttpStatus::HTTP_OK,
        );
    }

    public function resendOtp(ForgotPasswordRequest $request): JsonResponse
    {
        $email = strtolower($request->string('email')->toString());
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            return sendResponse(
                true,
                'If that email exists, a verification code has been sent.',
                null,
                HttpStatus::HTTP_OK,
            );
        }

        $purpose = $user->email_verified_at
            ? AuthOtpService::PURPOSE_PASSWORD_RESET
            : AuthOtpService::PURPOSE_REGISTER;

        $otp = $this->authOtpService->issue($email, $purpose);

        $payload = null;
        if (config('app.debug')) {
            $payload = ['otp' => $otp, 'verification_code' => $otp];
        }

        return sendResponse(
            true,
            'Verification code sent.',
            $payload,
            HttpStatus::HTTP_OK,
        );
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $email = strtolower($request->string('email')->toString());
        $otp = $request->otpCode();

        if (! $this->authOtpService->verify($email, AuthOtpService::PURPOSE_REGISTER, $otp)) {
            return sendResponse(
                false,
                'Invalid or expired verification code.',
                null,
                HttpStatus::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $user = User::query()->where('email', $email)->first();
        if (! $user) {
            return sendResponse(
                false,
                'User not found.',
                null,
                HttpStatus::HTTP_NOT_FOUND,
            );
        }

        $user->forceFill(['email_verified_at' => now()])->save();
        $user->load(['roles', 'permissions']);

        return sendResponse(
            true,
            'Email verified successfully.',
            new UserResource($user),
            HttpStatus::HTTP_OK,
        );
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $email = strtolower($request->string('email')->toString());
        $otp = $request->otpCode();

        if (! $this->authOtpService->verify($email, AuthOtpService::PURPOSE_PASSWORD_RESET, $otp)) {
            return sendResponse(
                false,
                'Invalid or expired reset code.',
                null,
                HttpStatus::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $user = User::query()->where('email', $email)->first();
        if (! $user) {
            return sendResponse(
                false,
                'User not found.',
                null,
                HttpStatus::HTTP_NOT_FOUND,
            );
        }

        $user->forceFill([
            'password' => Hash::make($request->string('password')->toString()),
        ])->save();

        $user->tokens()->delete();

        return sendResponse(
            true,
            'Password reset successfully. You can now sign in with your new password.',
            null,
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
