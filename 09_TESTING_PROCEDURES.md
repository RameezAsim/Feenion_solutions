# 🧪 COMPREHENSIVE TESTING PROCEDURES & QA CHECKLIST

## Overview

This document outlines detailed testing procedures to verify that the new parent-based fee collection system works correctly at every step. Follow these procedures BEFORE going live.

**Estimated Time:** 2-3 hours for full testing

---

## ✅ PRE-TESTING CHECKLIST

Before you begin testing, ensure:

- [ ] All 5 implementation steps completed
- [ ] Database backups made
- [ ] Test data created (parents, students, invoices)
- [ ] You have Admin/Accountant access
- [ ] All helpers functions added to functions.php
- [ ] New manual_collection_v2.php deployed
- [ ] Browser cache cleared (Ctrl+Shift+Delete)
- [ ] No production traffic during testing (test in quiet hours)

---

## 📊 TEST DATA SETUP

### Sample Families to Create (for testing)

**Family 1: Multi-Child Family**
```
Parent: Muhammad Ahmed (ID will be generated)
Children:
  - Ghulam Asghar (ID: 101, Admission: 136)
  - Momina Bibi (ID: 102, Admission: 137)
Month: September 2026
Ghulam Fees: PKR 10,000
Momina Fees: PKR 12,000
Total: PKR 22,000
```

**Family 2: Single-Child Family**
```
Parent: Fatima Khan (ID will be generated)
Child:
  - Ali Khan (ID: 103, Admission: 138)
Month: September 2026
Ali Fees: PKR 8,000
Total: PKR 8,000
```

**Family 3: Three-Child Family**
```
Parent: Hassan Ali (ID will be generated)
Children:
  - Sara Ali (ID: 104, Admission: 139) - PKR 9,000
  - Zara Ali (ID: 105, Admission: 140) - PKR 11,000
  - Hamza Ali (ID: 106, Admission: 141) - PKR 10,000
Total: PKR 30,000
```

### How to Create Test Data:

If test data already exists from migration, use that. Otherwise:

1. Go to Students → Add Student
2. Create 3 parents using parent selector
3. Add 6 students (2, 1, 3 per family)
4. Go to Invoices → Generate invoices for test month
5. Verify invoices created with correct totals

---

## 🧪 TEST SUITE 1: DATABASE MIGRATION

### Test 1.1: Parents Table Created
**Purpose:** Verify parents table exists with correct schema

**Steps:**
1. Open phpMyAdmin
2. Select your database
3. Look for table: `parents`
4. Click `parents` table
5. Click "Structure" tab

**Expected Result:**
```
Columns present:
✓ id (int, auto-increment)
✓ parent_name (varchar 255)
✓ email (varchar 100)
✓ phone (varchar 20)
✓ cnic (varchar 20)
✓ address (text)
✓ branch_id (int, FK)
✓ created_at (timestamp)
✓ updated_at (timestamp)
```

**Status:** ✅ PASS / ❌ FAIL

---

### Test 1.2: Combined Vouchers Table Created
**Purpose:** Verify combined_vouchers table exists

**Steps:**
1. In phpMyAdmin, look for table: `combined_vouchers`
2. Click to view structure

**Expected Result:**
```
Columns present:
✓ id (int, auto-increment)
✓ parent_id (int, FK)
✓ month_year (varchar 7)
✓ total_amount (decimal 10,2)
✓ amount_paid (decimal 10,2)
✓ discount_amount (decimal 10,2)
✓ discount_reason (varchar 255)
✓ status (enum: Unpaid, Partially Paid, Paid)
✓ file_path (varchar 255)
✓ branch_id (int, FK)
✓ created_at / updated_at (timestamps)
```

**Status:** ✅ PASS / ❌ FAIL

---

### Test 1.3: Invoice Table Modified
**Purpose:** Verify new columns added to invoices

**Steps:**
1. View `invoices` table structure
2. Scroll down to find new columns

**Expected Result:**
```
New columns present:
✓ parent_id (int, FK to parents)
✓ combined_voucher_id (int, FK to combined_vouchers)
```

**Status:** ✅ PASS / ❌ FAIL

---

### Test 1.4: Data Migration Successful
**Purpose:** Verify data was migrated correctly

