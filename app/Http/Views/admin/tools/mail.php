<?php
declare(strict_types=1);
// Minimal admin form (assumes RBAC guards this route)
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>CIS Mail Test</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
  <div class="container" style="max-width:760px">
    <h1 class="mb-3">CIS Mail Test</h1>
    <form id="f" class="card p-3">
      <div class="mb-3">
        <label class="form-label">To Email</label>
        <input class="form-control" name="to" placeholder="you@ecigdis.co.nz" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Recipient Name</label>
        <input class="form-control" name="name" value="Test User">
      </div>
      <div class="mb-3">
        <label class="form-label">Subject</label>
        <input class="form-control" name="subj" value="CIS Test Email">
      </div>
      <div class="mb-3">
        <label class="form-label">HTML</label>
        <textarea class="form-control" name="html" rows="5"><p>Hello from <strong>CIS V2</strong> via SendGrid.</p></textarea>
      </div>
      <button class="btn btn-primary">Send Test Email</button>
    </form>
    <pre id="out" class="mt-3 bg-light p-3 border small"></pre>
  </div>
<script>
document.getElementById('f').addEventListener('submit', async (e) => {
  e.preventDefault();
  const fd = new FormData(e.target);
  const r  = await fetch(location.pathname, { method:'POST', body:fd });
  document.getElementById('out').textContent = await r.text();
});
</script>
</body>
</html>
