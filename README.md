# Task Management System - Yii2

A comprehensive Task Management System built with Yii2 framework, featuring a RESTful API and a modern single-page frontend application. This project demonstrates best practices in API development, authentication, data management, and testing.

## Features

### Core Functionality
- **Task Management**: Create, read, update, and delete tasks
- **User Management**: User registration, authentication, and role-based access control (Admin/User)
- **Task Assignment**: Assign tasks to users
- **Tag System**: Organize tasks with tags (colored labels)
- **Soft Delete**: Tasks can be soft-deleted and restored
- **Optimistic Locking**: Prevents concurrent update conflicts using version control
- **Audit Logging**: Tracks all task modifications

### Task Features
- **Status Management**: Tasks can be pending, in progress, or completed
- **Priority Levels**: Low, medium, and high priority
- **Due Dates**: Set and track task due dates with validation
- **Filtering & Search**: Filter by status, priority, assigned user, tags, and keyword search
- **Pagination**: Efficient pagination for large task lists

### API Features
- **JWT Authentication**: JSON Web Token authentication with fallback to legacy access tokens
- **RESTful API**: Full REST API for tasks, users, tags, and authentication
- **CORS Support**: Cross-origin resource sharing enabled
- **Eager Loading**: Optimized queries with eager loading to prevent N+1 problems
- **Database Indexing**: Indexed fields for optimal query performance

### Frontend Features
- **Single-Page Application**: Modern HTML5/JavaScript frontend with Bootstrap 5
- **Real-time Updates**: Dynamic task list updates without page refresh
- **Responsive Design**: Mobile-friendly interface
- **Tag Management**: Visual tag selection with color-coded badges
- **User Assignment**: Easy task assignment through dropdown selection

## Technology Stack

- **Backend**: Yii2 Framework (PHP 8.3)
- **Database**: MariaDB 10.11
- **Frontend**: HTML5, Bootstrap 5, JavaScript (Vanilla), Axios
- **Authentication**: JWT (firebase/php-jwt) with HTTP Bearer Auth
- **Testing**: Codeception (PHPUnit)
- **Development Environment**: DDEV

## Requirements

- **DDEV**: Latest version installed and configured
- **PHP**: 8.3+ (handled by DDEV)
- **Composer**: For dependency management
- **Node.js**: Not required (frontend uses CDN resources)

## Installation & Setup

### 1. Prerequisites

