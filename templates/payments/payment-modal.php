<!-- Payment Recording Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="paymentModalLabel">
                    <i class="fas fa-money-bill-wave"></i> Record Payment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="paymentForm">
                <div class="modal-body">
                    <!-- Citation Summary -->
                    <div class="alert alert-info">
                        <h6 class="alert-heading"><i class="fas fa-info-circle"></i> Citation Summary</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Ticket Number:</strong> <span id="modal-ticket-number"></span><br>
                                <strong>Driver:</strong> <span id="modal-driver-name"></span><br>
                                <strong>License:</strong> <span id="modal-license-number"></span>
                            </div>
                            <div class="col-md-6">
                                <strong>Plate Number:</strong> <span id="modal-plate-number"></span><br>
                                <strong>Citation Date:</strong> <span id="modal-citation-date"></span><br>
                                <strong>Status:</strong> <span id="modal-status" class="badge"></span>
                            </div>
                        </div>
                        <hr>
                        <h4 class="mb-0">Total Fine: <strong class="text-danger" id="modal-total-fine"></strong></h4>
                    </div>

                    <!-- Hidden Fields -->
                    <input type="hidden" id="citation-id" name="citation_id">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

                    <!-- Payment Details -->
                    <div class="row g-3">
                        <!-- Amount Paid -->
                        <div class="col-md-6">
                            <label for="amount-paid" class="form-label">
                                Amount Paid <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" class="form-control" id="amount-paid" name="amount_paid"
                                       step="0.01" min="0" required>
                            </div>
                            <small class="form-text text-muted">Must match total fine amount</small>
                        </div>

                        <!-- Payment Method -->
                        <div class="col-md-6">
                            <label for="payment-method" class="form-label">
                                Payment Method <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="payment-method" name="payment_method" required>
                                <option value="">Select Method</option>
                                <option value="cash">Cash</option>
                                <option value="check">Check</option>
                                <option value="online">Online Transfer</option>
                                <option value="gcash">GCash</option>
                                <option value="paymaya">PayMaya</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="money_order">Money Order</option>
                            </select>
                        </div>

                        <!-- Check Details (shown only when payment method is check) -->
                        <div id="check-details" class="col-12" style="display: none;">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <i class="fas fa-check"></i> Check Details
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label for="check-number" class="form-label">Check Number</label>
                                            <input type="text" class="form-control" id="check-number" name="check_number">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="check-bank" class="form-label">Bank Name</label>
                                            <input type="text" class="form-control" id="check-bank" name="check_bank">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="check-date" class="form-label">Check Date</label>
                                            <input type="date" class="form-control" id="check-date" name="check_date">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Reference Number (for online payments) -->
                        <div id="reference-number-field" class="col-md-6" style="display: none;">
                            <label for="reference-number" class="form-label">Reference/Transaction Number</label>
                            <input type="text" class="form-control" id="reference-number" name="reference_number">
                        </div>

                        <!-- Notes -->
                        <div class="col-12">
                            <label for="payment-notes" class="form-label">Notes/Remarks</label>
                            <textarea class="form-control" id="payment-notes" name="notes" rows="2"></textarea>
                        </div>
                    </div>

                    <!-- Alert Messages -->
                    <div id="payment-alert" class="alert alert-dismissible fade mt-3" role="alert" style="display: none;">
                        <span id="payment-alert-message"></span>
                        <button type="button" class="btn-close" onclick="closePaymentAlert()"></button>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="submit-payment-btn">
                        <i class="fas fa-check"></i> Record Payment & Generate Receipt
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Payment Modal JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const paymentMethod = document.getElementById('payment-method');
    const checkDetails = document.getElementById('check-details');
    const referenceNumberField = document.getElementById('reference-number-field');
    const amountPaid = document.getElementById('amount-paid');

    // Show/hide conditional fields based on payment method
    paymentMethod.addEventListener('change', function() {
        const method = this.value;

        // Check details
        if (method === 'check') {
            checkDetails.style.display = 'block';
            document.getElementById('check-number').required = true;
        } else {
            checkDetails.style.display = 'none';
            document.getElementById('check-number').required = false;
        }

        // Reference number for online payments
        if (['online', 'gcash', 'paymaya', 'bank_transfer'].includes(method)) {
            referenceNumberField.style.display = 'block';
        } else {
            referenceNumberField.style.display = 'none';
        }
    });

    // Form submission
    document.getElementById('paymentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        submitPayment();
    });
});

function closePaymentAlert() {
    document.getElementById('payment-alert').style.display = 'none';
}
</script>
