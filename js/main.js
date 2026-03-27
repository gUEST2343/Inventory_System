/**
 * StockFlow - Inventory Management System
 * Main JavaScript File
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initSidebar();
    initMobileMenu();
    initSearch();
    initViewToggle();
    initKanban();
    initModal();
    initActiveNav();
    initOrderFilter();
    initThemeToggle();
});

/* ------------------------------------------------
   SIDEBAR FUNCTIONALITY
   ------------------------------------------------ */
function initSidebar() {
    const sidebar = document.getElementById('sidebar');
    const collapseBtn = document.querySelector('.collapse-btn');
    
    if (collapseBtn && sidebar) {
        collapseBtn.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            
            // Save state to localStorage
            const isCollapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
        });
        
        // Load saved state
        const savedState = localStorage.getItem('sidebarCollapsed');
        if (savedState === 'true') {
            sidebar.classList.add('collapsed');
        }
    }
}

/* ------------------------------------------------
   MOBILE MENU
   ------------------------------------------------ */
function initMobileMenu() {
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.querySelector('.sidebar-overlay');
    
    if (mobileMenuBtn && sidebar) {
        mobileMenuBtn.addEventListener('click', function() {
            sidebar.classList.toggle('mobile-open');
            if (sidebarOverlay) {
                sidebarOverlay.classList.toggle('active');
            }
        });
        
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('mobile-open');
                sidebarOverlay.classList.remove('active');
            });
        }
    }
}

/* ------------------------------------------------
   SEARCH FUNCTIONALITY
   ------------------------------------------------ */
function initSearch() {
    const searchInput = document.querySelector('.search-box input');
    
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase();
            filterContent(query);
        });
    }
}

function filterContent(query) {
    // Filter tables
    const tableRows = document.querySelectorAll('.data-table tbody tr');
    tableRows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(query)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
    
    // Filter product cards
    const productCards = document.querySelectorAll('.product-card');
    productCards.forEach(card => {
        const text = card.textContent.toLowerCase();
        if (text.includes(query)) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
    
    // Filter customer cards
    const customerCards = document.querySelectorAll('.customer-card');
    customerCards.forEach(card => {
        const text = card.textContent.toLowerCase();
        if (text.includes(query)) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}

/* ------------------------------------------------
   VIEW TOGGLE (Grid/List)
   ------------------------------------------------ */
function initViewToggle() {
    const viewToggleBtns = document.querySelectorAll('.view-toggle button');
    const productsContainer = document.querySelector('.products-grid');
    
    if (viewToggleBtns.length > 0 && productsContainer) {
        viewToggleBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const { view } = this.dataset;
                
                // Update buttons
                viewToggleBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Toggle view class
                if (view === 'list') {
                    productsContainer.classList.add('list-view');
                    productsContainer.classList.remove('grid-view');
                } else {
                    productsContainer.classList.add('grid-view');
                    productsContainer.classList.remove('list-view');
                }
            });
        });
    }
}

/* ------------------------------------------------
   KANBAN BOARD (Drag and Drop)
   ------------------------------------------------ */
function initKanban() {
    const kanbanCards = document.querySelectorAll('.kanban-card');
    const kanbanColumns = document.querySelectorAll('.kanban-cards');
    
    if (kanbanCards.length === 0) return;
    
    kanbanCards.forEach(card => {
        card.draggable = true;
        
        card.addEventListener('dragstart', function(e) {
            this.classList.add('dragging');
            e.dataTransfer.setData('text/plain', this.dataset.id);
        });
        
        card.addEventListener('dragend', function() {
            this.classList.remove('dragging');
        });
    });
    
    kanbanColumns.forEach(column => {
        column.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.background = 'rgba(37, 99, 235, 0.05)';
        });
        
        column.addEventListener('dragleave', function() {
            this.style.background = '';
        });
        
        column.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.background = '';
            
            const cardId = e.dataTransfer.getData('text/plain');
            const card = document.querySelector(`.kanban-card[data-id="${cardId}"]`);
            
            if (card) {
                this.appendChild(card);
                
                // Update order status
                updateOrderStatus(cardId, this.dataset.status);
                
                // Update column count
                updateColumnCount(this);
            }
        });
    });
}

function updateOrderStatus(orderId, status) {
    // In a real application, this would make an API call
    console.log(`Order ${orderId} status updated to: ${status}`);
    
    // Show notification
    showNotification(`Order moved to ${status}`);
}

function updateColumnCount(column) {
    const countElement = column.parentElement.querySelector('.kanban-count');
    if (countElement) {
        const count = column.children.length;
        countElement.textContent = count;
    }
}

/* ------------------------------------------------
   MODAL FUNCTIONALITY
   ------------------------------------------------ */
