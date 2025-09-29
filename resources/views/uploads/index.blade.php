<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Uploads Overview</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; }
        .nav-link.active { font-weight: 600; }
        .thumbnail-img { max-width: 100%; border-radius: 0.75rem; }
        .pagination span, .pagination a {
            padding: 0.25rem 0.5rem !important;
            font-size: 0.875rem !important;
        }
    </style>
</head>
<body>
@php
    use Illuminate\Support\Facades\Storage;
@endphp
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="{{ route('products.import') }}">Task A Console</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="{{ route('products.import') }}">Product Import</a></li>
                <li class="nav-item"><a class="nav-link active" aria-current="page" href="{{ route('uploads.index') }}">Uploads & CSV</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('products.index') }}">Products</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container py-5">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="h4 fw-semibold mb-1">Uploaded Images</h1>
                    <p class="text-muted mb-0">Review uploads, variants, and statuses.</p>
                </div>
            </div>

            @if ($uploads->isEmpty())
                <div class="alert alert-info">No uploads yet. Use the drag-and-drop uploader on the Product Import page.</div>
            @else
                <div class="d-flex flex-column gap-4">
                    @foreach ($uploads as $upload)
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                                    <div>
                                        <h2 class="h5 mb-1">{{ $upload->original_filename }}</h2>
                                        <div class="text-muted small">Upload ID: <code>{{ $upload->getKey() }}</code></div>
                                        <div class="text-muted small">Storage: {{ $upload->storage_disk }} &middot; Size: {{ number_format($upload->total_size / 1024, 1) }} KB</div>
                                    </div>
                                    <span class="badge text-bg-{{ $upload->status->value === 'completed' ? 'success' : ($upload->status->value === 'in_progress' ? 'warning' : 'secondary') }} text-uppercase">{{ str_replace('_', ' ', $upload->status->value) }}</span>
                                </div>

                                @if ($upload->images->isNotEmpty())
                                    <div class="mt-3">
                                        <div class="row g-3">
                                            @foreach ($upload->images as $image)
                                                <div class="col-md-6 col-xl-4">
                                                    <div class="border rounded-3 p-3 h-100">
                                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                                            <span class="badge text-bg-primary text-uppercase">{{ $image->variant }}</span>
                                                            <span class="small text-muted">{{ $image->width }}×{{ $image->height }}</span>
                                                        </div>
                                                        @php
                                                            $disk = $upload->storage_disk;
                                                            $exists = Storage::disk($disk)->exists($image->path);
                                                            $url = $exists ? route('uploads.image', [$upload, $image]) : null;
                                                        @endphp
                                                        @if ($url)
                                                            <img src="{{ $url }}" alt="Variant {{ $image->variant }}" class="thumbnail-img mb-2">
                                                        @else
                                                            <div class="bg-light border rounded-3 d-flex align-items-center justify-content-center text-muted" style="height: 140px;">Preview unavailable</div>
                                                        @endif
                                                        <div class="small text-muted">Path: <code>{{ $image->path }}</code></div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    <p class="text-muted small mt-3 mb-0">No image variants generated yet.</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4">
                    {!! $uploads->withQueryString()->links('pagination::bootstrap-5') !!}
                </div>

            @endif
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm sticky-top" style="top: 4rem;">
                <div class="card-body">
                    <h2 class="h5 fw-semibold">Generate Sample CSV</h2>
                    <p class="text-muted small">Download a CSV ready for the importer. Leave the upload selection empty to generate rows without images.</p>
                    <form method="post" action="{{ route('uploads.generate') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="rows" class="form-label">Number of rows</label>
                            <input type="number" class="form-control" id="rows" name="rows" value="25" min="1" max="10000" required>
                        </div>
                        <div class="mb-3">
                            <label for="prefix" class="form-label">SKU prefix (optional)</label>
                            <input type="text" class="form-control" id="prefix" name="prefix" maxlength="10" placeholder="SKU">
                        </div>
                        <div class="mb-3">
                            <label for="upload_ids" class="form-label">Use these uploads (optional)</label>
                            <select id="upload_ids" name="upload_ids[]" class="form-select" multiple size="6">
                                @foreach ($completedUploads as $item)
                                    <option value="{{ $item->getKey() }}">{{ $item->original_filename }} ({{ $item->getKey() }})</option>
                                @endforeach
                            </select>
                            <div class="form-text">Hold Ctrl/⌘ to select multiple uploads.</div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Download CSV</button>
                    </form>
                    <hr>
                    <p class="small text-muted mb-0">Need to import products? Head back to the <a href="{{ route('products.import') }}">Product Import</a> page.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>