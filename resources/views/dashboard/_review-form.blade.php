@if($serviceRequest->reviews->isNotEmpty())
    <p class="review-submitted">You reviewed this completed request.</p>
@else
    <details class="review-prompt" @if($errors->has('review') || $errors->has('rating') || $errors->has('comment')) open @endif>
        <summary>Write a review</summary>
        <form class="review-form" method="POST" action="{{ route('reviews.store', $serviceRequest) }}">
            @csrf
            <label>
                Rating
                <select name="rating" required>
                    <option value="">Choose a rating</option>
                    @foreach(range(5, 1) as $rating)
                        <option value="{{ $rating }}" @selected((string) old('rating') === (string) $rating)>{{ $rating }} / 5</option>
                    @endforeach
                </select>
                @error('rating')
                    <small role="alert">{{ $message }}</small>
                @enderror
            </label>
            <label>
                Comment <span class="muted">Optional</span>
                <textarea name="comment" rows="3" maxlength="1000">{{ old('comment') }}</textarea>
                @error('comment')
                    <small role="alert">{{ $message }}</small>
                @enderror
            </label>
            <button class="button button-small" type="submit">Submit review</button>
        </form>
    </details>
@endif
