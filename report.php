<?php
session_start();
require_once 'inc/functions.php';

// Check authentication and admin access
requireLogin('login.php');
requireAdmin('index.php');

$page_title = "Daily Reports";

// Get current date or selected date
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Check if PDF generation is requested
if (isset($_GET['generate_pdf']) && $_GET['generate_pdf'] == '1') {
    generateReportPDF($selected_date);
    exit;
}

// Get daily report data
$daily_report = getDailyReport($selected_date);
$daily_stats = getDailyStats($selected_date);

include 'inc/header.php';
?>

<div class="px-8 py-8">
    <!-- Page Header -->
    <div class="mb-8 bg-gradient-to-r from-red-50 to-white p-6 rounded-xl border border-red-100 shadow-sm">
        <div class="flex items-center space-x-3">
            <div class="bg-red-500 p-3 rounded-full shadow-lg">
                <span class="text-white text-2xl">📊</span>
            </div>
            <div>
                <h1 class="text-4xl font-bold text-black bg-gradient-to-r from-black to-gray-700 bg-clip-text text-transparent">Daily Reports</h1>
                <p class="mt-2 text-gray-600 text-lg font-medium">View daily rental statistics and transactions</p>
            </div>
        </div>
    </div>

    <!-- Date Selection -->
    <div class="mb-8 bg-white shadow-lg rounded-xl p-6 border border-gray-200">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold text-black">Select Report Date</h2>
            <form method="GET" action="report.php" class="flex items-center space-x-4">
                <input type="date" name="date" value="<?php echo $selected_date; ?>" 
                       class="p-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 font-medium">
                <button type="submit" class="bg-gradient-to-r from-black to-gray-800 text-white px-6 py-3 rounded-xl hover:from-gray-800 hover:to-black font-bold transition-all duration-200">
                    📊 View Report
                </button>
                <button type="submit" name="generate_pdf" value="1" class="bg-gradient-to-r from-red-500 to-red-600 text-white px-6 py-3 rounded-xl hover:from-red-600 hover:to-red-700 font-bold transition-all duration-200">
                    📄 Generate Report
                </button>
            </form>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Rentals -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
            <div class="flex items-center">
                <div class="bg-blue-100 p-3 rounded-full">
                    <span class="text-blue-600 text-2xl">🚴</span>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Total Rentals</p>
                    <p class="text-2xl font-bold text-black"><?php echo $daily_stats['total_rentals']; ?></p>
                </div>
            </div>
        </div>

        <!-- Total Revenue -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
            <div class="flex items-center">
                <div class="bg-green-100 p-3 rounded-full">
                    <span class="text-green-600 text-2xl">💰</span>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Total Revenue</p>
                    <p class="text-2xl font-bold text-black"><?php echo formatCurrency($daily_stats['total_revenue']); ?></p>
                </div>
            </div>
        </div>

        <!-- Active Rentals -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-500">
            <div class="flex items-center">
                <div class="bg-yellow-100 p-3 rounded-full">
                    <span class="text-yellow-600 text-2xl">⏱️</span>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Active Rentals</p>
                    <p class="text-2xl font-bold text-black"><?php echo $daily_stats['active_rentals']; ?></p>
                </div>
            </div>
        </div>

        <!-- Extra Charges -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500">
            <div class="flex items-center">
                <div class="bg-red-100 p-3 rounded-full">
                    <span class="text-red-600 text-2xl">⚠️</span>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Extra Charges</p>
                    <p class="text-2xl font-bold text-black"><?php echo formatCurrency($daily_stats['extra_charges']); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Report Table -->
    <div class="bg-white shadow-2xl rounded-2xl p-8 border border-gray-100">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-black">Daily Transaction Report</h2>
            <div class="text-sm text-gray-600">
                Report for: <span class="font-bold text-black"><?php echo date('F d, Y', strtotime($selected_date)); ?></span>
            </div>
        </div>

        <?php if (empty($daily_report)): ?>
            <div class="text-center py-12">
                <div class="text-6xl mb-4">📊</div>
                <p class="text-gray-600 text-xl">No transactions found for this date</p>
                <p class="text-gray-500 mt-2">Select a different date to view report</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse shadow-2xl rounded-xl overflow-hidden">
                    <thead>
                        <tr class="bg-gradient-to-r from-black to-gray-800 text-white">
                            <th class="text-left py-4 px-6 font-bold text-sm">Bill Number</th>
                            <th class="text-left py-4 px-6 font-bold text-sm">Customer</th>
                            <th class="text-left py-4 px-6 font-bold text-sm">Bike Type</th>
                            <th class="text-left py-4 px-6 font-bold text-sm">Start Time</th>
                            <th class="text-left py-4 px-6 font-bold text-sm">End Time</th>
                            <th class="text-left py-4 px-6 font-bold text-sm">Duration</th>
                            <th class="text-left py-4 px-6 font-bold text-sm">Base Amount</th>
                            <th class="text-left py-4 px-6 font-bold text-sm">Extra Charges</th>
                            <th class="text-left py-4 px-6 font-bold text-sm">Total Amount</th>
                            <th class="text-left py-4 px-6 font-bold text-sm">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daily_report as $bill): ?>
                        <tr class="border-b border-gray-100 hover:bg-gray-50 transition-all duration-200">
                            <td class="py-4 px-6">
                                <div class="bg-gray-100 px-3 py-1 rounded-lg inline-block">
                                    <span class="font-mono font-bold text-sm"><?php echo htmlspecialchars($bill['bill_number']); ?></span>
                                </div>
                            </td>
                            <td class="py-4 px-6">
                                <div>
                                    <div class="font-bold text-black text-sm"><?php echo htmlspecialchars($bill['customer_name']); ?></div>
                                    <div class="text-gray-600 text-xs"><?php echo htmlspecialchars($bill['customer_phone']); ?></div>
                                </div>
                            </td>
                            <td class="py-4 px-6">
                                <span class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-xs font-bold"><?php echo htmlspecialchars($bill['type_name']); ?></span>
                            </td>
                            <td class="py-4 px-6">
                                <div class="text-gray-700 font-medium text-sm"><?php echo date('H:i', strtotime($bill['start_time'])); ?></div>
                            </td>
                            <td class="py-4 px-6">
                                <div class="text-gray-700 font-medium text-sm">
                                    <?php echo $bill['end_time'] ? date('H:i', strtotime($bill['end_time'])) : '-'; ?>
                                </div>
                            </td>
                            <td class="py-4 px-6">
                                <div class="text-gray-700 font-medium text-sm">
                                    <?php echo $bill['actual_minutes'] ? formatDuration($bill['actual_minutes']) : '-'; ?>
                                </div>
                            </td>
                            <td class="py-4 px-6">
                                <span class="text-green-600 font-bold"><?php echo formatCurrency($bill['base_price']); ?></span>
                            </td>
                            <td class="py-4 px-6">
                                <span class="text-red-600 font-bold"><?php echo formatCurrency($bill['extra_charges']); ?></span>
                            </td>
                            <td class="py-4 px-6">
                                <span class="text-black font-bold text-lg"><?php echo formatCurrency($bill['total_amount']); ?></span>
                            </td>
                            <td class="py-4 px-6">
                                <?php if ($bill['status'] == 'open'): ?>
                                    <span class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-xs font-bold">🟡 Active</span>
                                <?php else: ?>
                                    <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-bold">✅ Completed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>



