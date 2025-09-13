<?php $title='Backups'; $page_title='Backups'; $page_icon='fa-solid fa-shield-halved'; $page_header=true; ob_start(); ?>
<main id="backups" data-page="backups">
  <div class="container-fluid py-3">
    <div class="row">
      <div class="col-lg-3 mb-3">
        <div class="card">
          <div class="card-header">
            <h5 class="card-title mb-0"><i class="fa-solid fa-shield-halved me-2"></i>Statistics</h5>
          </div>
          <div class="card-body">
            <p class="text-muted">Statistics and overview for Backups.</p>
            <!-- Add statistics here -->
          </div>
        </div>
      </div>
      <div class="col-lg-9 mb-3">
        <div class="card">
          <div class="card-header">
            <h5 class="card-title mb-0">Backups Management</h5>
          </div>
          <div class="card-body">
            <p class="text-muted">Main Backups interface and actions.</p>
            <!-- Add main interface here -->
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-primary" data-action="refresh">
                <i class="fa-solid fa-sync me-1"></i>Refresh
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
<?php $content=ob_get_clean(); $page_scripts = "import('/assets/js/pages/backups.js').then(m=>m.init());"; include __DIR__.'/layout.php'; ?>
