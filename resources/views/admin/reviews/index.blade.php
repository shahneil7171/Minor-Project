<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Reviews | KDP MART</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #080d1c;
            color: #e5e7eb;
        }

        .container {
            width: 95%;
            max-width: 1400px;
            margin: 30px auto;
        }

        h1 {
            margin-bottom: 25px;
        }

        .message {
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            background: #064e3b;
            color: #d1fae5;
        }

        .filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .filters a {
            padding: 9px 16px;
            border-radius: 7px;
            text-decoration: none;
            color: white;
            background: #1e293b;
        }

        .filters a.active {
            background: #2563eb;
        }

        .review-card {
            background: #111827;
            border: 1px solid #26304a;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 18px;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 15px;
        }

        .user-name {
            font-size: 18px;
            font-weight: 700;
        }

        .product {
            color: #60a5fa;
            margin-top: 5px;
            font-size: 14px;
        }

        .rating {
            color: #fbbf24;
            font-size: 20px;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-top: 8px;
        }

        .status.pending {
            background: #78350f;
            color: #fde68a;
        }

        .status.approved {
            background: #064e3b;
            color: #a7f3d0;
        }

        .status.rejected {
            background: #7f1d1d;
            color: #fecaca;
        }

        .comment {
            color: #cbd5e1;
            line-height: 1.6;
            margin: 15px 0;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .actions form {
            display: inline;
        }

        button,
        .edit-button {
            border: none;
            border-radius: 7px;
            padding: 8px 14px;
            cursor: pointer;
            color: white;
            text-decoration: none;
            font-size: 14px;
        }

        .approve {
            background: #059669;
        }

        .reject {
            background: #dc2626;
        }

        .edit {
            background: #2563eb;
        }

        .delete {
            background: #7f1d1d;
        }

        .date {
            color: #64748b;
            font-size: 13px;
            margin-top: 12px;
        }

        .empty {
            text-align: center;
            padding: 50px;
            background: #111827;
            border: 1px solid #26304a;
            border-radius: 12px;
            color: #94a3b8;
        }

        @media (max-width: 700px) {
            .review-header {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Manage Customer Reviews</h1>

    @if(session('success'))
        <div class="message">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="message">
            {{ session('error') }}
        </div>
    @endif


    <!-- Filters -->

    <div class="filters">

        <a
            href="{{ route('admin.reviews.index', ['status' => 'all']) }}"
            class="{{ $status === 'all' ? 'active' : '' }}"
        >
            All
        </a>

        <a
            href="{{ route('admin.reviews.index', ['status' => 'pending']) }}"
            class="{{ $status === 'pending' ? 'active' : '' }}"
        >
            Pending
        </a>

        <a
            href="{{ route('admin.reviews.index', ['status' => 'approved']) }}"
            class="{{ $status === 'approved' ? 'active' : '' }}"
        >
            Approved
        </a>

        <a
            href="{{ route('admin.reviews.index', ['status' => 'rejected']) }}"
            class="{{ $status === 'rejected' ? 'active' : '' }}"
        >
            Rejected
        </a>

    </div>


    <!-- Reviews -->

    @forelse($reviews as $review)

        <div class="review-card">

            <div class="review-header">

                <div>

                    <div class="user-name">
                        {{ $review->user?->name ?? 'Unknown Customer' }}
                    </div>

                    <div class="product">
                        Product:
                        {{ $review->product_slug }}
                    </div>

                    <div class="status {{ $review->status }}">
                        {{ ucfirst($review->status) }}
                    </div>

                </div>

                <div class="rating">

                    @for($i = 1; $i <= 5; $i++)

                        {{ $i <= $review->rating ? '★' : '☆' }}

                    @endfor

                </div>

            </div>


            <div class="comment">

                {{ $review->comment }}

            </div>


            <div class="actions">

                @if($review->status !== 'approved')

                    <form
                        method="POST"
                        action="{{ route('admin.reviews.approve', $review) }}"
                    >

                        @csrf

                        <button
                            type="submit"
                            class="approve"
                        >
                            Approve
                        </button>

                    </form>

                @endif


                @if($review->status !== 'rejected')

                    <form
                        method="POST"
                        action="{{ route('admin.reviews.reject', $review) }}"
                    >

                        @csrf

                        <button
                            type="submit"
                            class="reject"
                        >
                            Reject
                        </button>

                    </form>

                @endif


                <a
                    href="{{ route('admin.reviews.edit', $review) }}"
                    class="edit-button edit"
                >
                    Edit
                </a>


                <form
                    method="POST"
                    action="{{ route('admin.reviews.destroy', $review) }}"
                    onsubmit="return confirm('Are you sure you want to delete this review?');"
                >

                    @csrf
                    @method('DELETE')

                    <button
                        type="submit"
                        class="delete"
                    >
                        Delete
                    </button>

                </form>

            </div>


            <div class="date">

                Submitted:
                {{ $review->created_at?->format('d M Y, h:i A') }}

            </div>

        </div>

    @empty

        <div class="empty">

            No reviews found.

        </div>

    @endforelse


    {{ $reviews->links() }}

</div>

</body>
</html>