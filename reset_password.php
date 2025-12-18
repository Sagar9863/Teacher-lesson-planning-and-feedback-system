<?php
// reset_password.php
session_start();
$message = '';
$message_type = '';

// Redirect if the email isn't in the session (user hasn't gone through forgot_password.php)
if (!isset($_SESSION['password_reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_once __DIR__ . '/config/db.php';
    $email = $_SESSION['password_reset_email'];
    $otp = $_POST['otp'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($otp) || empty($new_password) || empty($confirm_password)) {
        $message = "All fields are required.";
        $message_type = 'error';
    } elseif ($new_password !== $confirm_password) {
        $message = "New passwords do not match.";
        $message_type = 'error';
    } else {
        // Check if the OTP is valid and not expired
        $stmt = $conn->prepare("SELECT * FROM password_resets WHERE email = ? AND otp = ? AND expires_at > NOW()");
        $stmt->bind_param("ss", $email, $otp);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            // OTP is valid, update the user's password
            // In a real app, you would HASH this password. Here we store it as plain text.
            $stmt_update = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt_update->bind_param("ss", $new_password, $email);
            
            if ($stmt_update->execute()) {
                // Delete the used OTP
                $stmt_delete = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
                $stmt_delete->bind_param("s", $email);
                $stmt_delete->execute();
                $stmt_delete->close();
                
                unset($_SESSION['password_reset_email']); // Clear the session
                $message = "Password has been reset successfully! You can now log in with your new password.";
                $message_type = 'success';
            } else {
                $message = "Error: Could not update password.";
                $message_type = 'error';
            }
            $stmt_update->close();
        } else {
            $message = "Invalid or expired OTP.";
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
    <title>Reset Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex flex-col items-center justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-xl shadow-lg p-8">
            <h2 class="text-2xl font-bold text-center text-gray-800">Set New Password</h2>
            <p class="text-center text-sm text-gray-600 mt-2">Enter the OTP from your email and your new password.</p>

            <?php if (!empty($message)): ?>
                <div class="mt-6 p-4 rounded-md <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                    <?php if ($message_type === 'success'): ?>
                        <a href="admin_login.php" class="font-bold underline mt-2 block">Click here to Login</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form action="reset_password.php" method="POST" class="mt-8 space-y-6">
                <div>
                    <label for="otp" class="block text-sm font-medium text-gray-700">6-Digit OTP</label>
                    <input type="text" name="otp" id="otp" required class="mt-1 p-2 border border-gray-300 rounded-md w-full shadow-sm">
                </div>
                <div>
                    <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
                    <input type="password" name="new_password" id="new_password" required class="mt-1 p-2 border border-gray-300 rounded-md w-full shadow-sm">
                </div>
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" required class="mt-1 p-2 border border-gray-300 rounded-md w-full shadow-sm">
                </div>
                <div>
                    <button type="submit" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
