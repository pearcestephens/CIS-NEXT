<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>429 - Too Many Requests | CIS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome - Latest Version with Fallback -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet" crossorigin="anonymous">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 text-center">
                <div class="error-template mt-5">
                    <h1>
                        <i class="fas fa-hourglass-half text-warning"></i>
                    </h1>
                    <h2>Too Many Requests</h2>
                    <div class="error-details mb-4">
                        You have made too many requests. Please wait a moment before trying again.
                    </div>
                    <div class="error-actions">
                        <a href="/" class="btn btn-primary btn-lg">
                            <i class="fas fa-home"></i> Take Me Home
                        </a>
                        <button onclick="window.location.reload()" class="btn btn-secondary btn-lg">
                            <i class="fas fa-sync"></i> Try Again
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
