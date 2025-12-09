<?php
// Set timezone to match your local time (adjust this to your timezone)
date_default_timezone_set('Asia/Colombo'); // Sri Lanka Time (UTC+5:30)
// You can change this to your actual timezone like:
// 'America/New_York', 'Asia/Dubai', 'Europe/London', etc.

// Include database connection
require_once 'db.php';

// Function to generate unique bill number
function generateBillNumber() {
    $year = date('Y');
    $month = date('m');
    return 'BILL-' . $year . $month . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

// Function to get all bicycle types
function getBicycleTypes() {
    global $conn;
    $sql = "SELECT * FROM bicycle_types WHERE status = 'active' ORDER BY type_name";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to add new bicycle type
function addBicycleType($type_name, $base_minutes, $base_price, $extra_charge_per_minute) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO bicycle_types (type_name, base_minutes, base_price, extra_charge_per_minute) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sidd", $type_name, $base_minutes, $base_price, $extra_charge_per_minute);
    return $stmt->execute();
}

// Function to update bicycle type
function updateBicycleType($type_id, $type_name, $base_minutes, $base_price, $extra_charge_per_minute) {
    global $conn;
    $stmt = $conn->prepare("UPDATE bicycle_types SET type_name = ?, base_minutes = ?, base_price = ?, extra_charge_per_minute = ? WHERE id = ?");
    $stmt->bind_param("siddi", $type_name, $base_minutes, $base_price, $extra_charge_per_minute, $type_id);
    return $stmt->execute();
}

// Function to toggle bicycle type status
function toggleBicycleTypeStatus($type_id) {
    global $conn;
    $stmt = $conn->prepare("UPDATE bicycle_types SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END WHERE id = ?");
    $stmt->bind_param("i", $type_id);
    return $stmt->execute();
}

// Function to delete bicycle type with safety check
function deleteBicycleType($type_id) {
    global $conn;
    
    // Check if bicycle type is being used in any bills
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM bills WHERE bicycle_type_id = ?");
    $check_stmt->bind_param("i", $type_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        return ['success' => false, 'message' => 'Cannot delete bicycle type! It is being used in existing bills.'];
    }
    
    $stmt = $conn->prepare("DELETE FROM bicycle_types WHERE id = ?");
    $stmt->bind_param("i", $type_id);
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Bicycle type deleted successfully!'];
    } else {
        return ['success' => false, 'message' => 'Error deleting bicycle type!'];
    }
}

