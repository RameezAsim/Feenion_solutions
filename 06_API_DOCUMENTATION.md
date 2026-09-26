# 📡 API DOCUMENTATION - Manual Collection V2 System

## Overview

The Enhanced Manual Collection page (`admin/manual_collection_v2.php`) uses AJAX endpoints to load and process data. This document details all endpoints, parameters, and responses.

---

## ENDPOINT 1: Get Family Months

### Purpose
Retrieve all available months (with invoices) for a student's family.

### URL
```
GET /admin/manual_collection_v2.php?action=get_family_months&student_id=X
```

### Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| action | string | Yes | Must be: `get_family_months` |
| student_id | integer | Yes | ID of the selected student |

### Response (Success)
```json
{
    "success": true,
    "months": ["2026-09", "2026-08", "2026-07"],
    "default_month": "2026-09",
    "parent_id": 5
}
```

### Response (Error)
```json
{
    "success": false,
    "error": "Student or parent not found"
}
```

### Example Usage (JavaScript)
```javascript
fetch(`?action=get_family_months&student_id=123`)
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            console.log("Available months:", data.months);
            currentMonth = data.default_month;
        }
    });
```

### What It Does
1. Takes a student ID
2. Finds the parent from the student record
3. Queries for all distinct months in invoices for that parent
4. Returns months sorted newest first
5. Recommends current month as default (or first available)

---

## ENDPOINT 2: Get Family Invoices

### Purpose
Retrieve all invoices and voucher details for a family for a specific month.

### URL
```
GET /admin/manual_collection_v2.php?action=get_family_invoices&student_id=X&month_year=YYYY-MM
```

### Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| action | string | Yes | Must be: `get_family_invoices` |
| student_id | integer | Yes | ID of the selected student |
| month_year | string | No | Format: YYYY-MM (defaults to current month) |

### Response (Success)
```json
{
    "success": true,
    "parent": {
        "id": 5,
        "parent_name": "Muhammad Ahmed",
        "email": "ahmed@example.com",
        "phone": "03001234567",
        "active_children": 2
    },
    "children": [
        {"id": 1, "student_name": "Ghulam Asghar", "admission_no": "136"},
        {"id": 2, "student_name": "Momina Bibi", "admission_no": "137"}
    ],
    "voucher": {
        "id": 42,
        "parent_id": 5,
        "month_year": "2026-09",
        "total_amount": "22000.00",
        "amount_paid": "5000.00",
        "discount_amount": "0.00",
        "discount_reason": null,
        "status": "Partially Paid"
    },
    "invoices": [
        {
            "invoice_id": 101,
            "invoice_uid": "INV-2026-0001",
            "student_id": 1,
            "student_name": "Ghulam Asghar",
            "admission_no": "136",
            "total_amount": "10000.00",
            "amount_paid": "2500.00",
            "due_amount": "7500.00",
            "status": "Partially Paid",
            "fee_items": "Tuition: 10000.00"
        },
        {
            "invoice_id": 102,
            "invoice_uid": "INV-2026-0002",
            "student_id": 2,
            "student_name": "Momina Bibi",
            "admission_no": "137",
            "total_amount": "12000.00",
            "amount_paid": "2500.00",
            "due_amount": "9500.00",
            "status": "Partially Paid",
            "fee_items": "Tuition: 12000.00"
        }
    ],
    "summary": {
        "total_amount": "22000.00",
        "amount_paid": "5000.00",
        "total_due": "17000.00",
        "child_count": 2,
        "discount_amount": "0.00",
        "discount_reason": null
    }
}
```

### Response (Error)
```json
{
    "success": false,
    "error": "Student or parent not found"
}
```

### What It Does
1. Gets parent from student
2. Ensures combined_voucher exists for that (parent, month, branch)
3. Retrieves parent information
4. Gets all active children for the parent
5. Gets all invoices for that family this month with fee breakdown
6. Calculates financial summary (totals, paid, due)
7. Returns complete family financial picture

### Frontend Usage
This response powers:
- Family info card (parent name, phone, children count)
- Invoices table (breakdown by student)
- Voucher preview (right panel)
- Summary totals (left panel)

---

## ENDPOINT 3: Payment Collection (POST)

### Purpose
Process and record a family payment for a specific month.

### URL
```
POST /admin/manual_collection_v2.php
```

