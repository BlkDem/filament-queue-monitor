{{--
    Styling for the read-only code and note blocks on the detail pages.

    Two rules only. The cards around them are filament sections, so they
    already follow the active theme, and these blocks sit on the section
    surface: alpha over whatever is behind them keeps the dark variant
    correct without a second colour palette to maintain.
--}}
<style>
    .fqm-code {
        margin: 0;
        padding: 12px;
        border-radius: 6px;
        overflow: auto;
        white-space: pre-wrap;
        overflow-wrap: break-word;
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: 12px;
        line-height: 1.7;
        background: rgb(0 0 0 / 0.04);
    }

    html.dark .fqm-code { background: rgb(255 255 255 / 0.06); }

    .fqm-code-error { color: #b91c1c; }

    html.dark .fqm-code-error { color: #fca5a5; }

    .fqm-note { margin: 0; font-size: 13px; }

    .fqm-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 16px 28px;
        margin: 0;
    }

    .fqm-grid dt { font-size: 12px; font-weight: 500; opacity: 0.7; }

    .fqm-grid dd { margin: 3px 0 0; font-size: 13px; overflow-wrap: anywhere; }
</style>
