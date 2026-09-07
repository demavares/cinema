<?php
// ============================================
// COMPROBANTE DE COMPRA EN PDF (desde Mis Boletos)
// Genera el comprobante en formato PDF sin pasar
// por la página de confirmación del proceso de compra.
// ============================================
require_once __DIR__ . '/user_auth.php';

$purchaseId = isset($_GET['purchase_id']) ? intval($_GET['purchase_id']) : 0;

if ($purchaseId <= 0) {
    http_response_code(400);
    die('ID de compra inválido.');
}

// Verificar que la compra pertenece al usuario y está completada
$stmt = $pdo->prepare("SELECT id, status FROM purchases WHERE id = ? AND user_id = ?");
$stmt->execute([$purchaseId, $_SESSION['user_id']]);
$purchaseCheck = $stmt->fetch();

if (!$purchaseCheck) {
    http_response_code(403);
    die('No autorizado para este comprobante.');
}

if ($purchaseCheck['status'] !== 'completed') {
    http_response_code(403);
    die('La compra no está completada.');
}

// Datos de la compra
$stmt = $pdo->prepare("SELECT * FROM purchases WHERE id = ? AND user_id = ? AND status = 'completed'");
$stmt->execute([$purchaseId, $_SESSION['user_id']]);
$purchase = $stmt->fetch();

$showtimeId = $purchase['showtime_id'];