### POST Parameters (Form Data)
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| action | string | Yes | Must be: `collect_family_payment` |
| parent_id | integer | Yes | Parent ID |
| month_year | string | Yes | Format: YYYY-MM |
| amount_collected | float | Yes | Payment amount (PKR) |
| discount_amount | float | No | Discount to apply (PKR) |
| discount_reason | string | No | Discount reason code |
| payment_method | string | No | Cash/Bank Transfer/Check/Mobile/Other |
| distribution_method | string | No | `proportional` or `equal` |

### Response (Success)
Page redirects to same page, shows success message:
```
"Payment of PKR 5,000.00 collected successfully! Discount of PKR 500.00 applied."
```

### Response (Error)
Shows error message:
```
"Error processing payment: [error details]"
```

### What It Does
1. Validates payment amount > 0
2. Gets all invoices for family this month
3. Creates payment record in `payments` table
4. Distributes payment among children's invoices:
   - **Proportional:** Splits by amount owed (e.g., child owing 10k of 22k total gets 45% of payment)
   - **Equal:** Divides equally among all children
5. Creates voucher record linking to payment
6. Applies discount if provided
7. Recalculates combined_voucher status
8. Returns to page with success message

### Example (JavaScript)
```javascript
const formData = new FormData();
formData.append('action', 'collect_family_payment');
formData.append('parent_id', 5);
formData.append('month_year', '2026-09');
formData.append('amount_collected', '5000');
formData.append('discount_amount', '500');
formData.append('discount_reason', 'Sibling Discount');
formData.append('payment_method', 'Cash');
formData.append('distribution_method', 'proportional');

fetch('', { method: 'POST', body: formData })
    .then(r => r.text())
    .then(html => location.reload());
```

---

## ENDPOINT 4: Edit Voucher Discount (POST)

### Purpose
Update the discount amount and reason for an existing combined voucher.

### URL
```
POST /admin/manual_collection_v2.php
```

### POST Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| action | string | Yes | Must be: `edit_voucher` |
| combined_voucher_id | integer | Yes | ID of combined_voucher to edit |
| discount_amount | float | Yes | New discount amount (PKR) |
| discount_reason | string | No | New discount reason |

### Response (Success)
Page redirects, shows:
```
"Voucher discount updated successfully!"
```

### Response (Error)
Shows error message:
```
"Error updating voucher: [error details]"
```

### What It Does
1. Updates combined_vouchers record
2. Sets new discount amount and reason
3. Redirects to refresh page
4. Voucher preview immediately reflects changes

### Use Case
Staff realizes they forgot to apply a discount for a family → click "Edit Voucher" → add discount retroactively → save.

---

## HELPER FUNCTION: Get Parent Details (AJAX)

### Purpose
Get parent information for display (used in student form parent selector).

### URL
```
GET /admin/students.php?action=get_parent_details&parent_id=X
```

### Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| action | string | Yes | Must be: `get_parent_details` |
| parent_id | integer | Yes | Parent ID to retrieve |

### Response (Success)
```json
{
    "success": true,
    "parent_name": "Muhammad Ahmed",
    "email": "ahmed@example.com",
    "phone": "03001234567"
}
```

### Response (Error)
```json
{
    "success": false,
    "error": "Parent not found"
}
```

### Used In
Student form - when existing parent is selected from dropdown, this loads their details for display.

---

## Database Queries for Reporting

These SQL queries can be used for custom reports:

### Query 1: Family Payment History
```sql
SELECT 
    p.parent_name,
    p.phone,
    COUNT(DISTINCT i.id) as invoice_count,
    SUM(i.total_amount) as total_billed,
    SUM(i.amount_paid) as total_paid,
    SUM(i.total_amount - i.amount_paid) as total_due
FROM parents p
JOIN invoices i ON i.parent_id = p.id
WHERE p.branch_id = 1
GROUP BY p.id
ORDER BY total_due DESC;
```

### Query 2: Monthly Revenue by Family
```sql
SELECT 
    cv.month_year,
    COUNT(DISTINCT cv.parent_id) as families,
    SUM(cv.total_amount) as total_billed,
    SUM(cv.amount_paid) as total_collected,
    SUM(cv.discount_amount) as discounts_given,
    SUM(cv.total_amount - cv.amount_paid) as outstanding
FROM combined_vouchers cv
WHERE cv.branch_id = 1
GROUP BY cv.month_year
ORDER BY cv.month_year DESC;
```