**Steps:**
1. Run query: `SELECT COUNT(*) FROM parents;`
2. Run query: `SELECT COUNT(*) FROM combined_vouchers;`
3. Run query: `SELECT COUNT(*) FROM invoices WHERE parent_id IS NOT NULL;`

**Expected Result:**
```
- parents count: > 0 (at least as many as unique parent_id in students)
- combined_vouchers count: > 0 (one per unique parent-month combo)
- invoices with parent_id: equals total invoice count (or close)
```

**Example:**
```
Parents: 5
Combined Vouchers: 15 (5 families × 3 months)
Invoices with parent_id: 30
```

**Status:** ✅ PASS / ❌ FAIL

---

## 🧪 TEST SUITE 2: HELPER FUNCTIONS

### Test 2.1: Functions Exist
**Purpose:** Verify helper functions added to functions.php

**Steps:**
1. Go to: `admin/index.php`
2. Open browser console (F12)
3. At bottom, run: `echo function_exists('get_family_details') ? 'YES' : 'NO';`

**Expected Result:**
```
YES
```

**Status:** ✅ PASS / ❌ FAIL

---

### Test 2.2: Test Each Helper Function

**In browser console or PHP script, test:**

```php
// Test 1: get_family_details
$family = get_family_details(1, $pdo);
echo "Family: " . $family['parent_name'] . " has " . $family['active_children'] . " children";
// Expected: "Family: Muhammad Ahmed has 2 children"

// Test 2: get_parent_children
$children = get_parent_children(1, 1, $pdo);
echo "Children count: " . count($children);
// Expected: Children count: 2

// Test 3: get_combined_voucher
$voucher = get_combined_voucher(1, '2026-09', 1, $pdo);
echo "Voucher total: " . $voucher['total_amount'];
// Expected: Voucher total: 22000

// Test 4: get_family_monthly_summary
$summary = get_family_monthly_summary(1, '2026-09', 1, $pdo);
echo "Due: " . $summary['total_due'];
// Expected: Due: 22000 (or less if paid)
```

**Status:** ✅ PASS / ❌ FAIL (for each function)

---

## 🧪 TEST SUITE 3: MANUAL COLLECTION PAGE

### Test 3.1: Page Loads Without Errors
**Purpose:** Verify new manual collection page is accessible

**Steps:**
1. Log in as Admin or Accountant
2. Click: Manual Collection (sidebar)
3. Click: Manual Collection (Enhanced - Family-Based)

**Expected Result:**
- Page loads
- No JavaScript errors (check F12 console)
- See student dropdown
- Right panel says "Select a student to view family voucher details"

**Status:** ✅ PASS / ❌ FAIL

---

### Test 3.2: Student Dropdown Works
**Purpose:** Verify student list populated correctly

**Steps:**
1. Click dropdown: "Select Student / Family"
2. Start typing student name

**Expected Result:**
```
Dropdown shows:
✓ Student names appear in list
✓ Multiple matches show for multi-child families
✓ Format shows student name + family parent name
```

**Example:**
```
Ghulam Asghar (136) - Muhammad Ahmed
Momina Bibi (137) - Muhammad Ahmed
Ali Khan (138) - Fatima Khan
```

**Status:** ✅ PASS / ❌ FAIL

---

### Test 3.3: Select Student Loads Family Data
**Purpose:** Verify data loads when student selected

**Steps:**
1. Select a multi-child student (e.g., Ghulam)
2. Wait 2 seconds for data to load

**Expected Result:**
```
Left panel:
✓ Month navigation appears (← Previous | Month | Next →)
✓ Family info card shows with parent name, phone, child count
✓ Invoices table shows both children
✓ Summary totals calculated and displayed

Right panel:
✓ Voucher preview shows combined invoice
✓ Both children listed
✓ Combined total showing
```

**Verify specific values:**
- Family Info shows "Muhammad Ahmed", child count "2"
- Invoices table shows:
  - Ghulam Asghar | 10000 | 0 | 10000
  - Momina Bibi  | 12000 | 0 | 12000
- Summary shows: Total Due: PKR 22,000.00

**Status:** ✅ PASS / ❌ FAIL

---

### Test 3.4: Month Navigation Works
**Purpose:** Verify month slider navigates correctly

