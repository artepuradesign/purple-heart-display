<?php
/**
 * Utility para salvar arquivos base64 em disco na pasta arquivosupload
 */
class FileUpload {
    private static $uploadDir;

    public static function getUploadDir() {
        if (!self::$uploadDir) {
            self::$uploadDir = realpath(__DIR__ . '/../../') . '/arquivosupload/';
        }
        if (!is_dir(self::$uploadDir)) {
            mkdir(self::$uploadDir, 0755, true);
        }
        return self::$uploadDir;
    }

    /**
     * Salva um arquivo base64 em disco com nome padronizado
     * @param string $base64Data - dados base64 (pode incluir header data:...)
     * @param string $originalName - nome original do arquivo
     * @param string $prefix - prefixo para o nome (ex: "ped_42_anexo1")
     * @return string|null - nome do arquivo salvo ou null em caso de erro
     */
    public static function saveBase64File($base64Data, $originalName, $prefix) {
        if (empty($base64Data)) return null;

        $dir = self::getUploadDir();

        // Extrair dados base64 (remover header data:xxx;base64,)
        $data = $base64Data;
        if (strpos($data, ',') !== false) {
            $data = explode(',', $data, 2)[1];
        }

        $decoded = base64_decode($data, true);
        if ($decoded === false) return null;

        // Extrair extensão do nome original
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        if (empty($ext)) $ext = 'pdf';
        $ext = strtolower($ext);

        // Nome padronizado: prefix_YYYYMMDD_HHmmss.ext
        $timestamp = date('Ymd_His');
        $standardName = "{$prefix}_{$timestamp}.{$ext}";

        $filePath = $dir . $standardName;
        $result = file_put_contents($filePath, $decoded);

        if ($result === false) return null;

        return $standardName;
    }

    /**
     * Deleta um arquivo do diretório de uploads
     * @param string $filename - nome do arquivo
     * @return bool
     */
    public static function deleteFile($filename) {
        if (empty($filename)) return false;
        $path = self::getUploadDir() . basename($filename);
        if (file_exists($path)) {
            return unlink($path);
        }
        return false;
    }

    /**
     * Retorna o caminho completo de um arquivo
     * @param string $filename
     * @return string|null
     */
    public static function getFilePath($filename) {
        if (empty($filename)) return null;
        $path = self::getUploadDir() . basename($filename);
        return file_exists($path) ? $path : null;
    }

    /**
     * Serve um arquivo para download
     * @param string $filename
     * @param string|null $downloadName - nome para download (opcional)
     */
    public static function serveFile($filename, $downloadName = null) {
        $path = self::getFilePath($filename);
        if (!$path) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Arquivo não encontrado']);
            return;
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
        ];
        $mime = $mimeTypes[$ext] ?? 'application/octet-stream';
        $dlName = $downloadName ?: $filename;

        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . $dlName . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: public, max-age=86400');
        readfile($path);
        exit;
    }
}
