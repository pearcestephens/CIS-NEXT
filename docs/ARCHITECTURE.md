# CIS V2 System Architecture

## Overview
CIS V2 is a modular, enterprise-grade PHP 8.3 application built on MVC/DDD principles with strict PSR-12 compliance and comprehensive observability.

## Architecture Layers

### 1. Entry Layer
- **index.php**: Application entry point
- **app/Shared/Bootstrap.php**: Dependency injection, configuration, error handling

### 2. HTTP Layer  
- **app/Http/Router.php**: Request routing with regex patterns, middleware support
- **routes/web.php**: Web interface routes
- **routes/api.php**: API endpoints with versioning

### 3. Middleware Stack (Planned)
1. **SecurityHeaders**: HSTS, CSP, X-Frame-Options
2. **RequestId**: UUID correlation tracking
3. **RateLimiter**: Request throttling per user/IP
4. **SessionStart**: Secure session management  
5. **CSRF**: Cross-site request forgery protection
6. **Auth**: Authentication validation
7. **RBAC**: Role-based access control
8. **Profiler**: Performance monitoring

### 4. Controller Layer
- **app/Http/Controllers/**: Request orchestration
- **BaseController**: Common functionality, view rendering
- Thin controllers - delegate to Domain Services

### 5. Domain Layer
- **app/Domain/Services/**: Business logic
- **app/Domain/Entities/**: Domain objects with invariants  
- **app/Domain/ValueObjects/**: Immutable value types

### 6. Model Layer
- **app/Models/BaseModel.php**: Database abstraction
- **app/Models/User.php**: User management with RBAC
- **app/Models/Permission.php**: Permission management
- Table prefix support via Database class

### 7. Infrastructure Layer
- **app/Infra/Persistence/MariaDB/Database.php**: Connection management
- **app/Infra/Persistence/MariaDB/QueryBuilder.php**: Fluent SQL builder
- **migrations/**: Schema versioning
- **seeds/**: Repeatable data population

### 8. Shared Services
- **app/Shared/Config/Config.php**: Environment configuration
- **app/Shared/Logging/Logger.php**: Structured JSON logging
- **app/Shared/Bootstrap.php**: Application initialization

## Key Contracts

### Request/Response Flow
```
Client → Router → Middleware Pipeline → Controller → Domain Service → Model → Database
                                                      ↓
Client ← View/JSON ← Controller ← Domain Service ← Model ← Database
```

### Error Handling
- All exceptions → structured error envelopes
- Request correlation via request_id
- Comprehensive logging with context

### Security Model
- Password hashing via bcrypt
- Session security (httpOnly, secure, sameSite)
- CSRF protection on state-changing operations
- SQL injection prevention via prepared statements
- XSS protection via output escaping

### Observability
- Structured JSON logging to var/logs/
- Performance profiling per request
- Database query logging with timing
- Error tracking with stack traces

## Database Schema
- 180+ tables in cis_full_schema.sql
- Comprehensive ERP coverage
- Foreign key constraints with cascade rules
- JSON columns for flexible metadata
- Optimized indexes for performance

## Technology Stack
- **Language**: PHP 8.3+ with strict types
- **Database**: MariaDB 10.11 (MySQL compatible)
- **Web Server**: Apache/Nginx with PHP-FPM
- **Frontend**: Bootstrap 4.2, Font Awesome, ES6+
- **Hosting**: Cloudways managed infrastructure

## Quality Standards
- PSR-12 code style compliance
- Minimum 70% test coverage target
- Static analysis via built-in tools
- Security scanning for common vulnerabilities
- Performance budgets enforced per route
