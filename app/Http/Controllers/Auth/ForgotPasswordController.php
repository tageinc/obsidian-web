<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    use SendsPasswordResetEmails;

    public function sendResetLinkEmail(Request $request)
    {
        $this->validateEmail($request);
        $broker = $this->broker();
        $deliveryFailed = false;
        $response = $broker->sendResetLink($this->credentials($request), function ($user, $token) use ($broker, &$deliveryFailed) {
            try {
                $user->sendPasswordResetNotification($token);
            } catch (\Throwable $exception) {
                $deliveryFailed = true;
                // Transport exceptions can contain credentials or the complete message.
                Log::warning('auth.password_reset_delivery_failed', ['exception' => get_class($exception)]);
                try {
                    $this->deleteFailedDeliveryToken($broker, $user, $token);
                } catch (\Throwable $cleanupException) {
                    Log::warning('auth.password_reset_token_cleanup_failed', ['exception' => get_class($cleanupException)]);
                }
            }
        });

        if ($deliveryFailed) {
            return $this->sendResetLinkFailedResponse($request, 'passwords.delivery_failed');
        }

        return $response === Password::RESET_LINK_SENT
            ? $this->sendResetLinkResponse($request, $response)
            : $this->sendResetLinkFailedResponse($request, $response);
    }

    private function deleteFailedDeliveryToken($broker, $user, string $token): void
    {
        $repository = $broker->getRepository();
        $table = config('auth.passwords.'.config('auth.defaults.passwords').'.table');
        $query = $repository->getConnection()->table($table)->where('email', $user->getEmailForPasswordReset());
        $storedHash = (clone $query)->value('token');
        // Do not invalidate a newer token if another request replaced this failed attempt.
        if (is_string($storedHash) && $repository->getHasher()->check($token, $storedHash)) {
            $query->where('token', $storedHash)->delete();
        }
    }
}
