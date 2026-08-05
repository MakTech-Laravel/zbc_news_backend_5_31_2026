<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Authenticable\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Authenticable\LoginRequest;
use App\Http\Requests\Api\V1\Authenticable\RegisterRequest;
use App\Http\Requests\Api\V1\Authenticable\ResendOtpRequest;
use App\Http\Requests\Api\V1\Authenticable\ResetPasswordRequest;
use App\Http\Requests\Api\V1\Authenticable\TwoFactorChallengeRequest;
use App\Http\Requests\Api\V1\Authenticable\VerifyOtpRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Http\Resources\TokenResource;
use App\Models\User;
use App\Services\AuthOtpService;
use App\Services\LoginSecurityAlertService;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class AuthenticableController extends Controller
{
    private const REGISTRATION_GENERIC_MESSAGE = 'Registration successful. Please check your email for a verification code.';

    private const FORGOT_PASSWORD_GENERIC_MESSAGE = 'If an account exists for that email, a reset code has been sent.';

    public function __construct(
        private readonly AuthOtpService $authOtpService,
        private readonly LoginSecurityAlertService $loginSecurityAlertService,
        private readonly NotificationPreferenceService $notificationPreferenceService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $email = strtolower($request->string('email')->toString());
        $existing = User::query()->where('email', $email)->first();

        if ($existing) {
            if ($existing->isPermanentlyDeleted()) {
                return sendResponse(
                    false,
                    'Unable to register with this email.',
                    null,
                    HttpStatus::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            if ($existing->isPendingDeletion()) {
                return sendResponse(
                    false,
                    'This email belongs to an account scheduled for deletion. Cancel the deletion request from your email first, or contact support.',
                    [
                        'account_pending_deletion' => true,
                    ],
                    HttpStatus::HTTP_UNPROCESSABLE_ENTITY,
                );
            }

            if (! $existing->email_verified_at) {
                $this->authOtpService->issue($email, AuthOtpService::PURPOSE_REGISTER);
            }

            return sendResponse(
                true,
                self::REGISTRATION_GENERIC_MESSAGE,
                [
                    'requires_email_verification' => true,
                    'email' => $email,
                ],
                HttpStatus::HTTP_OK,
            );
        }

        $acceptedAt = now();

        $user = User::create([
            'name' => $request->resolvedName(),
            'email' => $email,
            'password' => Hash::make($request->string('password')->toString()),
            'slug' => User::generateUniqueSlug($request->resolvedName()),
            'email_verified_at' => null,
            'terms_accepted_at' => $acceptedAt,
            'privacy_accepted_at' => $acceptedAt,
        ]);

        $user->assignRole('user');
        $this->notificationPreferenceService->getOrCreate($user);
        $this->authOtpService->issue($email, AuthOtpService::PURPOSE_REGISTER);

        return sendResponse(
            true,
            self::REGISTRATION_GENERIC_MESSAGE,
            [
                'requires_email_verification' => true,
                'email' => $email,
            ],
            HttpStatus::HTTP_OK,
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $email = strtolower(trim($request->string('email')->toString()));

        if (! Auth::guard('web')->attempt([
            'email' => $email,
            'password' => $request->string('password')->toString(),
        ])) {
            activity()
                ->performedOn(new User())
                ->causedBy($request->user())
                ->withProperties(['email' => $email, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()])
                ->log('Login failed');

            try {
                $this->loginSecurityAlertService->recordFailure(
                    $email,
                    $request->ip(),
                    $request->userAgent(),
                );
            } catch (\Throwable $exception) {
                report($exception);
            }

            return sendResponse(
                false,
                'Invalid credentials',
                null,
                HttpStatus::HTTP_UNAUTHORIZED,
            );
        }

        try {
            $this->loginSecurityAlertService->clear($email);
        } catch (\Throwable $exception) {
            report($exception);
        }

        $user = User::where('email', $email)->first();

        if ($user?->isPermanentlyDeleted()) {
            Auth::guard('web')->logout();

            return sendResponse(
                false,
                'This account has been permanently deleted and cannot be used.',
                null,
                HttpStatus::HTTP_FORBIDDEN,
            );
        }

        if ($user?->isPendingDeletion()) {
            Auth::guard('web')->logout();

            return sendResponse(
                false,
                'This account is scheduled for deletion. Check your email for instructions to cancel the request before the final deletion date.',
                [
                    'account_pending_deletion' => true,
                    'scheduled_permanent_deletion_at' => optional($user->scheduled_permanent_deletion_at)?->toIso8601String(),
                ],
                HttpStatus::HTTP_FORBIDDEN,
            );
        }

        if (! $user->email_verified_at) {
            $this->authOtpService->issue(
                strtolower((string) $user->email),
                AuthOtpService::PURPOSE_REGISTER,
            );

            return sendResponse(
                false,
                'Please verify your email before signing in. A new verification code has been sent.',
                [
                    'requires_email_verification' => true,
                    'email' => strtolower((string) $user->email),
                ],
                HttpStatus::HTTP_FORBIDDEN,
            );
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

        if (! $user || $user->isDeletionBlocked()) {
            session()->forget($request->attempt_token);

            return sendResponse(
                false,
                'This account cannot be signed in.',
                null,
                HttpStatus::HTTP_FORBIDDEN,
            );
        }

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

        if ($user && ! $user->isDeletionBlocked()) {
            $this->authOtpService->issue($email, AuthOtpService::PURPOSE_PASSWORD_RESET);
        }

        return sendResponse(
            true,
            self::FORGOT_PASSWORD_GENERIC_MESSAGE,
            null,
            HttpStatus::HTTP_OK,
        );
    }

    public function resendOtp(ResendOtpRequest $request): JsonResponse
    {
        $email = strtolower($request->string('email')->toString());
        $user = User::query()->where('email', $email)->first();

        if ($user && ! $user->isDeletionBlocked()) {
            $purpose = $user->email_verified_at
                ? AuthOtpService::PURPOSE_PASSWORD_RESET
                : AuthOtpService::PURPOSE_REGISTER;

            $this->authOtpService->issue($email, $purpose);
        }

        return sendResponse(
            true,
            'If that email exists, a verification code has been sent.',
            null,
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
        if (! $user || $user->isDeletionBlocked()) {
            return sendResponse(
                false,
                'Invalid or expired verification code.',
                null,
                HttpStatus::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $user->forceFill(['email_verified_at' => now()])->save();
        $user->load(['roles', 'permissions']);

        $tokenResult = $user->createToken('auth_token');

        return sendResponse(
            true,
            'Email verified successfully.',
            [
                'access_token' => $tokenResult->accessToken,
                'token_type' => 'Bearer',
                'expires_in' => $tokenResult->token->expires_at,
                'user' => new UserResource($user),
            ],
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
        if (! $user || $user->isDeletionBlocked()) {
            return sendResponse(
                false,
                'Invalid or expired reset code.',
                null,
                HttpStatus::HTTP_UNPROCESSABLE_ENTITY,
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

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return sendResponse(
            true,
            'Logged out from all devices successfully.',
            null,
            HttpStatus::HTTP_OK,
        );
    }
}
