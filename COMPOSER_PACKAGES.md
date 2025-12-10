# Required Composer Packages

Run these commands to install all required dependencies:

```bash
composer require spatie/laravel-permission
composer require firebase/php-jwt
composer require barryvdh/laravel-dompdf
composer require phpoffice/phpword
composer require smalot/pdfparser
```

## Package Details

### spatie/laravel-permission
- **Purpose**: Role-based permissions (Admin/User)
- **Version**: ^6.0
- **Usage**: User roles, permission checks

### firebase/php-jwt
- **Purpose**: JWT token verification
- **Version**: ^6.9
- **Usage**: Verify tokens from main platform

### barryvdh/laravel-dompdf
- **Purpose**: PDF report generation
- **Version**: ^2.0
- **Usage**: Generate PDF reports from chats

### phpoffice/phpword
- **Purpose**: DOCX file processing and generation
- **Version**: ^1.2
- **Usage**: Extract text from DOCX, generate DOCX reports

### smalot/pdfparser
- **Purpose**: PDF text extraction
- **Version**: ^2.0
- **Usage**: Extract text from PDF files for RAG

## After Installation

1. Publish Spatie Permissions config:
```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

2. Publish DomPDF config:
```bash
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
```

