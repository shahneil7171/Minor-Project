# Quick Setup Guide - User Profile Feature

## Pre-requisites
- PHP 8.1+
- Laravel 11+
- Database (MySQL/PostgreSQL)
- Composer installed

## Installation Steps

### 1. Run Migrations
Execute the new migrations to add profile fields and create the addresses table:

```bash
php artisan migrate
```

**What this does:**
- Adds `phone`, `bio`, `profile_photo_path`, and `last_login` columns to users table
- Creates new `addresses` table with all required fields

### 2. Set Up File Storage
Create symbolic link for profile photo storage:

```bash
php artisan storage:link
```

Set proper permissions:
```bash
chmod -R 775 storage/app/public
chmod -R 775 bootstrap/cache
```

### 3. Clear Application Cache
```bash
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

### 4. Verify Installation

Access the profile routes:
- Dashboard: `http://localhost:8000/dashboard`
- Profile: `http://localhost:8000/profile`
- Addresses: `http://localhost:8000/profile/addresses`
- Change Password: `http://localhost:8000/profile/change-password`

## Features Checklist

✅ **Profile Photo Upload**
- Users can upload profile photos
- Photos stored in `storage/app/public/profile-photos/{user_id}/`
- Default Gravatar if no photo

✅ **Edit Personal Information**
- Full Name, Email, Phone Number
- Optional Bio field
- Profile edit page at `/profile/edit`

✅ **Address Management**
- Add/Edit/Delete addresses
- Set default shipping and billing addresses
- Pagination for multiple addresses
- Accessible at `/profile/addresses`

✅ **Change Password**
- Strong password validation
- Real-time strength indicator
- Current password verification
- Accessible at `/profile/change-password`

✅ **Dashboard Integration**
- Profile widget showing user info
- Quick links to profile sections
- Address count display
- Account creation date

✅ **Premium UI Design**
- Responsive Bootstrap 5 layout
- Gradient design matching brand
- Font Awesome icons
- Smooth animations

## File Structure Created

```
app/
├── Http/Controllers/
│   ├── ProfileController.php         ✓ New
│   └── AddressController.php         ✓ New
├── Http/Requests/
│   ├── UpdateProfileRequest.php      ✓ New
│   ├── StoreAddressRequest.php       ✓ New
│   ├── UpdateAddressRequest.php      ✓ New
│   └── ChangePasswordRequest.php     ✓ New
├── Models/
│   ├── User.php                      ✓ Modified
│   └── Address.php                   ✓ New
├── Policies/
│   └── AddressPolicy.php             ✓ New
└── Providers/
    └── AppServiceProvider.php        ✓ Modified

resources/views/
├── layouts/
│   └── app.blade.php                 ✓ New
├── profile/
│   ├── layout.blade.php              ✓ New
│   ├── index.blade.php               ✓ New
│   ├── edit.blade.php                ✓ New
│   ├── change-password.blade.php     ✓ New
│   └── addresses/
│       ├── index.blade.php           ✓ New
│       ├── create.blade.php          ✓ New
│       └── edit.blade.php            ✓ New
└── dashboard.blade.php               ✓ Modified

database/migrations/
├── 2024_01_01_000003_add_profile_fields_to_users_table.php    ✓ New
└── 2024_01_02_000000_create_addresses_table.php               ✓ New

routes/
└── web.php                           ✓ Modified
```

## Testing the Features

### Test 1: Profile Photo Upload
1. Go to `/profile/edit`
2. Select a JPEG/PNG/JPG/GIF image (max 2MB)
3. Save changes
4. Verify photo appears on profile and dashboard

### Test 2: Edit Profile Information
1. Go to `/profile/edit`
2. Update Name, Email, Phone, Bio
3. Save changes
4. Verify changes on `/profile`

### Test 3: Address Management
1. Go to `/profile/addresses`
2. Click "Add New Address"
3. Fill all required fields
4. Mark as default shipping/billing
5. Save and verify in list
6. Edit an address
7. Delete an address

### Test 4: Change Password
1. Go to `/profile/change-password`
2. Enter current password
3. Enter strong new password (must have uppercase, lowercase, number, special char)
4. Confirm new password
5. Save and try logging out and in with new password

### Test 5: Dashboard Widget
1. Go to `/dashboard`
2. Verify profile photo shows
3. Verify name, email, phone display
4. Verify address count shows
5. Verify member date shows
6. Click links to navigate to profile sections

## Configuration Options

### Modify File Upload Size
Edit `app/Http/Requests/UpdateProfileRequest.php`:
```php
'profile_photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:5120'],
// max:5120 = 5MB
```

### Modify Password Requirements
Edit `app/Http/Requests/ChangePasswordRequest.php`:
```php
'password' => ['required', 'string', 'min:12', ...], // Change min:8 to min:12
```

### Customize Colors
Edit `resources/views/layouts/app.blade.php`:
```css
--primary-color: #667eea;      /* Change primary color */
--secondary-color: #764ba2;     /* Change secondary color */
```

## Database Schema

### Users Table (Modified)
```sql
ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULLABLE;
ALTER TABLE users ADD COLUMN bio TEXT NULLABLE;
ALTER TABLE users ADD COLUMN profile_photo_path VARCHAR(255) NULLABLE;
ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULLABLE;
```

### Addresses Table (New)
```sql
CREATE TABLE addresses (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    house_number VARCHAR(50) NOT NULL,
    street_address VARCHAR(255) NOT NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100) NOT NULL,
    pincode VARCHAR(20) NOT NULL,
    country VARCHAR(100) NOT NULL,
    additional_info TEXT NULLABLE,
    is_default_shipping BOOLEAN DEFAULT FALSE,
    is_default_billing BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (user_id),
    INDEX (is_default_shipping),
    INDEX (is_default_billing)
);
```

## Troubleshooting

### Issue: Photos not uploading
**Solution:**
```bash
php artisan storage:link
chmod -R 775 storage/app/public
```

### Issue: Route not found
**Solution:**
```bash
php artisan route:clear
php artisan config:clear
```

### Issue: Permission denied on migration
**Solution:**
```bash
php artisan migrate --force
```

### Issue: CSRF token mismatch
**Solution:** Ensure forms include `@csrf` directive (already included in templates)

### Issue: Authorization errors
**Solution:** Verify `AppServiceProvider.php` has `Address` policy registered

## Performance Tips

1. **Enable Query Caching:**
   ```bash
   php artisan cache:clear
   php artisan optimize
   ```

2. **Profile Photos:** Consider image compression for uploads

3. **Lazy Load Relationships:**
   ```php
   $user->load('addresses', 'defaultShippingAddress');
   ```

## Security Notes

✓ All routes are protected with `auth` middleware
✓ Address operations authorized by policy
✓ Password validation requires strong credentials
✓ CSRF protection on all forms
✓ Input validation on all requests
✓ SQL injection prevention via ORM
✓ XSS protection via Blade templating

## Next Steps

1. Run migrations: `php artisan migrate`
2. Create storage link: `php artisan storage:link`
3. Clear cache: `php artisan config:clear`
4. Test features following test checklist
5. Review PROFILE_FEATURE_DOCUMENTATION.md for detailed info
6. Customize colors and settings as needed

## Support

For detailed information about:
- Feature implementation: See PROFILE_FEATURE_DOCUMENTATION.md
- Code structure: Check file comments
- API routes: Review routes/web.php
- Validation rules: Check app/Http/Requests/

---

**Status**: ✅ Production Ready
**Version**: 1.0
**Last Updated**: 2024
