<?php
// forgot_password.php
session_start();
$message = '';
$message_type = '';
$otp_display = ''; // Variable to display the OTP for simulation

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_once __DIR__ . '/config/db.php';
    $email = $_POST['email'];

    if (empty($email)) {
        $message = "Email address is required.";
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            // User exists, generate OTP
            $otp = rand(100000, 999999); // Generate a 6-digit OTP
            $expires_at = date("Y-m-d H:i:s", strtotime('+15 minutes')); // OTP expires in 15 minutes

            // Delete any old OTPs for this email
            $stmt_delete = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt_delete->bind_param("s", $email);
            $stmt_delete->execute();
            $stmt_delete->close();

            // Store the new OTP in the database
            $stmt_insert = $conn->prepare("INSERT INTO password_resets (email, otp, expires_at) VALUES (?, ?, ?)");
            $stmt_insert->bind_param("sss", $email, $otp, $expires_at);
            
            if ($stmt_insert->execute()) {
                // --- Email Sending Simulation ---
                // In a real application, you would use a mail library (like PHPMailer) to send the OTP.
                // For this development environment, we will display it on the screen.
                $otp_display = "An OTP has been sent to your email. For development purposes, your OTP is: " . $otp;
                
                $message = "Please check your email for the OTP to reset your password.";
                $message_type = 'success';

                // Store email in session to use on the reset page
                $_SESSION['password_reset_email'] = $email;

            } else {
                $message = "Error: Could not generate a reset token.";
                $message_type = 'error';
            }
            $stmt_insert->close();
        } else {
            $message = "No user found with that email address.";
            $message_type = 'error';
        }
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex flex-col items-center justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-xl shadow-lg p-8">
            <h2 class="text-2xl font-bold text-center text-gray-800">Forgot Your Password?</h2>
            <p class="text-center text-sm text-gray-600 mt-2">Enter your email address and we will send you an OTP to reset your password.</p>
            
            <?php if (!empty($message)): ?>
                <div class="mt-6 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($otp_display)): ?>
                <div class="mt-4 p-4 rounded-md bg-blue-100 text-blue-800 text-center">
                    <p class="font-bold">SIMULATED EMAIL:</p>
                    <p><?php echo htmlspecialchars($otp_display); ?></p>
                    <a href="reset_password.php" class="font-bold underline mt-2 inline-block">Proceed to Reset Password</a>
                </div>
            <?php else: ?>
                <form action="forgot_password.php" method="POST" class="mt-8 space-y-6">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                        <input type="email" name="email" id="email" required class="mt-1 p-2 border border-gray-300 rounded-md w-full shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <button type="submit" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                            Send Reset Link
                        </button>
                    </div>
                </form>
            <?php endif; ?>
            <div class="mt-4 text-center">
                <a href="admin_login.php" class="text-sm text-indigo-600 hover:underline">Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>
