<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/fpdf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Método no permitido';
    exit;
}

$requiredFields = ['full_name', 'document_number', 'email', 'phone', 'benefit_description', 'delivery_date', 'amount', 'signature_data'];
foreach ($requiredFields as $field) {
    if (empty($_POST[$field])) {
        http_response_code(400);
        echo 'Falta el campo obligatorio: ' . htmlspecialchars($field);
        exit;
    }
}

$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$storage = rtrim(STORAGE_PATH, '/');
$signatureDir = $storage . '/signatures';
$uploadsDir = $storage . '/uploads';
$pdfDir = $storage . '/pdfs';

foreach ([$signatureDir, $uploadsDir, $pdfDir] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

function saveImageFromDataUrl(string $dataUrl, string $targetDir, string $prefix): string {
    if (!preg_match('/^data:image\/(png|jpg|jpeg);base64,/', $dataUrl, $matches)) {
        throw new RuntimeException('Formato de firma no válido');
    }
    $extension = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
    $data = substr($dataUrl, strpos($dataUrl, ',') + 1);
    $decoded = base64_decode($data);
    if ($decoded === false) {
        throw new RuntimeException('No se pudo decodificar la firma');
    }
    $filename = sprintf('%s/%s_%s.%s', rtrim($targetDir, '/'), $prefix, uniqid(), $extension);
    file_put_contents($filename, $decoded);
    return $filename;
}

function saveUploadedFile(array $file, string $targetDir, string $prefix): ?string {
    if (!isset($file['tmp_name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Error al subir archivo');
    }
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'png';
    $filename = sprintf('%s/%s_%s.%s', rtrim($targetDir, '/'), $prefix, uniqid(), $extension);
    if (!move_uploaded_file($file['tmp_name'], $filename)) {
        throw new RuntimeException('No se pudo guardar el archivo subido');
    }
    return $filename;
}

try {
    $pdo->beginTransaction();

    $signaturePath = saveImageFromDataUrl($_POST['signature_data'], $signatureDir, 'firma');
    $frontPath = saveUploadedFile($_FILES['id_front'] ?? [], $uploadsDir, 'cc_frente');
    $backPath = saveUploadedFile($_FILES['id_back'] ?? [], $uploadsDir, 'cc_reverso');

    $stmt = $pdo->prepare('INSERT INTO registrations (full_name, document_number, email, phone, benefit_description, delivery_date, amount, signature_path, id_front_path, id_back_path, pdf_path) VALUES (:full_name, :document_number, :email, :phone, :benefit_description, :delivery_date, :amount, :signature_path, :id_front_path, :id_back_path, :pdf_path)');

    $pdfPath = ''; // se actualizará después de generar el PDF

    $stmt->execute([
        ':full_name' => $_POST['full_name'],
        ':document_number' => $_POST['document_number'],
        ':email' => $_POST['email'],
        ':phone' => $_POST['phone'],
        ':benefit_description' => $_POST['benefit_description'],
        ':delivery_date' => $_POST['delivery_date'],
        ':amount' => $_POST['amount'],
        ':signature_path' => $signaturePath,
        ':id_front_path' => $frontPath,
        ':id_back_path' => $backPath,
        ':pdf_path' => '',
    ]);

    $registrationId = (int) $pdo->lastInsertId();

    $pdfFilename = sprintf('%s/registro_%d.pdf', $pdfDir, $registrationId);

    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, utf8_decode('FORMATO ENTREGA DE MITIGACIÓN DE BARRERAS'), 0, 1, 'C');
    $pdf->Ln(5);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, utf8_decode('Información diligenciada por el operador:'), 0, 1);
    $pdf->SetFont('Arial', '', 11);
    $pdf->MultiCell(0, 8, utf8_decode('De acuerdo con el proceso de orientación que se realiza al participante, se identifica la siguiente barrera a mitigar para el participante: ' . $_POST['benefit_description']));
    $pdf->Ln(2);
    $pdf->Cell(0, 8, utf8_decode('Valor: $' . number_format((float)$_POST['amount'], 2, ',', '.')), 0, 1);
    $pdf->Ln(6);

    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, utf8_decode('Información diligenciada por el participante:'), 0, 1);
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(0, 8, utf8_decode('Nombre: ' . $_POST['full_name']), 0, 1);
    $pdf->Cell(0, 8, utf8_decode('Documento: ' . $_POST['document_number']), 0, 1);
    $pdf->Cell(0, 8, utf8_decode('Correo: ' . $_POST['email']), 0, 1);
    $pdf->Cell(0, 8, utf8_decode('Teléfono: ' . $_POST['phone']), 0, 1);
    $pdf->Cell(0, 8, utf8_decode('Fecha de entrega: ' . $_POST['delivery_date']), 0, 1);
    $pdf->Ln(6);

    $pdf->Cell(0, 8, utf8_decode('Firma del participante:'), 0, 1);
    $pdf->Image($signaturePath, $pdf->GetX(), $pdf->GetY(), 60, 30);
    $pdf->Ln(36);
    $pdf->Cell(0, 8, utf8_decode('Fecha y hora de radicación: ' . date('Y-m-d H:i')), 0, 1);

    if ($frontPath) {
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, utf8_decode('Cédula - Frente'), 0, 1);
        $pdf->Image($frontPath, null, null, 180);
    }

    if ($backPath) {
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, utf8_decode('Cédula - Reverso'), 0, 1);
        $pdf->Image($backPath, null, null, 180);
    }

    $pdf->Output('F', $pdfFilename);

    $update = $pdo->prepare('UPDATE registrations SET pdf_path = :pdf_path WHERE id = :id');
    $update->execute([':pdf_path' => $pdfFilename, ':id' => $registrationId]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo 'Error al guardar los datos: ' . htmlspecialchars($e->getMessage());
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro guardado</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f6fa; padding: 32px; }
        .card { max-width: 720px; margin: 0 auto; background: #fff; border-radius: 10px; box-shadow: 0 10px 20px rgba(0,0,0,0.08); padding: 24px 28px; }
        h1 { margin-top: 0; }
        .actions { margin-top: 16px; }
        a { color: #2563eb; font-weight: 600; text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <h1>¡Registro completado!</h1>
        <p>Se guardaron tus datos, la firma en PNG y el PDF del formato. Puedes descargar el PDF desde aquí:</p>
        <div class="actions">
            <a href="<?php echo htmlspecialchars(str_replace(__DIR__ . '/../', '', $pdfFilename)); ?>" download>Descargar PDF</a>
        </div>
        <p style="margin-top:12px;"><a href="index.php">Registrar otra entrega</a></p>
    </div>
</body>
</html>
