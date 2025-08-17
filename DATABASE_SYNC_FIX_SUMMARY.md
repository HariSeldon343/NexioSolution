# Database Synchronization Fix Summary

## Date: 2025-08-12

## Issue Resolved
Fixed database synchronization issue where deleted companies were showing incorrect status in the database.

## Problem Analysis
The system uses **soft delete** mechanism for companies:
- Companies are NOT physically deleted from database
- Instead, their `stato` (status) column is updated to `'cancellata'` (deleted)
- UI only shows companies where `stato = 'attiva'` (active)

## Database Status Before Fix
| ID | Company Name | Status | Should Be |
|----|-------------|--------|-----------|
| 5 | Sud Marmi | cancellata | attiva |
| 6 | MedTec | attiva | attiva ✓ |
| 7 | Test 1 | cancellata | cancellata ✓ |
| 8 | Romolo Hospital | attiva | cancellata |
| 9 | S.Co Solution Consulting | attiva | attiva ✓ |

## Database Status After Fix
| ID | Company Name | Status | Visible in UI |
|----|-------------|--------|---------------|
| 5 | Sud Marmi | attiva | ✓ Yes |
| 6 | MedTec | attiva | ✓ Yes |
| 7 | Test 1 | cancellata | ✗ No |
| 8 | Romolo Hospital | cancellata | ✗ No |
| 9 | S.Co Solution Consulting | attiva | ✓ Yes |

## Solution Applied
```sql
-- Fix Sud Marmi - should be active
UPDATE aziende SET stato = 'attiva' WHERE id = 5;

-- Fix Romolo Hospital - should be deleted
UPDATE aziende SET stato = 'cancellata' WHERE id = 8;
```

## How the System Works

### 1. Soft Delete Mechanism
- **Location**: `/backend/functions/aziende-functions.php::deleteAzienda()`
- **Process**:
  1. Updates `stato` to `'cancellata'`
  2. Sets `data_cancellazione` to current timestamp (if column exists)
  3. Deactivates all related users in `utenti_aziende` table
  4. Clears session data if deleted company was active

### 2. Display Logic
- **Location**: `/aziende.php` (lines 472-474)
- **Query**: `SELECT * FROM aziende WHERE stato = 'attiva' ORDER BY nome`
- Only shows companies with `stato = 'attiva'`

### 3. Status Values
- `'attiva'` - Active company (visible in UI)
- `'cancellata'` - Deleted company (hidden from UI)
- `'sospesa'` - Suspended company (optional status)

## File Structure
```
/piattaforma-collaborativa/
├── aziende.php                              # Main company management page
├── backend/
│   ├── functions/
│   │   └── aziende-functions.php           # Core company functions
│   └── api/
│       └── switch-azienda.php              # Company switching API
└── database/
    └── fix-company-soft-delete.sql         # SQL script to fix status
```

## Verification Commands
```bash
# Check all companies and their status
/mnt/c/xampp/mysql/bin/mysql.exe -u root nexiosol -e "SELECT id, nome, stato FROM aziende ORDER BY id;"

# Check only active companies (what UI shows)
/mnt/c/xampp/mysql/bin/mysql.exe -u root nexiosol -e "SELECT id, nome FROM aziende WHERE stato = 'attiva' ORDER BY nome;"

# Count by status
/mnt/c/xampp/mysql/bin/mysql.exe -u root nexiosol -e "SELECT stato, COUNT(*) as total FROM aziende GROUP BY stato;"
```

## Prevention Measures
1. **UI and Database are now synchronized** - 3 active companies shown in both
2. **Soft delete is working correctly** - deleted companies marked as `'cancellata'`
3. **Documentation created** - SQL script saved for future reference

## Important Notes
- Companies are NEVER physically deleted to maintain referential integrity
- Deleted companies can be restored by updating `stato` back to `'attiva'`
- Related users are deactivated when company is deleted
- To permanently delete: `DELETE FROM aziende WHERE id = X` (may cause FK errors)

## Testing the Deletion
To test deletion works properly:
1. Navigate to `http://localhost/piattaforma-collaborativa/aziende.php`
2. Click on a company to view details
3. Click "Elimina Azienda" button (only visible to super_admin)
4. Confirm deletion in popup
5. Company will be soft-deleted (stato = 'cancellata')
6. Company will disappear from list but remain in database