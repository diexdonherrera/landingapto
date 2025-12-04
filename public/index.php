<?php
require_once __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro y firma digital</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background: #f5f6fa; margin: 0; padding: 0; color: #1f2937; }
        .container { max-width: 960px; margin: 32px auto 48px; background: #fff; border-radius: 12px; padding: 28px 32px; box-shadow: 0 12px 24px rgba(0,0,0,0.08); }
        h1 { margin-top: 0; font-size: 28px; color: #0f172a; }
        p.lead { color: #4b5563; margin-bottom: 24px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; }
        .field { display: flex; flex-direction: column; gap: 8px; }
        label { font-weight: 600; color: #111827; }
        input, textarea { padding: 12px 14px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 15px; background: #f9fafb; }
        input:focus, textarea:focus { outline: 2px solid #2563eb; background: #fff; }
        textarea { min-height: 120px; resize: vertical; }
        .signature-wrapper { border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb; padding: 12px; }
        canvas { background: #fff; border-radius: 6px; width: 100%; height: 220px; }
        .signature-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 8px; }
        button { border: none; cursor: pointer; border-radius: 8px; font-weight: 600; }
        .btn-primary { background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; padding: 12px 18px; font-size: 16px; box-shadow: 0 8px 20px rgba(37,99,235,0.25); }
        .btn-secondary { background: #e5e7eb; color: #111827; padding: 10px 14px; font-size: 14px; }
        .actions { margin-top: 24px; display: flex; justify-content: flex-end; }
        .note { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e3a8a; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; }
        .required { color: #dc2626; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Formato digital para entrega de mitigación de barreras</h1>
        <p class="lead">Completa el formulario, firma en el recuadro y obtén un PDF descargable. Los archivos quedan guardados de forma segura en tu servidor PHP + MySQL.</p>
        <div class="note">El formulario está pensado para usarse junto a un código QR que apunte a esta URL. Funciona en móviles y escritorio.</div>
        <form id="register-form" action="submit.php" method="POST" enctype="multipart/form-data">
            <div class="grid">
                <div class="field">
                    <label for="full_name">Nombre completo <span class="required">*</span></label>
                    <input type="text" id="full_name" name="full_name" required>
                </div>
                <div class="field">
                    <label for="document_number">Documento de identidad <span class="required">*</span></label>
                    <input type="text" id="document_number" name="document_number" required>
                </div>
                <div class="field">
                    <label for="email">Correo electrónico <span class="required">*</span></label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="field">
                    <label for="phone">Teléfono <span class="required">*</span></label>
                    <input type="tel" id="phone" name="phone" required>
                </div>
                <div class="field">
                    <label for="delivery_date">Fecha de entrega <span class="required">*</span></label>
                    <input type="date" id="delivery_date" name="delivery_date" required>
                </div>
                <div class="field">
                    <label for="amount">Valor asignado (COP) <span class="required">*</span></label>
                    <input type="number" min="0" step="0.01" id="amount" name="amount" placeholder="1110000" required>
                </div>
            </div>
            <div class="field" style="margin-top:16px;">
                <label for="benefit_description">Descripción del beneficio entregado <span class="required">*</span></label>
                <textarea id="benefit_description" name="benefit_description" placeholder="Ej: Almuerzo 1.100.000 pesos para alcance nutricional" required></textarea>
            </div>
            <div class="grid" style="margin-top:16px;">
                <div class="field">
                    <label for="id_front">Sube la cédula (frente)</label>
                    <input type="file" id="id_front" name="id_front" accept="image/*">
                </div>
                <div class="field">
                    <label for="id_back">Sube la cédula (reverso)</label>
                    <input type="file" id="id_back" name="id_back" accept="image/*">
                </div>
            </div>

            <div class="field" style="margin-top:24px;">
                <label>Firma digital <span class="required">*</span></label>
                <div class="signature-wrapper">
                    <canvas id="signature" width="840" height="240"></canvas>
                    <div class="signature-actions">
                        <span>Use su dedo o mouse para firmar</span>
                        <button type="button" class="btn-secondary" id="clear-signature">Limpiar</button>
                    </div>
                </div>
                <input type="hidden" name="signature_data" id="signature_data" required>
            </div>
            <div class="actions">
                <button class="btn-primary" type="submit">Guardar y generar PDF</button>
            </div>
        </form>
    </div>

    <script>
        const canvas = document.getElementById('signature');
        const signaturePad = new SignaturePad(canvas, { backgroundColor: 'rgba(255, 255, 255, 1)', penColor: '#111827' });
        const clearButton = document.getElementById('clear-signature');
        const form = document.getElementById('register-form');
        const signatureData = document.getElementById('signature_data');

        clearButton.addEventListener('click', () => signaturePad.clear());

        form.addEventListener('submit', (event) => {
            if (signaturePad.isEmpty()) {
                event.preventDefault();
                alert('Debes firmar el formulario antes de enviarlo.');
                return;
            }
            signatureData.value = signaturePad.toDataURL('image/png');
        });
    </script>
</body>
</html>