### Query 3: Discount Analysis
```sql
SELECT 
    cv.discount_reason,
    COUNT(*) as frequency,
    SUM(cv.discount_amount) as total_discounts,
    AVG(cv.discount_amount) as avg_discount
FROM combined_vouchers cv
WHERE cv.discount_reason IS NOT NULL
    AND cv.branch_id = 1
    AND cv.month_year >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 3 MONTH), '%Y-%m')
GROUP BY cv.discount_reason
ORDER BY total_discounts DESC;
```

### Query 4: Students by Family
```sql
SELECT 
    p.parent_name,
    p.phone,
    GROUP_CONCAT(DISTINCT s.student_name SEPARATOR ', ') as students,
    COUNT(DISTINCT s.id) as child_count
FROM parents p
JOIN students s ON s.parent_id = p.id
WHERE p.branch_id = 1
    AND s.status = 'Active'
GROUP BY p.id
ORDER BY p.parent_name;
```

### Query 5: Payment Distribution Analysis
```sql
SELECT 
    DATE_FORMAT(py.payment_date, '%Y-%m') as payment_month,
    py.payment_method,
    COUNT(*) as transaction_count,
    SUM(py.amount) as total_amount,
    AVG(py.amount) as avg_payment
FROM payments py
WHERE py.branch_id = 1
    AND py.status = 'Verified'
GROUP BY payment_month, py.payment_method
ORDER BY payment_month DESC;
```

---

## Error Handling

### Common Errors and Solutions

**Error: "No student selected"**
- Cause: student_id parameter missing
- Solution: Ensure student dropdown value is submitted

**Error: "Student or parent not found"**
- Cause: Invalid student_id OR student has no parent_id
- Solution: Ensure student exists and has parent linked

**Error: "No invoices found for this family this month"**
- Cause: Selected month has no invoices
- Solution: Check month selector, try previous month

**Error: "Please select an invoice and enter a valid amount"**
- Cause: Form validation failed
- Solution: Fill all required fields, enter amount > 0

---

## Response Format Standards

All successful AJAX responses follow this format:
```json
{
    "success": true,
    "data": { /* endpoint-specific data */ }
}
```

All error responses:
```json
{
    "success": false,
    "error": "Human-readable error message"
}
```

---

## Security Considerations

All endpoints:
- ✅ Require session authentication (check_session)
- ✅ Validate branch_id (ensures data isolation)
- ✅ Use prepared statements (SQL injection protection)
- ✅ Validate input types and ranges
- ✅ Implement CSRF token verification (POST requests)

---

## Performance Notes

### Optimization Tips
1. **Indexes Used:**
   - `parents.branch_id` - Fast branch filtering
   - `invoices.parent_id` - Fast family lookup
   - `invoices.combined_voucher_id` - Fast voucher linking
   - `combined_vouchers.month_year` - Fast month queries

2. **Query Optimization:**
   - Combined voucher calculations done on INSERT, not each query
   - Invoice amounts aggregated at database level
   - Payment distribution calculated in PHP (avoids temp tables)

3. **Caching Opportunity:**
   - Family month list could be cached (changes only on new invoice)
   - Parent details could be cached (rarely changes)

---

## Testing the Endpoints

### Using cURL
```bash
# Test get_family_months
curl "http://localhost/admin/manual_collection_v2.php?action=get_family_months&student_id=1"

# Test get_family_invoices
curl "http://localhost/admin/manual_collection_v2.php?action=get_family_invoices&student_id=1&month_year=2026-09"

# Test payment collection
curl -X POST "http://localhost/admin/manual_collection_v2.php" \
  -d "action=collect_family_payment&parent_id=5&month_year=2026-09&amount_collected=5000"
```

### Using Browser Console
```javascript
// Test endpoint 1
fetch(`?action=get_family_months&student_id=1`)
    .then(r => r.json())
    .then(console.log);

// Test endpoint 2
fetch(`?action=get_family_invoices&student_id=1&month_year=2026-09`)
    .then(r => r.json())
    .then(console.log);
```

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-09-07 | Initial release - 4 endpoints |

---

## Support

For issues with API endpoints, check:
1. Browser console (F12) for JavaScript errors
2. PHP error log for backend errors
3. Database for data integrity
4. Network tab for HTTP status codes
