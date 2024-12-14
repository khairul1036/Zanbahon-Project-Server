<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Include database connection
require '../Connection.php';

try {
    // Handle GET request
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Check if a specific user ID is provided in the query string
        if (isset($_GET['userId'])) {
            // Prepare SQL query to fetch bookings for a specific user
            $query = "SELECT * FROM bus_ticket_bookings WHERE user_id = :userId";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':userId', $_GET['userId'], PDO::PARAM_INT);
        } else {
            // Fetch all bookings
            $query = "SELECT * FROM bus_ticket_bookings";
            $stmt = $pdo->prepare($query);
        }

        // Execute the query
        $stmt->execute();

        // Fetch all results
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($bookings) {
            echo json_encode(["success" => true, "data" => $bookings]);
        } else {
            echo json_encode(["success" => false, "message" => "No bookings found"]);
        }
    } 

    // Handle POST request
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Decode the JSON input
        $data = json_decode(file_get_contents("php://input"), true);

        // Validate required fields
        if (
            !empty($data['userId']) && 
            !empty($data['userName']) && 
            !empty($data['seats']) && 
            !empty($data['totalAmount'])
        ) {
            // Prepare SQL query to insert a new booking
            $query = "INSERT INTO bus_ticket_bookings (user_id, user_name, seats, total_amount) 
                      VALUES (:userId, :userName, :seats, :totalAmount)";

            $stmt = $pdo->prepare($query);

            // Bind parameters
            $stmt->bindParam(':userId', $data['userId'], PDO::PARAM_INT);
            $stmt->bindParam(':userName', $data['userName'], PDO::PARAM_STR);
            $stmt->bindParam(':seats', $data['seats'], PDO::PARAM_STR);
            $stmt->bindParam(':totalAmount', $data['totalAmount'], PDO::PARAM_INT);

            // Execute the query
            if ($stmt->execute()) {
                echo json_encode(["success" => true, "message" => "Booking successful"]);
            } else {
                echo json_encode(["success" => false, "message" => "Booking failed"]);
            }
        } else {
            echo json_encode(["success" => false, "message" => "Invalid input"]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Invalid request method"]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
