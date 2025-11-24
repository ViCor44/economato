<?php
// src/ean_functions.php
require_once __DIR__ . '/../vendor/autoload.php'; // ajusta se precisares

use Picqer\Barcode\BarcodeGeneratorPNG;

/**
 * Calcula checksum EAN-13 a partir de 12 dígitos.
 */
function ean13_checksum(string $digits12): string {
    if (!preg_match('/^\d{12}$/', $digits12)) {
        throw new InvalidArgumentException("EAN base must be 12 digits");
    }
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $d = (int)$digits12[$i];
        // posições 1..12 (i+1) -> par multiplicador 3
        $sum += (($i + 1) % 2 === 0) ? $d * 3 : $d;
    }
    $mod = $sum % 10;
    $check = $mod === 0 ? 0 : (10 - $mod);
    return (string)$check;
}

function generate_ean13_from12(string $digits12): string {
    return $digits12 . ean13_checksum($digits12);
}

function validate_ean13(string $ean): bool {
    return preg_match('/^\d{13}$/', $ean) && substr($ean, -1) === ean13_checksum(substr($ean, 0, 12));
}

/**
 * Gera um EAN-13 único usando um algoritmo interno e verifica DB para unicidade.
 * $pdo: PDO connection
 * $prefix: string de 3 dígitos, opcional — personaliza se desejado
 */
function generate_unique_ean(PDO $pdo, string $prefix = '200'): string {
    // prefix tem de ser 3 dígitos para o nosso esquema (ajusta conforme necessidade)
    if (!preg_match('/^\d{3}$/', $prefix)) $prefix = '200';
    $tries = 0;
    do {
        // Gerar 9 dígitos únicos (timestamp+rand) -> total 12 com prefixo
        $body9 = str_pad(substr((string)time() . (string)rand(0,999), -9), 9, '0', STR_PAD_LEFT);
        $digits12 = $prefix . $body9; // total 12
        $ean = generate_ean13_from12($digits12);

        // Verificar unicidade na BD
        $stmt = $pdo->prepare("SELECT id FROM fardas WHERE ean = ?");
        $stmt->execute([$ean]);
        $exists = (bool)$stmt->fetchColumn();

        $tries++;
        if ($tries > 50) {
            throw new RuntimeException("Não foi possível gerar EAN único após várias tentativas");
        }
    } while ($exists);

    return $ean;
}

/**
 * Gera e grava .png do EAN (ou CODE128 se quiseres).
 * Retorna o caminho completo do ficheiro gerado.
 *
 * $ean: string (13 dígitos para EAN-13)
 * $outDir: diretório onde gravar (ex: __DIR__ . '/../public/barcodes/')
 */
function save_ean_png(string $ean, string $outDir): string {
    if (!preg_match('/^\d{13}$/', $ean)) {
        throw new InvalidArgumentException("EAN inválido para gerar imagem");
    }

    if (!is_dir($outDir)) {
        if (!mkdir($outDir, 0755, true)) {
            throw new RuntimeException("Não foi possível criar directório $outDir");
        }
    }

    $generator = new BarcodeGeneratorPNG();
    // TYPE_EAN_13; scale (2), height (60) ajusta conforme necessário
    $pngData = $generator->getBarcode($ean, $generator::TYPE_EAN_13, 2, 60);
    $filename = rtrim($outDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $ean . '.png';
    file_put_contents($filename, $pngData);
    return $filename;
}
