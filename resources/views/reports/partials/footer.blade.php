{{-- Numbered footer: DomPDF page_text instead of static text. --}}
<div class="footer">Free Public Internet Access Program (FPIAP) — FreeWiFi Device Operations — Confidential</div>
<script type="text/php">
if (isset($pdf)) {
    $pdf->page_text(520, 815, 'Page {PAGE_NUM} of {PAGE_COUNT}', null, 8, [0.58, 0.63, 0.71]);
}
</script>
