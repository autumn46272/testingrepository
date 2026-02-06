# Student Database Management System

A comprehensive web-based student database management system designed for tracking NCLEX candidates, their academic progress, SCORM training packages, and examination details.

## 🚀 Quick Start

1. Deploy files to `xampp/htdocs/student-db-system1`
2. Start Apache and MySQL servers
3. Import the database (see README_SETUP.txt for details)
4. Access via `http://localhost/student-db-system1`

---

## 📁 File Structure & Functions

### **Core Files**

#### `index.php`
- **Login page** for the system
- Handles user authentication with username/password
- Redirects authenticated users to dashboard
- Session management and security checks

#### `config.php`
- Database configuration file
- Contains PDO connection settings (host, database name, credentials)
- Defines important constants (SCORM paths, file size limits)

#### `functions.php`
- **Central utility functions** library
- Key functions include:
  - `clean_input()` - Sanitizes user input
  - `generate_student_id()` - Creates unique student IDs (YYYY-XXXX format)
  - `generate_branch_student_id()` - Creates branch-specific IDs (RAPH-XXX or RAUS-XXX)
  - `create_user_for_student()` - Automatically creates user accounts for new students
  - Database helpers: `db_query()`, `db_fetch()`, `db_insert()`
  - Flash message system: `set_flash()`, `get_flash()`
  - Activity logging: `log_activity()`

#### `auth_check.php`
- **Authentication guard** for protected pages
- Ensures users are logged in before accessing restricted content
- Redirects unauthenticated users to login page

#### `logout.php`
- Destroys user session and logs out the user
- Redirects to login page

---

### **Dashboard & Main Pages**

#### `dashboard.php`
- **Main dashboard** after login
- Displays key statistics:
  - Total students count
  - Active students count
  - Average academic scores
  - Attendance rates
- Shows recent student activities and academic records

#### `student_dashboard.php`
- Student-specific dashboard view
- Displays personalized information for individual students

---

### **Student/Candidate Management**

#### `students.php`
- **Main candidates management page**
- Features:
  - View all students/candidates in a table
  - Search and filter by groups/batches
  - Add new candidates with detailed forms
  - Edit existing candidate information
  - Delete candidates
  - Automatic user account creation for new candidates
- Handles extensive student data:
  - Personal info (name, email, birthdate, gender, contact)
  - Branch assignment (US or Philippines)
  - Location (city, BON/State)
  - Academic details (school, exam type, exam status, exam date)
  - Application status (CGFNS, BON, PEARSON VUE)
  - Emergency contacts
  - Group/batch assignments
  - Profile images

#### `student_add.php`
- Dedicated page for adding new students
- Extended form with all student fields
- Image upload functionality

#### `student_edit.php`
- Edit existing student information
- Pre-fills form with current student data
- Updates student records in database

#### `student_view.php`
- **Detailed student profile view**
- Displays complete student information
- Shows academic history and progress
- Training package enrollment status

---

### **Groups/Batches Management**

#### `groups.php`
- Manage student groups/batches
- Create, edit, and delete groups
- Assign students to multiple groups
- Track group-based cohorts

#### `group_view.php`
- View detailed information about a specific group
- Lists all students in the group
- Group-specific statistics

---

### **Academic Management**

#### `academic.php`
- **Academic records management**
- Track student academic performance
- Record scores, grades, and assessments
- View student academic history

#### `academic_batch_add.php`
- Batch add multiple academic records
- Efficient data entry for group assessments
- Bulk grade input capabilities

---

### **SCORM Training System**

#### `scorm_packages.php`
- **View all SCORM training packages**
- Lists uploaded iSpring SCORM courses
- Manage package assignments to students
- Track student enrollment

#### `scorm_upload.php`
- **Upload new SCORM packages**
- Accepts ZIP files (SCORM 1.2 format)
- Automatically extracts and stores packages
- Creates database entries with title and description
- Maximum file size: 50MB
- Supports iSpring exported packages

#### `scorm_player.php`
- **SCORM content player**
- Launches SCORM packages for students
- Loads the SCORM manifest
- Tracks student interactions and progress

#### `scorm_handler.php`
- Backend handler for SCORM API communication
- Processes SCORM tracking data
- Saves student progress and scores
- Manages SCORM session data

---

### **Reporting & Analytics**

#### `reports.php`
- Generate system reports
- Student progress reports
- Exam statistics
- Group performance analytics

#### `attendance_sheet.php`
- Manage student attendance records
- Generate attendance sheets for groups
- Track attendance patterns

---

### **User Management**

#### `users.php`
- Manage system users (admin, instructors, students)
- User roles and permissions
- Create, edit, delete user accounts
- Password management

#### `my_profile.php`
- User profile management page
- Update personal information
- Change password

#### `my_training.php`
- View assigned training packages
- Access SCORM courses
- Track personal training progress

---

### **Database Schema Management**

These files handle database migrations and schema updates:

#### `reset_and_migrate.php`
- Database reset and migration utility
- WARNING: Resets database to clean state

#### `update_schema_v2.php`
- Schema migration version 2
- Updates database structure

#### `update_schema_v5.php`
- Schema migration version 5
- Latest structural changes

#### `update_schema_m2m.php`
- Many-to-many relationship migration
- Updates student-group relationships

#### `update_schema_programs.php`
- Program/batch schema updates
- Academic program structure changes

---

### **Includes Directory**

#### `includes/header.php`
- HTML header template
- CSS imports
- Navigation bar
- Meta tags and page title

#### `includes/footer.php`
- HTML footer template
- JavaScript imports
- Closing HTML tags

#### `includes/sidebar.php`
- Navigation sidebar menu
- Links to all main features
- User information display

---

### **Assets Directory**

#### `assets/css/style.css`
- Main stylesheet
- Design system with CSS variables
- Component styles (cards, buttons, tables)
- Responsive layouts

#### `assets/js/script.js`
- Client-side JavaScript
- Toast notifications
- Form validation
- Interactive UI components

#### `assets/img/`
- System images and logos
- RALogo.png - Main logo

---

## 🎯 Key Features

- ✅ **Student/Candidate Management** - Complete CRUD for NCLEX candidates
- ✅ **Branch Support** - US and Philippines branch tracking
- ✅ **Automatic User Creation** - Auto-generates login credentials for new students
- ✅ **SCORM Integration** - Upload and track iSpring training packages
- ✅ **Group/Batch Management** - Organize students into cohorts
- ✅ **Academic Tracking** - Record scores, exams, and progress
- ✅ **Exam Status Tracking** - NCLEX application progress (CGFNS, BON, PEARSON VUE)
- ✅ **Attendance Management** - Track student attendance
- ✅ **Reporting System** - Generate comprehensive reports
- ✅ **User Roles** - Admin, instructors, and student access levels

---

## 🛠️ Technical Stack

- **Backend**: PHP 7.4+ with PDO
- **Database**: MySQL
- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **Server**: Apache (XAMPP)
- **SCORM**: SCORM 1.2 compliant (iSpring)

---

## 📋 Setup Instructions

See `README_SETUP.txt` for detailed installation instructions.

---

## 🔐 Default Login

- **Username**: admin
- **Password**: password123

---

## 📝 Notes

- Student IDs are auto-generated based on branch (RAPH-XXX for Philippines, RAUS-XXX for US)
- SCORM packages must be exported as SCORM 1.2 from iSpring
- Profile images are stored in the `uploads/` directory
- Activity logs track all major system actions

---

**Version**: 1.0  
**Last Updated**: February 2026
