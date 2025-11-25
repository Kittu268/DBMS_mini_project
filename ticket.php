<?php
// ticket.php — OFBMS-style e-ticket with Indigo scanner strip (A1)
// Drop-in replacement. Requires tcpdf_min/tcpdf.php and brand image at /mnt/data/15d4090a-5df3-459a-943a-49edcda6a43f.jpg

session_start();
$disable_background = true;
include 'db.php';

// TCPDF library
require_once __DIR__ . '/tcpdf_min/tcpdf.php';

// Auth checks (supports both session styles)
if (!isset($_SESSION['email']) && !isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
$userEmail = $_SESSION['email'] ?? (is_array($_SESSION['user']) && isset($_SESSION['user']['email']) ? $_SESSION['user']['email'] : (is_string($_SESSION['user']) ? $_SESSION['user'] : ''));

// Accept identification via either reservation_id or the flight+leg+date+seat query params
$reservation_id = isset($_GET['reservation_id']) ? (int)$_GET['reservation_id'] : 0;
$flight = $_GET['flight'] ?? '';
$leg    = isset($_GET['leg']) ? (int)$_GET['leg'] : 0;
$date   = $_GET['date'] ?? '';
$seat   = $_GET['seat'] ?? '';

// Build query to fetch full data
if ($reservation_id) {
    $sql = "
      SELECT r.*, f.Airline, li.Departure_time, li.Arrival_time,
             li.Departure_airport_code, li.Arrival_airport_code,
             s.Seat_class_id, sc.class_name AS Seat_class_name,
             (SELECT p.amount FROM payments p WHERE p.reservation_id = r.reservation_id ORDER BY p.created_at DESC LIMIT 1) AS paid_amount
      FROM reservation r
      LEFT JOIN flight f ON r.Flight_number = f.Flight_number
      LEFT JOIN leg_instance li ON r.Flight_number = li.Flight_number AND r.Leg_no = li.Leg_no AND r.Date = li.Date
      LEFT JOIN seat s ON r.Airplane_id = s.Airplane_id AND r.Seat_no = s.Seat_no
      LEFT JOIN seat_class sc ON s.Seat_class_id = sc.class_id
      WHERE r.reservation_id = ? AND r.Email = ?
      LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $reservation_id, $userEmail);
} else {
    if (!$flight || !$leg || !$date || !$seat) {
        die("Missing required reservation parameters.");
    }
    $sql = "
      SELECT r.*, f.Airline, li.Departure_time, li.Arrival_time,
             li.Departure_airport_code, li.Arrival_airport_code,
             s.Seat_class_id, sc.class_name AS Seat_class_name,
             (SELECT p.amount FROM payments p WHERE p.reservation_id = r.reservation_id ORDER BY p.created_at DESC LIMIT 1) AS paid_amount
      FROM reservation r
      LEFT JOIN flight f ON r.Flight_number = f.Flight_number
      LEFT JOIN leg_instance li ON r.Flight_number = li.Flight_number AND r.Leg_no = li.Leg_no AND r.Date = li.Date
      LEFT JOIN seat s ON r.Airplane_id = s.Airplane_id AND r.Seat_no = s.Seat_no
      LEFT JOIN seat_class sc ON s.Seat_class_id = sc.class_id
      WHERE r.Flight_number = ? AND r.Leg_no = ? AND r.Date = ? AND r.Seat_no = ? AND r.Email = ?
      LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sisss", $flight, $leg, $date, $seat, $userEmail);
}

$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    die("Reservation not found or you are not authorized to download this ticket.");
}
$r = $res->fetch_assoc();
$stmt->close();

// Compose fields (fallback-safe)
$passenger      = $r['Customer_name'] ?? 'Passenger';
$cphone         = $r['Cphone'] ?? '';
$email          = $r['Email'] ?? $userEmail;
$flight_no      = $r['Flight_number'] ?? $flight;
$leg_no         = $r['Leg_no'] ?? $leg;
$date_val       = $r['Date'] ?? $date;
$airplane_id    = $r['Airplane_id'] ?? '';
$seat_no        = $r['Seat_no'] ?? $seat;
$airline        = $r['Airline'] ?? '';
$dep_code       = $r['Departure_airport_code'] ?? '';
$arr_code       = $r['Arrival_airport_code'] ?? '';
$dep_time       = $r['Departure_time'] ?? '';
$arr_time       = $r['Arrival_time'] ?? '';
$seat_class     = $r['Seat_class_name'] ?? 'Economy';
$fare_val       = isset($r['fare']) ? (float)$r['fare'] : 0.0;
$fare           = number_format($fare_val,2);
$paid_amount_num= isset($r['paid_amount']) ? (float)$r['paid_amount'] : 0.0;
$paid_amount    = number_format($paid_amount_num,2);
$payment_status = $r['payment_status'] ?? '';
$booking_ts     = $r['reservation_created_at'] ?? $r['created_at'] ?? '';
$cancellation   = $r['cancellation_status'] ?? 'Active';
$gate = $r['Gate'] ?? ($r['gate'] ?? '—');

