<?php
include(__DIR__ . "/../config/db.php");

$sql = "
CREATE TABLE IF NOT EXISTS chat_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_no VARCHAR(20) UNIQUE NOT NULL,
    employee_id VARCHAR(50) NOT NULL,
    subject VARCHAR(255),
    status ENUM('Open', 'Closed') DEFAULT 'Open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    closed_at TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS chat_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    sender ENUM('User', 'AI') NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES chat_tickets(id) ON DELETE CASCADE
);
";

if ($conn->multi_query($sql)) {
    echo "Tables created successfully.";
} else {
    echo "Error creating tables: " . $conn->error;
}

$conn->close();
?>