$stmt = $pdo->prepare("
    SELECT s.*, m.id as movie_id, m.title, m.poster_url, m.duration, r.name as room_name
    FROM showtimes s
    JOIN movies m ON s.movie_id = m.id
    JOIN rooms r ON s.room_id = r.id
    WHERE s.id = ?
");
$stmt->execute([$showtimeId]);
$showtime = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT t.*, tt.name as ticket_type_name, tt.code as ticket_type_code
    FROM tickets t
    JOIN ticket_types tt ON t.ticket_type_id = tt.id
    WHERE t.purchase_id = ? AND t.status = 'confirmed'
    ORDER BY t.id ASC
");
$stmt->execute([$purchaseId]);
$purchaseTickets = $stmt->fetchAll();

// Asientos
$seatsFromDB = $purchase['seats'] ?? '';
$seatsArray = !empty($seatsFromDB) ? array_map('trim', explode(',', $seatsFromDB)) : [];

// Comida
$stmt = $pdo->prepare("
    SELECT fo.*, fi.name as food_name
    FROM food_orders fo
    JOIN food_items fi ON fo.food_item_id = fi.id
    WHERE fo.purchase_id = ? AND fo.status = 'completed'
    ORDER BY fo.id ASC
");
$stmt->execute([$purchaseId]);
$foodOrders = $stmt->fetchAll();

// Desglose por tipo de boleto
$ticketTypes = [];
$ticketTotal = 0;
foreach ($purchaseTickets as $pt) {
    $code = $pt['ticket_type_code'] ?? 'adult';
    $name = $pt['ticket_type_name'] ?? ucfirst($code);
    $price = floatval($pt['price_paid']);

    if (!isset($ticketTypes[$code])) {
        $ticketTypes[$code] = ['count' => 0, 'name' => $name, 'total' => 0];
    }
    $ticketTypes[$code]['count']++;
    $ticketTypes[$code]['total'] += $price;
    $ticketTotal += $price;
}

// Agrupar comida
$groupedFood = [];
$foodTotal = 0;
$hasFood = !empty($foodOrders);
if ($hasFood) {
    foreach ($foodOrders as $food) {
        $key = $food['food_name'];
        if (!isset($groupedFood[$key])) {
            $groupedFood[$key] = ['name' => $food['food_name'], 'quantity' => 0, 'total' => 0];
        }
        $groupedFood[$key]['quantity'] += intval($food['quantity']);
        $groupedFood[$key]['total'] += floatval($food['total_price']);
        $foodTotal += floatval($food['total_price']);
    }
}

$taxRate = floatval($purchase['tax_rate'] ?? 16);
$subtotal = $ticketTotal + $foodTotal;
$taxAmount = $subtotal * ($taxRate / 100);
$totalAmount = $subtotal + $taxAmount;

$siteConfig = getSiteConfig($pdo);
$siteName = $siteConfig['site_name'] ?? 'Cinema';
$companyRif = trim($siteConfig['company_rif'] ?? '');

// Promociones
$promotions = !empty($showtime['promotions']) ? explode(',', $showtime['promotions']) : [];
$hasMondayPromo = in_array('lunes_mitad', $promotions);
$hasPresale = in_array('preventa', $promotions);

$language = $showtime['language'] ?? 'español';
$languageLabel = $language == 'español' ? 'Español' : 'Subtítulos en Español';

$paymentMethod = $purchase['payment_method'] ?? 'movil';
$paymentLabels = ['movil' => 'Pago Móvil', 'tarjeta' => 'Tarjeta de Crédito/Débito'];
$paymentLabel = $paymentLabels[$paymentMethod] ?? ucfirst($paymentMethod);

$paymentReference = 'N/A';
$paymentDate = $purchase['purchase_date'] ?? date('Y-m-d H:i:s');
if (!empty($purchase['payment_data'])) {
    $paymentData = json_decode($purchase['payment_data'], true);
    if (is_array($paymentData)) {
        $paymentReference = $paymentData['reference'] ?? 'N/A';
        $paymentDate = $paymentData['date'] ?? $paymentDate;
    }
}

// Cliente
$stmt = $pdo->prepare("SELECT name, email, cedula_type, cedula_number FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$client = $stmt->fetch();

$clientCedula = '-';
if (!empty($client['cedula_type']) && !empty($client['cedula_number'])) {
    $clientCedula = trim($client['cedula_type'] . '-' . $client['cedula_number']);
}

// ============================================
// GENERACIÃ“N DEL PDF
// ============================================
require_once __DIR__ . '/../lib/fpdf/fpdf.php';

// FPDF usa ISO-8859-1; convertir desde UTF-8
$toLatin = function ($text) {
    if ($text === null || $text === '') return '';
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$text);
        return $converted !== false ? $converted : (string)$text;
    }
    return (string)$text;
};

class ComprobantePDF extends FPDF
{
    protected $toLatin;
    protected $siteName;
    protected $companyRif;

    public function __construct($toLatin, $siteName, $companyRif = '')
    {
        parent::__construct('P', 'mm', 'Letter');
        $this->toLatin = $toLatin;
        $this->siteName = $siteName;
        $this->companyRif = $companyRif;
    }

    public function header()
    {
        $conv = $this->toLatin;
        $this->SetFillColor(79, 70, 229);
        $this->Rect(0, 0, 215.9, 8, 'F');

        $this->SetY(13);
        $this->SetFont('Helvetica', 'B', 16);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 8, $conv($this->siteName), 0, 1, 'C');

        if (!empty($this->companyRif)) {
            $this->SetFont('Helvetica', '', 10);
            $this->SetTextColor(0, 0, 0);
            $this->Cell(0, 6, $conv($this->companyRif), 0, 1, 'C');
        }

        $this->SetFont('Helvetica', '', 10);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 6, $conv('Comprobante de compra'), 0, 1, 'C');
        $this->Ln(3);
    }

    public function footer()
    {
        $conv = $this->toLatin;
        $this->SetY(-18);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 5, $conv($this->siteName) . ' - ' . $conv('Gracias por tu compra'), 0, 1, 'C');
        $this->SetY(-14);
        $this->Cell(0, 5, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    public function sectionTitle($title)
    {
        $conv = $this->toLatin;
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(0, 0, 0);
        $this->SetFillColor(243, 244, 255);
        $this->Cell(0, 8, '  ' . $conv($title), 0, 1, 'L', true);
        $this->Ln(1);
    }

    public function kvRow($label, $value, $bold = false)
    {
        $conv = $this->toLatin;
        $font = $bold ? 'B' : '';
        $this->SetFont('Helvetica', $font, 9.5);
        $this->SetTextColor(0, 0, 0);
        $labelW = 48;
        $valueW = 130;
        $this->Cell($labelW, 6, $conv($label), 0, 0, 'L');
        $this->SetTextColor(0, 0, 0);
        $this->Cell($valueW, 6, $conv($value), 0, 1, 'L');
    }

    public function kvRow2($label, $value, $label2, $value2)
    {
        $conv = $this->toLatin;
        $this->SetFont('Helvetica', '', 9.5);
        $labelW = 32;
        $valueW = 58;
        $this->SetTextColor(0, 0, 0);
        $this->Cell($labelW, 6, $conv($label), 0, 0, 'L');
        $this->Cell($valueW, 6, $conv($value), 0, 0, 'L');
        $this->SetTextColor(0, 0, 0);
        $this->Cell($labelW, 6, $conv($label2), 0, 0, 'L');
        $this->SetTextColor(0, 0, 0);
        $this->Cell($valueW, 6, $conv($value2), 0, 1, 'L');
    }
}

