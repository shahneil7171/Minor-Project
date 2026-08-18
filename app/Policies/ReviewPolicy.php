<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    /**
     * Determine whether the user can view the review list.
     */
    public function viewAny(User $user): bool
    {
        return $user->account_type === 'admin';
    }

    /**
     * Determine whether the user can view a review.
     */
    public function view(User $user, Review $review): bool
    {
        return $user->account_type === 'admin';
    }

    /**
     * Determine whether the user can create a review.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update a review.
     */
    public function update(User $user, Review $review): bool
    {
        return $user->account_type === 'admin';
    }

    /**
     * Determine whether the user can delete a review.
     */
    public function delete(User $user, Review $review): bool
    {
        return $user->account_type === 'admin';
    }

    /**
     * Determine whether the user can restore a review.
     */
    public function restore(User $user, Review $review): bool
    {
        return $user->account_type === 'admin';
    }

    /**
     * Determine whether the user can permanently delete a review.
     */
    public function forceDelete(User $user, Review $review): bool
    {
        return $user->account_type === 'admin';
    }

    /**
     * Custom authorization ability used by ReviewsController.
     */
    public function manageReviews(User $user): bool
    {
        return $user->account_type === 'admin';
    }
}