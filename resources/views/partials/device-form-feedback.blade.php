@if (session('success'))
    <div class="alert alert-success alert-dismissible" role="status">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss notification"></button>
                </div>
@endif
@if (session('error'))
    <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        Please correct the highlighted fields. Your changes have not been saved.
    </div>
@endif
