<?php
/**
 * Admin Footer
 * Common footer for admin pages
 */
?>

<!-- Required Scripts -->
 <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
 <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
 <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
 <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
 <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
 
 <!-- Custom Scripts -->
 <script src="<?php echo APP_URL; ?>/assets/js/admin.js"></script>
 
 <script>
 // Common initialization
 $(document).ready(function() {
     // Initialize tooltips
     $('[data-bs-toggle="tooltip"]').tooltip();
     
     // Initialize popovers
     $('[data-bs-toggle="popover"]').popover();
     
     // DataTable defaults
     $.extend($.fn.dataTable.defaults, {
         language: {
             search: "_INPUT_",
             searchPlaceholder: "Search...",
             lengthMenu: "Show _MENU_ entries",
             info: "Showing _START_ to _END_ of _TOTAL_ entries",
             infoEmpty: "No entries found",
             infoFiltered: "(filtered from _MAX_ total entries)",
             paginate: {
                 first: "First",
                 last: "Last",
                 next: "Next",
                 previous: "Previous"
             }
         }
     });
     
     // Delete confirmation
     $('.confirm-delete').on('click', function(e) {
         if (!confirm('Are you sure you want to delete this item?')) {
             e.preventDefault();
         }
     });
     
     // Form validation
     $('.needs-validation').on('submit', function(e) {
         if (!this.checkValidity()) {
             e.preventDefault();
             e.stopPropagation();
         }
         $(this).addClass('was-validated');
     });
     
     // Auto-hide alerts
     setTimeout(function() {
         $('.alert').fadeOut('slow');
     }, 5000);
 });
 
 // Show loading spinner
 function showLoading() {
     $('#loadingSpinner').show();
 }
 
 // Hide loading spinner
 function hideLoading() {
     $('#loadingSpinner').hide();
 }
 
 // Show notification
 function showNotification(type, message, duration = 5000) {
     const alertHtml = `
         <div class="alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3" 
              style="z-index: 9999; min-width: 300px;" role="alert">
             ${message}
             <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
         </div>
     `;
     $('body').append(alertHtml);
     
     if (duration > 0) {
         setTimeout(function() {
             $('.alert').fadeOut('slow', function() {
                 $(this).remove();
             });
         }, duration);
     }
 }
 
 // AJAX form submission
 function ajaxFormSubmit(formId, successCallback, errorCallback) {
     const form = $('#' + formId);
     const formData = new FormData(form[0]);
     
     $.ajax({
         url: form.attr('action'),
         type: 'POST',
         data: formData,
         processData: false,
         contentType: false,
         beforeSend: function() {
             showLoading();
         },
         success: function(response) {
             hideLoading();
             if (response.success) {
                 showNotification('success', response.message || 'Operation successful!');
                 if (successCallback) successCallback(response);
             } else {
                 showNotification('danger', response.message || 'Operation failed!');
                 if (errorCallback) errorCallback(response);
             }
         },
         error: function(xhr, status, error) {
             hideLoading();
             showNotification('danger', 'An error occurred: ' + error);
             if (errorCallback) errorCallback({message: error});
         }
     });
 }
 
 // Format currency
 function formatCurrency(amount) {
     return 'KSh ' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
 }
 
 // Format date
 function formatDate(dateString) {
     const date = new Date(dateString);
     return date.toLocaleDateString('en-US', {
         year: 'numeric',
         month: 'short',
         day: 'numeric'
     });
 }
 
 // Format datetime
 function formatDateTime(dateString) {
     const date = new Date(dateString);
     return date.toLocaleDateString('en-US', {
         year: 'numeric',
         month: 'short',
         day: 'numeric',
         hour: '2-digit',
         minute: '2-digit'
     });
 }
 
 // Get URL parameters
 function getUrlParams() {
     const params = {};
     const searchParams = new URLSearchParams(window.location.search);
     for (const [key, value] of searchParams) {
         params[key] = value;
     }
     return params;
 }
 
 // Update URL without reload
 function updateUrl(params) {
     const url = new URL(window.location);
     for (const [key, value] of Object.entries(params)) {
         if (value) {
             url.searchParams.set(key, value);
         } else {
             url.searchParams.delete(key);
         }
     }
     window.history.pushState({}, '', url);
 }
 </script>
 
 <!-- Loading Spinner -->
 <div id="loadingSpinner" class="spinner-overlay" style="display: none;">
     <div class="spinner-border text-primary" role="status">
         <span class="visually-hidden">Loading...</span>
     </div>
 </div>
 
 </main>
 </main>
 </body>
 </html>
