ADMIN UI - quick install

1) Place this folder as: C:\xampp\htdocs\airline_project\admin\
2) Ensure db.php exists at C:\xampp\htdocs\airline_project\db.php (this project uses mysqli $conn)
3) Ensure users table has admin user with is_admin=1
   - Example SQL to set existing user as admin:
     UPDATE users SET is_admin = 1 WHERE email = 'admin@airline.com';

4) Access admin UI:
   http://localhost:8080/airline_project/admin/login.php

5) If DataTables or Chart.js CDN blocked, download locally or allow CDN.
6) To further extend: I can add AJAX CRUD modals, export CSV, or integrate login with your existing admin route.
