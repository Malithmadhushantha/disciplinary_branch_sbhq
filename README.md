# විනය ශාඛාව - ශ්‍රී ලංකා පොලිස් මූලස්ථානය
## Disciplinary Branch Database Management System

### පද්ධතියේ විස්තරය | System Description

මෙම පද්ධතිය ශ්‍රී ලංකා පොලිස් මූලස්ථානයේ විනය ශාඛාව සඳහා නිර්මාණය කර ඇති සම්පූර්ණ වෙබ් පදනම් කළ දත්ත සමුදාය කළමනාකරණ පද්ධතියකි. මෙය PHP + MySQL භාවිතයෙන් XAMPP මත ක්‍රියාත්මක වන අතර සිංහල අක්ෂර සහයෝගයෙන් යුක්තව නිර්මාණය කර ඇත.

This is a comprehensive web-based database management system designed for the Disciplinary Branch of Sri Lanka Police Headquarters. Built with PHP + MySQL on XAMPP with full Sinhala character support.

### සුවිශේෂ ලක්ෂණ | Key Features

- 🔐 **ආරක්ෂිත පිවිසුම් පද්ධතිය** - Secure login system
- 📋 **මූලික විමර්ශන කළමනාකරණය** - Preliminary investigation management
- 📄 **චෝදනා පත්‍ර කළමනාකරණය** - Charge sheet management
- ⚖️ **විධිමත් විනය පරීක්ෂණ** - Formal disciplinary investigations
- 🔍 **නිලධාරි සෙවුම් පද්ධතිය** - Officer search functionality
- 📊 **සම්පූර්ණ වාර්තා සහ සාරාංශ** - Complete reports and summaries
- 🎨 **Bootstrap සමඟ නවීන UI/UX** - Modern UI/UX with Bootstrap
- 🖨️ **මුද්‍රණ සහයෝගය** - Print support
- 📱 **ප්‍රතිචාරාත්මක සැලසුම** - Responsive design

### පද්ධති අවශ්‍යතා | System Requirements

- **XAMPP** (Apache + MySQL + PHP 7.4+)
- **වෙබ් බ්‍රවුසරය** - Chrome, Firefox, Safari, Edge
- **සිංහල අකුරු සහය** - Noto Sans Sinhala Font (auto-loaded)

### ස්ථාපනය | Installation

#### 1. XAMPP Setup
```bash
# XAMPP Download and Install
# Windows: https://www.apachefriends.org/download.html
# Start Apache and MySQL services
```

#### 2. Project Setup
```bash
# Copy all files to XAMPP htdocs directory
C:\xampp\htdocs\disciplinary_branch\

# Or create a new folder
mkdir C:\xampp\htdocs\disciplinary_branch
# Copy all PHP files to this directory
```

#### 3. Database Setup
```sql
-- Open phpMyAdmin (http://localhost/phpmyadmin)
-- Import the database file: disciplinary_branch.sql
-- Or run the SQL commands from database_structure.sql
```

#### 4. Configuration
```php
// Edit config.php if needed
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'disciplinary_branch_sbhq');
```

### පළමු වරට භාවිතය | First Time Usage

#### 1. පද්ධතියට ප්‍රවේශය | System Access
```
URL: http://localhost/disciplinary_branch/
```

#### 2. පෙරනිර්ධාරිත පරිශීලක ගිණුම | Default Admin Account
```
Email: admin@sbhq.lk
Password: password123
```

#### 3. ප්‍රථම පියවර | First Steps
1. පද්ධතියට පිවිසෙන්න
2. නව පරිශීලකයින් ලියාපදිංචි කරන්න
3. ක්‍රියාමාර්ග ඇතුළත් කරන්න (Actions)
4. මූලික විමර්ශන ඇරඹෙන්න

### ගොනු ව්‍යුහය | File Structure

```
disciplinary_branch/
├── config.php                              # Database configuration
├── index.php                               # Main dashboard
├── login.php                               # Login page
├── register.php                            # Registration page
├── logout.php                              # Logout functionality
├── preliminary_investigation.php           # Investigation management
├── add_new_pi.php                         # Add new investigation
├── update_preliminary_investigation.php    # Update investigation
├── including_actions_taken.php            # Manage actions
├── charge_sheets.php                      # Charge sheet management
├── formal_disciplinary_investigation.php  # Formal investigations
├── search_officer_status.php              # Officer search
├── summary.php                            # Reports and summary
└── disciplinary_branch.sql                # Database structure
```

