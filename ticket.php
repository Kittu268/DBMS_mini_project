<?php
// ticket.php
session_start();
$disable_background = true; // no big background for backend-like downloads
include 'db.php';

// Require TCPDF library - update path if you placed it elsewhere
// Make sure tcpdf_min directory exists and contains tcpdf.php
require_once(__DIR__ . '/tcpdf_min/tcpdf.php');

// must be logged in
if (!isset($_SESSION['email']) || !isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$userEmail = $_SESSION['email'];
$userName = is_array($_SESSION['user']) ? ($_SESSION['user']['Cname'] ?? $_SESSION['user']) : $_SESSION['user'];

// GET params identifying reservation
$flight = $_GET['flight'] ?? '';
$leg    = isset($_GET['leg']) ? (int)$_GET['leg'] : 0;
$date   = $_GET['date'] ?? '';
$seat   = $_GET['seat'] ?? '';

if (!$flight || !$leg || !$date || !$seat) {
    die("Missing required reservation parameters.");
}

// fetch reservation and join flight/leg for details
$stmt = $conn->prepare("
  SELECT r.Customer_name, r.Cphone, r.Email,
         r.Flight_number, r.Leg_no, r.Date, r.Seat_no, r.Airplane_id,
         f.Airline, li.Departure_time, li.Arrival_time
  FROM reservation r
  LEFT JOIN flight f ON r.Flight_number = f.Flight_number
  LEFT JOIN leg_instance li ON r.Flight_number = li.Flight_number AND r.Leg_no = li.Leg_no AND r.Date = li.Date
  WHERE r.Flight_number = ? AND r.Leg_no = ? AND r.Date = ? AND r.Seat_no = ? AND r.Email = ?
  LIMIT 1
");
$stmt->bind_param("sisss", $flight, $leg, $date, $seat, $userEmail);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    die("Reservation not found or you are not authorized to download this ticket.");
}
$row = $res->fetch_assoc();
$stmt->close();

// Compose ticket / PNR
$pnr = strtoupper(substr(bin2hex(random_bytes(4)),0,8)); // demo PNR
$issueTs = date("Y-m-d H:i:s");

// Build a simple content string for QR
$qrData = json_encode([
    'pnr' => $pnr,
    'flight' => $row['Flight_number'],
    'leg' => $row['Leg_no'],
    'date' => $row['Date'],
    'seat' => $row['Seat_no'],
    'name' => $row['Customer_name']
]);

// ----------------- CREATE PDF USING TCPDF -----------------
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// set document info
$pdf->SetCreator('Airline System');
$pdf->SetAuthor('Airline System');
$pdf->SetTitle('E-Ticket ' . $pnr);
$pdf->SetSubject('E-Ticket');

// remove header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// margins
$pdf->SetMargins(12, 12, 12, true);

// add a page
$pdf->AddPage();

// Colors / fonts
$pdf->SetFont('helvetica', 'B', 20);
$pdf->SetTextColor(10, 35, 100);
$pdf->Cell(0, 10, '✈️ E-Ticket', 0, 1, 'C');
$pdf->Ln(2);

// Flight summary block
$pdf->SetFont('helvetica', '', 12);
$html = '<table cellpadding="4" cellspacing="0" border="0">
<tr>
<td><strong>Passenger:</strong> ' . htmlspecialchars($row['Customer_name']) . '</td>
<td><strong>PNR:</strong> ' . $pnr . '</td>
</tr>
<tr>
<td><strong>Flight:</strong> ' . htmlspecialchars($row['Flight_number']) . ' (' . htmlspecialchars($row['Airline']) . ')</td>
<td><strong>Seat:</strong> ' . htmlspecialchars($row['Seat_no']) . '</td>
</tr>
<tr>
<td><strong>Date:</strong> ' . htmlspecialchars($row['Date']) . '</td>
<td><strong>Leg:</strong> ' . htmlspecialchars($row['Leg_no']) . '</td>
</tr>
<tr>
<td><strong>Departure:</strong> ' . htmlspecialchars($row['Departure_time'] ?? 'TBD') . '</td>
<td><strong>Arrival:</strong> ' . htmlspecialchars($row['Arrival_time'] ?? 'TBD') . '</td>
</tr>
<tr>
<td><strong>Airplane:</strong> ' . htmlspecialchars($row['Airplane_id']) . '</td>
<td><strong>Phone:</strong> ' . htmlspecialchars($row['Cphone']) . '</td>
</tr>
</table>';
$pdf->writeHTML($html, true, false, true, false, '');

// Draw a separator
$pdf->Ln(4);
$pdf->Line(12, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(6);

// Add QR code on the right and summary text on left
$startY = $pdf->GetY();
$leftX = 14;
$rightX = 120;

$pdf->SetFont('helvetica', '', 11);
$pdf->SetXY($leftX, $startY);
$pdf->MultiCell(90, 6, "This is your electronic ticket. Please present the printed or mobile ticket at boarding.\n\nIssued: $issueTs\n\nReservation details above.", 0, 'L', 0, 0, '', '', true);

// QR options and position
$style = array(
    'border' => 0,
    'vpadding' => 'auto',
    'hpadding' => 'auto',
    'fgcolor' => array(0,0,0),
    'bgcolor' => false,
);

// write 2d barcode (QR)
$pdf->write2DBarcode($qrData, 'QRCODE,M', $rightX, $startY, 60, 60, $style, 'N');

// Footer text / small print
$pdf->SetY($startY + 70);
$pdf->SetFont('helvetica', 'I', 9);
$pdf->SetTextColor(80,80,80);
$pdf->MultiCell(0, 5, "This e-ticket is computer generated and does not require a signature. PNR: $pnr", 0, 'L', 0, 1, '', '', true);

// Output PDF (force download)
$filename = "ticket_{$pnr}.pdf";
$pdf->Output($filename, 'D'); // 'I' = inline, 'D' = download
exit();
