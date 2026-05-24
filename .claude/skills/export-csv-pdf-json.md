# Skill: Export to CSV, PDF, JSON
Generate exports from the same dataset shown in the table (after filters/sorting).

**Interfaces**:
- `CsvExporter::export($data, $filename)`
- `PdfExporter::export($data, $filename)` (use dompdf or similar)
- `JsonExporter::export($data, $filename)`

**Requirements**:
- Eksportuj **wszystkie rekordy pasujące do aktywnych filtrów** — nie tylko bieżącą stronę paginacji.
- Set appropriate headers (`Content-Disposition`) for download.
- PDF export must be readable and include column headers.
- CSV: comma‑separated, quoted fields.
- JSON: pretty‑print optional.