<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Products Overview</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; }
        .nav-link.active { font-weight: 600; }
        .product-card-img { width: 100%; height: 200px; object-fit: cover; border-radius: 0.75rem; }
        .metadata-badge { font-size: 0.75rem; }
    </style>
</head>
<body>
@php use Illuminate\Support\Facades\Storage; @endphp
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="{{ route('products.import') }}">Task A Console</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="{{ route('products.import') }}">Product Import</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('uploads.index') }}">Uploads & CSV</a></li>
                <li class="nav-item"><a class="nav-link active" aria-current="page" href="{{ route('products.index') }}">Products</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h4 fw-semibold mb-1">Product Catalog</h1>
            <p class="text-muted mb-0">Review imported products, their metadata, and linked images.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('products.import') }}" class="btn btn-outline-primary">Import Products</a>
            <a href="{{ route('uploads.index') }}" class="btn btn-outline-secondary">Manage Uploads</a>
        </div>
    </div>

    @if ($products->isEmpty())
        <div class="alert alert-info">No products found. Import a CSV to populate the catalog.</div>
    @else
        <div class="row g-4">
            @foreach ($products as $product)
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex flex-column">
                            @php
                                $primaryImage = $product->primaryImage;
                                $imageUrl = null;
                                if ($primaryImage && $primaryImage->upload && Storage::disk($primaryImage->upload->storage_disk)->exists($primaryImage->path)) {
                                    $imageUrl = route('uploads.image', [$primaryImage->upload, $primaryImage]);
                                }
                            @endphp
                            @if ($imageUrl)
                                <img src="{{ $imageUrl }}" alt="Primary image for {{ $product->name }}" class="product-card-img mb-3">
                            @else
                                <div class="product-card-img mb-3 d-flex align-items-center justify-content-center bg-light text-muted">
                                    No image
                                </div>
                            @endif

                            <h2 class="h5 fw-semibold mb-1">{{ $product->name }}</h2>
                            <div class="text-muted small mb-2">SKU: {{ $product->sku }}</div>

                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <span class="badge text-bg-primary metadata-badge">${{ number_format($product->price, 2) }}</span>
                                @if ($product->primary_image_id)
                                    <span class="badge text-bg-success metadata-badge">Primary Image Linked</span>
                                @else
                                    <span class="badge text-bg-secondary metadata-badge">No Image Linked</span>
                                @endif
                            </div>

                            @if (! empty($product->description))
                                <p class="text-muted small mb-3">{{ \Illuminate\Support\Str::limit($product->description, 160) }}</p>
                            @endif

                            @if (! empty($product->metadata))
                                <div class="mb-3">
                                    <p class="text-muted small mb-1">Metadata</p>
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach ($product->metadata as $key => $value)
                                            <span class="badge text-bg-light text-dark metadata-badge">{{ $key }}: {{ is_array($value) ? json_encode($value) : $value }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if ($product->images->isNotEmpty())
                                <div class="mt-auto">
                                    <p class="text-muted small mb-2">Image Variants</p>
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach ($product->images as $variant)
                                            @php
                                                $variantUrl = null;
                                                if ($variant->upload && Storage::disk($variant->upload->storage_disk)->exists($variant->path)) {
                                                    $variantUrl = route('uploads.image', [$variant->upload, $variant]);
                                                }
                                            @endphp
                                            @if ($variantUrl)
                                                <a class="badge text-bg-info metadata-badge text-decoration-none" href="{{ $variantUrl }}" target="_blank" rel="noopener">
                                                    {{ strtoupper($variant->variant) }} ({{ $variant->width }}×{{ $variant->height }})
                                                </a>
                                            @else
                                                <span class="badge text-bg-secondary metadata-badge">{{ strtoupper($variant->variant) }}</span>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {!! $products->withQueryString()->links('pagination::bootstrap-5') !!}
        </div>
    @endif
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>