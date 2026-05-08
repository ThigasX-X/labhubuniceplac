<?php
class Upload
{
    private static array $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

    public static function foto(array $file, int $userId, ?string $fotoAnterior = null): ?string
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, self::$allowedExtensions)) {
            return null;
        }

        if (!is_dir(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0777, true);
        }

        // Remove foto anterior se não for a padrão
        if ($fotoAnterior && strpos($fotoAnterior, 'padrao') === false) {
            $caminhoAntigo = ROOT_PATH . '/public/' . ltrim($fotoAnterior, '/');
            if (file_exists($caminhoAntigo)) {
                unlink($caminhoAntigo);
            }
        }

        $nomeArquivo = "user_{$userId}_" . time() . ".{$ext}";
        $destino     = UPLOAD_DIR . $nomeArquivo;

        if (move_uploaded_file($file['tmp_name'], $destino)) {
            return '/uploads/' . $nomeArquivo;
        }

        return null;
    }
}
