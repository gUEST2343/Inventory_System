/**
 * Settings and Reports Functions
 * StockFlow Inventory Management System
 */

// ===============================================
// SETTINGS PAGE FUNCTIONS
// ===============================================

// User Management Functions
function openInviteModal() {
    const modal = document.getElementById('inviteModal');
    if (modal) {
        modal.classList.add('show');
        const overlay = document.createElement('div');
        overlay.className = 'modal-overlay';
        overlay.onclick = closeInviteModal;
        document.body.appendChild(overlay);
        setTimeout(() => overlay.classList.add('show'), 10);
    }
}

function closeInviteModal() {
    const modal = document.getElementById('inviteModal');
    if (modal) modal.classList.remove('show');
    document.querySelector('.modal-overlay')?.remove();
}

function inviteUser() {
    const name = document.getElementById('inviteName')?.value;
    const email = document.getElementById('inviteEmail')?.value;
    const role = document.getElementById('inviteRole')?.value;
    
    if (!name || !email) {
        showNotification('Please fill in all required fields', 'error');
        return;
    }
    
    if (!validateEmail(email)) {
        showNotification('Please enter a valid email address', 'error');
        return;
    }
    
    // Add to user list
    const userList = document.getElementById('userList');
    if (userList) {
        const newUser = document.createElement('div');
        newUser.className = 'user-item';
        newUser.innerHTML = `
            <div class="user-avatar"><i class="fas fa-user"></i></div>
            <div class="user-info"><h4>${name}</h4><p>${email}</p></div>
            <span class="badge badge-info">${role}</span>
            <button class="btn-icon" onclick="editUser('${email}')"><i class="fas fa-edit"></i></button>
            <button class="btn-icon" onclick="deleteUser('${email}')"><i class="fas fa-trash"></i></button>
        `;
        userList.appendChild(newUser);
        
        // Update stats
        const teamCount = document.getElementById('teamCount');
        if (teamCount) teamCount.textContent = parseInt(teamCount.textContent) + 1;
    }
    
    // Clear form
    document.getElementById('inviteName').value = '';
    document.getElementById('inviteEmail').value = '';
    
    closeInviteModal();
    showNotification('Invitation sent to ' + email, 'success');
}

function editUser(email) {
    const userItems = document.querySelectorAll('.user-item');
    userItems.forEach(item => {
        const userEmail = item.querySelector('.user-info p')?.textContent;
        if (userEmail === email) {
            const name = item.querySelector('.user-info h4')?.textContent;
            const badge = item.querySelector('.badge')?.textContent;
            
            document.getElementById('editUserEmail').value = email;
            document.getElementById('editUserName').value = name;
            document.getElementById('editUserRole').value = badge.toLowerCase();
            
            const modal = document.getElementById('editUserModal');
            if (modal) {
                modal.classList.add('show');
                const overlay = document.createElement('div');
                overlay.className = 'modal-overlay';
                overlay.onclick = () => modal.classList.remove('show');
                document.body.appendChild(overlay);
                setTimeout(() => overlay.classList.add('show'), 10);
            }
        }
    });
}

function saveUserChanges() {
    const email = document.getElementById('editUserEmail')?.value;
    const name = document.getElementById('editUserName')?.value;
    const role = document.getElementById('editUserRole')?.value;
    
    if (!name) {
        showNotification('Please enter a name', 'error');
        return;
    }
    
    const userItems = document.querySelectorAll('.user-item');
    userItems.forEach(item => {
        const userEmail = item.querySelector('.user-info p')?.textContent;
        if (userEmail === email) {
            item.querySelector('.user-info h4').textContent = name;
            item.querySelector('.badge').textContent = role.charAt(0).toUpperCase() + role.slice(1);
        }
    });
    
    document.getElementById('editUserModal')?.classList.remove('show');
    document.querySelector('.modal-overlay')?.remove();
    showNotification('User updated successfully', 'success');
}

