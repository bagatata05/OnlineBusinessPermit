# Online Business Permit and Licensing System

A comprehensive web-based system for managing business permit applications, renewals, and tracking with email notifications.

## Features

### Core Functionality
- **Business Registration**: Register new businesses with complete information
- **Permit Applications**: Apply for new, renewal, or amendment permits
- **Real-time Tracking**: Track application status online
- **Email Notifications**: Automated email updates at each application stage
- **Role-based Access**: Admin, Staff, and Applicant roles with appropriate permissions
- **Dashboard Analytics**: Charts and statistics for system monitoring

### User Roles
- **Applicant**: Register businesses, apply for permits, track applications
- **Staff**: Review applications, update status, manage requirements
- **Admin**: Full system access, user management, system settings

### Technical Features
- **Responsive Design**: Mobile-first approach with desktop support
- **AJAX Integration**: Dynamic updates without page reloads
- **Data Export**: CSV export functionality for reports
- **Search & Filter**: Advanced search with pagination
- **Security**: Password hashing, session management, input validation
- **Audit Logging**: Complete activity tracking

## Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- cURL extension enabled

### Setup Instructions

1. **Clone/Download the Project**
   ```bash
   # Extract to your web server directory
   # For XAMPP: C:/xampp/htdocs/OnlineBusinessPermit/
   ```

2. **Database Setup**
   ```bash
   # Import the database schema from db folder
   mysql -u root -p business_permit_system < db/database.sql
   ```

3. **Configuration**
   - Edit `config.php` and update database credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'business_permit_system');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

5. **File Permissions**
   ```bash
   # Make uploads directory writable
   chmod 755 uploads/
   ```

6. **Access the System**
   - Open browser: `http://localhost/OnlineBusinessPermit`
   - Default admin login: `admin` / `password`

## System Structure

```
OnlineBusinessPermit/
├── api/                    # AJAX endpoints
│   ├── export_csv.php
│   ├── get_dashboard_stats.php
│   ├── get_permit_details.php
│   ├── logout.php
│   ├── update_permit_status.php
│   └── update_requirement.php
├── assets/                 # Static assets
│   ├── css/
│   │   └── style.css      # Main stylesheet
│   └── js/
│       └── main.js        # JavaScript functions
├── db/                     # Database files
│   ├── database.sql       # Database schema
│   └── MIGRATION_SCRIPT.sql  # Migration scripts
├── docs/                   # Documentation
│   ├── IMPLEMENTATION_SUMMARY.md
│   ├── QUICK_REFERENCE.md
│   └── CLEANUP_SUMMARY.md
├── includes/               # PHP classes and utilities
│   ├── auth.php           # Authentication system
│   ├── permit.php         # Permit management
│   ├── email.php          # Email notifications
│   └── address.php        # Address management
├── pages/                  # Page templates
│   ├── 404.php
│   ├── admin.php
│   ├── business-registration.php
│   ├── dashboard.php
│   ├── login.php
│   ├── permit-application.php
│   ├── profile.php
│   ├── register.php
│   ├── renewals.php
│   └── tracking.php
├── uploads/                # File uploads directory
├── config.php             # Configuration file (not in repo)
├── index.php              # Main router
├── README.md              # Main documentation
└── .gitignore             # Git ignore file
```

## Database Schema

The system uses the following main tables:

- **users**: User accounts and authentication
- **businesses**: Registered business information
- **permits**: Permit applications and status
- **permit_requirements**: Document requirements tracking
- **payments**: Payment records and status
- **renewals**: Permit renewal applications
- **notification_logs**: Email notification history
- **audit_logs**: System activity logging
- **system_settings**: Configuration settings

## API Endpoints

### Authentication
- `POST api/logout.php` - User logout

### Permit Management
- `GET api/get_permit_details.php?permit_id={id}` - Get permit details
- `POST api/update_permit_status.php` - Update permit status
- `POST api/update_requirement.php` - Update requirement status

### Dashboard & Reports
- `GET api/get_dashboard_stats.php` - Get dashboard statistics
- `GET api/export_csv.php` - Export data to CSV

## Email Notifications

The system integrates with email for user notifications:

### Supported Message Types
- Application submitted confirmation
- Status updates (under review, approved, rejected)
- Permit release notification
- Renewal reminders
- Payment confirmations

### Configuration
Email notifications use PHP mail() by default. For production environments, configure SMTP settings in `config.php`:
- `SMTP_HOST` - SMTP server address
- `SMTP_PORT` - SMTP port (usually 587)
- `SMTP_USER` - SMTP username
- `SMTP_PASS` - SMTP password

## Security Features

- **Password Hashing**: Uses PHP's `password_hash()` with bcrypt
- **Session Management**: Secure session handling with timeout
- **Input Validation**: Server-side validation for all inputs
- **SQL Injection Prevention**: Prepared statements for all queries
- **XSS Protection**: Output escaping and input sanitization
- **Role-based Access Control**: Permission checks on all protected pages

## Customization

### Adding New Permit Types
1. Update the permit type dropdown in application forms
2. Modify `Permit::getDefaultRequirements()` method
3. Email notifications are sent automatically by the email system

### Styling
- Modify `assets/css/style.css` for visual changes
- CSS variables are defined at the top for easy theming

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Check database credentials in `config.php`
   - Ensure MySQL server is running
   - Verify database exists and schema is imported

2. **Email Not Sending**
   - Verify SMTP settings in `config.php` (if using SMTP)
   - Check email address is valid
   - Review server error logs for email errors
   - Ensure `mail()` function is enabled on server

3. **File Upload Issues**
   - Ensure `uploads/` directory is writable
   - Check PHP upload limits in `php.ini`
   - Verify file size restrictions

4. **Session Issues**
   - Check PHP session configuration
   - Ensure server time is correct
   - Clear browser cookies if needed

### Error Logging
- PHP errors are displayed in development mode
- Check web server error logs for detailed information
- Audit logs are stored in `audit_logs` table

## Support

For technical support or questions:
1. Check this README file
2. Review the code comments
3. Check the database schema in `database.sql`
4. Test with the default admin account

## License

This project is provided as-is for educational and development purposes.

## Version History

- **v2.0.0**: Email notifications upgrade & code cleanup
  - Replaced SMS with email notifications
  - Removed unused code and dependencies
  - Reorganized folder structure (db/, docs/)
  - Added comprehensive documentation
  - Enhanced security and performance

- **v1.0.0**: Initial release with core functionality
  - User authentication and role management
  - Business registration and permit applications
  - Admin dashboard with analytics
  - Real-time tracking system
