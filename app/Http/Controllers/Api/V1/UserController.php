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
}
