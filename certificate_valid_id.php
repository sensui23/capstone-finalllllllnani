<?php
require_once 'config.php';
requireRole(['official', 'admin']);
$user = currentUser();

if (!canApproveCertificateRequests($user)) {
    http_response_code(403);
    exit('You are not authorized to view resident ID attachments.');
}

$stmt = $db->prepare('SELECT valid_id_path FROM certificate_requests WHERE request_id = ?');
$stmt->execute([(int) ($_GET['id'] ?? 0)]);
$relativePath = $stmt->fetchColumn();
$uploadDirectory = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'certificates');
$filename = basename(str_replace('\\', '/', (string) $relativePath));

if (
    !$relativePath
    || !preg_match('/^valid-id-\d+-[a-f0-9]+\.(jpg|png|pdf)$/i', $filename)
    || str_replace('\\', '/', (string) $relativePath) !== 'assets/uploads/certificates/' . $filename
    || $uploadDirectory === false
) {
    http_response_code(404);
    exit('Valid ID attachment not found.');
}

$filePath = realpath($uploadDirectory . DIRECTORY_SEPARATOR . $filename);
if ($filePath === false || !is_file($filePath) || !is_readable($filePath)) {
    http_response_code(404);
    exit('Valid ID attachment not found.');
}

$mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($filePath);
if (!in_array($mimeType, ['image/jpeg', 'image/png', 'application/pdf'], true)) {
    http_response_code(404);
    exit('Valid ID attachment not found.');
}

header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="resident-valid-id.' . strtolower(pathinfo($filename, PATHINFO_EXTENSION)) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
