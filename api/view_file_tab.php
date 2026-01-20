<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();
if (!$auth->checkAuth()) {
    header('HTTP/1.0 401 Unauthorized');
    exit();
}

$user = $auth->getCurrentUser();
$requirement_id = intval($_GET['id'] ?? 0);

if (!$requirement_id) {
    header('HTTP/1.0 400 Bad Request');
    exit();
}

$conn = getDBConnection();

// Get requirement with permit info
$stmt = $conn->prepare("
    SELECT pr.*, p.permit_id, b.owner_id, p.permit_number
    FROM permit_requirements pr
    JOIN permits p ON pr.permit_id = p.permit_id
    JOIN businesses b ON p.business_id = b.business_id
    WHERE pr.requirement_id = ?
");
$stmt->bind_param("i", $requirement_id);
$stmt->execute();
$requirement = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$requirement || !$requirement['file_path']) {
    header('HTTP/1.0 404 Not Found');
    exit();
}

// Check permissions (owner, admin, or staff can view)
if ($user['role'] !== 'admin' && $user['role'] !== 'staff' && $requirement['owner_id'] != $user['user_id']) {
    header('HTTP/1.0 403 Forbidden');
    exit();
}

$file_path = __DIR__ . '/../' . $requirement['file_path'];

if (!file_exists($file_path)) {
    header('HTTP/1.0 404 Not Found');
    exit();
}

// Get file info
$file_info = pathinfo($file_path);
$file_extension = strtolower($file_info['extension'] ?? '');
$file_name = $requirement['file_name'] ?: basename($file_path);

// Determine file type and create appropriate viewer
switch ($file_extension) {
    case 'pdf':
        // Serve PDF directly for browser viewing
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . htmlspecialchars($file_name) . '"');
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: max-age=3600, must-revalidate');
        header('Pragma: public');
        readfile($file_path);
        break;
        
    case 'jpg':
    case 'jpeg':
    case 'png':
    case 'gif':
    case 'bmp':
    case 'webp':
        // Create an HTML page with the image
        $mime_type = mime_content_type($file_path);
        $image_data = base64_encode(file_get_contents($file_path));
        
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($requirement['requirement_type']) . ' - ' . htmlspecialchars($file_name) . '</title>
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            text-align: center;
        }
        .header {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .image-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: inline-block;
        }
        .document-image {
            max-width: 100%;
            max-height: 80vh;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .download-btn {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
            margin-top: 15px;
        }
        .download-btn:hover {
            background: #0056b3;
        }
        .file-info {
            color: #666;
            font-size: 14px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>' . htmlspecialchars($requirement['requirement_type']) . '</h2>
        <p><strong>File:</strong> ' . htmlspecialchars($file_name) . '</p>
        <p class="file-info">
            <strong>Permit:</strong> ' . htmlspecialchars($requirement['permit_number']) . ' | 
            <strong>Size:</strong> ' . number_format(filesize($file_path)) . ' bytes | 
            <strong>Status:</strong> ' . ($requirement['verified'] ? 'Verified' : 'Submitted') . '
        </p>
        <a href="download_document.php?id=' . $requirement_id . '" class="download-btn" target="_blank">
            ⬇️ Download File
        </a>
    </div>
    
    <div class="image-container">
        <img src="data:' . $mime_type . ';base64,' . $image_data . '" 
             alt="' . htmlspecialchars($file_name) . '" 
             class="document-image">
    </div>
</body>
</html>';
        break;
        
    case 'txt':
    case 'log':
        // Create an HTML page with text content
        $text_content = file_get_contents($file_path);
        $escaped_content = htmlspecialchars($text_content, ENT_QUOTES, 'UTF-8');
        
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($requirement['requirement_type']) . ' - ' . htmlspecialchars($file_name) . '</title>
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #f5f5f5;
        }
        .header {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .text-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .text-content {
            background: #f8f9fa;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 4px;
            white-space: pre-wrap;
            font-family: monospace;
            font-size: 14px;
            line-height: 1.4;
            max-height: 70vh;
            overflow-y: auto;
        }
        .download-btn {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
            margin-top: 15px;
        }
        .download-btn:hover {
            background: #0056b3;
        }
        .file-info {
            color: #666;
            font-size: 14px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>' . htmlspecialchars($requirement['requirement_type']) . '</h2>
        <p><strong>File:</strong> ' . htmlspecialchars($file_name) . '</p>
        <p class="file-info">
            <strong>Permit:</strong> ' . htmlspecialchars($requirement['permit_number']) . ' | 
            <strong>Size:</strong> ' . number_format(filesize($file_path)) . ' bytes | 
            <strong>Status:</strong> ' . ($requirement['verified'] ? 'Verified' : 'Submitted') . '
        </p>
        <a href="download_document.php?id=' . $requirement_id . '" class="download-btn" target="_blank">
            ⬇️ Download File
        </a>
    </div>
    
    <div class="text-container">
        <div class="text-content">' . $escaped_content . '</div>
    </div>
</body>
</html>';
        break;
        
    default:
        // For unsupported file types, show download page
        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($requirement['requirement_type']) . ' - ' . htmlspecialchars($file_name) . '</title>
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            text-align: center;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            max-width: 500px;
            margin: 50px auto;
        }
        .file-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        .download-btn {
            background: #007bff;
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
            font-size: 16px;
            margin-top: 20px;
        }
        .download-btn:hover {
            background: #0056b3;
        }
        .file-info {
            color: #666;
            font-size: 14px;
            margin-top: 15px;
        }
        h2 {
            color: #333;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="file-icon">📄</div>
        <h2>' . htmlspecialchars($requirement['requirement_type']) . '</h2>
        <p><strong>File:</strong> ' . htmlspecialchars($file_name) . '</p>
        <p class="file-info">
            <strong>Type:</strong> ' . htmlspecialchars(strtoupper($file_extension)) . ' file<br>
            <strong>Size:</strong> ' . number_format(filesize($file_path)) . ' bytes<br>
            <strong>Permit:</strong> ' . htmlspecialchars($requirement['permit_number']) . '<br>
            <strong>Status:</strong> ' . ($requirement['verified'] ? 'Verified' : 'Submitted') . '
        </p>
        <p style="color: #666; margin: 20px 0;">
            This file type cannot be previewed in the browser.<br>
            Please download the file to view its contents.
        </p>
        <a href="download_document.php?id=' . $requirement_id . '" class="download-btn">
            ⬇️ Download File
        </a>
    </div>
</body>
</html>';
        break;
}

$conn->close();
?>
