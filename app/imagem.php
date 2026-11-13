<?php
defined('JC') || exit;

const JC_UPLOAD_MAX = 6 * 1024 * 1024; // 6 MB por arquivo (o painel já reduz antes de enviar)
const JC_UPLOAD_TIPOS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

/**
 * Valida um arquivo de $_FILES e devolve a imagem GD ou uma mensagem de erro.
 * Checa erro de envio, tamanho, tipo real (finfo) e se é mesmo uma imagem decodificável.
 * @return GdImage|string
 */
function upload_imagem(array $f)
{
    if (!isset($f['error']) || is_array($f['error'])) {
        return 'Envio inválido.';
    }
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || ($f['size'] ?? 0) > JC_UPLOAD_MAX) {
        return 'A imagem passa de 6 MB.';
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        return 'Não foi possível receber a imagem.';
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset(JC_UPLOAD_TIPOS[$mime])) {
        return 'Envie a foto em JPG, PNG ou WEBP.';
    }
    $info = @getimagesize($f['tmp_name']);
    if (!$info || $info[0] < 200 || $info[1] < 200 || $info[0] * $info[1] > 25_000_000) {
        return 'A imagem precisa ter entre 200 px e 25 megapixels.';
    }
    $img = @imagecreatefromstring((string) file_get_contents($f['tmp_name']));
    if (!$img) {
        return 'Não foi possível ler a imagem.';
    }
    return $img;
}

/** Recorta no centro para a proporção pedida e redimensiona. */
function imagem_recorte($src, int $w, int $h, bool $alpha = false)
{
    $sw = imagesx($src);
    $sh = imagesy($src);
    $r = $w / $h;
    if ($sw / $sh > $r) {
        $cw = (int) round($sh * $r);
        $ch = $sh;
    } else {
        $cw = $sw;
        $ch = (int) round($sw / $r);
    }
    $sx = (int) (($sw - $cw) / 2);
    $sy = (int) (($sh - $ch) / 2);
    $dst = imagecreatetruecolor($w, $h);
    if ($alpha) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    }
    imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $w, $h, $cw, $ch);
    return $dst;
}

/**
 * Salva em uploads/<pasta>/ com nome aleatório. Sempre recodifica (remove metadados e qualquer conteúdo embutido).
 * Retorna o caminho relativo à raiz do site.
 */
function imagem_salvar($img, string $pasta, bool $alpha = false): string
{
    $dir = JC_ROOT . '/uploads/' . $pasta;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Pasta de upload indisponível');
    }
    $nome = bin2hex(random_bytes(10));
    if (function_exists('imagewebp')) {
        $arq = $nome . '.webp';
        if ($alpha) {
            imagesavealpha($img, true);
        }
        imagewebp($img, $dir . '/' . $arq, 80);
    } elseif ($alpha) {
        $arq = $nome . '.png';
        imagesavealpha($img, true);
        imagepng($img, $dir . '/' . $arq, 8);
    } else {
        $arq = $nome . '.jpg';
        imagejpeg($img, $dir . '/' . $arq, 82);
    }
    return 'uploads/' . $pasta . '/' . $arq;
}

/** Apaga um arquivo enviado (somente dentro de uploads/). */
function imagem_apagar(?string $rel): void
{
    if (!$rel || !str_starts_with($rel, 'uploads/') || str_contains($rel, '..')) {
        return;
    }
    $f = JC_ROOT . '/' . $rel;
    if (is_file($f)) {
        @unlink($f);
    }
}

/** Reduz mantendo a proporção: o maior lado passa a ter no máximo $maxLado px. */
function imagem_reduzir($src, int $maxLado = 1600)
{
    $w = imagesx($src);
    $h = imagesy($src);
    if (max($w, $h) <= $maxLado) {
        return $src;
    }
    $f = $maxLado / max($w, $h);
    $nw = max(1, (int) round($w * $f));
    $nh = max(1, (int) round($h * $f));
    $dst = imagecreatetruecolor($nw, $nh);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    return $dst;
}

const JC_VIDEO_MAX = 50 * 1024 * 1024; // 50 MB

/**
 * Valida e guarda um vídeo MP4 em uploads/<pasta>/. Não dá para recodificar vídeo na hospedagem
 * compartilhada, então a checagem é rígida: tamanho, tipo real (finfo) e assinatura "ftyp" do MP4.
 * @return string caminho relativo ao site, ou mensagem de erro iniciada por "!"
 */
function upload_video_salvar(array $f, string $pasta): string
{
    if (!isset($f['error']) || is_array($f['error'])) {
        return '!Envio inválido.';
    }
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || ($f['size'] ?? 0) > JC_VIDEO_MAX) {
        return '!O vídeo passa de 50 MB. Reduza a qualidade ao exportar ou use o link do YouTube.';
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        return '!Não foi possível receber o vídeo.';
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $cab = (string) file_get_contents($f['tmp_name'], false, null, 0, 12);
    if ($mime !== 'video/mp4' || substr($cab, 4, 4) !== 'ftyp') {
        return '!Envie o vídeo em formato MP4 (ou use o link do YouTube).';
    }
    $dir = JC_ROOT . '/uploads/' . $pasta;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Pasta de upload indisponível');
    }
    $arq = bin2hex(random_bytes(10)) . '.mp4';
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $arq)) {
        return '!Não foi possível guardar o vídeo.';
    }
    return 'uploads/' . $pasta . '/' . $arq;
}
