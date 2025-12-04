<?php
session_start();
require_once 'inc/functions.php';

// Check authentication and admin access
requireLogin('login.php');
requireAdmin('index.php');

$page_title = "Settings";

// Handle form submissions
$message = "";
$message_type = "";

// Get messages from session
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    $message_type = "success";
    unset($_SESSION['success_message']);
} elseif (isset($_SESSION['error_message'])) {
    $message = $_SESSION['error_message'];
    $message_type = "error";
    unset($_SESSION['error_message']);
}

// Handle form submissions
handleSettingsActions();

// Get all bicycle types (including inactive)
$all_bicycle_types = getAllBicycleTypes();

include 'inc/header.php';
?>

<div class="px-8 py-8">
    <!-- Page Header -->
    <div class="mb-12">
        <h1 class="text-4xl font-bold text-black">Settings</h1>
        <p class="mt-3 text-gray-700 text-lg">Manage bicycle types and rental packages</p>
    </div>

    <!-- Message Alert -->
    <?php if ($message): ?>
    <div id="messageAlert" class="mb-8 p-6 rounded-lg border-2 <?php echo $message_type == 'success' ? 'bg-white border-red-500 text-red-700' : 'bg-white border-red-500 text-red-700'; ?> shadow-lg transition-opacity duration-500">
        <div class="flex justify-between items-center">
            <span class="font-semibold text-lg"><?php echo htmlspecialchars($message); ?></span>
            <button onclick="hideMessage()" class="text-red-800 hover:text-red-900 font-bold text-xl">
                ×
            </button>
        </div>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
        <!-- Add New Bicycle Type -->
        <div class="bg-white shadow-lg rounded-xl p-8 border-2 border-gray-200">
            <h2 class="text-2xl font-bold text-black mb-6">➕ Add New Bicycle Type</h2>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add_bicycle_type">
                
                <div>
                    <label class="block text-sm font-medium text-black mb-1">Type Name</label>
                    <input type="text" name="type_name" required placeholder="e.g., Type A, etc." 
                           class="w-full p-3 border-gray-300 border rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                      </div>
                
                <div>
                    <label class="block text-sm font-medium text-black mb-1">Base Duration (minutes)</label>
                    <select name="base_minutes" required class="w-full p-3 border-gray-300 border rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                        <option value="">Select duration...</option>
                        <option value="15">15 minutes</option>
                        <option value="30">30 minutes</option>
                        <option value="45">45 minutes</option>
                        <option value="60">1 hour</option>
                        <option value="90">1.5 hours</option>
                        <option value="120">2 hours</option>
                        <option value="180">3 hours</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-black mb-1">Base Price (Rs.)</label>
                    <input type="number" name="base_price" step="0.01" min="0" required placeholder="100.00" 
                           class="w-full p-3 border-gray-300 border rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-black mb-1">Extra Charge per Minute (Rs.)</label>
                    <input type="number" name="extra_charge_per_minute" step="0.01" min="0" required placeholder="3.00" 
                           class="w-full p-3 border-gray-300 border rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                    <p class="text-xs text-gray-600 mt-1">Charged for each minute beyond base duration</p>
                </div>
                
                <button type="submit" class="w-full bg-black text-white py-3 px-4 rounded-md hover:bg-gray-800 font-medium">
                    Add Bicycle Type
                </button>
            </form>
        </div>

        <!-- Existing Bicycle Types -->
        <div class="bg-white shadow-lg rounded-xl p-8 border-2 border-gray-200">
            <h2 class="text-2xl font-bold text-black mb-6">🚴 Existing Bicycle Types</h2>
            
            <div class="space-y-4">
                <?php if (empty($all_bicycle_types)): ?>
                <p class="text-gray-500 text-center py-4">No bicycle types found. Add one using the form.</p>
                <?php else: ?>
                <?php foreach ($all_bicycle_types as $type): ?>
                <div class="border-2 rounded-xl p-6 shadow-md <?php echo $type['status'] == 'active' ? 'border-red-300 bg-red-50' : 'border-gray-300 bg-gray-50'; ?>">
                    <div class="flex justify-between items-start">
                        <div class="flex-grow">
                            <h3 class="font-bold text-black text-lg"><?php echo htmlspecialchars($type['type_name']); ?></h3>
                            <div class="text-base text-gray-700 mt-2 space-y-1">
                                <p>Duration: <?php echo formatDuration($type['base_minutes']); ?></p>
                                <p>Base Price: <?php echo formatCurrency($type['base_price']); ?></p>
                                <p>Extra Charge: <?php echo formatCurrency($type['extra_charge_per_minute']); ?>/min</p>
                                <p><strong>Status:</strong> <span class="<?php echo $type['status'] == 'active' ? 'text-red-600 font-bold' : 'text-gray-600'; ?>"><?php echo ucfirst($type['status']); ?></span></p>
                            </div>
                        </div>
                        
                       <div class="flex flex-col space-y-3 ml-6">

                        <!-- Edit Button -->
                        <button onclick="editBicycleType(<?php echo htmlspecialchars(json_encode($type)); ?>)" 
                                class="bg-black text-white px-4 py-2 rounded-lg hover:bg-gray-800 font-semibold text-sm">
                            Edit
                        </button>

                        <!-- Toggle Status -->
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="type_id" value="<?php echo $type['id']; ?>">
                            <button type="submit" 
                                    class="w-full bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 font-semibold text-sm">
                                <?php echo $type['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>
                            </button>
                        </form>

                        <!-- Remove Button -->
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="delete_bicycle_type">
                            <input type="hidden" name="type_id" value="<?php echo $type['id']; ?>">
                            <button type="submit" class="w-full bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 font-semibold text-sm"
                                    onclick="return confirm('Are you sure you want to delete this bicycle type? This action cannot be undone!')">
                                Remove
                            </button>
                        </form>

                    </div>

                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-8 border-2 w-96 shadow-2xl rounded-2xl bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold text-black">Edit Bicycle Type</h3>
                <button onclick="closeEditModal()" class="text-black hover:text-red-600 text-2xl font-bold">
                    ×
                </button>
            </div>
            
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="update_bicycle_type">
                <input type="hidden" name="type_id" id="edit_type_id">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-black mb-1">Type Name</label>
                        <input type="text" name="type_name" id="edit_type_name" required 
                               class="w-full p-2 border-gray-300 border rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-black mb-1">Base Duration (minutes)</label>
                        <select name="base_minutes" id="edit_base_minutes" required class="w-full p-2 border-gray-300 border rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                            <option value="15">15 minutes</option>
                            <option value="30">30 minutes</option>
                            <option value="45">45 minutes</option>
                            <option value="60">1 hour</option>
                            <option value="90">1.5 hours</option>
                            <option value="120">2 hours</option>
                            <option value="180">3 hours</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-black mb-1">Base Price (Rs.)</label>
                        <input type="number" name="base_price" id="edit_base_price" step="0.01" min="0" required 
                               class="w-full p-2 border-gray-300 border rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-black mb-1">Extra Charge per Minute (Rs.)</label>
                        <input type="number" name="extra_charge_per_minute" id="edit_extra_charge" step="0.01" min="0" required 
                               class="w-full p-2 border-gray-300 border rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                    </div>
                </div>
                
                <div class="flex justify-end space-x-4 mt-8">
                    <button type="button" onclick="closeEditModal()" class="px-6 py-3 text-gray-600 border-2 border-gray-300 rounded-xl hover:bg-gray-50 font-bold">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-3 bg-black text-white rounded-xl hover:bg-gray-800 font-bold">
                        Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function hideMessage() {
    const alert = document.getElementById('messageAlert');
    if (alert) {
        alert.style.opacity = '0';
        setTimeout(function() {
            alert.style.display = 'none';
        }, 500);
    }
}

function editBicycleType(type) {
    document.getElementById('edit_type_id').value = type.id;
    document.getElementById('edit_type_name').value = type.type_name;
    document.getElementById('edit_base_minutes').value = type.base_minutes;
    document.getElementById('edit_base_price').value = type.base_price;
    document.getElementById('edit_extra_charge').value = type.extra_charge_per_minute;
    
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    // Edit Modal - Close when clicking outside
    const editModal = document.getElementById('editModal');
    if (editModal) {
        editModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
    }
    
    // Auto-hide message after 5 seconds
    const messageAlert = document.getElementById('messageAlert');
    if (messageAlert) {
        setTimeout(function() {
            hideMessage();
        }, 5000);
    }
});
</script>

<?php include 'inc/footer.php'; ?>