**Setup:** Ensure test family has invoices for multiple months
(If not, migration would only create current month)

**Steps:**
1. Student selected
2. Note current month displaying
3. Click: ← Previous

**Expected Result:**
```
✓ Month changes to previous
✓ Invoices update for that month
✓ Totals recalculate
✓ Voucher preview updates
```

**Status:** ✅ PASS / ❌ FAIL

---

### Test 3.5: Record Payment - Proportional Distribution
**Purpose:** Verify payment records correctly with proportional split

**Setup:**
```
Select: Muhammad Ahmed family
Month: September 2026
Ghulam owes: PKR 10,000 (45% of 22k)
Momina owes: PKR 12,000 (55% of 22k)
```

**Steps:**
1. Enter Amount Collected: `5000`
2. Leave Discount as 0
3. Payment Method: Cash
4. Distribution Method: ✓ Proportional (default)
5. Click: "✓ Record Payment"

**Expected Result:**
```
✓ Page reloads
✓ Success message shows: "Payment of PKR 5,000.00 collected successfully!"
✓ Invoices updated in database:
  - Ghulam: amount_paid = 0 + (5000 × 0.45) = 2,250
  - Momina: amount_paid = 0 + (5000 × 0.55) = 2,750
✓ Payment record created in payments table
✓ Voucher record created in vouchers table
```

**SQL Verification:**
```sql
SELECT student_id, amount_paid FROM invoices 
WHERE DATE_FORMAT(created_at, '%Y-%m') = '2026-09' 
  AND parent_id = 1;

-- Should show:
-- Student 101 (Ghulam): 2250
-- Student 102 (Momina): 2750
```

**Status:** ✅ PASS / ❌ FAIL

---

### Test 3.6: Record Payment - Equal Distribution
**Purpose:** Verify equal split distribution works

**Setup:** Same family, second payment

**Steps:**
1. Enter Amount Collected: `2000`
2. Distribution Method: ✓ Equal Split (select this radio)
3. Click: "✓ Record Payment"

**Expected Result:**
```
✓ Payment recorded
✓ Both children get equal: 1000 each
✓ Invoices updated:
  - Ghulam: amount_paid = 2250 + 1000 = 3,250
  - Momina: amount_paid = 2750 + 1000 = 3,750
```

**Status:** ✅ PASS / ❌ FAIL

---

### Test 3.7: Apply Discount
**Purpose:** Verify discount application

**Setup:** Third payment for same family

**Steps:**
1. Enter Amount Collected: `3000`
2. Discount Amount: `500`
3. Discount Reason: "Sibling Discount"
4. Click: "✓ Record Payment"

**Expected Result:**
```
✓ Success message: "Payment of PKR 3,000.00 collected successfully! Discount of PKR 500.00 applied."
✓ Voucher preview shows discount applied
✓ Database shows: combined_vouchers.discount_amount = 500
```

**Status:** ✅ PASS / ❌ FAIL

---

### Test 3.8: Edit Voucher
**Purpose:** Verify voucher can be edited after creation

**Steps:**
1. After previous payment, right panel shows voucher preview
2. Click: "✏️ Edit Voucher" button
3. Modal appears with fields
4. Change: Discount Amount to `750`
5. Change: Discount Reason to "Loyalty Discount"
6. Click: "Save Changes"

**Expected Result:**
```
✓ Modal closes
✓ Page reloads
✓ Voucher preview shows new discount amount (750)
✓ Database updated: combined_vouchers.discount_amount = 750
```

**Status:** ✅ PASS / ❌ FAIL

---

### Test 3.9: Print Voucher
**Purpose:** Verify voucher print formatting

**Steps:**
1. Student selected, month selected
2. Right panel shows voucher preview
3. Click: "🖨️ Print Voucher"

**Expected Result:**
```
✓ Print dialog appears
✓ Preview shows:
  - Combined Invoice header
  - Parent name
  - Month/Year
  - All children listed with amounts
  - Combined total
  - Any discounts applied
  - Professional formatting
✓ Print successful
```

**Status:** ✅ PASS / ❌ FAIL

---

### Test 3.10: Single-Child Family Works Same Way
**Purpose:** Verify system works for families with only 1 child

