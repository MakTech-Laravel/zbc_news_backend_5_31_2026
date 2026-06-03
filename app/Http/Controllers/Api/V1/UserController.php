<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\TwoFactorAuthenticationProvider;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class UserController extends Controller
{
    //
    public function twoFactorEnable(Request $request): JsonResponse
    {
        $user = $request->user();

        // ✅ STEP 1: ENABLE 2FA (IMPORTANT)
        app(EnableTwoFactorAuthentication::class)($user);

        // refresh user after update
        $user->refresh();

        // ✅ STEP 2: QR SVG
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
}
