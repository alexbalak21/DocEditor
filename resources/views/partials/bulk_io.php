<script>
/* Shared JSON export / import helpers for /documents, /customers and /items */
function bulkDownload(filename, payload) {
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([JSON.stringify(payload, null, 2)], {type: 'application/json'}));
  a.download = filename;
  document.body.appendChild(a); a.click(); a.remove();
  setTimeout(() => URL.revokeObjectURL(a.href), 2000);
}

/**
 * Read a chosen .json file, send it to `url` in batches and report the result.
 *   key    : property that holds the rows when the file is an object ({documents:[…]}, …)
 *   single : (documents only) a lone object is treated as one row
 */
async function bulkImport(input, {url, key, label, batch = 25, single = false, done}) {
  const file = input.files[0];
  input.value = '';
  if (!file) return;
  let parsed;
  try { parsed = JSON.parse(await file.text()); }
  catch (e) { toast('Not a valid JSON file', 'error'); return; }

  let rows = Array.isArray(parsed) ? parsed
           : (parsed && Array.isArray(parsed[key])) ? parsed[key]
           : (single && parsed && typeof parsed === 'object') ? [parsed] : null;
  if (!rows || !rows.length) { toast(`No ${label} found in this file`, 'error'); return; }
  if (!confirm(`Import ${rows.length} ${label}?\n\nThose that already exist are skipped, so nothing is duplicated.`)) return;

  const total = {imported: 0, skipped: 0, failed: 0, errors: []};
  try {
    for (let i = 0; i < rows.length; i += batch) {
      toast(`Importing ${Math.min(i + batch, rows.length)}/${rows.length}…`);
      const res  = await fetch(url, {method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({[key]: rows.slice(i, i + batch)})});
      const json = await res.json();
      if (!json.ok) throw new Error(json.error || 'Import failed');
      total.imported += json.imported; total.skipped += json.skipped; total.failed += json.failed;
      total.errors.push(...json.errors);
    }
  } catch (e) {
    toast(`Import stopped: ${e.message} (${total.imported} imported before the error)`, 'error');
    if (done) done();
    return;
  }
  if (done) done();
  toast(`Imported ${total.imported}, skipped ${total.skipped} already present` + (total.failed ? `, ${total.failed} failed` : ''), total.failed ? 'error' : 'success');
  if (total.failed) alert(`These ${label} could not be imported:\n\n` + total.errors.join('\n'));
}
</script>
