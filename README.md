# Smart Attendance & Learning Insights System

## Gmail OTP verification

Registration now requires email verification before a row is created in `users`:

1. The user submits the registration form.
2. A randomly generated 6-digit code is sent through Gmail SMTP.
3. The user verifies the code (10-minute expiry, maximum 5 attempts).
4. The account is created only after successful verification.

The registration OTP and pending account data are stored in `registration_otps`.

Password reset also uses Gmail SMTP:

The login page now includes an OTP-based password reset flow:

1. The user enters their registered email at `forgot_password.php`.
2. A randomly generated 6-digit code is sent through Gmail SMTP.
3. The user verifies the code (10-minute expiry, maximum 5 attempts).
4. The user sets a new password. The OTP is hashed in the database and marked used after reset.

### Installation

1. Install dependencies:

   ```bash
   composer install
   ```

2. Copy `.env.example` to `.env` (or export the same variables in the web server environment) and fill in the Gmail settings:

   ```dotenv
   SMTP_HOST=smtp.gmail.com
   SMTP_PORT=587
   SMTP_USERNAME=your-account@gmail.com
   SMTP_PASSWORD=your-google-app-password
   SMTP_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=your-account@gmail.com
   MAIL_FROM_NAME=Smart Attendance System
   ```

   Gmail requires **2-Step Verification** and a Google **App Password**. Use the 16-character App Password as `SMTP_PASSWORD`; do not use the normal Gmail password.

3. Run the migrations against `smart_attendance_system`:

   ```bash
   mysql -u root -p smart_attendance_system < database/password_reset_otps.sql
   mysql -u root -p smart_attendance_system < database/registration_otps.sql
   ```

   The application also attempts to create either table automatically when its respective flow is used.

4. Make sure the PHP process can read the environment variables and that `vendor/autoload.php` exists.

For production, use HTTPS, keep `.env` outside version control, and configure a real application URL in `APP_URL` if reset links are later added.