function initModal() {
    const modalTriggers = document.querySelectorAll('[data-modal]');
    const modalOverlays = document.querySelectorAll('.modal-overlay');
    
    // Open modal
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            const modalId = this.dataset.modal;
            openModal(modalId);
        });
    });
    
    // Close modal on overlay click
    modalOverlays.forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(this.id);
            }
        });
    });
    
    // Close modal on button click
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-close')) {
            const modal = e.target.closest('.modal-overlay');
            if (modal) {
                closeModal(modal.id);
            }
        }
    });
    
    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const activeModal = document.querySelector('.modal-overlay.active');
            if (activeModal) {
                closeModal(activeModal.id);
            }
        }
    });
    
    // Handle modal form submissions
    const modalForms = document.querySelectorAll('.modal form');
    modalForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            handleModalSubmit(this);
        });
    });
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

function handleModalSubmit(form) {
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    // Handle form submission
    console.log('Form submitted:', data);
    
    // Show success notification
    showNotification('Stock adjusted successfully!');
    
    // Close modal
    const modal = form.closest('.modal-overlay');
    if (modal) {
        closeModal(modal.id);
    }
    
    // Reload page to show updated data
    setTimeout(() => {
        window.location.reload();
    }, 1000);
}

/* ------------------------------------------------
   ACTIVE NAVIGATION
   ------------------------------------------------ */
function initActiveNav() {
    const currentPage = window.location.pathname.split('/').pop() || 'index.html';
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href && href.includes(currentPage.replace('.html', ''))) {
            link.classList.add('active');
        }
    });
    
    // Also check for data attributes
    const activeLinks = document.querySelectorAll('.nav-link[data-page]');
    activeLinks.forEach(link => {
        const { page } = link.dataset;
        if (currentPage.includes(page)) {
            link.classList.add('active');
        }
    });
}

/* ------------------------------------------------
   NOTIFICATIONS
   ------------------------------------------------ */
function showNotification(message, type = 'success') {
    // Remove existing notifications
    const existingNotification = document.querySelector('.notification-toast');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    // Create notification
    const notification = document.createElement('div');
    notification.className = `notification-toast notification-${type}`;
    notification.innerHTML = `
        <span>${message}</span>
        <button class="notification-close">&times;</button>
    `;
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 90px;
        right: 20px;
        padding: 16px 20px;
        background: ${type === 'success' ? '#0d9488' : type === 'error' ? '#ef4444' : '#2563eb'};
        color: white;
        border-radius: 8px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        display: flex;
        align-items: center;
        gap: 12px;
        z-index: 3000;
        animation: slideIn 0.3s ease;
    `;
    
    // Add animation keyframes
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    `;
    document.head.appendChild(style);
    
    // Close button handler
    const closeBtn = notification.querySelector('.notification-close');
    closeBtn.addEventListener('click', () => notification.remove());
    
    document.body.appendChild(notification);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

/* ------------------------------------------------
   TABLE SORTING
   ------------------------------------------------ */
function initTableSorting() {
    const tableHeaders = document.querySelectorAll('.data-table th');
    
    tableHeaders.forEach((header, index) => {
        if (header.querySelector('.sort-icon')) {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => sortTable(index));
        }
    });
}

function sortTable(columnIndex) {
    const table = document.querySelector('.data-table');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    // Toggle sort direction
    const isAscending = !table.classList.contains('sorted-desc');
    table.classList.toggle('sorted-asc', isAscending);
    table.classList.toggle('sorted-desc', !isAscending);
    
    // Sort rows
    rows.sort((a, b) => {
        const aValue = a.cells[columnIndex].textContent.trim();
        const bValue = b.cells[columnIndex].textContent.trim();
        
        // Try numeric comparison
        const aNum = parseFloat(aValue.replace(/[^0-9.-]/g, ''));
        const bNum = parseFloat(bValue.replace(/[^0-9.-]/g, ''));
        
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return isAscending ? aNum - bNum : bNum - aNum;
        }
        
        // String comparison
        return isAscending 
            ? aValue.localeCompare(bValue) 
            : bValue.localeCompare(aValue);
    });
    
    // Re-append rows
    rows.forEach(row => tbody.appendChild(row));
}

/* ------------------------------------------------
   FILTER DROPDOWNS
   ------------------------------------------------ */
function initFilters() {
    const filterSelects = document.querySelectorAll('.filter-select, .form-select');
    
    filterSelects.forEach(select => {
        select.addEventListener('change', applyFilters);
    });
}