### දත්ත සමුදාය ව්‍යුහය | Database Structure

#### මූලික වගු | Main Tables

1. **users** - පරිශීලක ගිණුම්
2. **preliminary_investigations** - මූලික විමර්ශන
3. **actions_taken** - ගත් ක්‍රියාමාර්ග
4. **charge_sheets** - චෝදනා පත්‍ර
5. **formal_investigations** - විධිමත් විමර්ශන

### භාවිතා මාර්ගෝපදේශ | Usage Guide

#### 1. මූලික විමර්ශනයක් ඇරඹීම
1. "මූලික විමර්ශන" වෙත යන්න
2. "නව මූලික විමර්ශනයක් එක් කරන්න" ක්ලික් කරන්න
3. සියලු අනිවාර්ය තොරතුරු ඇතුළත් කරන්න
4. සුරකින්න

#### 2. චෝදනා පත්‍රයක් නිකුත් කිරීම
1. මුලින්ම මූලික විමර්ශනයේ ක්‍රියාමාර්ගය "චෝදනා පත්‍ර නිකුත් කිරීම" ලෙස සකසන්න
2. "චෝදනා පත්‍ර" පිටුවට යන්න
3. සුදුසු විමර්ශනය තෝරන්න
4. චෝදනා පත්‍ර විස්තර ඇතුළත් කරන්න

#### 3. නිලධාරියෙකු සෙවීම
1. "නිලධාරි තත්ත්වය සොයන්න" වෙත යන්න
2. නම, නිල අංකය, හෝ NIC අංකය ඇතුළත් කරන්න
3. සෙවුම් ප්‍රතිඵල පරීක්ෂා කරන්න
4. අවශ්‍ය නම් මුද්‍රණය කරන්න

### ආරක්ෂණ ලක්ෂණ | Security Features

- 🔑 **Password Hashing** - BCrypt encryption
- 🛡️ **SQL Injection Protection** - Prepared statements
- 🔒 **Session Management** - Secure session handling
- 🚫 **XSS Protection** - Input sanitization
- 👤 **User Authentication** - Role-based access

### අභිරුචිකරණය | Customization

#### නව ක්‍රියාමාර්ගයක් එක් කිරීම
```sql
INSERT INTO actions_taken (action_name, action_code) 
VALUES ('නව ක්‍රියාමාර්ගය', 'new_action');
```

#### UI Themes වෙනස් කිරීම
CSS variables භාවිතයෙන් වර්ණ පැලට වෙනස් කළ හැක:
```css
:root {
    --primary-color: #3498db;
    --secondary-color: #2c3e50;
    --success-color: #27ae60;
}
```

### දෝෂ නිරාකරණය | Troubleshooting

#### සිංහල අක්ෂර නොපෙන්වන්නේ නම්
1. Database charset: `utf8mb4_unicode_ci`
2. MySQL configuration: `character-set-server=utf8mb4`
3. Browser encoding: UTF-8

#### Database Connection Error
1. XAMPP MySQL service running කර තිබේද පරීක්ෂා කරන්න
2. config.php හි credentials නිවැරදිද බලන්න
3. Database created කර තිබේද බලන්න

### සහයෝගය | Support

```
Database Administrator: PC 97204 DKP SENEWIRATHNA
Developer: PC 93037 SMM Madhushantha
Organization: Sri Lanka Police Headquarters
```

### බලපත්‍රය | License

This system is developed specifically for Sri Lanka Police Disciplinary Branch.
Internal use only - Not for public distribution.

### අනුවර්තන | Updates

Version 1.0 - January 2025
- Initial release with core functionality
- Sinhala language support
- Bootstrap UI implementation
- Complete CRUD operations
- Reporting system

---

**⚠️ වැදගත්:** මෙම පද්ධතිය භාවිතා කිරීමට පෙර regular database backups ගන්න.

**⚠️ Important:** Take regular database backups before using this system in production.
