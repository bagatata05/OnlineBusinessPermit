<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center" style="min-height: 100vh;">
            <div class="col-12 col-md-6 text-center">
                <div class="card shadow-lg">
                    <div class="card-body p-5">
                        <h1 class="display-1 text-primary mb-4">404</h1>
                        <h2 class="h4 mb-3">Page Not Found</h2>
                        <p class="text-gray mb-4">
                            The page you're looking for doesn't exist or has been moved.
                        </p>
                        <div class="d-flex justify-content-center gap-2">
                            <a href="index.php?page=login" class="btn btn-primary">
                                Go to Login
                            </a>
                            <a href="javascript:history.back()" class="btn btn-outline-primary">
                                Go Back
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