function applyFilters() {
    const categoryFilter = document.getElementById('categoryFilter')?.value || 'all';
    const statusFilter = document.getElementById('statusFilter')?.value || 'all';
    const priceFilter = document.getElementById('priceFilter')?.value || 'all';
    
    // Filter products
    const productCards = document.querySelectorAll('.product-card');
    
    productCards.forEach(card => {
        let show = true;
        
        // Category filter
        if (categoryFilter !== 'all') {
            const cardCategory = card.dataset.category;
            if (cardCategory !== categoryFilter) {
                show = false;
            }
        }
        
        // Status filter
        if (statusFilter !== 'all') {
            const cardStatus = card.dataset.status;
            if (cardStatus !== statusFilter) {
                show = false;
            }
        }
        
        // Price filter
        if (priceFilter !== 'all') {
            const price = parseFloat(card.dataset.price);
            if (priceFilter === 'low' && price > 50) show = false;
            if (priceFilter === 'medium' && (price < 50 || price > 100)) show = false;
            if (priceFilter === 'high' && price < 100) show = false;
        }
        
        card.style.display = show ? '' : 'none';
    });
    
    // Update counts
    updateFilterCounts();
}

function updateFilterCounts() {
    const visibleCards = document.querySelectorAll('.product-card:not([style*="display: none"])');
    const countElement = document.querySelector('.products-count');
    if (countElement) {
        countElement.textContent = visibleCards.length;
    }
}

/* ------------------------------------------------
   STOCK ADJUSTMENT
   ------------------------------------------------ */
function openStockAdjustment(productId, productName, currentStock) {
    const modal = document.getElementById('stockModal');
    if (!modal) return;
    
    // Populate modal with product info
    const productNameEl = modal.querySelector('.modal-product-name');
    const currentStockEl = modal.querySelector('.current-stock-value');
    const productIdInput = modal.querySelector('input[name="product_id"]');
    
    if (productNameEl) productNameEl.textContent = productName;
    if (currentStockEl) currentStockEl.textContent = currentStock;
    if (productIdInput) productIdInput.value = productId;
    
    openModal('stockModal');
}

/* ------------------------------------------------
   DATA EXPORT
   ------------------------------------------------ */
function exportData(format) {
    showNotification(`Exporting data as ${format.toUpperCase()}...`);
    
    // In a real application, this would trigger a download
    setTimeout(() => {
        showNotification('Data exported successfully!');
    }, 1500);
}

/* ------------------------------------------------
   BREADCRUMB
   ------------------------------------------------ */
function updateBreadcrumb(pages) {
    const breadcrumb = document.querySelector('.breadcrumb');
    if (!breadcrumb) return;
    
    breadcrumb.innerHTML = pages.map((page, index) => {
        const isLast = index === pages.length - 1;
        return isLast 
            ? `<span class="breadcrumb-item active">${page.name}</span>`
            : `<a href="${page.url}" class="breadcrumb-item">${page.name}</a>`;
    }).join(' <span class="breadcrumb-separator">/</span> ');
}

/* ------------------------------------------------
   THEME TOGGLE (Optional)
   ------------------------------------------------ */
function initThemeToggle() {
    const themeToggle = document.querySelector('.theme-toggle');
    if (!themeToggle) return;
    
    themeToggle.addEventListener('click', () => {
        document.body.classList.toggle('dark-mode');
        const isDark = document.body.classList.contains('dark-mode');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
    });
    
    // Load saved theme
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
    }
}

/* ------------------------------------------------
   ANIMATIONS ON SCROLL
   ------------------------------------------------ */
function initScrollAnimations() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    document.querySelectorAll('.card, .stat-card, .product-card, .customer-card').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        observer.observe(el);
    });
    
    // Add animation class styles
    const style = document.createElement('style');
    style.textContent = `
        .animate-in {
            opacity: 1 !important;
            transform: translateY(0) !important;
        }
    `;
    document.head.appendChild(style);
}

/* ------------------------------------------------
   UTILITY FUNCTIONS
   ------------------------------------------------ */
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}

function formatNumber(num) {
    return new Intl.NumberFormat('en-US').format(num);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    }).format(date);
}

function timeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);
    
    const intervals = {
        year: 31536000,
        month: 2592000,
        week: 604800,
        day: 86400,
        hour: 3600,
        minute: 60
    };
    
    for (const [unit, secondsInUnit] of Object.entries(intervals)) {
        const interval = Math.floor(seconds / secondsInUnit);
        if (interval >= 1) {
            return `${interval} ${unit}${interval > 1 ? 's' : ''} ago`;
        }
    }
    
    return 'Just now';
}

/* ------------------------------------------------
   ORDER FILTER FUNCTIONALITY
   ------------------------------------------------ */
