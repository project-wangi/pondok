<style>
    :root {
        color-scheme: light;
    }

    /* Ukuran kertas mengikuti pilihan di dialog cetak. */
    @page {
        size: auto;
        margin: 12mm;
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
        max-width: 190mm;
        margin: 0 auto;
    }

    .toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        width: 100%;
        max-width: 190mm;
        margin: 0 auto 12px;
    }

    .toolbar-note {
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

    .rapor-sheet {
        display: grid;
        gap: 10px;
    }

    .rapor-sheet-header {
        display: grid;
        gap: 2px;
        padding-bottom: 8px;
        border-bottom: 2px solid #111827;
        text-align: center;
    }

    .rapor-sheet-cop {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 14px;
        margin-bottom: 6px;
    }

    .rapor-sheet-logo {
        width: 60px;
        height: 60px;
        flex-shrink: 0;
        object-fit: contain;
    }

    .rapor-sheet-school {
        display: grid;
        gap: 2px;
        text-align: center;
    }

    .rapor-school {
        margin: 0;
        font-size: 15px;
        font-weight: 700;
        letter-spacing: 0.02em;
    }

    .rapor-school-address {
        margin: 0;
        color: #374151;
        font-size: 10px;
    }

    .rapor-sheet-title {
        margin: 4px 0 0;
        font-size: 14px;
        font-weight: 800;
        letter-spacing: 0.08em;
    }

    .rapor-sheet-subtitle {
        margin: 0;
        font-size: 11px;
        font-weight: 600;
    }

    .rapor-identity {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 3px 24px;
        margin: 0;
    }

    .rapor-identity > div {
        display: grid;
        grid-template-columns: 110px minmax(0, 1fr);
        gap: 8px;
    }

    .rapor-identity dt {
        color: #374151;
    }

    .rapor-identity dd {
        margin: 0;
        font-weight: 700;
        overflow-wrap: anywhere;
    }

    .rapor-sheet-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 11px;
    }

    .rapor-sheet-table :is(th, td) {
        padding: 4px 6px;
        border: 1px solid #9ca3af;
        text-align: left;
        vertical-align: middle;
        overflow-wrap: anywhere;
    }

    .rapor-sheet-table thead th {
        background: #e5e7eb;
        font-weight: 700;
        text-align: center;
        white-space: nowrap;
    }

    .rapor-sheet-table .rapor-no-col,
    .rapor-sheet-table .rapor-kkm-col,
    .rapor-sheet-table .rapor-score-col,
    .rapor-sheet-table .rapor-letter-col {
        width: 58px;
        text-align: center;
    }

    .presensi-numeric {
        font-variant-numeric: tabular-nums;
    }

    .rapor-below-kkm {
        color: #b91c1c;
        font-weight: 700;
    }

    .rapor-empty-cell {
        padding: 14px 8px;
        text-align: center;
        color: #4b5563;
    }

    .rapor-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 6px;
    }

    .rapor-summary > div {
        display: flex;
        justify-content: space-between;
        gap: 8px;
        padding: 5px 8px;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        background: #f9fafb;
    }

    .rapor-summary span {
        color: #374151;
        font-size: 10px;
    }

    .rapor-summary strong {
        font-variant-numeric: tabular-nums;
    }

    .rapor-sheet-columns {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .rapor-block h3 {
        margin: 0 0 5px;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .rapor-note {
        margin: 0;
        padding-top: 8px;
        border-top: 1px dashed #9ca3af;
        color: #4b5563;
        font-size: 9px;
        line-height: 1.55;
    }

    .rapor-signatures {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        margin-top: 18px;
        text-align: center;
        page-break-inside: avoid;
        break-inside: avoid;
    }

    .rapor-signatures > div {
        display: grid;
        align-content: start;
        gap: 42px;
    }

    .rapor-signatures span {
        color: #374151;
        font-size: 10px;
    }

    .rapor-signatures strong {
        padding-top: 3px;
        border-top: 1px solid #9ca3af;
        font-weight: 700;
        overflow-wrap: anywhere;
    }

    .presensi-sr-only {
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

    @media (max-width: 640px) {
        .rapor-identity,
        .rapor-sheet-columns {
            grid-template-columns: 1fr;
        }

        .rapor-signatures {
            grid-template-columns: 1fr;
            gap: 24px;
        }
    }

    @media print {
        .toolbar {
            display: none !important;
        }

        .rapor-sheet-table thead th {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .rapor-sheet-table tr,
        .rapor-block,
        .rapor-summary {
            break-inside: avoid;
            page-break-inside: avoid;
        }
    }
</style>
