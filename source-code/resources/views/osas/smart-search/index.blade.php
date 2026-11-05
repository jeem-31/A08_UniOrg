@extends((request()->boolean('fragment') && request()->ajax()) ? 'layouts.fragment' : 'layouts.osas')

@section('title', 'Document Library')

@section('content')
    <x-document-library.page :library="$library" :filter-options="$filterOptions" :filter-labels="$filterLabels" />
@endsection

@push('styles')
    @vite(['resources/css/document-library.css'])
@endpush

@push('scripts')
    @vite(['resources/js/document-library.js'])
@endpush