function deleteUser(email) {
    if (confirm('Are you sure you want to remove this user?')) {
        const userItems = document.querySelectorAll('.user-item');
        userItems.forEach(item => {
            const userEmail = item.querySelector('.user-info p')?.textContent;
            if (userEmail === email) {
                item.remove();
                const teamCount = document.getElementById('teamCount');
                if (teamCount) teamCount.textContent = parseInt(teamCount.textContent) - 1;
                showNotification('User removed successfully', 'success');
            }
        });
    }
}

// Settings Tab Functions
function initSettingsTabs() {
    const tabs = document.querySelectorAll('.settings-tab');
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const tabId = this.dataset.tab;
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            document.querySelectorAll('.settings-panel').forEach(p => p.classList.remove('active'));
            document.getElementById(tabId)?.classList.add('active');
        });
    });
}

// Billing Functions
function addPaymentMethod() {
    showNotification('Add payment method functionality', 'info');
}

function updateBillingInfo() {
    showNotification('Billing information updated!', 'success');
}

// Integrations Functions
function toggleIntegration(name, enabled) {
    showNotification(name + ' ' + (enabled ? 'enabled' : 'disabled'), 'success');
}

// Security Functions
function updatePassword() {
    const newPass = document.getElementById('newPassword')?.value;
    const confirmPass = document.getElementById('confirmPassword')?.value;
    
    if (!newPass || !confirmPass) {
        showNotification('Please fill in all password fields', 'error');
        return;
    }
    
    if (newPass !== confirmPass) {
        showNotification('New passwords do not match', 'error');
        return;
    }
    
    if (newPass.length < 8) {
        showNotification('Password must be at least 8 characters', 'error');
        return;
    }
    
    showNotification('Password updated successfully', 'success');
}

function toggle2FA(enabled) {
    showNotification('Two-factor authentication ' + (enabled ? 'enabled' : 'disabled'), 'success');
}

// Notifications Functions
function updateNotificationSettings() {
    showNotification('Notification preferences saved!', 'success');
}

// Company Functions
function saveCompanyInfo() {
    const companyName = document.getElementById('companyName')?.value;
    if (!companyName) {
        showNotification('Company name is required', 'error');
        return;
    }
    showNotification('Company information saved!', 'success');
}

// ===============================================
// REPORTS PAGE FUNCTIONS
// ===============================================

function setDateRange(range) {
    const dateRangeSelect = document.getElementById('dateRange');
    if (dateRangeSelect) dateRangeSelect.value = range;
    generateReport(range);
    document.querySelectorAll('.date-range-btn').forEach(btn => btn.classList.remove('active'));
    event?.target?.classList.add('active');
}

function showCustomDateRange() {
    document.getElementById('customDateModal')?.classList.add('show');
}

function applyCustomDateRange() {
    const startDate = document.getElementById('customStartDate')?.value;
    const endDate = document.getElementById('customEndDate')?.value;
    
    if (!startDate || !endDate) {
        showNotification('Please select both dates', 'error');
        return;
    }
    
    if (new Date(startDate) > new Date(endDate)) {
        showNotification('Start date must be before end date', 'error');
        return;
    }
    
    document.getElementById('customDateModal')?.classList.remove('show');
    showNotification('Showing data from ' + startDate + ' to ' + endDate, 'success');
    generateReport('custom');
}

function generateReport(type) {
    const dateRange = document.getElementById('dateRange')?.value || 'month';
    const reportContent = document.getElementById('reportContent');
    
    if (!reportContent) return;
    
    const data = getReportData(type, dateRange);
    reportContent.innerHTML = generateReportHTML(data, type);
    showNotification(type.charAt(0).toUpperCase() + type.slice(1) + ' report generated', 'success');
}