// Function to get all bicycle types
function getAllBicycleTypes() {
    global $conn;
    $sql = "SELECT * FROM bicycle_types ORDER BY type_name";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to start a new rental
function startRental($bicycle_type_id, $customer_name, $customer_phone, $customer_nic, $user_id) {
    global $conn;
    
    // Get bicycle type details
    $stmt = $conn->prepare("SELECT * FROM bicycle_types WHERE id = ?");
    $stmt->bind_param("i", $bicycle_type_id);
    $stmt->execute();
    $bicycle_type = $stmt->get_result()->fetch_assoc();
    
    if (!$bicycle_type) {
        return false;
    }
    
    $bill_number = generateBillNumber();
    $start_time = date('Y-m-d H:i:s');
    $total_amount = $bicycle_type['base_price'];
    
    $stmt = $conn->prepare("INSERT INTO bills (bill_number, bicycle_type_id, customer_name, customer_phone, customer_nic, start_time, base_minutes, base_price, extra_charge_per_minute, total_amount, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sissssidddi", $bill_number, $bicycle_type_id, $customer_name, $customer_phone, $customer_nic, $start_time, $bicycle_type['base_minutes'], $bicycle_type['base_price'], $bicycle_type['extra_charge_per_minute'], $total_amount, $user_id);
    
    if ($stmt->execute()) {
        return $conn->insert_id;
    }
    return false;
}

// Function to close a rental and calculate charges
function closeRental($bill_id, $user_id) {
    global $conn;
    
    // Get bill details
    $stmt = $conn->prepare("SELECT * FROM bills WHERE id = ? AND status = 'open'");
    $stmt->bind_param("i", $bill_id);
    $stmt->execute();
    $bill = $stmt->get_result()->fetch_assoc();
    
    if (!$bill) {
        return false;
    }
    
    $end_time = date('Y-m-d H:i:s');
    $start_time = new DateTime($bill['start_time']);
    $end_time_obj = new DateTime($end_time);
    $interval = $start_time->diff($end_time_obj);
    $actual_minutes = ($interval->h * 60) + $interval->i;
    
    $extra_minutes = max(0, $actual_minutes - $bill['base_minutes']);
    $extra_charges = $extra_minutes * $bill['extra_charge_per_minute'];
    $total_amount = $bill['base_price'] + $extra_charges;
    
    $stmt = $conn->prepare("UPDATE bills SET end_time = ?, actual_minutes = ?, extra_minutes = ?, extra_charges = ?, total_amount = ?, status = 'closed', closed_by = ?, closed_at = ? WHERE id = ?");
    $stmt->bind_param("siiddisi", $end_time, $actual_minutes, $extra_minutes, $extra_charges, $total_amount, $user_id, $end_time, $bill_id);
    
    return $stmt->execute();
}

// Function to close rental with detailed calculation information
function closeRentalWithCalculation($bill_id, $user_id) {
    global $conn;
    
    // Get bill details with bicycle type info
    $stmt = $conn->prepare("SELECT b.*, bt.type_name FROM bills b JOIN bicycle_types bt ON b.bicycle_type_id = bt.id WHERE b.id = ? AND b.status = 'open'");
    $stmt->bind_param("i", $bill_id);
    $stmt->execute();
    $bill = $stmt->get_result()->fetch_assoc();
    
    if (!$bill) {
        return ['success' => false, 'message' => 'Bill not found or already closed'];
    }
    
    $end_time = date('Y-m-d H:i:s');
    $start_time = new DateTime($bill['start_time']);
    $end_time_obj = new DateTime($end_time);
    $interval = $start_time->diff($end_time_obj);
    $actual_minutes = ($interval->h * 60) + $interval->i;
    
    $extra_minutes = max(0, $actual_minutes - $bill['base_minutes']);
    $extra_charges = $extra_minutes * $bill['extra_charge_per_minute'];
    $total_amount = $bill['base_price'] + $extra_charges;
    
    // Update the bill
    $stmt = $conn->prepare("UPDATE bills SET end_time = ?, actual_minutes = ?, extra_minutes = ?, extra_charges = ?, total_amount = ?, status = 'closed', closed_by = ?, closed_at = ? WHERE id = ?");
    $stmt->bind_param("siiddisi", $end_time, $actual_minutes, $extra_minutes, $extra_charges, $total_amount, $user_id, $end_time, $bill_id);
    
    if ($stmt->execute()) {
        // Return detailed calculation information
        return [
            'success' => true,
            'bill_id' => $bill_id,
            'bill_number' => $bill['bill_number'],
            'customer_name' => $bill['customer_name'],
            'customer_phone' => $bill['customer_phone'],
            'type_name' => $bill['type_name'],
            'start_time' => $bill['start_time'],
            'end_time' => $end_time,
            'base_minutes' => $bill['base_minutes'],
            'actual_minutes' => $actual_minutes,
            'extra_minutes' => $extra_minutes,
            'base_price' => $bill['base_price'],
            'extra_charge_per_minute' => $bill['extra_charge_per_minute'],
            'extra_charges' => $extra_charges,
            'total_amount' => $total_amount
        ];
    } else {
        return ['success' => false, 'message' => 'Failed to update bill'];
    }
}

// Function to get open bills
function getOpenBills() {
    global $conn;
    $sql = "SELECT b.*, bt.type_name FROM bills b 
            JOIN bicycle_types bt ON b.bicycle_type_id = bt.id 
            WHERE b.status = 'open' 
            ORDER BY b.start_time DESC";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to get all bills
function getAllBills($limit = 50) {
    global $conn;
    $sql = "SELECT b.*, bt.type_name, u1.username as created_by_name, u2.username as closed_by_name 
            FROM bills b 
            JOIN bicycle_types bt ON b.bicycle_type_id = bt.id 
            LEFT JOIN users u1 ON b.created_by = u1.id 
            LEFT JOIN users u2 ON b.closed_by = u2.id 
            ORDER BY b.created_at DESC 
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Function to format time duration
function formatDuration($minutes) {
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return $hours > 0 ? "{$hours}h {$mins}m" : "{$mins}m";
}

// Function to format currency
function formatCurrency($amount) {
    return 'Rs. ' . number_format($amount, 2);
}





// Function to get pending bills for billing process
function getPendingBills($limit = 20) {
    global $conn;
    $sql = "SELECT b.*, bt.type_name FROM bills b 
            JOIN bicycle_types bt ON b.bicycle_type_id = bt.id 
            WHERE b.status = 'open' 
            ORDER BY b.start_time ASC
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Function to process payment for a bill
function processPayment($bill_id, $user_id, $payment_method = 'cash') {
    global $conn;
    
    // Get bill details
    $stmt = $conn->prepare("SELECT * FROM bills WHERE id = ?");
    $stmt->bind_param("i", $bill_id);
    $stmt->execute();
    $bill = $stmt->get_result()->fetch_assoc();
    
    if (!$bill) {
        return false;
    }
    
    // Insert payment record
    $payment_type = 'base';
    $notes = 'Initial payment for rental';
    $stmt = $conn->prepare("INSERT INTO payments (bill_id, amount, payment_type, payment_method, received_by, notes) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("idssss", $bill_id, $bill['base_price'], $payment_type, $payment_method, $user_id, $notes);
    
    return $stmt->execute();
}

// Function to get closed bills
function getClosedBills($limit = 50) {
    global $conn;
    $sql = "SELECT b.*, bt.type_name, u1.username as created_by_name, u2.username as closed_by_name,
            b.actual_minutes as total_minutes
            FROM bills b 
            JOIN bicycle_types bt ON b.bicycle_type_id = bt.id 
            LEFT JOIN users u1 ON b.created_by = u1.id 
            LEFT JOIN users u2 ON b.closed_by = u2.id 
            WHERE b.status = 'closed'
            ORDER BY b.closed_at DESC 
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Function to calculate current charges for overtime billing
function calculateCurrentCharges($bill_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM bills WHERE id = ? AND status = 'open'");
    $stmt->bind_param("i", $bill_id);
    $stmt->execute();
    $bill = $stmt->get_result()->fetch_assoc();
    
    if (!$bill) {
        return false;
    }
    
    $start_time = new DateTime($bill['start_time']);
    $current_time = new DateTime();
    $interval = $start_time->diff($current_time);
    $elapsed_minutes = ($interval->h * 60) + $interval->i;
    
    $extra_minutes = max(0, $elapsed_minutes - $bill['base_minutes']);
    $extra_charges = $extra_minutes * $bill['extra_charge_per_minute'];
    $total_amount = $bill['base_price'] + $extra_charges;
    
    return [
        'elapsed_minutes' => $elapsed_minutes,
        'extra_minutes' => $extra_minutes,
        'extra_charges' => $extra_charges,
        'total_amount' => $total_amount,
        'is_overtime' => $elapsed_minutes > $bill['base_minutes']
    ];
}



// Action Handler Functions

// Function to handle confirm order action
function handleConfirmOrder($bicycle_type_id, $customer_name, $customer_phone, $customer_nic, $user_id, $payment_method) {
    $bill_id = startRental($bicycle_type_id, $customer_name, $customer_phone, $customer_nic, $user_id);
    if ($bill_id) {
        // Process payment
        $payment_result = processPayment($bill_id, $user_id, $payment_method);
        if ($payment_result) {
            $_SESSION['last_bill_id'] = $bill_id;
            return [
                'success' => true,
                'message' => "Order confirmed and payment processed successfully!",
                'type' => 'success'
            ];
        } else {
            return [
                'success' => false,
                'message' => "Order created but payment processing failed!",
                'type' => 'error'
            ];
        }
    } else {
        return [
            'success' => false,
            'message' => "Error creating order!",
            'type' => 'error'
        ];
    }
}

// Function to handle close rental action
function handleCloseRental($order_id, $user_id) {
    $result = closeRentalWithCalculation($order_id, $user_id);
    if ($result && $result['success']) {
        $_SESSION['rental_calculation'] = $result;
        return [
            'success' => true,
            'message' => "Rental closed successfully!",
            'type' => 'success'
        ];
    } else {
        return [
            'success' => false,
            'message' => "Error closing rental!",
            'type' => 'error'
        ];
    }
}

// Function to handle update bicycle type action
function handleUpdateBicycleType($type_id, $type_name, $base_minutes, $base_price, $extra_charge_per_minute) {
    $result = updateBicycleType($type_id, $type_name, $base_minutes, $base_price, $extra_charge_per_minute);
    if ($result) {
        $_SESSION['success_message'] = "Bicycle type updated successfully!";
        return ['success' => true, 'redirect' => 'settings.php'];
    } else {
        $_SESSION['error_message'] = "Error updating bicycle type!";
        return ['success' => false, 'redirect' => 'settings.php'];
    }
}

// Function to handle toggle bicycle type status action
function handleToggleStatus($type_id) {
    $result = toggleBicycleTypeStatus($type_id);
    if ($result) {
        $_SESSION['success_message'] = "Bicycle type status updated!";
        return ['success' => true, 'redirect' => 'settings.php'];
    } else {
        $_SESSION['error_message'] = "Error updating status!";
        return ['success' => false, 'redirect' => 'settings.php'];
    }
}

// Function to handle delete bicycle type action
function handleDeleteBicycleType($type_id) {
    $result = deleteBicycleType($type_id);
    if ($result['success']) {
        $_SESSION['success_message'] = $result['message'];
        return ['success' => true, 'redirect' => 'settings.php'];
    } else {
        $_SESSION['error_message'] = $result['message'];
        return ['success' => false, 'redirect' => 'settings.php'];
    }
}

// Function to handle add bicycle type action
function handleAddBicycleType($type_name, $base_minutes, $base_price, $extra_charge_per_minute) {
    $result = addBicycleType($type_name, $base_minutes, $base_price, $extra_charge_per_minute);
    if ($result) {
        $_SESSION['success_message'] = "Bicycle type added successfully!";
        return ['success' => true, 'redirect' => 'settings.php'];
    } else {
        $_SESSION['error_message'] = "Error adding bicycle type!";
        return ['success' => false, 'redirect' => 'settings.php'];
    }
}

// Function to handle all POST actions
function handlePostActions() {
    $message = '';
    $message_type = '';
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'confirm_order':
                    $result = handleConfirmOrder(
                        $_POST['bicycle_type_id'],
                        $_POST['customer_name'],
                        $_POST['customer_phone'],
                        $_POST['customer_nic'],
                        $_SESSION['user_id'],
                        $_POST['payment_method']
                    );
                    $message = $result['message'];
                    $message_type = $result['type'];
                    break;
                    
                case 'close_rental':
                case 'end_rental':
                    // Handle both close_rental and end_rental actions
                    $order_id = isset($_POST['order_id']) ? $_POST['order_id'] : $_POST['bill_id'];
                    $result = handleCloseRental($order_id, $_SESSION['user_id']);
                    $message = $result['message'];
                    $message_type = $result['type'];
                    break;
            }
        }
    }
    
    return ['message' => $message, 'type' => $message_type];
}

// Function to handle login action
function handleLogin($username, $password) {
    $auth_result = authenticateUser($username, $password);
    
    if ($auth_result['success']) {
        loginUser($auth_result['user']);
        return ['success' => true, 'redirect' => 'index.php'];
    } else {
        return ['success' => false, 'error' => $auth_result['error']];
    }
}

// Function to handle login POST actions
function handleLoginActions() {
    $error = "";
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $result = handleLogin($_POST['username'], $_POST['password']);
        
        if ($result['success']) {
            header("Location: " . $result['redirect']);
            exit;
        } else {
            $error = $result['error'];
        }
    }
    
    return $error;
}

// Function to handle settings POST actions
function handleSettingsActions() {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_bicycle_type':
                    $result = handleUpdateBicycleType($_POST['type_id'], $_POST['type_name'], $_POST['base_minutes'], $_POST['base_price'], $_POST['extra_charge_per_minute']);
                    if ($result['redirect']) {
                        header('Location: ' . $result['redirect']);
                        exit;
                    }
                    break;
                    
                case 'toggle_status':
                    $result = handleToggleStatus($_POST['type_id']);
                    if ($result['redirect']) {
                        header('Location: ' . $result['redirect']);
                        exit;
                    }
                    break;
                    
                case 'delete_bicycle_type':
                    $result = handleDeleteBicycleType($_POST['type_id']);
                    if ($result['redirect']) {
                        header('Location: ' . $result['redirect']);
                        exit;
                    }
                    break;
                    
                case 'add_bicycle_type':
                    $result = handleAddBicycleType($_POST['type_name'], $_POST['base_minutes'], $_POST['base_price'], $_POST['extra_charge_per_minute']);
                    if ($result['redirect']) {
                        header('Location: ' . $result['redirect']);
                        exit;
                    }
                    break;
            }
        }
    }
}

