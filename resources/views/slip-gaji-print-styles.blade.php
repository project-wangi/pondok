<style>
    :root {
        color-scheme: light;
    }

    /* Ukuran kertas mengikuti pilihan di dialog cetak. */
    @page {
        size: auto;
        margin: 14mm;
    }

    * {
        box-sizing: border-box;
    }

    html,
    body {
        margin: 0;
        padding: 0;
        background: #ffffff;
        color: #111827;
    }

    body {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 12px;
        line-height: 1.45;
    }

    .sheet {
        width: 100%;
        max-width: 186mm;
        margin: 0 auto;
    }

    /* Setiap lembar slip dimulai pada halaman baru. */
    .sheet + .sheet {
        page-break-before: always;
        break-before: page;
    }

    .toolbar {
        display: flex;
        justify-content: flex-end;
        margin-bottom: 12px;
    }

    .toolbar-split {
        justify-content: space-between;
        align-items: center;
        max-width: 186mm;
        margin: 0 auto 12px;
    }

    .toolbar-count {
        color: #374151;
        font-size: 11px;
        font-weight: 600;
    }

    .toolbar button {
        padding: 8px 14px;
        border: 1px solid #9ca3af;
        border-radius: 6px;
        background: #f3f4f6;
        color: #111827;
        font: inherit;
        font-weight: 600;
        cursor: pointer;
    }

    header {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        justify-content: space-between;
        gap: 12px;
        padding-bottom: 10px;
        border-bottom: 2px solid #111827;
    }

    .school {
        margin: 0;
        color: #374151;
        font-size: 10px;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    h1 {
        margin: 4px 0 0;
        font-size: 20px;
        line-height: 1.15;
    }

    .period {
        text-align: right;
    }

    .period span {
        display: block;
        color: #374151;
        font-size: 10px;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .period strong {
        display: block;
        margin-top: 2px;
        font-size: 14px;
    }

    .identity {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 8px 18px;
        margin: 14px 0;
    }

    .identity div {
        min-width: 0;
    }

    dt {
        color: #374151;
        font-size: 10px;
    }

    dd {
        margin: 2px 0 0;
        font-weight: 700;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th,
    td {
        padding: 6px 8px;
        border: 1px solid #9ca3af;
        text-align: left;
        vertical-align: top;
    }

    thead th {
        background: #e5e7eb;
        color: #111827;
        font-weight: 700;
        text-align: center;
    }

    tbody td:first-child {
        font-weight: 600;
    }

    tbody td:nth-child(2) {
        color: #374151;
    }

    tbody td:last-child,
    thead th:last-child {
        text-align: right;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }

    tbody tr:nth-child(even) {
        background: #f9fafb;
    }

    tfoot th,
    tfoot td {
        background: #e5e7eb;
        font-weight: 700;
    }

    tfoot th {
        text-align: right;
    }

    tfoot td {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .note {
        margin-top: 12px;
        color: #4b5563;
        font-size: 10px;
        line-height: 1.6;
    }

    .signatures {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10mm 18mm;
        margin-top: 26px;
        page-break-inside: avoid;
    }

    .sign-role {
        margin: 0;
        color: #374151;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .sign-space {
        height: 18mm;
    }

    .sign-name {
        width: 100%;
        max-width: 62mm;
        min-height: 5mm;
        margin: 0;
        padding-bottom: 1mm;
        font-weight: 700;
        border-bottom: 1px solid #9ca3af;
    }

    .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        margin: -1px;
        padding: 0;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }

    @media print {
        .toolbar {
            display: none !important;
        }
    }

    @media (max-width: 640px) {
        .identity {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
</style>
