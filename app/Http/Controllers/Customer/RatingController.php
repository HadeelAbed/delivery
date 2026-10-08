<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\RatingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RatingController extends Controller
{
    public function index(Order $order)
    {
        Gate::authorize('rate', $order);

        $canRate = app(RatingService::class)->canRate($order, auth()->user());

        if (! $canRate['canRate']) {
            return back()
                ->with('status', $canRate['reason'] ?? 'cannot_rate')
                ->with('type', 'error');
        }

        return view('customer.rating-create', [
            'order' => $order,
            'merchant_score' => null,
            'driver_score' => null,
            'comment' => null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => ['required', 'exists:orders,id',
                Rule::unique('ratings', 'order_id')
                    ->where('customer_id', auth()->id())],
            'merchant_score' => ['required', 'integer', 'between:1,5'],
            'driver_score' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        $result = app(RatingService::class)->submitRating($validated, auth()->user());

        if (! $result['success']) {
            return back()
                ->with('status', $result['errors'][0]['message'] ?? 'validation_failed')
                ->with('type', 'error');
        }

        return back()
            ->with('status', $result['message'])
            ->with('type', 'success');
    }
}
