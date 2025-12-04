<?php
session_start();
require_once 'inc/functions.php';

// Check authentication
requireLogin('login.php');

// Handle form submissions
$result = handlePostActions();
$message = $result['message'];
$message_type = $result['type'];

// Get data
$bicycle_types = getBicycleTypes();
$open_bills = getOpenBills();
$closed_bills = getClosedBills(20);
$pending_bills = getPendingBills(10);
$all_bills = getAllBills(20);

include 'inc/header.php';
?>

<style>
.view-container {
    transition: opacity 0.3s ease-in-out;
}
.view-container.hidden {
    display: none;
}
</style>

<div class="px-4 py-4">
    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-black">Dashboard</h1>
        <p class="mt-2 text-gray-700 text-base">Manage bicycle rentals and view billing information</p>
    </div>

    <!-- Message Alert -->
    <?php if ($message): ?>
    <div id="messageAlert" class="mb-4 p-4 rounded-lg border <?php echo $message_type == 'success' ? 'bg-green-50 border-green-500 text-green-700' : 'bg-red-50 border-red-500 text-red-700'; ?> shadow transition-opacity duration-500">
        <div class="flex justify-between items-center">
            <span class="font-semibold"><?php echo htmlspecialchars($message); ?></span>
            <button onclick="hideMessage()" class="<?php echo $message_type == 'success' ? 'text-green-800 hover:text-green-900' : 'text-red-800 hover:text-red-900'; ?> font-bold text-lg">
                ×
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- View Toggle Controls -->
    <div class="mb-4">
        <div class="flex justify-center">
            <div class="bg-gray-200 p-1 rounded-lg inline-flex space-x-1">
                <button id="addRentalToggle" onclick="showAddRentalView()" 
                        class="px-6 py-2 rounded-lg font-bold transition-all duration-200 bg-black text-white text-sm">
                    🚴 Add Rental
                </button>
                <button id="openBikesToggle" onclick="showOpenBikesView()" 
                        class="px-6 py-2 rounded-lg font-bold transition-all duration-200 bg-gray-300 text-gray-700 hover:bg-gray-400 text-sm">
                    🚴‍♂️ Open Bikes <span class="ml-2 bg-red-500 text-white text-xs px-2 py-1 rounded-full"><?php echo count($open_bills); ?></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Add Rental View -->
    <div id="addRentalView" class="view-container">
        <div class="max-w-6xl mx-auto bg-white shadow-lg rounded-lg p-4 border border-gray-200">
        <div class="flex items-center mb-4">
            <span class="text-2xl mr-3">🚴</span>
            <h2 class="text-xl font-bold text-black">Add Bicycle to Open Bikes (Start Rental)</h2>
        </div>
        
        <!-- Step 1: Select Bicycle Type -->
        <div class="mb-6">
            <label class="block text-lg font-bold text-black mb-3">Step 1: Select Bicycle Type</label>
            <button id="selectBicycleTypeBtn" onclick="openBicycleTypeModal()" 
                    class="w-full bg-red-500 text-white py-4 px-6 rounded-lg hover:bg-red-600 font-bold text-lg border border-red-600">
                🚴‍♂️ Choose Bicycle Type
            </button>
            <div id="selectedTypeDisplay" class="mt-4 hidden">
                <div class="bg-white border border-red-500 rounded-lg p-4">
                    <div class="flex justify-between items-center">
                        <div id="selectedTypeInfo" class="text-black font-semibold"></div>
                        <button onclick="openBicycleTypeModal()" class="text-red-600 hover:text-red-800 font-bold">
                            Change
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 2: Enter Customer Details -->
        <div id="userDetailsForm" class="mb-6 hidden">
            <label class="block text-lg font-bold text-black mb-3">Step 2: Enter Customer Details</label>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="confirm_order">
                <input type="hidden" name="bicycle_type_id" id="selectedBicycleTypeId">
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-bold text-black mb-2">Customer Name</label>
                        <input type="text" name="customer_name" required 
                               class="w-full p-3 border border-gray-300 rounded focus:ring-2 focus:ring-red-500 focus:border-red-500">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-black mb-2">Phone Number</label>
                        <input type="tel" name="customer_phone" required 
                               class="w-full p-3 border border-gray-300 rounded focus:ring-2 focus:ring-red-500 focus:border-red-500">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-black mb-2">NIC Number</label>
                        <input type="text" name="customer_nic" required 
                               class="w-full p-3 border border-gray-300 rounded focus:ring-2 focus:ring-red-500 focus:border-red-500" 
                               placeholder="Enter NIC">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-black mb-2">Payment Method</label>
                        <select name="payment_method" required 
                                class="w-full p-3 border border-gray-300 rounded focus:ring-2 focus:ring-red-500 focus:border-red-500">
                            <option value="">Select method...</option>
                            <option value="cash">💵 Cash</option>
                            <option value="card">💳 Card</option>
                            <option value="mobile">📱 Mobile</option>
                            <option value="bank_transfer">🏦 Bank</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex justify-center pt-4">
                    <button type="submit" name="action" value="confirm_order" 
                            class="bg-black text-white py-3 px-8 rounded-lg hover:bg-gray-800 font-bold text-lg">
                        Confirm Order & Process Payment
                    </button>
                </div>
            </form>
        </div>
        </div>
    </div>

    <!-- Open Bikes View -->
    <div id="openBikesView" class="view-container hidden">
        <div class="max-w-7xl mx-auto bg-white shadow-lg rounded-xl p-6 border-2 border-gray-200">
        <div class="flex items-center mb-4">
            <span class="text-2xl mr-3">🚴‍♂️</span>
            <h2 class="text-xl font-bold text-black">Open Bikes</h2>
            <span class="ml-auto bg-red-500 text-white text-xs font-bold px-3 py-1 rounded-full">
                <?php echo count($open_bills); ?> Active
            </span>
        </div>
        
        <div class="overflow-x-auto">
            <?php if (empty($open_bills)): ?>
                <div class="text-center py-12">
                    <div class="text-6xl mb-4">🚴‍♂️</div>
                    <p class="text-gray-600 text-xl">No bikes currently rented</p>
                    <p class="text-gray-500 mt-2">Click "Add Rent Bike" to start a new rental</p>
                </div>
            <?php else: ?>
                <table class="w-full border-collapse shadow-lg rounded-lg overflow-hidden">
                    <thead>
                        <tr class="bg-black text-white">
                            <th class="text-left py-3 px-4 font-semibold text-sm">Customer Details</th>
                            <th class="text-left py-3 px-4 font-semibold text-sm">Bill ID</th>
                            <th class="text-left py-3 px-4 font-semibold text-sm">Bike Type</th>
                            <th class="text-left py-3 px-4 font-semibold text-sm">Start Time</th>
                            <th class="text-left py-3 px-4 font-semibold text-sm">Duration</th>
                            <th class="text-left py-3 px-4 font-semibold text-sm">Status</th>
                            <th class="text-center py-3 px-4 font-semibold text-sm">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($open_bills as $bill): ?>
                            <?php
                            $start_time = new DateTime($bill['start_time']);
                            $current_time = new DateTime();
                            $elapsed_minutes = ($current_time->getTimestamp() - $start_time->getTimestamp()) / 60;
                            $is_overtime = $elapsed_minutes > $bill['base_minutes'];
                            ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50 <?php echo $is_overtime ? 'bg-red-50' : ''; ?> transition-colors">
                                <td class="py-3 px-4">
                                    <div>
                                        <div class="font-bold text-black text-sm"><?php echo htmlspecialchars($bill['customer_name']); ?></div>
                                        <div class="text-gray-600 text-xs"><?php echo htmlspecialchars($bill['customer_phone']); ?></div>
                                        <?php if (!empty($bill['customer_nic'])): ?>
                                        <div class="text-gray-500 text-xs">NIC: <?php echo htmlspecialchars($bill['customer_nic']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-mono font-bold text-black text-sm"><?php echo htmlspecialchars($bill['bill_number']); ?></div>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs font-bold"><?php echo htmlspecialchars($bill['type_name']); ?></span>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="text-gray-700 font-bold text-sm"><?php echo date('H:i', strtotime($bill['start_time'])); ?></div>
                                    <div class="text-gray-500 text-xs"><?php echo date('M d', strtotime($bill['start_time'])); ?></div>
                                </td>
                                <td class="py-3 px-4">
                                    <div>
                                        <span class="<?php echo $is_overtime ? 'text-red-600 font-bold text-sm' : 'text-gray-700 font-bold text-sm'; ?>">
                                            <?php echo formatDuration($elapsed_minutes); ?>
                                        </span>
                                        <div class="text-gray-500 text-xs">of <?php echo formatDuration($bill['base_minutes']); ?></div>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <?php if ($is_overtime): ?>
                                        <span class="bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs font-bold">⏰ Overtime</span>
                                    <?php else: ?>
                                        <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs font-bold">✅ On Time</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <form method="post" class="inline" id="closeForm_<?php echo $bill['id']; ?>">
                                        <input type="hidden" name="order_id" value="<?php echo $bill['id']; ?>">
                                        <button type="button" 
                                                class="bg-black text-white px-3 py-2 rounded-lg hover:bg-gray-800 font-bold text-sm transition-colors shadow-md"
                                                onclick="showCloseConfirmModal(<?php echo $bill['id']; ?>, '<?php echo htmlspecialchars($bill['bill_number']); ?>', '<?php echo htmlspecialchars($bill['customer_name']); ?>')">
                                            Close Rental
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        </div>
    </div>

        <!-- Receipt Modal -->
        <?php if (isset($_SESSION['last_bill_id']) && $message_type == 'success'): ?>
        <?php
        $bill_stmt = $conn->prepare("SELECT b.*, bt.type_name FROM bills b JOIN bicycle_types bt ON b.bicycle_type_id = bt.id WHERE b.id = ?");
        $bill_stmt->bind_param("i", $_SESSION['last_bill_id']);
        $bill_stmt->execute();
        $receipt_bill = $bill_stmt->get_result()->fetch_assoc();
        unset($_SESSION['last_bill_id']);
        ?>
        <div id="receiptModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center">
            <div class="relative p-4 border w-full max-w-sm shadow-lg rounded-lg bg-white mx-4">
                <div class="mt-2">
                    <h3 class="text-base font-bold text-gray-900 mb-3 text-center">🧾 Order Receipt</h3>
                    
                    <div class="receipt-content p-3 border rounded bg-gray-50">
                        <div class="text-center mb-3">
                            <h4 class="font-bold text-base">Bicycle Rental System</h4>
                            <p class="text-xs text-gray-600"><?php echo date('Y-m-d H:i:s'); ?></p>
                        </div>
                        
                        <div class="space-y-1 text-xs">
                            <div class="flex justify-between">
                                <span class="font-medium">Order ID:</span>
                                <span class="font-semibold text-right break-all"><?php echo htmlspecialchars($receipt_bill['bill_number']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">Customer:</span>
                                <span class="text-right break-words max-w-[60%]"><?php echo htmlspecialchars($receipt_bill['customer_name']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">Phone:</span>
                                <span class="text-right"><?php echo htmlspecialchars($receipt_bill['customer_phone']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">NIC:</span>
                                <span class="text-right break-all"><?php echo htmlspecialchars($receipt_bill['customer_nic'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">Bicycle Type:</span>
                                <span class="text-right font-semibold text-red-600"><?php echo htmlspecialchars($receipt_bill['type_name']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">Base Duration:</span>
                                <span class="text-right"><?php echo formatDuration($receipt_bill['base_minutes']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">Start Time:</span>
                                <span class="text-right font-semibold"><?php echo date('H:i', strtotime($receipt_bill['start_time'])); ?></span>
                            </div>
                            <hr class="my-2 border-gray-300">
                            <div class="flex justify-between font-bold text-sm">
                                <span>Base Amount:</span>
                                <span class="text-green-600"><?php echo formatCurrency($receipt_bill['base_price']); ?></span>
                            </div>
                            <div class="mt-2 p-2 bg-yellow-50 border border-yellow-200 rounded">
                                <p class="text-xs text-gray-700">Extra charges: <span class="font-semibold"><?php echo formatCurrency($receipt_bill['extra_charge_per_minute']); ?>/min</span> after base duration</p>
                                <p class="text-xs font-semibold text-red-600 mt-1">⚠️ Please return on time to avoid extra charges</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-center space-x-2 mt-3">
                        <button onclick="printReceipt()" class="px-3 py-2 bg-black text-white rounded text-sm hover:bg-gray-800">
                            Print Receipt
                        </button>
                        <button onclick="closeReceipt()" class="px-3 py-2 bg-black text-white rounded text-sm hover:bg-gray-800">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

<!-- Bicycle Type Selection Modal -->
<div id="bicycleTypeModal" class="fixed inset-0 bg-black bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-10 mx-auto p-8 border w-full max-w-4xl shadow-2xl rounded-2xl bg-white mx-4">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-8">
                <h3 class="text-3xl font-bold text-black">🚴‍♂️ Select Bicycle Type</h3>
                <button onclick="closeBicycleTypeModal()" class="text-black hover:text-red-600 text-3xl font-bold">
                    ×
                </button>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 max-h-96 overflow-y-auto">
                <?php foreach ($bicycle_types as $type): ?>
                <div class="bicycle-type-card border-3 border-gray-300 rounded-xl p-6 cursor-pointer hover:border-red-500 hover:shadow-lg transition-all duration-200 bg-white" 
                     onclick="selectBicycleType(<?php echo htmlspecialchars(json_encode($type)); ?>)">
                    <div class="text-center mb-4">
                        <div class="text-4xl mb-2">🚴‍♂️</div>
                        <h4 class="text-xl font-bold text-black"><?php echo htmlspecialchars($type['type_name']); ?></h4>
                    </div>
                    
                    <div class="space-y-3 text-center">
                        <div class="bg-red-50 p-3 rounded-lg">
                            <p class="text-lg font-bold text-red-600"><?php echo formatCurrency($type['base_price']); ?></p>
                            <p class="text-sm text-gray-700">for <?php echo formatDuration($type['base_minutes']); ?></p>
                        </div>
                        
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <p class="text-base font-semibold text-black"><?php echo formatCurrency($type['extra_charge_per_minute']); ?>/min</p>
                            <p class="text-xs text-gray-600">after base time</p>
                        </div>
                    </div>
                    
                    <div class="mt-4 text-center">
                        <button class="bg-black text-white px-4 py-2 rounded-lg font-bold hover:bg-gray-800">
                            Select This Type
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="flex justify-center mt-8">
                <button onclick="closeBicycleTypeModal()" class="px-8 py-3 text-gray-600 border-2 border-gray-300 rounded-xl hover:bg-gray-50 font-bold text-lg">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Close Rental Confirmation Modal -->
<div id="closeConfirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-1/2 transform -translate-y-1/2 mx-auto p-5 border w-96 shadow-lg rounded-lg bg-white">
        <div class="mt-3 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z" />
                </svg>
            </div>
            <h3 class="text-lg leading-6 font-medium text-gray-900 mt-4">Close Rental Confirmation</h3>
            <div class="mt-2 px-7 py-3">
                <p class="text-sm text-gray-500">
                    Are you sure you want to close this rental?
                </p>
                <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                    <div class="text-sm text-gray-700">
                        <div class="flex justify-between mb-2">
                            <span class="font-medium">Bill Number:</span>
                            <span id="confirmBillNumber" class="font-semibold"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-medium">Customer:</span>
                            <span id="confirmCustomerName" class="font-semibold"></span>
                        </div>
                    </div>
                </div>
                <div class="mt-3 text-xs text-gray-500">
                    ⚠️ This action cannot be undone
                </div>
            </div>
            <div class="flex gap-3 px-6 py-3">
                <button type="button" 
                        onclick="hideCloseConfirmModal()" 
                        class="flex-1 bg-gray-300 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-400 transition-colors font-semibold">
                    Cancel
                </button>
                <button type="button" 
                        onclick="confirmCloseRental()" 
                        class="flex-1 bg-red-600 text-white py-2 px-4 rounded-lg hover:bg-red-700 transition-colors font-semibold">
                    Yes, Close Rental
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Rental Calculation Modal -->
<?php if (isset($_SESSION['rental_calculation']) && $_SESSION['rental_calculation']['success']): ?>
<?php 
$calc = $_SESSION['rental_calculation'];
unset($_SESSION['rental_calculation']); // Clear after use
?>
<div id="calculationModal" class="fixed inset-0 bg-black bg-opacity-50 overflow-y-auto h-full w-full z-50 flex items-center justify-center">
    <div class="relative p-4 border w-full max-w-md shadow-lg rounded-lg bg-white mx-4">
        <div class="mt-2">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-black">💰 Rental Calculation</h3>
                <button onclick="closeCalculationModal()" class="text-black hover:text-red-600 text-xl font-bold">
                    ×
                </button>
            </div>
            
            <div class="bg-gray-50 rounded-lg p-3 mb-4">
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="font-semibold">Customer:</span>
                        <span class="text-black"><?php echo htmlspecialchars($calc['customer_name']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-semibold">Order ID:</span>
                        <span class="text-black"><?php echo htmlspecialchars($calc['bill_number']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="font-semibold">Bicycle Type:</span>
                        <span class="text-red-600"><?php echo htmlspecialchars($calc['type_name']); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="bg-white border border-gray-300 rounded-lg p-4 space-y-3">
                <h4 class="text-base font-bold text-black mb-3">📊 Calculation Details:</h4>
                
                <div class="space-y-2">
                    <div class="flex justify-between items-center text-sm">
                        <span class="font-semibold">Actual Duration:</span>
                        <span class="font-bold"><?php echo $calc['actual_minutes']; ?> min (<?php echo number_format($calc['actual_minutes']/60, 1); ?>h)</span>
                    </div>
                    
                    <div class="flex justify-between items-center text-sm">
                        <span class="font-semibold">Base Duration:</span>
                        <span><?php echo $calc['base_minutes']; ?> minutes</span>
                    </div>
                    
                    <div class="flex justify-between items-center text-sm">
                        <span class="font-semibold">Base Amount:</span>
                        <span class="text-green-600 font-bold"><?php echo formatCurrency($calc['base_price']); ?></span>
                    </div>
                    
                    <?php if ($calc['extra_minutes'] > 0): ?>
                    <hr class="border-red-300">
                    <div class="bg-red-50 p-3 rounded-lg">
                        <div class="flex justify-between items-center text-red-700 text-sm">
                            <span class="font-semibold">Extra Minutes:</span>
                            <span class="font-bold"><?php echo $calc['extra_minutes']; ?> min</span>
                        </div>
                        <div class="flex justify-between items-center text-red-700 mt-1 text-sm">
                            <span class="font-semibold">Extra Charges:</span>
                            <span class="font-bold"><?php echo formatCurrency($calc['extra_charges']); ?></span>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="bg-green-50 p-3 rounded-lg">
                        <div class="flex justify-between items-center text-green-700 text-sm">
                            <span class="font-semibold">Extra Minutes:</span>
                            <span class="font-bold">0 (Within base time)</span>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <hr class="border-gray-300">
                    <div class="flex justify-between items-center text-base">
                        <span class="font-bold">Total Amount:</span>
                        <span class="font-bold text-red-600 text-lg"><?php echo formatCurrency($calc['total_amount']); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-center space-x-3 mt-4">
                <?php if ($calc['extra_minutes'] > 0): ?>
                <button onclick="printBill()" class="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 font-bold text-sm">
                    🖨️ Print Bill
                </button>
                <?php endif; ?>
                <button onclick="closeCalculationModal()" class="bg-black text-white px-4 py-2 rounded-lg hover:bg-gray-800 font-bold text-sm">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Global variables
let selectedBicycleType = null;
let currentView = 'addRental'; // Default view

// View Toggle Functions
function showAddRentalView() {
    // Hide open bikes view
    document.getElementById('openBikesView').classList.add('hidden');
    
    // Show add rental view
    document.getElementById('addRentalView').classList.remove('hidden');
    
    // Update toggle buttons
    document.getElementById('addRentalToggle').classList.remove('bg-gray-300', 'text-gray-700');
    document.getElementById('addRentalToggle').classList.add('bg-black', 'text-white');
    
    document.getElementById('openBikesToggle').classList.remove('bg-black', 'text-white');
    document.getElementById('openBikesToggle').classList.add('bg-gray-300', 'text-gray-700');
    
    currentView = 'addRental';
}

function showOpenBikesView() {
    // Hide add rental view
    document.getElementById('addRentalView').classList.add('hidden');
    
    // Show open bikes view
    document.getElementById('openBikesView').classList.remove('hidden');
    
    // Update toggle buttons
    document.getElementById('openBikesToggle').classList.remove('bg-gray-300', 'text-gray-700');
    document.getElementById('openBikesToggle').classList.add('bg-black', 'text-white');
    
    document.getElementById('addRentalToggle').classList.remove('bg-black', 'text-white');
    document.getElementById('addRentalToggle').classList.add('bg-gray-300', 'text-gray-700');
    
    currentView = 'openBikes';
}

// Bicycle Type Modal Functions
function openBicycleTypeModal() {
    document.getElementById('bicycleTypeModal').classList.remove('hidden');
}

function closeBicycleTypeModal() {
    document.getElementById('bicycleTypeModal').classList.add('hidden');
}

function selectBicycleType(type) {
    selectedBicycleType = type;
    
    // Update the selected type display
    document.getElementById('selectedBicycleTypeId').value = type.id;
    document.getElementById('selectedTypeInfo').innerHTML = `
        <div>
            <span class="font-bold">${type.type_name}</span><br>
            <span class="text-red-600">${formatCurrency(type.base_price)} for ${formatDuration(type.base_minutes)}</span><br>
            <span class="text-gray-600">${formatCurrency(type.extra_charge_per_minute)}/min extra</span>
        </div>
    `;
    
    // Show selected type and user details form
    document.getElementById('selectedTypeDisplay').classList.remove('hidden');
    document.getElementById('userDetailsForm').classList.remove('hidden');
    
    // Update button text
    document.getElementById('selectBicycleTypeBtn').innerHTML = '✅ Bicycle Type Selected - ' + type.type_name;
    document.getElementById('selectBicycleTypeBtn').classList.remove('bg-red-500', 'hover:bg-red-600');
    document.getElementById('selectBicycleTypeBtn').classList.add('bg-green-500', 'hover:bg-green-600');
    
    // Close modal
    closeBicycleTypeModal();
}

// Close Confirmation Modal Functions
let currentOrderId = null;

function showCloseConfirmModal(orderId, billNumber, customerName) {
    currentOrderId = orderId;
    document.getElementById('confirmBillNumber').textContent = billNumber;
    document.getElementById('confirmCustomerName').textContent = customerName;
    document.getElementById('closeConfirmModal').classList.remove('hidden');
}

function hideCloseConfirmModal() {
    document.getElementById('closeConfirmModal').classList.add('hidden');
    currentOrderId = null;
}

function confirmCloseRental() {
    if (currentOrderId) {
        // Add the action input to the form and submit it
        const form = document.getElementById('closeForm_' + currentOrderId);
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'close_rental';
        form.appendChild(actionInput);
        form.submit();
    }
}

// Utility Functions
function formatCurrency(amount) {
    return 'Rs. ' + parseFloat(amount).toFixed(2);
}

function formatDuration(minutes) {
    if (minutes < 60) {
        return minutes + ' min';
    } else {
        const hours = Math.floor(minutes / 60);
        const remainingMinutes = minutes % 60;
        if (remainingMinutes === 0) {
            return hours + ' hr';
        } else {
            return hours + ' hr ' + remainingMinutes + ' min';
        }
    }
}

// Receipt Modal Functions
function printReceipt() {
    window.print();
}

function closeReceipt() {
    document.getElementById('receiptModal').style.display = 'none';
}

// Calculation Modal Functions
function closeCalculationModal() {
    document.getElementById('calculationModal').classList.add('hidden');
}

function printBill() {
    const printContent = `
        <div style="font-family: Arial, sans-serif; max-width: 400px; margin: 0 auto; padding: 20px;">
            <div style="text-align: center; margin-bottom: 20px;">
                <h2>🚴 Bicycle Rental System</h2>
                <h3>EXTRA CHARGES BILL</h3>
                <p>${new Date().toLocaleString()}</p>
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <p style="font-size: 12px;">Thank you for using our service!</p>
            </div>
        </div>
    `;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head><title>Extra Charges Bill</title></head>
        <body onload="window.print(); window.close();">
            ${printContent}
        </body>
        </html>
    `);
    printWindow.document.close();
}

// Message Functions
function hideMessage() {
    const alert = document.getElementById('messageAlert');
    if (alert) {
        alert.style.opacity = '0';
        setTimeout(function() {
            alert.style.display = 'none';
        }, 500);
    }
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    
    // Auto-hide message after 5 seconds
    const messageAlert = document.getElementById('messageAlert');
    if (messageAlert) {
        setTimeout(function() {
            hideMessage();
        }, 5000);
        
        // If there's a success message and receipt modal, switch to open bikes view
        const receiptModal = document.getElementById('receiptModal');
        if (receiptModal && messageAlert.classList.contains('bg-green-50')) {
            setTimeout(function() {
                showOpenBikesView();
            }, 1000); // Switch after 1 second
        }
    }
    
    // Bicycle Type Modal - Close when clicking outside
    const bicycleTypeModal = document.getElementById('bicycleTypeModal');
    if (bicycleTypeModal) {
        bicycleTypeModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeBicycleTypeModal();
            }
        });
    }
    
    // Calculation Modal - Close when clicking outside
    const calculationModal = document.getElementById('calculationModal');
    if (calculationModal) {
        calculationModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeCalculationModal();
            }
        });
    }
    
    // Close Confirmation Modal - Close when clicking outside
    const closeConfirmModal = document.getElementById('closeConfirmModal');
    if (closeConfirmModal) {
        closeConfirmModal.addEventListener('click', function(e) {
            if (e.target === this) {
                hideCloseConfirmModal();
            }
        });
    }
});
</script>

<?php include 'inc/footer.php'; ?>