**Steps:**
1. Select: Ali Khan (single-child family)
2. Load data
3. Record payment
4. Print voucher

**Expected Result:**
```
✓ All steps work identically
✓ Invoices table shows 1 child only
✓ Totals are correct
✓ Payment processes
✓ Voucher prints single child
```

**Status:** ✅ PASS / ❌ FAIL

---

## 🧪 TEST SUITE 4: STUDENT FORM (Optional - Step 5)

### Test 4.1: Parent Selector Shows in Form
**Purpose:** Verify parent selector integrated into student form

**Steps:**
1. Go to: Students → Add Student
2. Scroll to parent section

**Expected Result:**
```
✓ Radio buttons visible:
  - "Select existing parent" (default selected)
  - "Create new parent"
✓ Parent dropdown visible (select existing)
```

**Status:** ✅ PASS / ❌ FAIL

---

### Test 4.2: Add Student with Existing Parent
**Purpose:** Verify can link to existing parent

**Steps:**
1. Radio: "Select existing parent" (default)
2. Click dropdown
3. Select: "Muhammad Ahmed"
4. Fill in student details
5. Click: Save

**Expected Result:**
```
✓ Student created
✓ Linked to existing parent (parent_id = 1)
✓ No new parent created
✓ Can immediately see in manual collection
```

**Status:** ✅ PASS / ❌ FAIL

---

### Test 4.3: Add Student with New Parent
**Purpose:** Verify can create new parent while adding student

**Steps:**
1. Radio: "Create new parent" (click this)
2. Form expands with parent fields
3. Fill in:
   - Parent Name: "Ali Hassan"
   - Email: "ali@example.com"
   - Phone: "0312-345-6789"
4. Fill in student details
5. Click: Save

**Expected Result:**
```
✓ Student created
✓ New parent created
✓ Student linked to new parent
✓ Parent appears in parents table
✓ Immediately available in manual collection
```

**Status:** ✅ PASS / ❌ FAIL

---

## 🧪 TEST SUITE 5: EDGE CASES & ERROR HANDLING

### Test 5.1: Overpayment Handling
**Purpose:** Verify overpayment goes to advance balance

**Setup:** Create a family owing PKR 10,000

**Steps:**
1. Select student
2. Enter Amount: `12000` (PKR 2,000 over)
3. Record payment

**Expected Result:**
```
✓ Invoice marked Paid
✓ Student advance_balance increased by 2,000
✓ Next month, balance automatically credits
```

**Status:** ✅ PASS / ❌ FAIL

---

### Test 5.2: Partial Payment
**Purpose:** Verify partial payments work correctly

**Setup:** Student owes PKR 20,000

**Steps:**
1. Enter Amount: `5000` (25% of total)
2. Record payment

**Expected Result:**
```
✓ Invoice status = "Partially Paid"
✓ amount_paid = 5000
✓ outstanding_due = 15000
✓ Can make additional payments
```

**Status:** ✅ PASS / ❌ FAIL

---

### Test 5.3: Zero Payment (Should Fail)
**Purpose:** Verify system rejects zero/negative payments

**Steps:**
1. Enter Amount: `0`
2. Click Record Payment

**Expected Result:**
```
✓ Error message appears
✓ Payment NOT recorded
✓ No voucher created
```

**Status:** ✅ PASS / ❌ FAIL

---

### Test 5.4: Invalid Amount (Text)
**Purpose:** Verify form validation

**Steps:**
1. Click Amount field
2. Type: "abc"
3. Try to Record Payment

**Expected Result:**
```
✓ Either: Field prevents text entry
✓ Or: Error message shown
✓ Payment NOT recorded
```

**Status:** ✅ PASS / ❌ FAIL

---

### Test 5.5: No Invoices for Month
**Purpose:** Verify graceful handling when no invoices exist

**Steps:**
1. Select student
2. Use month navigation to go to month with no invoices
3. Try to Record Payment

**Expected Result:**
```
✓ Invoices table shows: "No invoices for this month"
✓ Error message if trying to pay: "No invoices found"
✓ System doesn't crash
```

**Status:** ✅ PASS / ❌ FAIL

---

## 📊 TEST SUITE 6: DATABASE INTEGRITY

