<?php

namespace App\Http\Responses;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

class CustomRegisterResponse implements RegisterResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return redirect('/login');
        }

        $planCode = Str::lower((string) $request->input('plan_code', 'free'));
        $plan = SubscriptionPlan::query()
            ->whereRaw('LOWER(code) = ?', [$planCode])
            ->where('status', 'active')
            ->first();

        if ($plan && (float) $plan->price > 0) {
            $cycle = $request->input('cycle', 'monthly');
            if (! in_array($cycle, ['monthly', 'yearly'])) {
                $cycle = 'monthly';
            }
            return redirect()->route('billing.checkout', ['plan' => Str::lower($plan->code), 'cycle' => $cycle]);
        }

        return redirect()->intended('/dashboard');
    }
}