STUDENT DATABASE SYSTEM - INSTALLATION INSTRUCTIONS
===================================================

Since XAMPP is installed, follow these steps to get the system running:

STEP 1: DEPLOY FILES
--------------------
1.  Locate your XAMPP installation folder (usually C:\xampp).
2.  Open the 'htdocs' folder inside it.
3.  Create a new folder named 'student-db-system'.
4.  Copy ALL files from this project folder:
    (C:\Users\paulo\.gemini\antigravity\scratch\student-db-system)
    ...paste them into your new 'student-db-system' folder in htdocs.

STEP 2: START SERVERS
---------------------
1.  Open the XAMPP Control Panel.
2.  Click 'Start' next to **Apache**.
3.  Click 'Start' next to **MySQL**.

STEP 3: SETUP DATABASE
----------------------
1.  Open your browser and go to: http://localhost/phpmyadmin
2.  Click "New" to create a database.
3.  Database Name: student_db
4.  Click "Create".
5.  Click the "Import" tab at the top.
6.  Click "Choose File" and select the 'student_db.sql' file from your project folder.
7.  Click "Import" (or "Go") at the bottom.

STEP 4: RUN THE APP
-------------------
1.  Open your browser to: http://localhost/student-db-system
2.  Login with:
    Username: admin
    Password: password123

TROUBLESHOOTING
---------------
- If you have a password set for your root MySQL user, edit 'config.php' and update the DB_PASS constant.