// Daily Report Functions

// Function to get daily report data
function getDailyReport($date) {
    global $conn;
    
    $sql = "SELECT b.*, bt.type_name 
            FROM bills b 
            JOIN bicycle_types bt ON b.bicycle_type_id = bt.id 
            WHERE DATE(b.start_time) = ?
            ORDER BY b.start_time DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $date);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Function to get daily statistics
function getDailyStats($date) {
    global $conn;
    
    // Total rentals for the day
    $stmt = $conn->prepare("SELECT COUNT(*) as total_rentals FROM bills WHERE DATE(start_time) = ?");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $total_rentals = $stmt->get_result()->fetch_assoc()['total_rentals'];
    
    // Total revenue for the day
    $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as total_revenue FROM bills WHERE DATE(start_time) = ? AND status = 'closed'");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $total_revenue = $stmt->get_result()->fetch_assoc()['total_revenue'];
    
    // Active rentals (bills that started today and are still open)
    $stmt = $conn->prepare("SELECT COUNT(*) as active_rentals FROM bills WHERE DATE(start_time) = ? AND status = 'open'");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $active_rentals = $stmt->get_result()->fetch_assoc()['active_rentals'];
    
    // Extra charges for the day
    $stmt = $conn->prepare("SELECT COALESCE(SUM(extra_charges), 0) as extra_charges FROM bills WHERE DATE(start_time) = ? AND status = 'closed'");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $extra_charges = $stmt->get_result()->fetch_assoc()['extra_charges'];
    
    return [
        'total_rentals' => $total_rentals,
        'total_revenue' => $total_revenue,
        'active_rentals' => $active_rentals,
        'extra_charges' => $extra_charges
    ];
}

