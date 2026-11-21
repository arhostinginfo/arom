{{-- AMC pages: their own stylesheet and script (public/css/amc.css, public/js/amc.js), so no shared file changes. --}}
@once
<link rel="stylesheet" href="{{ asset('css/amc.css') }}?v={{ filemtime(public_path('css/amc.css')) }}">
@push('scripts')
<script src="{{ asset('js/amc.js') }}?v={{ filemtime(public_path('js/amc.js')) }}"></script>
@endpush
@endonce