$pdf = new ComprobantePDF($toLatin, $siteName, $companyRif);
$pdf->SetTitle('Comprobante_' . str_pad($purchase['sale_number'] ?? $purchase['id'], 8, '0', STR_PAD_LEFT));
$pdf->AliasNbPages();
$pdf->SetMargins(15, 22, 15);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

// ===== DATOS PRINCIPALES =====
$pdf->SetFont('Helvetica', 'B', 13);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 7, $toLatin('Detalle de tu compra'), 0, 1, 'C');
$pdf->Ln(2);

$pdf->SetFont('Helvetica', 'B', 20);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 10, '# ' . str_pad($purchase['sale_number'] ?? $purchase['id'], 8, '0', STR_PAD_LEFT), 0, 1, 'C');

$pdf->SetFont('Helvetica', '', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 6, $toLatin('Comprado: ' . formatDateShort($purchase['purchase_date']) . ' - ' . strtolower(formatTimeVenezuela($purchase['purchase_date']))), 0, 1, 'C');
$pdf->Ln(4);

// ===== CLIENTE =====
$pdf->sectionTitle('Cliente');
$pdf->kvRow2('Nombre:', $client['name'] ?? '-', 'Identificación:', $clientCedula);

$pdf->SetFont('Helvetica', '', 9.5);
$pdf->SetTextColor(0, 0, 0);
$labelW = 48;
$valueW = 130;
$pdf->Cell($labelW, 6, $toLatin('Correo:'), 0, 0, 'L');
$pdf->SetTextColor(0, 0, 0);
$pdf->MultiCell($valueW, 5, $toLatin($client['email'] ?? '-'), 0, 'L');
$pdf->Ln(2);

// ===== PELíCULA =====
$pdf->sectionTitle('Película');
$pdf->kvRow2('Título:', $showtime['title'], 'Hora:', formatTimeVenezuela($showtime['show_time']));
$pdf->kvRow2('Sala:', $showtime['room_name'], 'Formato:', $showtime['format'] ?? '2D');
$pdf->kvRow2('Fecha:', formatDateShort($showtime['show_date']), 'Idioma:', $languageLabel);
if ($hasMondayPromo) {
    $pdf->kvRow2('Promoción:', 'Lunes a mitad de precio', '', '');
}
if ($hasPresale) {
    $pdf->kvRow2('Promoción:', 'Preventa', '', '');
}
$pdf->Ln(4);

// ===== BOLETOS =====
$pdf->sectionTitle('Boletos');
$pdf->SetFont('Helvetica', '', 9.5);
foreach ($ticketTypes as $code => $info) {
    $left = $info['count'] . ' x ' . $info['name'];
    $right = formatCurrency($info['total'], $siteConfig);
    $pdf->SetFont('Helvetica', '', 9.5);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(120, 6, $toLatin($left), 0, 0, 'L');
    $pdf->Cell(60, 6, $toLatin($right), 0, 1, 'R');
}
$pdf->Ln(2);