Ensure you have DDEV installed. If not, follow the [DDEV installation guide](https://ddev.readthedocs.io/en/stable/users/install/).

### 2. Clone and Start DDEV

```bash
# Navigate to project directory
cd task-management-system

# Start DDEV (this will automatically configure the environment)
ddev start
```

DDEV will automatically:
- Set up PHP 8.3
- Configure MariaDB 10.11
- Set up Nginx web server
- Configure the project URL: `https://task-management-system.ddev.site`

### 3. Install Dependencies

```bash
# Install PHP dependencies via Composer
ddev composer install
```

### 4. Configure Database

Edit `config/db.php` with your database credentials. DDEV automatically provides these:

```php
return [
    'class' => 'yii\db\Connection',
    'dsn' => 'mysql:host=db;dbname=db',
    'username' => 'root',
    'password' => 'root',
    'charset' => 'utf8',
];
```

**Note**: DDEV automatically creates the database, so you don't need to create it manually.

### 5. Run Migrations

```bash
# Run database migrations to create tables
ddev exec php yii migrate
```

This will create the following tables:
- `user` - User accounts
- `task` - Tasks
- `tag` - Tags
- `task_tag` - Task-Tag relationships
- `task_log` - Audit logs

### 6. (Optional) Seed Sample Data

```bash
# Seed database with sample users and tasks
ddev exec php yii seed
```

### 7. Access the Application

- **Frontend**: https://task-management-system.ddev.site/tasks.html
- **API Base URL**: https://task-management-system.ddev.site/api
- **API Documentation**: See Postman collection in `postman/Task ManagerAPI.postman_collection.json`

## Development

### DDEV Commands

```bash
# Start the project
ddev start

# Stop the project
ddev stop

# Restart the project
ddev restart

# View logs
ddev logs

# Access database
ddev mysql

# Execute commands in the container
ddev exec <command>

# SSH into the container
ddev ssh

# Run Composer commands
ddev composer <command>

# Run Yii console commands
ddev exec php yii <command>
```

### Database Management

```bash
# Run migrations
ddev exec php yii migrate

# Rollback last migration
ddev exec php yii migrate/down

# Create new migration
ddev exec php yii migrate/create <migration_name>
```

## Testing

### Running Tests

The project uses Codeception for testing. Tests are located in the `tests/` directory.

```bash
# Install REST module
composer require codeception/module-rest --dev 

# Build
ddev exec vendor/bin/codecept build 

# Run all tests
ddev exec vendor/bin/codecept run

# Run unit tests only
ddev exec vendor/bin/codecept run unit

# Run functional tests only
ddev exec vendor/bin/codecept run functional

# Run specific test file
ddev exec vendor/bin/codecept run unit models/TaskTest

# Run with coverage (requires Xdebug)
ddev exec vendor/bin/codecept run --coverage --coverage-html
```

### Test Suites

- **Unit Tests** (`tests/unit/`): Test individual components, models, and behaviors
- **Functional Tests** (`tests/functional/`): Test API endpoints and controllers
- **Acceptance Tests** (`tests/acceptance/`): Browser-based tests (disabled by default)

### Test Database

Tests use a separate database configured in `config/test_db.php`. The test database is automatically created and migrated when running tests.

## API Documentation

### Authentication

The API uses JWT (JSON Web Token) authentication with HTTP Bearer token. If JWT decoding fails, it falls back to legacy access token validation.

**Register User:**
```http
POST /api/auth/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "user123",
  "role": "user"
}
```

**Login:**
```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "user123"
}
```

Response includes both `jwt` and `access_token` for compatibility.

**Using Authentication:**
```http
GET /api/tasks
Authorization: Bearer <jwt_token_or_access_token>
```

### API Endpoints

#### Tasks
- `GET /api/tasks` - List tasks (with filtering, pagination)
- `GET /api/tasks/{id}` - Get task details
- `POST /api/tasks` - Create task
- `PUT /api/tasks/{id}` - Update task
- `PATCH /api/tasks/{id}/toggle-status` - Toggle task status
- `DELETE /api/tasks/{id}` - Soft delete task
- `PATCH /api/tasks/{id}/restore` - Restore soft-deleted task

#### Users
- `GET /api/users` - List users

#### Tags
- `GET /api/tags` - List tags
- `POST /api/tags` - Create tag
- `GET /api/tags/{id}` - Get tag
- `PUT /api/tags/{id}` - Update tag
- `DELETE /api/tags/{id}` - Delete tag

### Request/Response Examples

**Create Task:**
```http
POST /api/tasks
Authorization: Bearer <token>
Content-Type: application/json

{
  "title": "Complete project documentation",
  "description": "Write comprehensive README",
  "status": "pending",
  "priority": "high",
  "due_date": "2026-02-01",
  "assigned_to": 1,
  "tag_ids": [1, 2]
}
```

**Update Task (with optimistic locking):**
```http
PUT /api/tasks/1
Authorization: Bearer <token>
Content-Type: application/json

{
  "title": "Updated title",
  "version": 5
}
```

**Filter Tasks:**
```http
GET /api/tasks?status=completed&priority=high&assigned_to=1&keyword=report&page=1&pageSize=12
Authorization: Bearer <token>
```

For complete API documentation, import the Postman collection from `postman/Task ManagerAPI.postman_collection.json`.

## Project Structure

```
task-management-system/
├── behaviors/              # Custom behaviors (SoftDelete, AuditLog)
├── commands/               # Console commands (SeedController)
├── config/                 # Application configuration
│   ├── db.php             # Database configuration
│   └── web.php            # Web application configuration
├── migrations/             # Database migrations
├── models/                 # ActiveRecord models
│   ├── Task.php           # Task model
│   ├── User.php           # User model
│   ├── Tag.php            # Tag model
│   └── TaskSearch.php     # Task search/filter model
├── modules/
│   └── api/               # API module
│       ├── components/     # Custom components (JwtOrBearerAuth)
│       └── controllers/   # API controllers
│           ├── TaskController.php
│           ├── UserController.php
│           ├── TagController.php
│           └── AuthController.php
├── tests/                  # Test suites
│   ├── unit/              # Unit tests
│   ├── functional/        # Functional tests
│   └── acceptance/        # Acceptance tests
├── web/                    # Web-accessible files
│   ├── index.php          # Entry script
│   └── tasks.html         # Frontend SPA
├── postman/               # Postman API collection
└── .ddev/                 # DDEV configuration
    └── config.yaml        # DDEV project config
```

## Key Features Implementation

### Optimistic Locking
Tasks use version-based optimistic locking to prevent concurrent update conflicts. Each update must include the current `version` number.

### Soft Delete
Tasks are soft-deleted (marked with `deleted_at` timestamp) rather than physically removed. Use `show_deleted=true` parameter to view deleted tasks.

### Eager Loading
All queries use eager loading (`with(['assignedUser', 'tags'])`) to prevent N+1 query problems.

### Database Indexing
Key fields are indexed for performance:
- `status`, `priority`, `due_date`
- `assigned_to`, `deleted_at`, `created_at`

## Troubleshooting

### DDEV Issues

**Port already in use:**
```bash
ddev stop
# Or change ports in .ddev/config.yaml
```

**Database connection errors:**
```bash
ddev restart
ddev exec php yii migrate
```

**Permission issues:**
```bash
ddev exec chmod -R 777 runtime web/assets
```

### Common Issues

**Migrations fail:**
- Ensure database is running: `ddev start`
- Check database credentials in `config/db.php`

**API returns 401:**
- Check if JWT token is valid
- Ensure `Authorization: Bearer <token>` header is set
- Try logging in again to get a fresh token

**Frontend not loading:**
- Check browser console for errors
- Verify API base URL in `web/tasks.html`
- Ensure CORS is properly configured

## Contributing

1. Create a feature branch
2. Make your changes
3. Write/update tests
4. Ensure all tests pass: `ddev exec vendor/bin/codecept run`
5. Submit a pull request

## License

BSD-3-Clause

## Support

For issues and questions:
- Check the [Yii2 Documentation](https://www.yiiframework.com/doc/guide/2.0/en)
- Review the [DDEV Documentation](https://ddev.readthedocs.io/)
- Check API examples in the Postman collection