// Function to get monthly report data
function getMonthlyReport($year, $month) {
    global $conn;
    
    $sql = "SELECT b.*, bt.type_name 
            FROM bills b 
            JOIN bicycle_types bt ON b.bicycle_type_id = bt.id 
            WHERE YEAR(b.start_time) = ? AND MONTH(b.start_time) = ?
            ORDER BY b.start_time DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $year, $month);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Function to get monthly statistics
function getMonthlyStats($year, $month) {
    global $conn;
    
    // Total rentals for the month
    $stmt = $conn->prepare("SELECT COUNT(*) as total_rentals FROM bills WHERE YEAR(start_time) = ? AND MONTH(start_time) = ?");
    $stmt->bind_param("ii", $year, $month);
    $stmt->execute();
    $total_rentals = $stmt->get_result()->fetch_assoc()['total_rentals'];
    
    // Total revenue for the month
    $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as total_revenue FROM bills WHERE YEAR(start_time) = ? AND MONTH(start_time) = ? AND status = 'closed'");
    $stmt->bind_param("ii", $year, $month);
    $stmt->execute();
    $total_revenue = $stmt->get_result()->fetch_assoc()['total_revenue'];
    
    // Active rentals for the month
    $stmt = $conn->prepare("SELECT COUNT(*) as active_rentals FROM bills WHERE YEAR(start_time) = ? AND MONTH(start_time) = ? AND status = 'open'");
    $stmt->bind_param("ii", $year, $month);
    $stmt->execute();
    $active_rentals = $stmt->get_result()->fetch_assoc()['active_rentals'];
    
    // Extra charges for the month
    $stmt = $conn->prepare("SELECT COALESCE(SUM(extra_charges), 0) as extra_charges FROM bills WHERE YEAR(start_time) = ? AND MONTH(start_time) = ? AND status = 'closed'");
    $stmt->bind_param("ii", $year, $month);
    $stmt->execute();
    $extra_charges = $stmt->get_result()->fetch_assoc()['extra_charges'];
    
    return [
        'total_rentals' => $total_rentals,
        'total_revenue' => $total_revenue,
        'active_rentals' => $active_rentals,
        'extra_charges' => $extra_charges
    ];
}

