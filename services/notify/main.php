<?php
require '../includes/dbh.inc.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

function getMailIds(int $patient_id, PDO $conn): void {
    $sql = "SELECT d.email 
            FROM donor d
            JOIN patient p ON d.pincode = p.pincode AND d.blood = p.blood
            WHERE p.id = :patient_id";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare statement: " . implode(" | ", $conn->errorInfo()));
        return;
    }

    $stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
    if (!$stmt->execute()) {
        error_log("Query execution failed: " . implode(" | ", $stmt->errorInfo()));
        return;
    }

    $emails = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($emails)) {
        error_log("No matching donors found for patient ID: $patient_id");
        return;
    }

    foreach ($emails as $email) {
        sendEmail($email);
    }
}

function sendEmail(string $email): void {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'];
        $mail->Password   = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = $_ENV['SMTP_SECURE'];
        $mail->Port       = $_ENV['SMTP_PORT'];

        $mail->setFrom($_ENV['SENDER'], 'Blood Bank');
        $mail->addAddress($email);

        $mail->Subject = 'Urgent Blood Request';
        $mail->Body    = 'A patient in your area needs a blood donation. Please contact the nearest blood bank.';

        $mail->send();
        echo "Email sent to $email\n";
    } catch (Exception $e) {
        error_log("Email could not be sent to $email. Error: " . $mail->ErrorInfo);
    }
}

// Example usage
$patient_id = 1; // Change this to the actual patient ID
notifyDonors($patient_id, $conn);
?>