function getReportData(type, dateRange) {
    const salesData = [
        { date: '2024-01-01', amount: 12500, orders: 45 },
        { date: '2024-01-02', amount: 15800, orders: 52 },
        { date: '2024-01-03', amount: 14200, orders: 48 },
        { date: '2024-01-04', amount: 18900, orders: 61 },
        { date: '2024-01-05', amount: 22100, orders: 72 },
        { date: '2024-01-06', amount: 19500, orders: 65 },
        { date: '2024-01-07', amount: 24800, orders: 81 }
    ];
    
    const inventoryData = [
        { product: 'iPhone 15 Pro', stock: 45, sold: 120, revenue: 119880 },
        { product: 'MacBook Pro 14"', stock: 28, sold: 85, revenue: 169150 },
        { product: 'AirPods Max', stock: 62, sold: 210, revenue: 62790 },
        { product: 'Apple Watch S9', stock: 55, sold: 180, revenue: 89910 },
        { product: 'iPad Pro 12.9"', stock: 35, sold: 95, revenue: 142500 }
    ];
    
    const productPerformance = [
        { name: 'iPhone 15 Pro', sales: 120, revenue: 119880, rating: 4.8 },
        { name: 'MacBook Pro 14"', sales: 85, revenue: 169150, rating: 4.9 },
        { name: 'AirPods Max', sales: 210, revenue: 62790, rating: 4.6 },
        { name: 'Apple Watch S9', sales: 180, revenue: 89910, rating: 4.7 },
        { name: 'iPad Pro 12.9"', sales: 95, revenue: 142500, rating: 4.8 }
    ];
    
    const customerAnalytics = [
        { segment: 'New Customers', count: 245, revenue: 45200 },
        { segment: 'Returning Customers', count: 580, revenue: 125600 },
        { segment: 'VIP Customers', count: 85, revenue: 89400 },
        { segment: 'Corporate', count: 42, revenue: 156800 }
    ];
    
    switch(type) {
        case 'sales': return salesData;
        case 'inventory': return inventoryData;
        case 'product': return productPerformance;
        case 'customer': return customerAnalytics;
        default: return salesData;
    }
}

function generateReportHTML(data, type) {
    let html = '<div class="report-data-table"><table class="data-table"><thead><tr>';
    
    if (type === 'sales') {
        html += '<th>Date</th><th>Orders</th><th>Revenue</th></tr></thead><tbody>';
        data.forEach(item => {
            html += '<tr><td>' + item.date + '</td><td>' + item.orders + '</td><td>$' + item.amount.toLocaleString() + '</td></tr>';
        });
    } else if (type === 'inventory') {
        html += '<th>Product</th><th>Stock</th><th>Sold</th><th>Revenue</th></tr></thead><tbody>';
        data.forEach(item => {
            html += '<tr><td>' + item.product + '</td><td>' + item.stock + '</td><td>' + item.sold + '</td><td>$' + item.revenue.toLocaleString() + '</td></tr>';
        });
    } else if (type === 'product') {
        html += '<th>Product</th><th>Sales</th><th>Revenue</th><th>Rating</th></tr></thead><tbody>';
        data.forEach(item => {
            html += '<tr><td>' + item.name + '</td><td>' + item.sales + '</td><td>$' + item.revenue.toLocaleString() + '</td><td>' + item.rating + ' <i class="fas fa-star" style="color: #f59e0b;"></i></td></tr>';
        });
    } else if (type === 'customer') {
        html += '<th>Segment</th><th>Customers</th><th>Revenue</th></tr></thead><tbody>';
        data.forEach(item => {
            html += '<tr><td>' + item.segment + '</td><td>' + item.count + '</td><td>$' + item.revenue.toLocaleString() + '</td></tr>';
        });
    }
    
    html += '</tbody></table></div>';
    return html;
}

