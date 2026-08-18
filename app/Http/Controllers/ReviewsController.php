<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewsController extends Controller
{
    /**
 * Store a new product review.
 */
public function store(Request $request, string $slug)
{
    $user = $request->user();

    $validated = $request->validate([
        'rating' => [
            'required',
            'integer',
            'min:1',
            'max:5',
        ],
        'comment' => [
            'required',
            'string',
            'max:1000',
        ],
    ]);

    // Prevent the same user from reviewing the same product twice
    $existingReview = Review::where('user_id', $user->id)
        ->where('product_slug', $slug)
        ->exists();

    if ($existingReview) {
        return back()->with(
            'error',
            'You have already reviewed this product.'
        );
    }

    Review::create([
        'user_id' => $user->id,
        'product_slug' => $slug,
        'rating' => $validated['rating'],
        'comment' => $validated['comment'],
        'status' => 'pending',
    ]);

    return back()->with(
        'success',
        'Your review has been submitted and is waiting for approval.'
    );
}
    public function index()
    {
        $this->authorize('manage-reviews');

        $status = request()->get('status', 'all');
        $productSlug = request()->get('product_slug');

        $query = Review::with('user');

        // Filter by review status
        if ($status !== 'all' && in_array($status, [
            'pending',
            'approved',
            'rejected',
        ])) {
            $query->where('status', $status);
        }

        // Filter by product slug
        if ($productSlug) {
            $query->where('product_slug', $productSlug);
        }

        $reviews = $query
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view(
            'admin.reviews.index',
            compact('reviews', 'status', 'productSlug')
        );
    }

    /**
     * Show the form for editing a review.
     */
    public function edit(Review $review)
    {
        $this->authorize('update', $review);

        return view(
            'admin.reviews.edit',
            compact('review')
        );
    }

    /**
     * Update a review.
     */
    public function update(
        Request $request,
        Review $review
    ) {
        $this->authorize('update', $review);

        $validated = $request->validate([
            'status' => [
                'required',
                'in:pending,approved,rejected',
            ],
            'comment' => [
                'sometimes',
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        $review->update($validated);

        return back()->with(
            'success',
            'Review ' . $review->status . ' successfully.'
        );
    }

    /**
     * Delete a review.
     */
    public function destroy(Review $review)
    {
        $this->authorize('delete', $review);

        $review->delete();

        return back()->with(
            'success',
            'Review deleted successfully.'
        );
    }

    /**
     * Approve a review.
     */
    public function approve(Review $review)
    {
        $this->authorize('update', $review);

        $review->update([
            'status' => 'approved',
        ]);

        return back()->with(
            'success',
            'Review approved successfully.'
        );
    }

    /**
     * Reject a review.
     */
    public function reject(Review $review)
    {
        $this->authorize('update', $review);

        $review->update([
            'status' => 'rejected',
        ]);

        return back()->with(
            'success',
            'Review rejected successfully.'
        );
    }
}