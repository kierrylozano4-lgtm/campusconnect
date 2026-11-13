@extends('layouts.app')
@section('content')
<section class="section-heading">
    <div>
        <p class="eyebrow">Marketplace moderation</p>
        <h1>Manage services</h1>
        <p class="muted">Review listings, update availability, or moderate multiple services at once.</p>
    </div>
    <a class="button button-small" href="{{ route('admin.index') }}">Admin overview</a>
</section>

@include('admin._navigation', ['active' => 'services'])

<section class="panel admin-section" aria-labelledby="admin-services-heading">
            <div class="admin-section-heading">
                <div>
                    <p class="eyebrow">Service listings</p>
                    <h2 id="admin-services-heading">Marketplace services</h2>
                    <p class="muted">Select listings for a bulk update or moderate them individually.</p>
                </div>
                <span class="admin-count"><strong>{{ $services->count() }}</strong><span>listings</span></span>
            </div>

            <form id="bulk-service-form" method="POST" action="{{ route('admin.services.bulk') }}" class="form-stack admin-bulk-form">
                @csrf
                @method('PATCH')
                <div class="bulk-panel">
                    <label>Status
                        <select name="status">
                            <option value="Active">Activate selected</option>
                            <option value="Inactive">Deactivate selected</option>
                        </select>
                    </label>
                    <label>Reason
                        <textarea name="inactive_reason" rows="2" placeholder="Optional moderation reason..."></textarea>
                    </label>
                    <label class="check"><input type="checkbox" name="hide_from_marketplace" value="1"> Hide selected services from the marketplace</label>
                    <button class="button button-small" type="submit">Apply to selected</button>
                </div>
            </form>

            <div class="admin-list">
                @forelse($services as $service)
                    <div class="admin-row admin-service-row">
                        <div class="service-check-wrap">
                            <input type="checkbox" name="service_ids[]" value="{{ $service->id }}" form="bulk-service-form" aria-label="Select {{ $service->title }}">
                        </div>
                        <div class="service-meta">
                            <strong>{{ $service->title }}</strong>
                            <span>By {{ $service->user->full_name }} · <span class="status {{ $service->status === 'Active' ? 'completed' : 'declined' }}">{{ $service->status }}</span>@if($service->inactive_reason) · {{ $service->inactive_reason }}@endif</span>
                        </div>
                        <div class="row-actions service-actions">
                            <form method="POST" action="{{ route('admin.services.toggle', $service) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="{{ $service->status === 'Active' ? 'Inactive' : 'Active' }}">
                                <input type="hidden" name="hide_from_marketplace" value="1">
                                <input type="hidden" name="inactive_reason" value="Moderated by administrator">
                                <button class="button button-small" type="submit">{{ $service->status === 'Active' ? 'Deactivate' : 'Activate' }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.services.destroy', $service) }}">
                                @csrf
                                @method('DELETE')
                                <button class="button button-small button-danger" type="submit">Delete</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="admin-empty">There are no service listings to moderate.</p>
                @endforelse
            </div>
</section>
@endsection
