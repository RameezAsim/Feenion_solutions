<?php
/**
 * ============================================================================
 * FEENION - System Configuration for Enhanced Fee Collection
 * ============================================================================
 * 
 * This file contains all customizable settings for the new parent-based
 * fee collection system. Adjust these values to match your school's policy.
 * 
 * File Location: includes/fee_collection_config.php
 * Usage: Include this in includes/functions.php
 * 
 * ============================================================================
 */

// ============================================================================
// SECTION 1: DISCOUNT REASONS
// ============================================================================
// Customize these to match your school's discount policy
// Format: 'code' => 'Display Name - Description'

$DISCOUNT_REASONS = [
    'sibling_discount' => 'Sibling Discount - Multiple children in school (PKR 500-2000 per child)',
    'bulk_discount' => 'Bulk Discount - Payment for multiple months upfront (5% - 10%)',
    'loyalty_discount' => 'Loyalty Discount - Long-term student (3+ years)',
    'financial_hardship' => 'Financial Hardship - Temporary financial difficulty (case-by-case)',
    'early_payment' => 'Early Payment Discount - Paid before due date (2% - 5%)',
    'staff_discount' => 'Staff Discount - Staff member\'s child (25% - 50% depending on policy)',
    'merit_discount' => 'Merit Discount - Academic or athletic achievement (5% - 10%)',
    'scholarship' => 'Scholarship - Merit or need-based scholarship',
    'referral_discount' => 'Referral Discount - Family referred new student',
    'custom' => 'Other - Custom reason (enter in notes)',
];

// Recommended discount amounts by category (for guidance only - staff decides actual amount)
$DISCOUNT_GUIDELINES = [
    'sibling_discount' => ['min' => 500, 'max' => 2000, 'type' => 'fixed'],      // Fixed amount per child
    'bulk_discount' => ['min' => 5, 'max' => 10, 'type' => 'percent'],           // Percentage
    'loyalty_discount' => ['min' => 1000, 'max' => 5000, 'type' => 'fixed'],     // Fixed amount
    'financial_hardship' => ['min' => 10, 'max' => 50, 'type' => 'percent'],     // Percentage (case by case)
    'early_payment' => ['min' => 2, 'max' => 5, 'type' => 'percent'],            // Percentage
    'staff_discount' => ['min' => 25, 'max' => 50, 'type' => 'percent'],         // Percentage
    'merit_discount' => ['min' => 5, 'max' => 10, 'type' => 'percent'],          // Percentage
];

// ============================================================================
// SECTION 2: PAYMENT METHODS
// ============================================================================
// Available payment methods for fee collection
// Add or remove based on your school's capabilities

$PAYMENT_METHODS = [
    'cash' => 'Cash (Handed directly to accountant)',
    'bank_transfer' => 'Bank Transfer (Wire transfer to school account)',
    'check' => 'Check (Physical check with number)',
    'mobile_payment' => 'Mobile Payment (JazzCash, Easypaisa, etc.)',
    'card' => 'Card Payment (Credit/Debit card)',
    'online' => 'Online Payment (Portal payment)',
    'other' => 'Other (Specify in notes)',
];

// Banks for bank transfer (customize with your school\'s bank details)
$SCHOOL_BANKS = [
    'HBL' => [
        'name' => 'Habib Bank Limited',
        'account_holder' => 'IQRA International Islamic School',
        'account_number' => 'XXXXXXXX',
        'iban' => 'PK36 HBMZXXXXXXX',
        'branch' => 'Islamabad'
    ],
    'NBP' => [
        'name' => 'National Bank of Pakistan',
        'account_holder' => 'IQRA International Islamic School',
        'account_number' => 'XXXXXXXX',
        'iban' => 'PK16 NWBKXXXXXXX',
        'branch' => 'Islamabad'
    ],
];

// ============================================================================
// SECTION 3: PAYMENT DISTRIBUTION LOGIC
// ============================================================================
// How payments are split among children when a family pays