### Test 6.1: Foreign Key Relationships
**Purpose:** Verify foreign keys working correctly

**Steps:**
1. Run query: `SELECT * FROM invoices WHERE combined_voucher_id IS NULL AND parent_id IS NOT NULL;`

**Expected Result:**
```
✓ Most invoices have combined_voucher_id
✓ Very few (if any) without link
✓ No orphaned records
```

**Status:** ✅ PASS / ❌ FAIL

---

### Test 6.2: Combined Voucher Totals Match Invoices
**Purpose:** Verify combined voucher totals are accurate

**Steps:**
```sql
SELECT 
    cv.id,
    cv.total_amount,
    (SELECT SUM(total_amount) FROM invoices 
     WHERE combined_voucher_id = cv.id) as calculated_total
FROM combined_vouchers cv
LIMIT 5;
```

**Expected Result:**
```
✓ cv.total_amount matches calculated_total for all rows
✓ No discrepancies
```

**Status:** ✅ PASS / ❌ FAIL

---

### Test 6.3: Payment Records Link Correctly
**Purpose:** Verify payment→voucher→combined_voucher chain

**Steps:**
1. Select a family with payment
2. Check database: `SELECT * FROM payments WHERE parent_id = X LIMIT 1;`
3. Get payment_id
4. Check: `SELECT * FROM vouchers WHERE payment_id = X;`
5. Check: `SELECT * FROM combined_vouchers WHERE id = (voucher.combined_voucher_id);`

**Expected Result:**
```
✓ Payment → Voucher → Combined Voucher chain complete
✓ All IDs link correctly
✓ No broken links
```

**Status:** ✅ PASS / ❌ FAIL

---

## 📋 TEST SUITE 7: USER ACCEPTANCE TEST

### UAT 7.1: Full Workflow - Accountant Perspective
**Purpose:** Real-world scenario test

**Scenario:**
*"Accountant Muhammad is collecting fees. Parent Ahmad comes to pay for 2 kids"*

**Workflow:**
1. Open manual collection
2. Select: Ahmad's student
3. See: Both kids + combined total
4. Ahmad says: "I'm paying PKR 8,000"
5. Enter payment amount
6. Ahmad also mentions: "I have 3 kids in school for 5 years"
7. Apply loyalty discount: PKR 500
8. Record payment
9. Print voucher
10. Give to Ahmad

**Expected Result:**
```
✓ All steps complete without errors
✓ Voucher shows:
  - Both children
  - Combined total
  - Discount applied
  - Clean, professional appearance
✓ Ahmad happy with single voucher (not 2 separate)
✓ System handles complex scenario smoothly
```

**Status:** ✅ PASS / ❌ FAIL

---

## 📊 TEST RESULTS SUMMARY

After completing all test suites, fill in this summary:

```
TEST SUITE                          STATUS          NOTES
─────────────────────────────────────────────────────────────
1. Database Migration               ✓/✗            ________
2. Helper Functions                 ✓/✗            ________
3. Manual Collection Page           ✓/✗            ________
4. Student Form (Optional)          ✓/✗            ________
5. Edge Cases                       ✓/✗            ________
6. Database Integrity               ✓/✗            ________
7. User Acceptance                  ✓/✗            ________

OVERALL STATUS:                     ✓ READY / ✗ ISSUES
```

---

## 🚫 KNOWN ISSUES & WORKAROUNDS

### Issue: Month dropdown shows no months
**Cause:** Student has no invoices  
**Workaround:** Verify invoices generated for test family

### Issue: Print doesn't work
**Cause:** Browser popup blocker  
**Workaround:** Allow popups or use Ctrl+P

### Issue: Discount amount shows 0 after entering
**Cause:** JavaScript not running  
**Workaround:** Refresh page, try again

---

## ✅ SIGN-OFF

Once all tests pass, document sign-off:

```
Tested By: _______________________
Date: _____________________________
Environment: Test / Staging / Production
Result: ✓ PASSED / ✗ FAILED

Issues Found: ______________________
_____________________________________
_____________________________________

Approved by: ________________________
Manager/Director Sign-off
```

---

**System:** Feenion Enhanced Manual Collection v1.0  
**Test Version:** 1.0  
**Last Updated:** September 7, 2026
