<?php include 'header.php'; ?>

<?php
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $flight = $_POST['flight_number'];
    $leg = (int)$_POST['leg_no'];
    $date = $_POST['date'];
    $airplane = $_POST['airplane_id'];
    $seat = $_POST['seat_no'];
    $name = $_POST['customer_name'];
    $phone = $_POST['cphone'];

    // Insert dependencies
    $conn->query("INSERT IGNORE INTO Airport (Airport_code, Name, City, State)
                  VALUES ('BLR', 'Kempegowda Intl', 'Bangalore', 'Karnataka'),
                         ('DEL', 'Indira Gandhi Intl', 'Delhi', 'Delhi')");

    $conn->query("INSERT IGNORE INTO Flight (Flight_number) VALUES ('$flight')");

    $conn->query("INSERT IGNORE INTO Flight_Leg (Flight_number, Leg_no, Departure_airport_code, Arrival_airport_code)
                  VALUES ('$flight', $leg, 'BLR', 'DEL')");

    $conn->query("INSERT IGNORE INTO Airplane_Type (Type_name, Max_seats, Company)
                  VALUES ('Airbus-A320', 180, 'Airbus')");

    $conn->query("INSERT IGNORE INTO Airplane (Airplane_id, Total_no_of_seats, Type_name)
                  VALUES ('$airplane', 180, 'Airbus-A320')");

    $conn->query("INSERT IGNORE INTO Seat (Airplane_id, Seat_no) VALUES ('$airplane', '$seat')");

    // Insert Leg_Instance if missing
    $check = $conn->prepare("SELECT 1 FROM Leg_Instance WHERE Flight_number=? AND Leg_no=? AND Date=?");
    $check->bind_param("sis", $flight, $leg, $date);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows === 0) {
        $insert_leg = $conn->prepare("INSERT INTO Leg_Instance (
            Flight_number, Leg_no, Date,
            Number_of_available_seats, Airplane_id,
            Departure_airport_code, Departure_time,
            Arrival_airport_code, Arrival_time
        ) VALUES (?, ?, ?, 100, ?, 'BLR', '10:00:00', 'DEL', '12:30:00')");
        $insert_leg->bind_param("siss", $flight, $leg, $date, $airplane);
        $insert_leg->execute();
    }

    // Insert Reservation
    $stmt = $conn->prepare("INSERT INTO Reservation (
        Flight_number, Leg_no, Date,
        Airplane_id, Seat_no,
        Customer_name, Cphone
    ) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sisssss", $flight, $leg, $date, $airplane, $seat, $name, $phone);

    if ($stmt->execute()) {
        header("Location: reservation.php?status=success");
        exit();
    } else {
        header("Location: reservation.php?status=error");
        exit();
    }
}
?>
