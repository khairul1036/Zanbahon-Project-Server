<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require '../Connection.php';

try {
    // Fetch all parking bookings where the parking end time is earlier than now and the booking status is not 'Completed'
    $stmt = $pdo->prepare("SELECT 
                                ParkingBookingId, ParkingStartTime, ParkingEndTime, RatePerHour, OvertimeRatePerHour,
                                OvertimeParking, OvertimeFee, RegularAmount, OvertimeAmount, ParkingId
                           FROM ParkingBooking
                           WHERE ParkingEndTime < NOW() AND BookingStatus != 'Completed'"); // Filter for bookings that ended before now
    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Check if any bookings were found
    if ($bookings) {
        foreach ($bookings as $booking) {
            $parkingStartTime = new DateTime($booking['ParkingStartTime']);
            $parkingEndTime = new DateTime($booking['ParkingEndTime']);
            $currentTime = new DateTime(); // Get current time

            $ratePerHour = $booking['RatePerHour'];
            $overtimeRatePerHour = $booking['OvertimeRatePerHour'];
            $regularAmount = $booking['RegularAmount'];
            $overtimeAmount = $booking['OvertimeAmount'];

            // Calculate the overtime if the current time exceeds the ParkingEndTime
            if ($parkingEndTime < $currentTime) {
                // Calculate the overtime duration in hours (current time - parking end time)
                $interval = $parkingEndTime->diff($currentTime);
                
                // Total hours of overtime (including days converted to hours)
                $totalOvertimeHours = $interval->h + ($interval->days * 24); 

                // Total minutes of overtime
                $totalOvertimeMinutes = $interval->i;

                // Convert the total minutes into fractional overtime hours
                $overtimeDurationInHours = $totalOvertimeHours + ($totalOvertimeMinutes / 60);

                // Calculate overtime fee
                $overtimeFee = $overtimeDurationInHours * $overtimeRatePerHour;
                $overtimeAmount = $overtimeFee;
                $totalAmount = $regularAmount + $overtimeAmount;

                // Mark as overtime parking
                $overtime = true;
            } else {
                // No overtime, set overtime amount to 0
                $overtimeAmount = 0;
                $totalAmount = $regularAmount;
                $overtime = false;
            }

            // Update the parking booking with the overtime status and fee in the database
            $updateStmt = $pdo->prepare("UPDATE ParkingBooking 
                                         SET OvertimeParking = ?, OvertimeFee = ?, OvertimeAmount = ?, TotalAmount = ?, PaymentStatus = 'Paid', BookingStatus = 'Completed', ParkingEndTime = NOW()
                                         WHERE ParkingBookingId = ?");
            $updateStmt->execute([$overtime, $overtimeFee, $overtimeAmount, $totalAmount, $booking['ParkingBookingId']]);

            // Update parking slot status to 'Available' (free up the parking slot)
            $updateParkingStmt = $pdo->prepare("UPDATE Parking 
                                                SET Status = 'Available'
                                                WHERE ParkingId = ?");
            $updateParkingStmt->execute([$booking['ParkingId']]);
        }

        // Send success response
        echo json_encode([
            "message" => "Overtime check and updates completed successfully"
        ]);
    } else {
        echo json_encode(["message" => "No parking bookings found that require updates"]);
    }
} catch (PDOException $e) {
    echo json_encode([
        "message" => "An error occurred",
        "error" => $e->getMessage()
    ]);
}
?>
