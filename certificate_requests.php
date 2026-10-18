<?php
require_once 'config.php';
requireRole(['resident', 'official', 'admin']);
$user = currentUser();
$isStaff = in_array($user['role'], ['official', 'admin'], true);
$canApprove = canApproveCertificateRequests($user);
$errors = [];

function certificateBadgeClass($status) {
    return $status === 'Rejected' ? 'badge-rejected' : ($status === 'Processing' ? 'badge-progress' : (in_array($status, ['APPROVED', 'Ready for Pick-up', 'Released'], true) ? 'badge-resolved' : 'badge-pending'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isStaff) {
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    $allowed = ['Processing', 'APPROVED', 'Released', 'Rejected'];
    if ($requestId < 1 || !in_array($status, $allowed, true)) {
        $errors[] = 'Choose a valid request status.';
    } elseif ($status === 'APPROVED' && !$canApprove) {
        $errors[] = 'Only Maria Santos or an administrator can approve certificate requests.';
    } else {
        $releaseDate = in_array($status, ['APPROVED', 'Released'], true) ? date('Y-m-d H:i:s') : null;
        $requestStmt = $db->prepare('SELECT claim_method, digital_generated_at, status FROM certificate_requests WHERE request_id = ?');
        $requestStmt->execute([$requestId]);
        $requestRecord = $requestStmt->fetch(PDO::FETCH_ASSOC);
        if (!$requestRecord) {
            $errors[] = 'Certificate request not found.';
        } elseif ($status === 'Released' && !in_array($requestRecord['status'], ['APPROVED', 'Ready for Pick-up', 'Released'], true)) {
            $errors[] = 'A certificate must be approved before it can be marked as released.';
        } else {
            $isApproved = in_array($status, ['APPROVED', 'Released'], true);
            $digitalGeneratedAt = $requestRecord['claim_method'] === 'digital' && $isApproved
                ? ($requestRecord['digital_generated_at'] ?: $releaseDate)
                : null;
            $update = $db->prepare('UPDATE certificate_requests SET status = ?, notes = ?, release_date = ?, digital_generated_at = ?, processed_by = ? WHERE request_id = ?');
            $update->execute([$status, $notes ?: null, $releaseDate, $digitalGeneratedAt, $user['id'], $requestId]);
            setFlash($status === 'APPROVED'
                ? ($requestRecord['claim_method'] === 'digital'
                    ? 'Request approved. The signed digital certificate is now available.'
                    : 'Request approved. The unsigned certificate is ready to print for in-person signing.')
                : 'Certificate request updated successfully.', 'success');
            header('Location: certificate_requests.php');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isStaff) {
    $fullName = trim($_POST['full_name'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $civilStatus = trim($_POST['civil_status'] ?? '');
    $claimMethod = $_POST['claim_method'] ?? 'pickup';
    $validIdPath = null;
    if (!in_array($claimMethod, ['pickup', 'digital'], true)) {
        $errors[] = 'Choose a valid claim method.';
    }
    if ($fullName === '' || preg_match('/\s/', $fullName) !== 1) {
        $errors[] = 'Please enter your complete name, including your first name and surname.';
    }
    if ($purpose === '' || $address === '') {
        $errors[] = 'Purpose and address are required.';
    }
    $validId = $_FILES['valid_id'] ?? null;
    if ($validId && ($validId['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if (($validId['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || $validId['size'] > 5 * 1024 * 1024) {
            $errors[] = 'The valid ID must be a file up to 5 MB.';
        } else {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($validId['tmp_name']);
            $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
            if (!isset($extensions[$mime])) {
                $errors[] = 'Valid ID must be a JPG, PNG, or PDF file.';
            } else {
                $directory = __DIR__ . '/assets/uploads/certificates';
                if (!is_dir($directory)) mkdir($directory, 0755, true);
                $filename = 'valid-id-' . $user['id'] . '-' . bin2hex(random_bytes(8)) . '.' . $extensions[$mime];
                if (move_uploaded_file($validId['tmp_name'], $directory . '/' . $filename)) {
                    $validIdPath = 'assets/uploads/certificates/' . $filename;
                } else {
                    $errors[] = 'The valid ID could not be uploaded.';
                }
            }
        }
    }
    if (!$errors) {
        $insert = $db->prepare('INSERT INTO certificate_requests (resident_id, purpose, full_name, birthdate, address, civil_status, phone, valid_id_path, claim_method) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $insert->execute([$user['id'], $purpose, $fullName, $user['birthdate'] ?: null, $address, $civilStatus ?: null, $user['phone'] ?: null, $validIdPath, $claimMethod]);
        setFlash('Your certificate request has been submitted.', 'success');
        header('Location: certificate_requests.php');
        exit;
    }
}

if ($isStaff) {
    $requests = $db->query('SELECT c.*, u.email AS resident_email FROM certificate_requests c JOIN users u ON u.id = c.resident_id ORDER BY c.requested_at DESC')->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $db->prepare('SELECT * FROM certificate_requests WHERE resident_id = ? ORDER BY requested_at DESC');
    $stmt->execute([$user['id']]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$flash = flashMessage();
$dashboard = $isStaff ? ($user['role'] === 'admin' ? 'admin_dashboard.php' : 'official_dashboard.php') : 'resident_dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isStaff ? 'Manage' : 'Request' ?> Barangay Certificates</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=20260909-logo">
</head>
<body>
<main class="main" style="max-width:1240px;margin:auto;">
  <div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;">
    <div><h1 class="page-title"><?= $isStaff ? 'Certificate Requests' : 'Barangay Certificate' ?></h1><p class="page-sub"><?= $isStaff ? 'Review, update, and release resident certificate requests.' : 'Request an official certificate and track its release status.' ?></p></div>
    <a href="<?= $dashboard ?>" class="btn-secondary">← Back to dashboard</a>
  </div>
  <?php if ($flash): ?><div class="alert alert-success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
  <?php foreach ($errors as $error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endforeach; ?>

  <?php if (!$isStaff): ?>
  <div class="card">
    <div class="card-title">New Certificate Request</div>
    <form method="post" enctype="multipart/form-data">
      <div class="form-group"><label class="form-label" for="purpose">Purpose of certificate *</label><input class="form-control" id="purpose" name="purpose" value="<?= htmlspecialchars($_POST['purpose'] ?? '') ?>" placeholder="e.g. Employment, scholarship, legal requirement" required></div>
    <div class="form-row"><div class="form-group"><label class="form-label" for="full_name">Complete name *</label><input class="form-control" id="full_name" name="full_name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" placeholder="e.g. Juan Dela Cruz" required><small class="form-help">Enter the name exactly as it should appear on the certificate.</small></div><div class="form-group"><label class="form-label">Birthdate</label><input class="form-control" value="<?= htmlspecialchars($user['birthdate'] ?: 'Not provided') ?>" disabled></div></div>
      <div class="form-group"><label class="form-label" for="address">Complete address *</label><input class="form-control" id="address" name="address" value="<?= htmlspecialchars($_POST['address'] ?? ($user['address'] ?? '')) ?>" required></div>
    <div class="form-row"><div class="form-group"><label class="form-label" for="civil_status">Civil status</label><select class="form-control" id="civil_status" name="civil_status"><option value="">Select</option><?php foreach (['Single','Married','Widowed','Separated'] as $option): ?><option <?= ($_POST['civil_status'] ?? '') === $option ? 'selected' : '' ?>><?= $option ?></option><?php endforeach; ?></select></div><div class="form-group"><label class="form-label" for="valid_id">Valid ID attachment</label><input class="form-control" id="valid_id" type="file" name="valid_id" accept="image/jpeg,image/png,application/pdf"><small class="form-help">Optional. JPG, PNG, or PDF up to 5 MB.</small></div></div>
    <div class="form-group"><span class="form-label">Claim method *</span><label style="display:flex;align-items:flex-start;gap:9px;margin:10px 0"><input type="radio" name="claim_method" value="pickup" <?= ($_POST['claim_method'] ?? 'pickup') === 'pickup' ? 'checked' : '' ?> required><span><strong>Pick-up</strong> — In-person at Barangay Hall</span></label><label style="display:flex;align-items:flex-start;gap:9px;margin:10px 0"><input type="radio" name="claim_method" value="digital" <?= ($_POST['claim_method'] ?? '') === 'digital' ? 'checked' : '' ?> required><span><strong>Digital Copy / Online Release</strong> — View and print/download a clearance preview after approval</span></label><small id="claim-method-help" class="form-help" aria-live="polite">When approved, bring your request number and a valid ID to Barangay Hall to claim the physical copy.</small></div>
      <button class="btn-primary" type="submit">Submit Certificate Request</button>
    </form>
  </div>
  <?php endif; ?>

  <div class="card"><div class="card-title"><?= $isStaff ? 'Incoming Requests' : 'My Requests' ?></div>
        <div class="overflow-table"><table class="data-table"><thead><tr><th>Request</th><?php if ($isStaff): ?><th>Resident</th><?php endif; ?><th>Purpose</th><th>Claim Method</th><th>Requested</th><th>Status</th><th>Action</th></tr></thead><tbody>
        <?php if (!$requests): ?><tr><td colspan="<?= $isStaff ? 7 : 6 ?>">No certificate requests yet.</td></tr><?php endif; ?>
        <?php foreach ($requests as $request): ?>
            <?php
                $isDigitalRequest = ($request['claim_method'] ?? 'pickup') === 'digital';
                $approved = in_array($request['status'], ['APPROVED', 'Ready for Pick-up', 'Released'], true);
                $validIdExtension = strtolower(pathinfo((string) ($request['valid_id_path'] ?? ''), PATHINFO_EXTENSION));
                $requestDetails = htmlspecialchars(json_encode([
                        'requestId' => (int) $request['request_id'],
                        'fullName' => $request['full_name'],
                        'email' => $request['resident_email'] ?? '',
                        'purpose' => $request['purpose'],
                        'address' => $request['address'],
                        'birthdate' => $request['birthdate'] ?: 'Not provided',
                        'civilStatus' => $request['civil_status'] ?: 'Not provided',
                        'phone' => $request['phone'] ?: 'Not provided',
                        'validIdUrl' => $canApprove && !empty($request['valid_id_path']) ? 'certificate_valid_id.php?id=' . (int) $request['request_id'] : '',
                        'validIdExtension' => $validIdExtension,
                        'claimMethod' => $isDigitalRequest ? 'Online Release' : 'For Pick-up at Barangay Hall',
                        'status' => $request['status'] === 'Ready for Pick-up' ? 'APPROVED' : $request['status'],
                        'statusBadge' => certificateBadgeClass($request['status']),
                        'notes' => $request['notes'] ?: 'No staff notes',
                        'approved' => $approved,
                        'previewUrl' => 'print_certificate.php?id=' . (int) $request['request_id']
                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
            ?>
            <tr>
                <td>#<?= (int) $request['request_id'] ?></td>
                <?php if ($isStaff): ?><td><?= htmlspecialchars($request['full_name']) ?><br><small><?= htmlspecialchars($request['resident_email']) ?></small></td><?php endif; ?>
                <td><?= htmlspecialchars($request['purpose']) ?></td>
                <td><span class="badge <?= $isDigitalRequest ? 'badge-progress' : 'badge-pending' ?>"><?= $isDigitalRequest ? 'Online Release' : 'For Pick-up at Barangay Hall' ?></span></td>
                <td><?= date('M d, Y', strtotime($request['requested_at'])) ?></td>
                <td><span class="badge <?= certificateBadgeClass($request['status']) ?>"><?= htmlspecialchars($request['status'] === 'Ready for Pick-up' ? 'APPROVED' : $request['status']) ?></span></td>
                <td>
                    <?php if ($isStaff): ?>
                        <button type="button" class="btn-secondary" data-certificate-request="<?= $requestDetails ?>" onclick="openCertificateDetails(this)" style="padding:7px 10px;margin-bottom:5px">Details</button>
                        <form method="post" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                            <input type="hidden" name="request_id" value="<?= (int) $request['request_id'] ?>">
                            <select name="status" class="form-control" style="width:auto;padding:7px">
                                <option value="Processing" <?= $request['status'] === 'Processing' ? 'selected' : '' ?>>Processing</option>
                                <option value="APPROVED" <?= $request['status'] === 'APPROVED' || $request['status'] === 'Ready for Pick-up' ? 'selected' : '' ?> <?= $canApprove ? '' : 'disabled' ?>>Approve Request</option>
                                <option value="Released" <?= $request['status'] === 'Released' ? 'selected' : '' ?> <?= $approved ? '' : 'disabled' ?>>Released</option>
                                <option value="Rejected" <?= $request['status'] === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                            <input name="notes" class="form-control" style="width:150px;padding:7px" placeholder="Note" value="<?= htmlspecialchars($request['notes'] ?? '') ?>">
                            <button class="btn-secondary" type="submit" style="padding:7px 10px">Update</button>
                            <?php if ($approved): ?><a href="print_certificate.php?id=<?= (int) $request['request_id'] ?>" target="_blank" rel="noopener" class="btn-primary" style="padding:7px 10px;text-decoration:none"><?= $isDigitalRequest ? 'Download / Release' : 'Print unsigned copy' ?></a><?php endif; ?>
                        </form>
                        <?php if (!$isDigitalRequest && $request['status'] !== 'Released'): ?><small>For Pick-up at Barangay Hall</small><?php endif; ?>
                    <?php else: ?>
                        <?php if ($approved && $isDigitalRequest): ?><a href="print_certificate.php?id=<?= (int) $request['request_id'] ?>" target="_blank" rel="noopener" class="action-link">View / Download Digital Copy</a>
                        <?php elseif (!$isDigitalRequest && $request['status'] === 'Released'): ?>Physical copy released
                        <?php elseif ($approved): ?><strong>Approved for pick-up at Barangay Hall</strong><br><small>Bring request #<?= (int) $request['request_id'] ?> and a valid ID.</small>
                        <?php else: ?>Awaiting processing<?php endif; ?>
                        <?php if (!empty($request['notes'])): ?>
                            <div style="margin-top:8px;padding:8px 10px;border-left:3px solid var(--primary);background:var(--surface,#f5f7fb);border-radius:4px;max-width:320px">
                                <strong>Staff notes:</strong>
                                <div style="white-space:pre-wrap"><?= nl2br(htmlspecialchars($request['notes'], ENT_QUOTES, 'UTF-8')) ?></div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
  </div>
</main>
<?php if ($isStaff): ?>
<div class="modal-overlay" id="certificate-details-modal" role="dialog" aria-modal="true" aria-labelledby="certificate-modal-title" aria-hidden="true" onclick="if (event.target === this) closeCertificateDetails()">
    <div class="modal-box" style="max-width:900px;width:min(100%,900px)">
        <button type="button" class="modal-close" aria-label="Close request details" onclick="closeCertificateDetails()">×</button>
        <div class="modal-sub" id="certificate-modal-request-number"></div>
        <h2 class="modal-title" id="certificate-modal-title">Clearance Request Details</h2>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin:14px 0 20px">
            <span class="badge" id="certificate-modal-claim-method"></span>
            <span class="badge" id="certificate-modal-status"></span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;font-size:14px">
            <div><strong>Resident:</strong> <span id="certificate-modal-resident"></span></div>
            <div><strong>Email:</strong> <span id="certificate-modal-email"></span></div>
            <div><strong>Purpose:</strong> <span id="certificate-modal-purpose"></span></div>
            <div><strong>Birthdate:</strong> <span id="certificate-modal-birthdate"></span></div>
            <div><strong>Civil status:</strong> <span id="certificate-modal-civil-status"></span></div>
            <div><strong>Phone:</strong> <span id="certificate-modal-phone"></span></div>
            <div style="grid-column:1/-1"><strong>Address:</strong> <span id="certificate-modal-address"></span></div>
            <div style="grid-column:1/-1"><strong>Staff notes:</strong> <span id="certificate-modal-notes"></span></div>
        </div>
        <?php if ($canApprove): ?><section id="certificate-valid-id-section" style="margin-top:18px">
            <strong>Resident's Valid ID</strong>
            <p id="certificate-valid-id-empty" class="form-help">No valid ID was attached to this request.</p>
            <img id="certificate-valid-id-image" alt="Resident's attached valid ID" style="display:none;max-width:100%;max-height:520px;margin-top:10px;border:1px solid var(--border);border-radius:8px">
            <iframe id="certificate-valid-id-pdf" title="Resident's attached valid ID PDF" style="display:none;width:100%;height:520px;margin-top:10px;border:1px solid var(--border);border-radius:8px;background:#fff"></iframe>
            <a id="certificate-valid-id-open" class="action-link" target="_blank" rel="noopener" style="display:none;margin-top:10px">Open attached Valid ID</a>
        </section><?php endif; ?>
        <div id="certificate-pickup-instructions" class="alert alert-warn" style="margin-top:18px;display:none">Approved for pick-up at Barangay Hall. Print the unsigned certificate and have it physically signed and stamped before handover.</div>
        <div id="certificate-digital-preview" style="margin-top:18px;display:none">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:10px">
                <strong>Generated clearance preview</strong>
                <a id="certificate-download-release" class="btn-primary" target="_blank" rel="noopener" style="text-decoration:none">Download / Release Digital Clearance</a>
            </div>
            <iframe id="certificate-preview-frame" title="Generated barangay clearance preview" style="width:100%;height:520px;border:1px solid var(--border);border-radius:10px;background:#fff"></iframe>
            <small class="form-help">Use “Print / Save as PDF” in the preview to download a PDF copy.</small>
        </div>
        <p id="certificate-digital-pending" class="alert alert-warn" style="margin-top:18px;display:none">Online Release selected. The clearance preview and release button will be available after approval.</p>
    </div>
</div>
<script>
let certificateDetailsTrigger = null;
function openCertificateDetails(button) {
    const request = JSON.parse(button.dataset.certificateRequest);
    const modal = document.getElementById('certificate-details-modal');
    certificateDetailsTrigger = button;
    document.getElementById('certificate-modal-request-number').textContent = 'Request #' + request.requestId;
    document.getElementById('certificate-modal-title').textContent = 'Clearance Request Details';
    document.getElementById('certificate-modal-claim-method').textContent = request.claimMethod;
    document.getElementById('certificate-modal-claim-method').className = 'badge ' + (request.claimMethod === 'Online Release' ? 'badge-progress' : 'badge-pending');
    document.getElementById('certificate-modal-status').textContent = request.status;
    document.getElementById('certificate-modal-status').className = 'badge ' + request.statusBadge;
    const detailValues = {
        'certificate-modal-resident': request.fullName,
        'certificate-modal-email': request.email,
        'certificate-modal-purpose': request.purpose,
        'certificate-modal-birthdate': request.birthdate,
        'certificate-modal-civil-status': request.civilStatus,
        'certificate-modal-phone': request.phone,
        'certificate-modal-address': request.address,
        'certificate-modal-notes': request.notes
    };
    Object.keys(detailValues).forEach(function(id) { document.getElementById(id).textContent = detailValues[id]; });
    const validIdImage = document.getElementById('certificate-valid-id-image');
    if (validIdImage) {
        const validIdPdf = document.getElementById('certificate-valid-id-pdf');
        const validIdOpen = document.getElementById('certificate-valid-id-open');
        const validIdEmpty = document.getElementById('certificate-valid-id-empty');
        const hasValidId = Boolean(request.validIdUrl);
        validIdImage.style.display = hasValidId && ['jpg', 'jpeg', 'png'].includes(request.validIdExtension) ? 'block' : 'none';
        validIdPdf.style.display = hasValidId && request.validIdExtension === 'pdf' ? 'block' : 'none';
        validIdOpen.style.display = hasValidId ? 'inline-block' : 'none';
        validIdEmpty.style.display = hasValidId ? 'none' : 'block';
        validIdImage.src = hasValidId && ['jpg', 'jpeg', 'png'].includes(request.validIdExtension) ? request.validIdUrl : '';
        validIdPdf.src = hasValidId && request.validIdExtension === 'pdf' ? request.validIdUrl : 'about:blank';
        validIdOpen.href = hasValidId ? request.validIdUrl : '#';
    }
    const isDigital = request.claimMethod === 'Online Release';
    const isReady = request.approved;
    document.getElementById('certificate-pickup-instructions').style.display = isDigital ? 'none' : 'block';
    document.getElementById('certificate-digital-preview').style.display = isDigital && isReady ? 'block' : 'none';
    document.getElementById('certificate-digital-pending').style.display = isDigital && !isReady ? 'block' : 'none';
    const preview = document.getElementById('certificate-preview-frame');
    const releaseLink = document.getElementById('certificate-download-release');
    preview.src = isDigital && isReady ? request.previewUrl : 'about:blank';
    releaseLink.href = request.previewUrl;
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    modal.querySelector('.modal-close').focus();
}
function closeCertificateDetails() {
    const modal = document.getElementById('certificate-details-modal');
    if (!modal.classList.contains('open')) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.getElementById('certificate-preview-frame').src = 'about:blank';
    const validIdPdf = document.getElementById('certificate-valid-id-pdf');
    const validIdImage = document.getElementById('certificate-valid-id-image');
    if (validIdPdf) validIdPdf.src = 'about:blank';
    if (validIdImage) validIdImage.src = '';
    if (certificateDetailsTrigger) certificateDetailsTrigger.focus();
    certificateDetailsTrigger = null;
}
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') closeCertificateDetails();
});
</script>
<?php endif; ?>
<?php if (!$isStaff): ?>
<script>
document.querySelectorAll('input[name="claim_method"]').forEach(function(option) {
    option.addEventListener('change', function() {
        document.getElementById('claim-method-help').textContent = this.value === 'digital'
            ? 'After staff approval, your clearance preview will be available here to view, print, or save as PDF.'
            : 'When approved, bring your request number and a valid ID to Barangay Hall to claim the physical copy.';
    });
});
const selectedClaimMethod = document.querySelector('input[name="claim_method"]:checked');
if (selectedClaimMethod) selectedClaimMethod.dispatchEvent(new Event('change'));
</script>
<?php endif; ?>
</body></html>