<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Error | CIS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .debug-error { font-family: 'Courier New', monospace; }
        .stack-trace { max-height: 400px; overflow-y: auto; }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid mt-3">
        <div class="row">
            <div class="col-12">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        <h4 class="mb-0"><i class="fas fa-bug"></i> Debug Error</h4>
                    </div>
                    <div class="card-body debug-error">
                        <h5>Exception: <?php echo htmlspecialchars(get_class($e)); ?></h5>
                        <p><strong>Message:</strong> <?php echo htmlspecialchars($e->getMessage()); ?></p>
                        <p><strong>File:</strong> <?php echo htmlspecialchars($e->getFile()); ?></p>
                        <p><strong>Line:</strong> <?php echo $e->getLine(); ?></p>
                        
                        <h6>Stack Trace:</h6>
                        <div class="stack-trace bg-dark text-light p-3 rounded">
                            <pre><?php echo htmlspecialchars($e->getTraceAsString()); ?></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-6">
                <div class="card">
                    <div class="card-header">Request Information</div>
                    <div class="card-body debug-error">
                        <pre><?php print_r($_SERVER); ?></pre>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card">
                    <div class="card-header">Environment Variables</div>
                    <div class="card-body debug-error">
                        <pre><?php print_r($_ENV); ?></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