$DISTRIBUTION_METHODS = [
    'proportional' => [
        'name' => 'Proportional (Recommended)',
        'description' => 'Splits payment based on each child\'s proportion of total owed',
        'formula' => 'Child Amount / Total Amount = Child\'s % of Payment',
        'example' => 'If child owes 10k of 22k total, they get 45% of payment',
        'default' => true,
    ],
    'equal' => [
        'name' => 'Equal Split',
        'description' => 'Divides payment equally among all children',
        'formula' => 'Payment Amount / Number of Children = Each Child\'s Share',
        'example' => 'If paying 2000 for 2 kids, each gets 1000',
        'default' => false,
    ],
];

// ============================================================================
// SECTION 4: SYSTEM SETTINGS
// ============================================================================
// General system configuration

$SYSTEM_SETTINGS = [
    // Voucher settings
    'VOUCHER' => [
        'show_fee_breakdown' => true,           // Show itemized fees on voucher
        'show_previous_balance' => true,        // Show balance from previous months
        'show_discount_reason' => true,         // Show discount reason on voucher
        'show_payment_method' => true,          // Show payment method on voucher
        'show_next_due_date' => true,           // Show next month due date
        'include_contact_info' => true,         // Include school contact on voucher
    ],
    
    // Payment settings
    'PAYMENT' => [
        'require_verification' => true,         // Admin must verify payment
        'auto_update_invoice_status' => true,   // Automatically update invoice status
        'allow_overpayment' => true,            // Allow paying more than due
        'advance_balance_expiry_months' => 12,  // Advance balance expires after X months
    ],
    
    // Discount settings
    'DISCOUNT' => [
        'require_approval' => false,            // Require admin approval for discount (true/false)
        'approval_level' => 'Accountant',       // Who can approve: 'Admin' or 'Accountant'
        'track_discount_history' => true,       // Keep history of all discounts
        'max_discount_percent' => 50,           // Maximum discount % allowed (0 = unlimited)
    ],
    
    // Email settings
    'EMAIL' => [
        'send_payment_receipt' => true,         // Send receipt email to parent
        'send_outstanding_reminder' => true,    // Send reminder for outstanding fees
        'reminder_days_before_due' => 5,        // Days before due date to send reminder
    ],
    
    // Reporting settings
    'REPORTING' => [
        'show_combined_vouchers_in_reports' => true,  // Reports group by family
        'show_family_totals' => true,                 // Show family-level summaries
        'include_discount_analysis' => true,         // Include discount breakdown in reports
    ],
];

// ============================================================================
// SECTION 5: CURRENCY & FORMATTING
// ============================================================================

$CURRENCY_SETTINGS = [
    'symbol' => 'PKR',                  // Currency symbol
    'decimal_places' => 2,              // Decimal places (2 for rupees)
    'thousands_separator' => ',',       // Thousands separator
    'decimal_separator' => '.',         // Decimal separator
];

// Format: format_currency(1234567.89)  => "PKR 1,234,567.89"
function format_fee_currency($amount) {
    $symbol = 'PKR';
    return $symbol . ' ' . number_format($amount, 2, '.', ',');
}

// ============================================================================
// SECTION 6: VALIDATION RULES
// ============================================================================
// Business rules for validation

$VALIDATION_RULES = [
    'payment_amount' => [
        'min' => 0,                     // Minimum payment amount (0 = no minimum)
        'max' => 500000,                // Maximum payment amount (500k PKR)
        'decimal_places' => 2,          // 2 decimal places
    ],
    
    'discount_amount' => [
        'min' => 0,                     // Minimum discount (0 = no discount)
        'max' => 100000,                // Maximum discount (100k PKR)
        'decimal_places' => 2,
    ],
    
    'parent_name' => [
        'min_length' => 3,              // Minimum characters
        'max_length' => 255,            // Maximum characters
    ],
];

// ============================================================================
// SECTION 7: STATUS MESSAGES
// ============================================================================
// Customizable messages for different scenarios

$STATUS_MESSAGES = [
    'payment_success' => 'Payment of {amount} collected successfully!{discount_msg}',
    'payment_success_with_discount' => ' Discount of {discount} applied.',
    'payment_partial' => 'Partial payment recorded. Outstanding due: {due}',
    'payment_overpayment' => 'Overpayment of {overpayment} has been credited to advance balance.',
    
    'voucher_created' => 'Combined voucher created for {family_name} - {month}',
    'voucher_updated' => 'Voucher discount updated successfully.',
    'voucher_printed' => 'Voucher printed for {family_name}',
    
    'discount_applied' => 'Discount of {amount} ({reason}) applied to {family_name}',
    'discount_expired' => 'Discount period has expired',
    
    'error_no_invoices' => 'No invoices found for this family in {month}',
    'error_invalid_amount' => 'Please enter a valid payment amount',
    'error_student_not_found' => 'Student not found',
    'error_parent_not_found' => 'Parent not found',
];