// Quick Reports Functions
function generateQuickReport(type) {
    const quickReportContent = document.getElementById('quickReportContent');
    if (!quickReportContent) return;
    
    let data, title;
    
    if (type === 'daily') {
        data = { totalOrders: 156, totalRevenue: 45280, avgOrder: 290.26, topProducts: [
            { name: 'iPhone 15 Pro', sales: 28, revenue: 27972 },
            { name: 'MacBook Pro 14"', sales: 15, revenue: 29985 },
            { name: 'AirPods Max', sales: 42, revenue: 12558 }
        ]};
        title = 'Daily Sales Report';
    } else if (type === 'weekly') {
        data = { totalOrders: 892, totalRevenue: 267840, avgOrder: 300.27, topProducts: [
            { name: 'iPhone 15 Pro', sales: 145, revenue: 144855 },
            { name: 'MacBook Pro 14"', sales: 78, revenue: 155610 }
        ]};
        title = 'Weekly Sales Report';
    } else {
        data = { totalOrders: 3845, totalRevenue: 1156280, avgOrder: 300.72, topProducts: [
            { name: 'iPhone 15 Pro', sales: 620, revenue: 619380 },
            { name: 'MacBook Pro 14"', sales: 345, revenue: 688155 }
        ]};
        title = 'Monthly Sales Report';
    }
    
    let productsHTML = data.topProducts.map(p => '<p>' + p.name + ': ' + p.sales + ' sales - $' + p.revenue.toLocaleString() + '</p>').join('');
    
    quickReportContent.innerHTML = '<h4>' + title + '</h4><div class="report-summary"><div class="summary-card"><h5>Total Orders</h5><p class="summary-value">' + data.totalOrders + '</p></div><div class="summary-card"><h5>Total Revenue</h5><p class="summary-value">$' + data.totalRevenue.toLocaleString() + '</p></div><div class="summary-card"><h5>Average Order</h5><p class="summary-value">$' + data.avgOrder.toFixed(2) + '</p></div></div><div class="report-details"><h5>Top Products</h5>' + productsHTML + '</div>';
    
    showNotification(title + ' generated successfully', 'success');
}

// Export Functions
function exportReport(format) {
    const reportType = document.querySelector('.report-category-btn.active')?.dataset.report || 'sales';
    
    if (format === 'csv') {
        const data = getReportData(reportType, 'month');
        let csvContent = 'data:text/csv;charset=utf-8,';
        
        if (reportType === 'sales') {
            csvContent += 'Date,Orders,Revenue\n';
            data.forEach(item => csvContent += item.date + ',' + item.orders + ',' + item.amount + '\n');
        } else if (reportType === 'inventory') {
            csvContent += 'Product,Stock,Sold,Revenue\n';
            data.forEach(item => csvContent += item.product + ',' + item.stock + ',' + item.sold + ',' + item.revenue + '\n');
        }
        
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement('a');
        link.setAttribute('href', encodedUri);
        link.setAttribute('download', reportType + '_report.csv');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        showNotification('CSV export completed!', 'success');
    } else if (format === 'pdf') {
        showNotification('Generating PDF report...', 'info');
        setTimeout(() => showNotification('PDF report downloaded!', 'success'), 1500);
    } else if (format === 'excel') {
        showNotification('Generating Excel report...', 'info');
        setTimeout(() => showNotification('Excel report downloaded!', 'success'), 1500);
    } else if (format === 'print') {
        window.print();
    }
}

// Helper Functions
function validateEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

// Notification function
function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.className = 'notification notification-' + (type || 'info');
    
    const icon = type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle';
    
    notification.innerHTML = '<div class="notification-content"><i class="fas fa-' + icon + '"></i><span>' + message + '</span></div><button class="notification-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>';
    
    const bgColor = type === 'success' ? '#0d9488' : type === 'error' ? '#ef4444' : '#2563eb';
    
    notification.style.cssText = 'position:fixed;top:20px;right:20px;background:' + bgColor + ';color:white;padding:1rem 1.5rem;border-radius:10px;box-shadow:0 10px 25px rgba(0,0,0,0.2);z-index:10000;display:flex;align-items:center;gap:1rem;animation:slideIn 0.3s ease';
    
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 4000);
}

// Add animation
const style = document.createElement('style');
style.textContent = '@keyframes slideIn {from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}';
document.head.appendChild(style);
