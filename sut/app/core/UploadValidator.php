<?php
/**
 * UploadValidator — admission rules for citizen document uploads.
 *
 * Extracted from DocumentController so the rules are exercisable without a
 * live multipart request. The controller keeps ownership of filesystem effects
 * (finfo sniffing, move_uploaded_file); everything decidable from metadata
 * lives here.
 */
class UploadValidator {
    public const OK             = 'ok';
    public const ERR_UPLOAD     = 'upload_error';
    public const ERR_COUNT      = 'too_many_files';
    public const ERR_SIZE       = 'file_too_large';
    public const ERR_TYPE       = 'type_not_allowed';

    /** Extensions that map to an allowed MIME type, for the extension-only path. */
    public const EXTENSION_MAP = [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
    ];

    /**
     * @param array $file  ['name'=>string, 'size'=>int, 'mime'=>string, 'error'=>int]
     *                     'mime' is the content-sniffed type, not the client-declared one.
     * @param int $existingCount documents already attached to the application
     * @return array ['ok'=>bool, 'code'=>string, 'ext'=>?string]
     */
    public static function validate(array $file, int $existingCount = 0): array {
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'code' => self::ERR_UPLOAD, 'ext' => null];
        }
        if ($existingCount >= MAX_FILES) {
            return ['ok' => false, 'code' => self::ERR_COUNT, 'ext' => null];
        }

        // Defect: doc_size_limit_not_enforced — the 5 MB cap stops being applied
        if (!defectActive('doc_size_limit_not_enforced')
            && (int)($file['size'] ?? 0) > MAX_FILE_SIZE) {
            return ['ok' => false, 'code' => self::ERR_SIZE, 'ext' => null];
        }

        // Defect: file_type_extension_only — admission decided by the filename
        // extension instead of the sniffed content type, so a PHP payload named
        // invoice.pdf is accepted and stored.
        if (defectActive('file_type_extension_only')) {
            $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
            if (!isset(self::EXTENSION_MAP[$ext])) {
                return ['ok' => false, 'code' => self::ERR_TYPE, 'ext' => null];
            }
            return ['ok' => true, 'code' => self::OK, 'ext' => $ext === 'jpeg' ? 'jpg' : $ext];
        }

        $mime = (string)($file['mime'] ?? '');
        if (!isset(ALLOWED_MIME_TYPES[$mime])) {
            return ['ok' => false, 'code' => self::ERR_TYPE, 'ext' => null];
        }
        return ['ok' => true, 'code' => self::OK, 'ext' => ALLOWED_MIME_TYPES[$mime]];
    }

    /**
     * Name a file will be stored under on disk. Must not be derivable from
     * user input, or the upload directory becomes attacker-addressable.
     */
    public static function storedName(string $originalName, string $ext): string {
        // Defect: doc_path_traversal_stored_name — the client-supplied name is
        // reused as the storage name, carrying traversal sequences with it.
        if (defectActive('doc_path_traversal_stored_name')) {
            return $originalName;
        }
        return bin2hex(random_bytes(16)) . '.' . $ext;
    }
}
