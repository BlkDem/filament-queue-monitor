<script>
    (function () {
        if (window.__queueMonitorGroupCollapseInstalled) {
            return;
        }

        window.__queueMonitorGroupCollapseInstalled = true;

        function collapseGroups() {
            document.querySelectorAll('[data-queue-monitor-group-table]').forEach(function (table) {
                if (table.__queueMonitorGroupsCollapsed) {
                    return;
                }

                var headers = table.querySelectorAll('.fi-ta-group-header');

                if (headers.length === 0) {
                    return;
                }

                headers.forEach(function (header) {
                    var row = header.closest('tr');
                    var nextRow = row ? row.nextElementSibling : null;

                    if (nextRow && nextRow.hasAttribute('hidden')) {
                        return;
                    }

                    header.click();
                });

                table.__queueMonitorGroupsCollapsed = true;
            });
        }

        document.addEventListener('livewire:init', function () {
            [0, 120, 400, 900].forEach(function (delay) {
                window.setTimeout(collapseGroups, delay);
            });
        });
    })();
</script>