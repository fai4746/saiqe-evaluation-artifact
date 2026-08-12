<?php
class DocumentController {
    public function showUpload(): void {
        Auth::requireRole([ROLE_CITIZEN]);
        $citizen = (new CitizenModel())->findByUserId(Session::get('user_id'));
        $apps    = $citizen ? (new ApplicationModel())->getByCitizen($citizen['citizen_id']) : [];
        $errors  = Session::get('doc_errors', []);
        Session::delete('doc_errors');
        $pageTitle = 'Upload Documents';
        include APP_ROOT . '/app/views/shared/layout_header.php';
        include APP_ROOT . '/app/views/citizen/document_upload.php';
        include APP_ROOT . '/app/views/shared/layout_footer.php';
    }

    public function upload(): void {
        Auth::requireRole([ROLE_CITIZEN, ROLE_OFFICER]);
        if (!Session::verifyCsrf($_POST['csrf_token'] ?? '')) {
            header('Location: ' . BASE_URL . '/documents/upload'); exit;
        }

        $userId  = Session::get('user_id');
        $appId   = (int)($_POST['application_id'] ?? 0);
        $app     = (new ApplicationModel())->findById($appId);
        if (!$app) { http_response_code(404); return; }

        // Citizens can only upload to own applications
        if (Session::get('role_id') === ROLE_CITIZEN) {
            $citizen = (new CitizenModel())->findByUserId($userId);
            if (!$citizen || (int)$app['citizen_id'] !== $citizen['citizen_id']) {
                http_response_code(403); return;
            }
        }

        $docModel = new DocumentModel();
        $existing = $docModel->countByApplication($appId);
        $audit    = new AuditLogModel();
        $errors   = [];
        $uploaded = 0;

        if (empty($_FILES['documents']['name'][0])) {
            $errors['file'] = 'No files selected.';
        } else {
            $files = $_FILES['documents'];
            $count = count($files['name']);
            for ($i = 0; $i < $count; $i++) {
                $finfo    = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($files['tmp_name'][$i]);

                $verdict = UploadValidator::validate([
                    'name'  => $files['name'][$i],
                    'size'  => $files['size'][$i],
                    'mime'  => $mimeType,
                    'error' => $files['error'][$i],
                ], $existing);

                if (!$verdict['ok']) {
                    switch ($verdict['code']) {
                        case UploadValidator::ERR_UPLOAD:
                            $errors['file'] = 'Upload error on file ' . htmlspecialchars($files['name'][$i]); continue 2;
                        case UploadValidator::ERR_COUNT:
                            $errors['file'] = 'Maximum ' . MAX_FILES . ' files allowed per application.'; break 2;
                        case UploadValidator::ERR_SIZE:
                            $errors[$i] = basename($files['name'][$i]) . ': File exceeds 5 MB limit.'; continue 2;
                        default:
                            $errors[$i] = basename($files['name'][$i]) . ': File type not allowed.'; continue 2;
                    }
                }

                $ext    = $verdict['ext'];
                $stored = UploadValidator::storedName($files['name'][$i], $ext);
                if (!move_uploaded_file($files['tmp_name'][$i], UPLOAD_DIR . $stored)) { $errors[$i] = 'Failed to save file.'; continue; }

                $docModel->create(['application_id'=>$appId,'original_filename'=>basename($files['name'][$i]),'stored_filename'=>$stored,'file_type'=>$mimeType,'file_size_bytes'=>$files['size'][$i],'uploaded_by'=>$userId]);
                $audit->log('Document Uploaded', 'application', $appId);
                $existing++;
                $uploaded++;
            }
        }

        if (!$errors) {
            // Recompute risk score after new document (unless defect disables it)
            if (!TrainingDefectModel::isActive('risk_score_not_recomputed')) {
                $case = (new CaseModel())->findByApplicationId($appId);
                if ($case) {
                    $wizardStarted = null;
                    $appData = (new ApplicationModel())->findById($appId);
                    if ($appData && !empty($appData['wizard_started_at'])) {
                        $wizardStarted = new DateTime($appData['wizard_started_at']);
                    }
                    (new RiskScoreEngine())->compute($appId, $wizardStarted);
                }
            }
        }

        if ($errors) {
            Session::set('doc_errors', $errors);
            header('Location: ' . BASE_URL . '/documents/upload'); exit;
        }
        Session::set('doc_success', "$uploaded file(s) uploaded successfully.");
        header('Location: ' . BASE_URL . '/documents/upload'); exit;
    }

    public function download(string $docId): void {
        Auth::requireRole([ROLE_OFFICER, ROLE_SUPERVISOR, ROLE_AUDITOR, ROLE_ADMIN, ROLE_QR]);
        $doc = (new DocumentModel())->findById((int)$docId);
        if (!$doc) { http_response_code(404); return; }

        $path = UPLOAD_DIR . $doc['stored_filename'];
        if (!file_exists($path)) { http_response_code(404); echo 'File not found on server.'; return; }

        header('Content-Type: ' . $doc['file_type']);
        header('Content-Disposition: attachment; filename="' . rawurlencode($doc['original_filename']) . '"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }
}
