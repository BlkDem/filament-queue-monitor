<div class="space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-semibold">Jobs</h1>
        @if(isset($selectedQueue) && $selectedQueue)
            <span class="text-sm text-gray-500">Queue: {{ $selectedQueue }}</span>
        @endif
    </div>

    {{ $this->getTable() }}
</div>
