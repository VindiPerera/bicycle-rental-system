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
function startRental($bicycle_type_id, $customer_name, $customer_phone, $customer_nic, $user_id, $order_id = null) {
    global $conn;
    
    // Get bicycle type details
    $stmt = $conn->prepare("SELECT * FROM bicycle_types WHERE id = ?");
    $stmt->bind_param("i", $bicycle_type_id);
    $stmt->execute();
    $bicycle_type = $stmt->get_result()->fetch_assoc();
    
    if (!$bicycle_type) {
        return false;
    }
    
    $bill_number = $order_id ?: generateBillNumber();
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



// Function to add bicycle to open bikes (start rental with order ID)
function addOpenBike($bicycle_type_id, $customer_name, $customer_phone, $user_id, $order_id = null) {
    if (!$order_id) {
        $order_id = 'ORD-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    }
    
    return startRental($bicycle_type_id, $customer_name, $customer_phone, $user_id, $order_id);
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
    $bill_id = startRental($bicycle_type_id, $customer_name, $customer_phone, $customer_nic, $user_id, null);
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
                    $result = handleCloseRental($_POST['order_id'], $_SESSION['user_id']);
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