// Function to get popular bicycle types report
function getPopularBicycleTypes($start_date = null, $end_date = null) {
    global $conn;
    
    $sql = "SELECT bt.type_name, COUNT(b.id) as rental_count, COALESCE(SUM(b.total_amount), 0) as total_revenue
            FROM bicycle_types bt
            LEFT JOIN bills b ON bt.id = b.bicycle_type_id";
    
    if ($start_date && $end_date) {
        $sql .= " AND DATE(b.start_time) BETWEEN ? AND ?";
    }
    
    $sql .= " GROUP BY bt.id, bt.type_name
              ORDER BY rental_count DESC, total_revenue DESC";
    
    $stmt = $conn->prepare($sql);
    
    if ($start_date && $end_date) {
        $stmt->bind_param("ss", $start_date, $end_date);
    }
    
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Function to get peak hours report
function getPeakHoursReport($date) {
    global $conn;
    
    $sql = "SELECT HOUR(start_time) as hour, COUNT(*) as rental_count
            FROM bills 
            WHERE DATE(start_time) = ?
            GROUP BY HOUR(start_time)
            ORDER BY hour";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $date);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Function to generate Report PDF
function generateReportPDF($date) {
    // Get report data
    $daily_report = getDailyReport($date);
    $daily_stats = getDailyStats($date);
    
    // Generate PDF content as HTML (without auto-print)
    $htmlContent = generateDownloadablePDFContent($date, $daily_report, $daily_stats);
    
    // Set headers to force download as HTML file (user can save as PDF)
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="Daily_Report_' . $date . '.html"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // Output HTML content
    echo $htmlContent;
    exit;
}

// Function to download daily report as HTML PDF (Alternative method)
function downloadDailyReportPDF($date) {
    // Get report data
    $daily_report = getDailyReport($date);
    $daily_stats = getDailyStats($date);
    
    // Set headers for HTML download that can be printed as PDF
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="Daily_Report_' . $date . '.html"');
    header('Cache-Control: no-cache, must-revalidate');
    
    // Generate HTML content that auto-prints when opened
    $html = generateDownloadablePDFContent($date, $daily_report, $daily_stats);
    echo $html;
}

// Function to generate downloadable PDF HTML content (Simplified version)
function generateDownloadablePDFContent($date, $daily_report, $daily_stats) {
    ob_start();
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Daily Report - <?php echo date('F d, Y', strtotime($date)); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 20px; margin-bottom: 30px; }
        .stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-box { border: 1px solid #ddd; padding: 15px; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f5f5f5; }
        @media print { body { margin: 0; } }
    </style>
    <!-- No auto-print - just download the file -->
</head>
<body>
    <div class="header">
        <h1>🚴 Bicycle Rental System</h1>
        <h2>Daily Report</h2>
        <p>Report Date: <?php echo date('F d, Y', strtotime($date)); ?></p>
        <p>Generated: <?php echo date('F d, Y g:i A'); ?></p>
    </div>
    
    <!-- Statistics removed for clean report display -->
    
    <?php if (!empty($daily_report)): ?>
    <h3>Transaction Details</h3>
    <table>
        <thead>
            <tr>
                <th>Bill No.</th>
                <th>Customer</th>
                <th>Phone</th>
                <th>Bike Type</th>
                <th>Start</th>
                <th>End</th>
                <th>Duration</th>
                <th>Base Amount</th>
                <th>Extra Charges</th>
                <th>Total</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($daily_report as $bill): ?>
            <tr>
                <td><?php echo htmlspecialchars($bill['bill_number']); ?></td>
                <td><?php echo htmlspecialchars($bill['customer_name']); ?></td>
                <td><?php echo htmlspecialchars($bill['customer_phone']); ?></td>
                <td><?php echo htmlspecialchars($bill['type_name']); ?></td>
                <td><?php echo date('H:i', strtotime($bill['start_time'])); ?></td>
                <td><?php echo $bill['end_time'] ? date('H:i', strtotime($bill['end_time'])) : '-'; ?></td>
                <td><?php echo $bill['actual_minutes'] ? formatDuration($bill['actual_minutes']) : '-'; ?></td>
                <td><?php echo formatCurrency($bill['base_price']); ?></td>
                <td><?php echo formatCurrency($bill['extra_charges']); ?></td>
                <td><strong><?php echo formatCurrency($bill['total_amount']); ?></strong></td>
                <td><?php echo strtoupper($bill['status']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p style="text-align: center; padding: 40px; color: #666;">No transactions found for this date.</p>
    <?php endif; ?>
    
    <div style="margin-top: 40px; text-align: center; border-top: 1px solid #ddd; padding-top: 20px;">
        <p>Bicycle Rental System - <?php echo date('Y'); ?></p>
    </div>
</body>
</html>
    <?php
    return ob_get_clean();
}

// Function to generate simple PDF content
function generateSimplePDFContent($date, $daily_report, $daily_stats) {
    $content = "BICYCLE RENTAL SYSTEM - DAILY REPORT\n";
    $content .= "=====================================\n\n";
    $content .= "Report Date: " . date('F d, Y', strtotime($date)) . "\n";
    $content .= "Generated: " . date('F d, Y g:i A') . "\n\n";
    
    $content .= "DAILY STATISTICS\n";
    $content .= "================\n";
    $content .= "Total Rentals: " . $daily_stats['total_rentals'] . "\n";
    $content .= "Total Revenue: " . formatCurrency($daily_stats['total_revenue']) . "\n";
    $content .= "Active Rentals: " . $daily_stats['active_rentals'] . "\n";
    $content .= "Extra Charges: " . formatCurrency($daily_stats['extra_charges']) . "\n\n";
    
    if (!empty($daily_report)) {
        $content .= "TRANSACTION DETAILS\n";
        $content .= "==================\n";
        foreach ($daily_report as $bill) {
            $content .= "Bill: " . $bill['bill_number'] . "\n";
            $content .= "Customer: " . $bill['customer_name'] . " (" . $bill['customer_phone'] . ")\n";
            $content .= "Bike Type: " . $bill['type_name'] . "\n";
            $content .= "Start Time: " . date('H:i', strtotime($bill['start_time'])) . "\n";
            $content .= "End Time: " . ($bill['end_time'] ? date('H:i', strtotime($bill['end_time'])) : 'Active') . "\n";
            $content .= "Duration: " . ($bill['actual_minutes'] ? formatDuration($bill['actual_minutes']) : 'Active') . "\n";
            $content .= "Base Amount: " . formatCurrency($bill['base_price']) . "\n";
            $content .= "Extra Charges: " . formatCurrency($bill['extra_charges']) . "\n";
            $content .= "Total Amount: " . formatCurrency($bill['total_amount']) . "\n";
            $content .= "Status: " . strtoupper($bill['status']) . "\n";
            $content .= "---\n";
        }
    } else {
        $content .= "No transactions found for this date.\n";
    }
    
    return $content;
}

// Function to generate PDF HTML content (Advanced version)
function generatePDFHTMLContent($date, $daily_report, $daily_stats) {
    ob_start();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Report - <?php echo date('F d, Y', strtotime($date)); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: white;
            color: #333;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #dc2626;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 0;
            font-size: 32px;
            color: #000;
        }
        .header h2 {
            margin: 10px 0;
            font-size: 20px;
            color: #dc2626;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            border: 2px solid #e5e7eb;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            background: #f9fafb;
        }
        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #374151;
            font-size: 16px;
        }
        .stat-card .value {
            font-size: 28px;
            font-weight: bold;
            color: #000;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 14px;
        }
        th, td {
            border: 1px solid #d1d5db;
            padding: 12px 8px;
            text-align: left;
        }
        th {
            background-color: #111827;
            color: white;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            border-top: 2px solid #d1d5db;
            padding-top: 20px;
            color: #6b7280;
        }
        .no-print {
            margin-bottom: 20px;
        }
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
    </style>
    <script>
        // Auto-print immediately when page loads
        window.onload = function() {
            window.print();
        };
        
        // Alternative: trigger print after minimal delay
        setTimeout(function() {
            window.print();
        }, 100);
    </script>
</head>
<body>

    <div class="header">
        <h1>🚴 Bicycle Rental System</h1>
        <h2>Daily Report</h2>
        <p><strong>Report Date:</strong> <?php echo date('F d, Y', strtotime($date)); ?></p>
        <p><strong>Generated:</strong> <?php echo date('F d, Y g:i A'); ?></p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>🚴 Total Rentals</h3>
            <div class="value"><?php echo $daily_stats['total_rentals']; ?></div>
        </div>
        <div class="stat-card">
            <h3>💰 Total Revenue</h3>
            <div class="value" style="color: #16a34a;"><?php echo formatCurrency($daily_stats['total_revenue']); ?></div>
        </div>
        <div class="stat-card">
            <h3>⏱️ Active Rentals</h3>
            <div class="value"><?php echo $daily_stats['active_rentals']; ?></div>
        </div>
        <div class="stat-card">
            <h3>⚠️ Extra Charges</h3>
            <div class="value" style="color: #dc2626;"><?php echo formatCurrency($daily_stats['extra_charges']); ?></div>
        </div>
    </div>

    <?php if (empty($daily_report)): ?>
        <div style="text-align: center; padding: 60px; color: #6b7280;">
            <h3>📊 No Transactions Found</h3>
            <p>No rental transactions were recorded for this date.</p>
        </div>
    <?php else: ?>
        <h3 style="color: #000; border-bottom: 2px solid #dc2626; padding-bottom: 10px;">📋 Transaction Details</h3>
        
        <table>
            <thead>
                <tr>
                    <th>Bill Number</th>
                    <th>Customer</th>
                    <th>Phone</th>
                    <th>Bike Type</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Duration</th>
                    <th>Base Amount</th>
                    <th>Extra Charges</th>
                    <th>Total Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($daily_report as $bill): ?>
                <tr>
                    <td><?php echo htmlspecialchars($bill['bill_number']); ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($bill['customer_name']); ?></strong><br>
                        <small><?php echo htmlspecialchars($bill['customer_phone']); ?></small>
                    </td>
                    <td><?php echo htmlspecialchars($bill['customer_phone']); ?></td>
                    <td><?php echo htmlspecialchars($bill['type_name']); ?></td>
                    <td><?php echo date('H:i', strtotime($bill['start_time'])); ?></td>
                    <td><?php echo $bill['end_time'] ? date('H:i', strtotime($bill['end_time'])) : '-'; ?></td>
                    <td><?php echo $bill['actual_minutes'] ? formatDuration($bill['actual_minutes']) : '-'; ?></td>
                    <td style="color: #16a34a; font-weight: bold;"><?php echo formatCurrency($bill['base_price']); ?></td>
                    <td style="color: #dc2626; font-weight: bold;"><?php echo formatCurrency($bill['extra_charges']); ?></td>
                    <td style="font-weight: bold;"><?php echo formatCurrency($bill['total_amount']); ?></td>
                    <td>
                        <?php if ($bill['status'] == 'open'): ?>
                            <span style="background: #fef3c7; color: #92400e; padding: 4px 8px; border-radius: 12px; font-size: 12px;">ACTIVE</span>
                        <?php else: ?>
                            <span style="background: #d1fae5; color: #065f46; padding: 4px 8px; border-radius: 12px; font-size: 12px;">COMPLETED</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="footer">
        <p><strong>Bicycle Rental System</strong> - Professional Rental Management</p>
        <p>Generated on <?php echo date('F d, Y \a\t g:i A'); ?></p>
    </div>
</body>
</html>
    <?php
    return ob_get_clean();
}

// JavaScript Functions (Output as script tags when needed)

function outputJavaScriptFunctions() {
    echo '<script>';
    echo '
// Global variables';
    echo '
let selectedBicycleType = null;';
    echo '

// Bicycle Type Modal Functions';
    echo '
function openBicycleTypeModal() {';
    echo '\n    document.getElementById("bicycleTypeModal").classList.remove("hidden");';
    echo '\n}';
    echo '

function closeBicycleTypeModal() {';
    echo '\n    document.getElementById("bicycleTypeModal").classList.add("hidden");';
    echo '\n}';
    echo '

function selectBicycleType(type) {';
    echo '\n    selectedBicycleType = type;';
    echo '\n    ';
    echo '\n    // Update the selected type display';
    echo '\n    document.getElementById("selectedBicycleTypeId").value = type.id;';
    echo '\n    document.getElementById("selectedTypeInfo").innerHTML = `';
    echo '\n        <div>';
    echo '\n            <span class="text-lg font-bold">${type.type_name}</span><br>';
    echo '\n            <span class="text-red-600">${formatCurrency(type.base_price)} for ${formatDuration(type.base_minutes)}</span><br>';
    echo '\n            <span class="text-gray-600">${formatCurrency(type.extra_charge_per_minute)}/min extra</span>';
    echo '\n        </div>';
    echo '\n    `;';
    echo '\n    ';
    echo '\n    // Show selected type and user details form';
    echo '\n    document.getElementById("selectedTypeDisplay").classList.remove("hidden");';
    echo '\n    document.getElementById("userDetailsForm").classList.remove("hidden");';
    echo '\n    ';
    echo '\n    // Update button text';
    echo '\n    document.getElementById("selectBicycleTypeBtn").innerHTML = "✅ Bicycle Type Selected - " + type.type_name;';
    echo '\n    document.getElementById("selectBicycleTypeBtn").classList.remove("bg-red-500", "hover:bg-red-600");';
    echo '\n    document.getElementById("selectBicycleTypeBtn").classList.add("bg-green-500", "hover:bg-green-600");';
    echo '\n    ';
    echo '\n    // Close modal';
    echo '\n    closeBicycleTypeModal();';
    echo '\n}';
    echo '

// Utility Functions';
    echo '
function formatCurrency(amount) {';
    echo '\n    return "Rs. " + parseFloat(amount).toFixed(2);';
    echo '\n}';
    echo '

function formatDuration(minutes) {';
    echo '\n    if (minutes < 60) {';
    echo '\n        return minutes + " min";';
    echo '\n    } else {';
    echo '\n        const hours = Math.floor(minutes / 60);';
    echo '\n        const remainingMinutes = minutes % 60;';
    echo '\n        if (remainingMinutes === 0) {';
    echo '\n            return hours + " hr";';
    echo '\n        } else {';
    echo '\n            return hours + " hr " + remainingMinutes + " min";';
    echo '\n        }';
    echo '\n    }';
    echo '\n}';
    echo '

// Receipt Modal Functions';
    echo '
function printReceipt() {';
    echo '\n    window.print();';
    echo '\n}';
    echo '

function closeReceipt() {';
    echo '\n    document.getElementById("receiptModal").style.display = "none";';
    echo '\n}';
    echo '

// Calculation Modal Functions';
    echo '
function closeCalculationModal() {';
    echo '\n    document.getElementById("calculationModal").classList.add("hidden");';
    echo '\n}';
    echo '

function printBill() {';
    echo '\n    const printContent = `';
    echo '\n        <div style="font-family: Arial, sans-serif; max-width: 400px; margin: 0 auto; padding: 20px;">';
    echo '\n            <div style="text-align: center; margin-bottom: 20px;">';
    echo '\n                <h2>🚴 Bicycle Rental System</h2>';
    echo '\n                <h3>EXTRA CHARGES BILL</h3>';
    echo '\n                <p>${new Date().toLocaleString()}</p>';
    echo '\n            </div>';
    echo '\n            <div style="text-align: center; margin-top: 20px;">';
    echo '\n                <p style="font-size: 12px;">Thank you for using our service!</p>';
    echo '\n            </div>';
    echo '\n        </div>';
    echo '\n    `;';
    echo '\n    ';
    echo '\n    const printWindow = window.open("", "_blank");';
    echo '\n    printWindow.document.write(`';
    echo '\n        <html>';
    echo '\n        <head><title>Extra Charges Bill</title></head>';
    echo '\n        <body onload="window.print(); window.close();">';
    echo '\n            ${printContent}';
    echo '\n        </body>';
    echo '\n        </html>';
    echo '\n    `);';
    echo '\n    printWindow.document.close();';
    echo '\n}';
    echo '

// Settings Modal Functions';
    echo '
function editBicycleType(type) {';
    echo '\n    document.getElementById("edit_type_id").value = type.id;';
    echo '\n    document.getElementById("edit_type_name").value = type.type_name;';
    echo '\n    document.getElementById("edit_base_minutes").value = type.base_minutes;';
    echo '\n    document.getElementById("edit_base_price").value = type.base_price;';
    echo '\n    document.getElementById("edit_extra_charge").value = type.extra_charge_per_minute;';
    echo '\n    ';
    echo '\n    document.getElementById("editModal").classList.remove("hidden");';
    echo '\n}';
    echo '

function closeEditModal() {';
    echo '\n    document.getElementById("editModal").classList.add("hidden");';
    echo '\n}';
    echo '

// Message Functions';
    echo '
function hideMessage() {';
    echo '\n    const alert = document.getElementById("messageAlert");';
    echo '\n    if (alert) {';
    echo '\n        alert.style.opacity = "0";';
    echo '\n        setTimeout(function() {';
    echo '\n            alert.style.display = "none";';
    echo '\n        }, 500);';
    echo '\n    }';
    echo '\n}';
    echo '

// Event Listeners';
    echo '
document.addEventListener("DOMContentLoaded", function() {';
    echo '\n    ';
    echo '\n    // Bicycle Type Modal - Close when clicking outside';
    echo '\n    const bicycleTypeModal = document.getElementById("bicycleTypeModal");';
    echo '\n    if (bicycleTypeModal) {';
    echo '\n        bicycleTypeModal.addEventListener("click", function(e) {';
    echo '\n            if (e.target === this) {';
    echo '\n                closeBicycleTypeModal();';
    echo '\n            }';
    echo '\n        });';
    echo '\n    }';
    echo '\n    ';
    echo '\n    // Edit Modal - Close when clicking outside';
    echo '\n    const editModal = document.getElementById("editModal");';
    echo '\n    if (editModal) {';
    echo '\n        editModal.addEventListener("click", function(e) {';
    echo '\n            if (e.target === this) {';
    echo '\n                closeEditModal();';
    echo '\n            }';
    echo '\n        });';
    echo '\n    }';
    echo '\n    ';
    echo '\n    // Calculation Modal - Close when clicking outside';
    echo '\n    const calculationModal = document.getElementById("calculationModal");';
    echo '\n    if (calculationModal) {';
    echo '\n        calculationModal.addEventListener("click", function(e) {';
    echo '\n            if (e.target === this) {';
    echo '\n                closeCalculationModal();';
    echo '\n            }';
    echo '\n        });';
    echo '\n    }';
    echo '\n    ';
    echo '\n    // Auto-hide message after 5 seconds';
    echo '\n    const messageAlert = document.getElementById("messageAlert");';
    echo '\n    if (messageAlert) {';
    echo '\n        setTimeout(function() {';
    echo '\n            hideMessage();';
    echo '\n        }, 5000);';
    echo '\n    }';
    echo '\n});';
    echo '
</script>';
}

// Authentication Functions

// Function to authenticate user login
function authenticateUser($username, $password) {
    global $conn;
    
    $username = trim($username);
    $password = trim($password);
    
    // Query user from database
    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            return ['success' => true, 'user' => $user];
        }
    }
    
    $stmt->close();
    return ['success' => false, 'error' => 'Invalid username or password!'];
}

// Function to login user and set session
function loginUser($user) {
    $_SESSION['logged_in'] = true;
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
}

// Function to logout user
function logoutUser() {
    session_start();
    session_destroy();
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

// Function to check if user has admin role
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Function to redirect if not logged in
function requireLogin($redirect_url = 'inc/login.php') {
    if (!isLoggedIn()) {
        header("Location: $redirect_url");
        exit;
    }
}

// Function to redirect if not admin
function requireAdmin($redirect_url = '../index.php') {
    if (!isAdmin()) {
        header("Location: $redirect_url");
        exit;
    }
}

?>