// PNR & metadata
$pnr = strtoupper(substr(bin2hex(random_bytes(4)),0,8));
$issued_at = date("Y-m-d H:i:s");

// QR payload
$qrPayload = json_encode([
    'pnr' => $pnr,
    'reservation_id' => $r['reservation_id'] ?? null,
    'flight' => $flight_no,
    'leg' => $leg_no,
    'date' => $date_val,
    'seat' => $seat_no,
    'name' => $passenger,
    'email' => $email
]);

// Brand image path (uploaded)
$brandImagePath = '/mnt/data/15d4090a-5df3-459a-943a-49edcda6a43f.jpg';
if (!file_exists($brandImagePath)) $brandImagePath = null;

// ----------------- CREATE PDF USING TCPDF -----------------
$pdf = new TCPDF('P','mm','A4', true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetCreator('Airline System');
$pdf->SetAuthor('Airline System');
$pdf->SetTitle("E-Ticket {$pnr}");
$pdf->SetSubject('E-Ticket');
$pdf->SetMargins(10, 10, 10);
$pdf->AddPage();

// Fonts and colors
$pdf->SetFont('helvetica', '', 11);
$indigo = [12,73,155]; // indigo
$panelBlue = $indigo;

// Layout coordinates
$card_x = 12;
$card_y = 18;
$card_w = 186;
$card_h = 140; // increased height to accommodate scanner strip
$panel_w = 56;
$radius = 6;

// Draw main card background (white)
$pdf->SetDrawColor(220,220,220);
$pdf->SetFillColor(255,255,255);
$pdf->SetLineWidth(0.45);
$pdf->RoundedRect($card_x, $card_y, $card_w, $card_h, $radius, '1111', 'DF', array('width'=>0.6,'color'=>array(220,220,220)));

// Right-side branding panel
$panel_x = $card_x + $card_w - $panel_w;
$panel_y = $card_y;
$panel_h = $card_h;
$pdf->SetFillColor($panelBlue[0], $panelBlue[1], $panelBlue[2]);
$pdf->RoundedRect($panel_x, $panel_y, $panel_w, $panel_h, $radius, '1111', 'F');

// Panel title and image
$pdf->SetTextColor(255,255,255);
$pdf->SetFont('helvetica','B',14);
$pdf->SetXY($panel_x, $panel_y + 8);
$pdf->Cell($panel_w, 8, 'OFBMS', 0, 1, 'C');

if ($brandImagePath) {
    $img_w = 36; $img_h = 36;
    $img_x = $panel_x + ($panel_w - $img_w)/2;
    $img_y = $panel_y + 28;
    // suppress warnings if image fails
    @$pdf->Image($brandImagePath, $img_x, $img_y, $img_w, $img_h, '', '', '', false, 300, '', false, false, 0, false, false, false);
}

// Left content
$left_x = $card_x + 8;
$left_w = $card_w - $panel_w - 16;
$currentY = $card_y + 6;

// Title row
$pdf->SetTextColor($indigo[0], $indigo[1], $indigo[2]);
$pdf->SetFont('helvetica','B',14);
$pdf->SetXY($left_x, $currentY);
$pdf->Cell($left_w, 6, 'Online Flight Booking', 0, 1, 'L');

$pdf->SetFont('helvetica','B',18);
$currentY += 6;
$pdf->SetXY($left_x, $currentY);
$pdf->Cell($left_w, 8, strtoupper($seat_class), 0, 1, 'C');
$currentY += 8;

// Separator
$pdf->SetDrawColor(235,235,235);
$pdf->Line($left_x, $currentY, $left_x + $left_w, $currentY);
$currentY += 6;

// Airline / From / To
$pdf->SetFont('helvetica','',9);
$pdf->SetTextColor(120,120,120);
$colW1 = $left_w * 0.45;
$colW2 = $left_w * 0.275;
$pdf->SetXY($left_x, $currentY);
$pdf->Cell($colW1, 5, 'AIRLINE', 0, 0, 'L');
$pdf->Cell($colW2, 5, 'FROM', 0, 0, 'C');
$pdf->Cell($colW2, 5, 'TO', 0, 1, 'R');

$currentY += 5;
$pdf->SetFont('helvetica','B',13);
$pdf->SetTextColor(30,30,30);
$pdf->SetXY($left_x, $currentY);
$pdf->Cell($colW1, 9, strtoupper($airline ?: $flight_no), 0, 0, 'L');
$pdf->Cell($colW2, 9, strtoupper($dep_code ?: '—'), 0, 0, 'C');
$pdf->Cell($colW2, 9, strtoupper($arr_code ?: '—'), 0, 1, 'R');
$currentY += 11;

// Passenger & Board time
$pdf->SetFont('helvetica','',9);
$pdf->SetTextColor(120,120,120);
$pdf->SetXY($left_x, $currentY);
$pdf->Cell($left_w * 0.6, 5, 'PASSENGER', 0, 0, 'L');
$pdf->Cell($left_w * 0.4, 5, 'BOARD TIME', 0, 1, 'R');

$currentY += 5;
$pdf->SetFont('helvetica','B',11);
$pdf->SetTextColor(30,30,30);
$pdf->SetXY($left_x, $currentY);
$pdf->Cell($left_w * 0.6, 9, $passenger, 0, 0, 'L');
$pdf->Cell($left_w * 0.4, 9, ($dep_time ?: 'TBD'), 0, 1, 'R');

$currentY += 12;

// Separator
$pdf->SetDrawColor(235,235,235);
$pdf->Line($left_x, $currentY, $left_x + $left_w, $currentY);
$currentY += 6;

// Bottom info block: 4 columns (Departure / Arrival / Gate / Seat)
$col_w = $left_w / 4;
$pdf->SetFont('helvetica','',9);
$pdf->SetTextColor(120,120,120);
$pdf->SetXY($left_x, $currentY);
$pdf->Cell($col_w, 5, 'DEPARTURE', 0, 0, 'L');
$pdf->Cell($col_w, 5, 'ARRIVAL', 0, 0, 'C');
$pdf->Cell($col_w, 5, 'GATE', 0, 0, 'C');
$pdf->Cell($col_w, 5, 'SEAT', 0, 1, 'R');

$currentY += 5;
$pdf->SetFont('helvetica','B',11);
$pdf->SetTextColor(25,25,25);

$depDate = $date_val ?: '—';
$arrDate = $date_val ?: '—';
$depTimeShort = $dep_time ? (strlen($dep_time) > 5 ? substr($dep_time,0,5) : $dep_time) : '—';
$arrTimeShort = $arr_time ? (strlen($arr_time) > 5 ? substr($arr_time,0,5) : $arr_time) : '—';

$pdf->SetXY($left_x, $currentY);
$pdf->MultiCell($col_w, 12, $depDate . "\n" . $depTimeShort, 0, 'L', false, 0, '', '', true, 0, false, true, 12, 'M');
$pdf->MultiCell($col_w, 12, $arrDate . "\n" . $arrTimeShort, 0, 'C', false, 0, '', '', true, 0, false, true, 12, 'M');
$pdf->MultiCell($col_w, 12, $gate ?: '—', 0, 'C', false, 0, '', '', true, 0, false, true, 12, 'M');
$pdf->MultiCell($col_w, 12, $seat_no ?: '—', 0, 'R', false, 1, '', '', true, 0, false, true, 12, 'M');

$currentY += 12;

// ----------------- Indigo scanner strip inside card at bottom (A1) -----------------
// We'll draw a bar that spans the left content portion (not over the branding panel)
// Scanner strip dimensions and position
$strip_h = 28;
$strip_x = $left_x;
$strip_w = $left_w;
$strip_y = $card_y + $card_h - $strip_h - 12; // inside card with padding

// Draw the indigo strip background
$pdf->SetFillColor($indigo[0], $indigo[1], $indigo[2]);
$pdf->Rect($strip_x, $strip_y, $strip_w, $strip_h, 'F');

// Draw subtle lighter "scanner beam" overlay (semi-transparent white)
if (method_exists($pdf, 'SetAlpha')) { // tcpdf supports SetAlpha
    $pdf->SetAlpha(0.12);
    $beam_w = $strip_w * 0.35;
    $beam_x = $strip_x + ($strip_w - $beam_w) / 2;
    $beam_y = $strip_y + 4;
    $pdf->SetFillColor(255,255,255);
    $pdf->Rect($beam_x, $beam_y, $beam_w, $strip_h - 8, 'F');
    $pdf->SetAlpha(1);
}

// Left area inside strip: QR
$qr_size = 22;
$qr_x = $strip_x + 6;
$qr_y = $strip_y + 3;
$qrStyle = array('border'=>0,'vpadding'=>'auto','hpadding'=>'auto','fgcolor'=>array(0,0,0),'bgcolor'=>false);
$pdf->write2DBarcode($qrPayload, 'QRCODE,M', $qr_x, $qr_y, $qr_size, $qr_size, $qrStyle, 'N');

// Middle area: "Scan at Gate → PNR" text (white)
$pdf->SetTextColor(255,255,255);
$pdf->SetFont('helvetica','B',11);
$text_x = $qr_x + $qr_size + 8;
$pdf->SetXY($text_x, $strip_y + 6);
$pdf->Cell($strip_w - ($qr_size + 8) - 10, 6, "Scan at Gate → PNR: " . $pnr, 0, 1, 'L');

// Right area inside strip: 1D barcode (small) and fare/status metadata
$barcode_w = 70;
$barcode_h = 14;
$barcode_x = $strip_x + $strip_w - $barcode_w - 8;
$barcode_y = $strip_y + 6;

// Draw small white box behind barcode for contrast
$pdf->SetFillColor(255,255,255);
$pdf->Rect($barcode_x - 4, $strip_y + 3, $barcode_w + 8, $barcode_h + 6, 'F');

// Draw the barcode (black)
$pdf->SetTextColor(0,0,0);
$pdf->write1DBarcode($pnr, 'C128', $barcode_x, $barcode_y, $barcode_w, $barcode_h, 0.4, array('position'=>'C','border'=>0,'padding'=>0,'fgcolor'=>array(0,0,0),'bgcolor'=>false), 'N');

// Above the strip we reserved space for meta; also place Fare & Payment small labels above strip to the right
$meta_x = $strip_x + $strip_w - $barcode_w - 10;
$meta_y = $strip_y - 28;
$pdf->SetFont('helvetica','',9);
$pdf->SetTextColor(80,80,80);
$pdf->SetXY($meta_x, $meta_y);
$pdf->Cell($barcode_w + 10, 5, "Fare: ₹" . $fare, 0, 1, 'R');
$pdf->SetXY($meta_x, $meta_y + 5);
$pdf->Cell($barcode_w + 10, 5, "Payment: " . ($payment_status ?: 'Pending') . " (₹" . $paid_amount . ")", 0, 1, 'R');

// Additional info under card: passenger contact and airplane
$infoY = $card_y + $card_h + 6;
$pdf->SetFont('helvetica','',9);
$pdf->SetTextColor(90,90,90);
$pdf->SetXY($card_x, $infoY);
$pdf->Cell(($card_w/2), 5, "Passenger: " . $passenger . " | Phone: " . $cphone, 0, 0, 'L');
$pdf->Cell(($card_w/2), 5, "Airplane: " . ($airplane_id ?: '—'), 0, 1, 'R');

// Barcode & footer centered below
$barcode_w_full = 140;
$barcode_x_full = $card_x + ($card_w - $barcode_w_full) / 2;
$barcode_y_full = $infoY + 10;
$pdf->SetFont('helvetica','',9);
$pdf->SetTextColor(120,120,120);
$pdf->SetXY($card_x, $barcode_y_full - 6);
$pdf->Cell($card_w, 4, "This is your electronic ticket. Present on mobile or printed copy. Issued: $issued_at", 0, 1, 'C');

$pdf->write1DBarcode($pnr, 'C128', $barcode_x_full, $barcode_y_full, $barcode_w_full, 14, 0.45, array('position'=>'C','border'=>0,'padding'=>0,'fgcolor'=>array(0,0,0),'bgcolor'=>false), 'N');

// Output (force download)
$filename = "eticket_{$pnr}.pdf";
$pdf->Output($filename, 'D');
exit();