</div>

<script>
function printReport() {
    const date = '<?php echo date('F d, Y', strtotime($selected_date)); ?>';
    const stats = {
        total_rentals: '<?php echo $daily_stats['total_rentals']; ?>',
        total_revenue: '<?php echo formatCurrency($daily_stats['total_revenue']); ?>',
        active_rentals: '<?php echo $daily_stats['active_rentals']; ?>',
        extra_charges: '<?php echo formatCurrency($daily_stats['extra_charges']); ?>'
    };
    
    const printContent = `
        <div style="font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;">
            <div style="text-align: center; margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 20px;">
                <h1 style="margin: 0; font-size: 24px;">🚴 Bicycle Rental System</h1>
                <h2 style="margin: 10px 0; font-size: 18px; color: #dc2626;">DAILY REPORT1</h2>
                <p style="margin: 5px 0; font-size: 14px;">Report Date: ${date}</p>
                <p style="margin: 5px 0; font-size: 12px;">Generated on: ${new Date().toLocaleString()}</p>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px;">
                <div style="border: 1px solid #ddd; padding: 15px; border-radius: 8px;">
                    <h4 style="margin: 0 0 5px 0; color: #374151;">Total Rentals</h4>
                    <p style="margin: 0; font-size: 20px; font-weight: bold;">${stats.total_rentals}</p>
                </div>
                <div style="border: 1px solid #ddd; padding: 15px; border-radius: 8px;">
                    <h4 style="margin: 0 0 5px 0; color: #374151;">Total Revenue</h4>
                    <p style="margin: 0; font-size: 20px; font-weight: bold; color: #16a34a;">${stats.total_revenue}</p>
                </div>
                <div style="border: 1px solid #ddd; padding: 15px; border-radius: 8px;">
                    <h4 style="margin: 0 0 5px 0; color: #374151;">Active Rentals</h4>
                    <p style="margin: 0; font-size: 20px; font-weight: bold;">${stats.active_rentals}</p>
                </div>
                <div style="border: 1px solid #ddd; padding: 15px; border-radius: 8px;">
                    <h4 style="margin: 0 0 5px 0; color: #374151;">Extra Charges</h4>
                    <p style="margin: 0; font-size: 20px; font-weight: bold; color: #dc2626;">${stats.extra_charges}</p>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px;">
                <p style="font-size: 12px; color: #6b7280;">Thank you for using Bicycle Rental System</p>
            </div>
        </div>
    `;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Daily Report - ${date}</title>
            <style>
                body { margin: 0; padding: 20px; }
                @media print {
                    body { margin: 0; }
                }
            </style>
        </head>
        <body onload="window.print(); window.close();">
            ${printContent}
        </body>
        </html>
    `);
    printWindow.document.close();
}
</script>

<?php include 'inc/footer.php'; ?>
