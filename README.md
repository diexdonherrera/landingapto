# Formulario de registro con firma digital

Solución PHP + MySQL que permite diligenciar un formulario desde web o móvil (ideal para escanear con un código QR), capturar la firma en PNG, guardar evidencia (cédula) y generar un PDF listo para descargar.

## Requisitos
- PHP 8+ con extensiones `pdo_mysql` y `fileinfo` habilitadas.
- Servidor MySQL/MariaDB accesible.
- Servidor web o `php -S` para servir la carpeta `public/`.

## Instalación
1. Clona este repositorio en tu servidor.
2. Importa la base de datos:
   ```sql
   mysql -u root -p < schema.sql
   ```
3. Ajusta las credenciales en [`config.php`](config.php).
4. Sirve la carpeta `public/` (por ejemplo: `php -S 0.0.0.0:8000 -t public`).
5. Abre la URL en el navegador o genera un código QR apuntando a ella.

## Uso
1. Completa los campos obligatorios y opcionales de cédula.
2. Firma con el dedo o mouse; el botón **Limpiar** reinicia el trazo.
3. Al enviar, se guarda:
   - Registro en MySQL (`registrations`).
   - Firma en PNG en `storage/signatures/`.
   - Archivos cargados en `storage/uploads/`.
   - PDF completo en `storage/pdfs/`.
4. La pantalla de confirmación ofrece el enlace directo para descargar el PDF.

## Estructura
- `public/index.php`: formulario responsivo con captura de firma por canvas.
- `public/submit.php`: validación, guardado en MySQL, almacenamiento de archivos y generación del PDF.
- `lib/fpdf.php`: biblioteca libre utilizada para armar el PDF.
- `schema.sql`: script SQL para crear la base de datos y tabla.
- `storage/`: carpeta contenedora de firmas, PDFs y adjuntos (vacía en el repositorio; los contenidos están ignorados por `.gitignore`).

## Notas
- Todo es software libre: FPDF bajo licencia propia gratuita.
- Si publicas el formulario en HTTPS, los navegadores móviles permiten firmar desde pantalla táctil sin configuraciones adicionales.