// ============================================================================
// SECTION 8: BRANCH-SPECIFIC SETTINGS (If Multi-Branch)
// ============================================================================
// Different settings for different branches

$BRANCH_SETTINGS = [
    // Branch ID 1: Usman Ghani
    1 => [
        'name' => 'Usman Ghani',
        'address' => 'Islamabad Homes Phase 1, Street 4',
        'phone' => '051-XXXX-XXXX',
        'email' => 'usmangani@iqra.edu.pk',
        'default_discount_reason' => 'sibling_discount',
        'enable_mobile_payment' => true,
        'enable_check_payment' => false,
        'fiscal_year_start' => '2026-04-01',  // April (Academic year start)
    ],
    
    // Branch ID 2: Bhadana
    2 => [
        'name' => 'Bhadana',
        'address' => 'Bhadana, Islamabad',
        'phone' => '051-XXXX-XXXX',
        'email' => 'bhadana@iqra.edu.pk',
        'default_discount_reason' => 'other',
        'enable_mobile_payment' => true,
        'enable_check_payment' => true,
        'fiscal_year_start' => '2026-04-01',
    ],
];

// ============================================================================
// SECTION 9: HELPER FUNCTION TO GET CONFIG
// ============================================================================

/**
 * Get a configuration value with default fallback
 * 
 * @param string $section Config section (e.g., 'DISCOUNT', 'PAYMENT')
 * @param string $key Config key (e.g., 'require_approval')
 * @param mixed $default Default value if not found
 * @return mixed Configuration value
 */
function get_fee_config($section, $key = null, $default = null) {
    global $SYSTEM_SETTINGS;
    
    if ($key === null) {
        return $SYSTEM_SETTINGS[$section] ?? $default;
    }
    
    return $SYSTEM_SETTINGS[$section][$key] ?? $default;
}

/**
 * Get discount reasons
 * 
 * @return array Array of discount reasons
 */
function get_configured_discount_reasons() {
    global $DISCOUNT_REASONS;
    return $DISCOUNT_REASONS;
}

/**
 * Get payment methods
 * 
 * @return array Array of payment methods
 */
function get_configured_payment_methods() {
    global $PAYMENT_METHODS;
    return $PAYMENT_METHODS;
}

// ============================================================================
// SECTION 10: CUSTOMIZATION GUIDE
// ============================================================================
/*
 
HOW TO CUSTOMIZE:

1. DISCOUNT REASONS:
   - Edit $DISCOUNT_REASONS array
   - Add or remove discount types
   - Update descriptions with your policy
   - Example: 'bulk_discount' => 'Pay 3 months = 10% discount'
   
2. PAYMENT METHODS:
   - Add methods your school accepts
   - Remove methods you don't use
   - Add bank details in $SCHOOL_BANKS
   
3. SYSTEM SETTINGS:
   - Toggle features on/off
   - Set approval requirements
   - Configure email notifications
   
4. BRANCH-SPECIFIC:
   - If you have multiple branches
   - Override default settings per branch
   - Set branch contact info
   
5. VALIDATION RULES:
   - Change min/max payment amounts
   - Adjust decimal places if needed
   - Set name field requirements
   
6. MESSAGES:
   - Customize success/error messages
   - Use {placeholders} for dynamic content
   - Translate to Urdu if needed

EXAMPLE - Adding New Discount Type:

$DISCOUNT_REASONS = [
    // ... existing reasons ...
    'group_discount' => 'Group Discount - Family with 4+ children (25% discount)',
];

$DISCOUNT_GUIDELINES = [
    // ... existing guidelines ...
    'group_discount' => ['min' => 25, 'max' => 25, 'type' => 'percent'],
];

Then staff can select "Group Discount" when applicable.

*/

// End of configuration file
?>
