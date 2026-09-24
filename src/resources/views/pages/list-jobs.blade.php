<div class="fqm-page-wrap">
    <style>
        .fqm-page-wrap { display: flex; flex-direction: column; gap: 24px; }
        .fqm-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .fqm-title { margin: 0; font-size: 22px; font-weight: 600; color: #111827; }
        html.dark .fqm-title { color: #f9fafb; }
    </style>

    <header class="fqm-header">
        <h1 class="fqm-title">Jobs</h1>
    </header>

    {{ $this->getTable() }}
</div>