function initOrderFilter() {
    const filterBtn = document.getElementById('filterBtn');
    const filterMenu = document.getElementById('filterMenu');
    
    if (filterBtn && filterMenu) {
        // Toggle filter menu
        filterBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            filterMenu.classList.toggle('show');
        });
        
        // Close on outside click
        document.addEventListener('click', function(e) {
            if (!filterMenu.contains(e.target) && !filterBtn.contains(e.target)) {
                filterMenu.classList.remove('show');
            }
        });
    }
}

function filterOrders() {
    const statusFilter = document.getElementById('filterStatus')?.value || 'all';
    const dateFilter = document.getElementById('filterDate')?.value || 'all';
    const customerFilter = document.getElementById('filterCustomer')?.value.toLowerCase() || '';
    const minAmount = parseFloat(document.getElementById('filterMinAmount')?.value) || 0;
    const maxAmount = parseFloat(document.getElementById('filterMaxAmount')?.value) || Infinity;
    
    const kanbanCards = document.querySelectorAll('.kanban-card');
    let visibleCount = 0;
    
    kanbanCards.forEach(card => {
        let show = true;
        
        // Get card data - Using object destructuring as suggested
        const { status: cardStatus } = card.closest('.kanban-cards')?.dataset || {};
        const cardText = card.textContent.toLowerCase();
        
        // Extract amount from card
        const amountText = card.querySelector('.kanban-card-footer span')?.textContent.replace('$', '') || '0';
        const cardAmount = parseFloat(amountText);
        
        // Status filter
        if (statusFilter !== 'all' && cardStatus !== statusFilter) {
            show = false;
        }
        
        // Date filter (simple text matching for demo)
        if (dateFilter !== 'all') {
            const cardTime = card.querySelector('.kanban-card-footer span:last-child')?.textContent.toLowerCase() || '';
            if (dateFilter === 'today' && !cardTime.includes('today')) {
                show = false;
            } else if (dateFilter === 'yesterday' && !cardTime.includes('yesterday')) {
                show = false;
            } else if (dateFilter === 'week' && !cardTime.includes('day')) {
                show = false;
            } else if (dateFilter === 'month' && !cardTime.includes('day') && !cardTime.includes('today') && !cardTime.includes('yesterday')) {
                show = false;
            }
        }
        
        // Customer filter
        if (customerFilter && !cardText.includes(customerFilter)) {
            show = false;
        }
        
        // Amount filter
        if (cardAmount < minAmount || cardAmount > maxAmount) {
            show = false;
        }
        
        card.style.display = show ? '' : 'none';
        if (show) visibleCount++;
    });
    
    // Update filter count
    const filterCount = document.getElementById('filterCount');
    if (filterCount) {
        const total = kanbanCards.length;
        if (visibleCount === total) {
            filterCount.textContent = `Showing all ${total} orders`;
        } else {
            filterCount.textContent = `Showing ${visibleCount} of ${total} orders`;
        }
    }
    
    // Update column counts
    updateFilteredColumnCounts();
}

function updateFilteredColumnCounts() {
    const columns = document.querySelectorAll('.kanban-cards');
    columns.forEach(column => {
        const visibleCards = column.querySelectorAll('.kanban-card:not([style*="display: none"])');
        const countElement = column.parentElement.querySelector('.kanban-count');
        if (countElement) {
            countElement.textContent = visibleCards.length;
        }
    });
}

function resetOrderFilters() {
    // Reset filter inputs
    const filterStatus = document.getElementById('filterStatus');
    const filterDate = document.getElementById('filterDate');
    const filterCustomer = document.getElementById('filterCustomer');
    const filterMinAmount = document.getElementById('filterMinAmount');
    const filterMaxAmount = document.getElementById('filterMaxAmount');
    
    if (filterStatus) filterStatus.value = 'all';
    if (filterDate) filterDate.value = 'all';
    if (filterCustomer) filterCustomer.value = '';
    if (filterMinAmount) filterMinAmount.value = '';
    if (filterMaxAmount) filterMaxAmount.value = '';
    
    // Show all cards
    const kanbanCards = document.querySelectorAll('.kanban-card');
    kanbanCards.forEach(card => {
        card.style.display = '';
    });
    
    // Update counts
    updateFilteredColumnCounts();
    
    // Update filter count display
    const filterCount = document.getElementById('filterCount');
    if (filterCount) {
        filterCount.textContent = `Showing all ${kanbanCards.length} orders`;
    }
    
    // Show notification
    showNotification('Filters reset successfully');
}

// Export functions for global use
window.StockFlow = {
    openModal,
    closeModal,
    showNotification,
    exportData,
    openStockAdjustment,
    formatCurrency,
    formatNumber,
    formatDate,
    timeAgo,
    filterOrders,
    resetOrderFilters
};