// ===== ASIENTOS =====
if (!empty($seatsArray)) {
    $pdf->sectionTitle('Asientos');
$pdf->SetFont('Helvetica', '', 9.5);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->MultiCell(0, 5, $toLatin(implode(' · ', $seatsArray)), 0, 'L');
    $pdf->Ln(1);
}

// ===== COMIDA =====
if ($hasFood && !empty($groupedFood)) {
    $pdf->sectionTitle('Comida');
    $pdf->SetFont('Helvetica', '', 9.5);
    foreach ($groupedFood as $item) {
        $left = $item['quantity'] . ' x ' . $item['name'];
        $right = formatCurrency($item['total'], $siteConfig);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(120, 6, $toLatin($left), 0, 0, 'L');
        $pdf->Cell(60, 6, $toLatin($right), 0, 1, 'R');
    }
    $pdf->Ln(2);
}

// ===== TOTALES =====
$pdf->Ln(2);
$pdf->SetDrawColor(220, 220, 230);
$pdf->Line(15, $pdf->GetY(), 200.9, $pdf->GetY());
$pdf->Ln(3);

$pdf->SetFont('Helvetica', '', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(120, 7, $toLatin('Subtotal'), 0, 0, 'L');
$pdf->Cell(60, 7, $toLatin(formatCurrency($subtotal, $siteConfig)), 0, 1, 'R');

$pdf->Cell(120, 7, $toLatin('IVA (' . number_format($taxRate, 2) . '%)'), 0, 0, 'L');
$pdf->Cell(60, 7, $toLatin(formatCurrency($taxAmount, $siteConfig)), 0, 1, 'R');

$pdf->SetDrawColor(200, 200, 215);
$pdf->Line(15, $pdf->GetY(), 200.9, $pdf->GetY());
$pdf->Ln(2);

$pdf->SetFont('Helvetica', 'B', 12);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(120, 8, $toLatin('Total pagado'), 0, 0, 'L');
$pdf->Cell(60, 8, $toLatin(formatCurrency($totalAmount, $siteConfig)), 0, 1, 'R');
$pdf->Ln(6);

// ===== PAGO =====
$pdf->sectionTitle('Método de pago');
$pdf->kvRow2('Método:', $paymentLabel, 'Referencia:', $paymentReference);
if ($paymentMethod === 'movil') {
    $pdf->kvRow2('Banco:', 'Banco de Venezuela', 'Teléfono:', '0412-1234567');
    $pdf->kvRow2('Cuenta:', '0102-0123-45-1234567890', 'Fecha de pago:', date('d/m/Y H:i:s', strtotime($paymentDate)));
} elseif ($paymentMethod === 'tarjeta') {
    $pdf->kvRow2('Tarjeta:', '•••• •••• •••• 1234', 'Titular:', $client['name'] ?? 'Cliente');
    $pdf->kvRow('Fecha de pago:', date('d/m/Y H:i:s', strtotime($paymentDate)));
}
$pdf->Ln(3);

// ===== CÃ“DIGO =====
$pdf->SetFont('Helvetica', 'B', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 7, $toLatin('Código de taquilla: #' . str_pad($purchase['sale_number'] ?? $purchase['id'], 6, '0', STR_PAD_LEFT) . '-' . str_pad($purchase['total_tickets'] ?? count($seatsArray), 2, '0', STR_PAD_LEFT)), 0, 1, 'C');

$pdf->SetFont('Helvetica', '', 9);
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(2);
$pdf->Cell(0, 5, $toLatin('Presenta este comprobante en taquilla para su facturación y confirmación.'), 0, 1, 'C');

$filename = 'Comprobante_' . str_pad($purchase['sale_number'] ?? $purchase['id'], 8, '0', STR_PAD_LEFT) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

$pdf->Output('I', $filename);
exit;