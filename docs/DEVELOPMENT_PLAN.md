# CIS V2 Development Plan

## Phase Overview
Autonomous development of enterprise-grade PHP/MariaDB MVC system with strict quality gates and comprehensive observability.

## Phase 1: Foundation & Bootstrap (Current)

### 1.1 File/Layout Guardrails ✅
- **Status**: COMPLETE
- **Deliverables**:
  - Knowledge index created (`docs/knowledge/knowledge_index.json`)
  - Long-term memory system (`docs/knowledge/brain.jsonl`)
  - Quality gates framework (`tools/quality_gates.php`)
  - PSR-12 linter (`tools/lint.php`)
  - Test runner (`tools/test_runner.php`)

### 1.2 Router + Basic Routes (Next: HIGH PRIORITY)
- **Objective**: Standardize routing with middleware pipeline
- **Tasks**:
  - Enhance Router.php with middleware execution order
  - Create SecurityHeaders middleware
  - Create RequestId middleware  
  - Add basic error handling middleware
  - Register 10 essential routes
  - Create custom 404/500 error pages

### 1.3 Logger + Profiler + Health Endpoints
- **Objective**: Complete observability foundation
- **Tasks**:
  - Enhance Logger.php for structured JSON output
  - Create Profiler middleware for request timing
  - Create cis_perf_spans table migration
  - Implement /_health and /_selftest endpoints
  - Add performance monitoring dashboard

### 1.4 Migrations/Seeders Framework
- **Objective**: Database schema management
- **Tasks**:
  - Create Migration base class with idempotent operations
  - Create Seeder base class with rollback support
  - Implement migrate.php and seed.php tools
  - Load initial schema (users, sessions, system_config)
  - Create admin user seeder

### 1.5 Admin Shell Interface
- **Objective**: Protected admin interface foundation
- **Tasks**:
  - Create admin layout template
  - Implement authentication middleware
  - Build navigation system
  - Create dashboard skeleton
  - Add tools interface

**Phase 1 Acceptance Criteria**:
- All routes respond with proper HTTP codes
- JSON logs generated for all requests
- Self-test endpoint passes all checks
- Quality gates score >80%
- Admin interface accessible

## Phase 2: Auth, Sessions, RBAC

### 2.1 AuthService + Login Flow
- Session security with rotation
- Login/logout with CSRF protection
- Lockout thresholds for brute force
- Password hashing with bcrypt

### 2.2 RBAC Middleware + Permission Cache
- Role-based access control
- Permission caching in Redis
- Admin user management interface
- Role assignment system

### 2.3 Settings UI + Config Audit
- System configuration management
- Change audit trail
- Feature flags implementation
- Admin configuration interface

### 2.4 Email Harness
- SMTP configuration
- Email templates
- Logging email actions
- Development email interception

## Phase 3: Observability & Telemetry

### 3.1 Audit Log System
- Sensitive mutation logging
- User action tracking
- Data lineage recording
- Compliance audit trails

### 3.2 Consented Telemetry
- Privacy-compliant session analytics
- User consent management
- Data retention policies
- Anonymized behavior tracking

### 3.3 Live Log Console
- Real-time log streaming
- Log filtering and search
- SSE-based updates
- Administrative log viewer

## Phase 4: API v1 + Admin Tools

### 4.1 REST API Framework
- Envelope response format
- API versioning strategy
- Rate limiting implementation
- OpenAPI documentation

### 4.2 Tools Web Interface
- Migration runner interface
- Test execution dashboard
- Performance monitoring
- System health checks

### 4.3 Queue System
- Redis-backed job queue
- Background job processing
- Dead letter queue handling
- Job monitoring dashboard

## Phase 5: Integration Skeletons

### 5.1 Vend Integration
- API client implementation
- Product synchronization
- Health monitoring
- Error handling

### 5.2 Deputy Integration
- Employee data sync
- Schedule integration
- HR data management
- Audit trail

### 5.3 Xero Integration
- Financial data sync
- Transaction mapping
- Reconciliation tools
- Error recovery

## Development Standards

### Code Quality Requirements
- PSR-12 compliance: 95%+
- Strict types: 90%+
- Test coverage: 70%+
- Security scan: 0 critical issues
- Performance: <700ms p95

### Documentation Requirements
- All governance docs updated per phase
- API documentation maintained
- Decision records (ADR format)
- Performance benchmarks
- Security considerations

### Testing Strategy
- Unit tests for Domain Services
- Integration tests for Database operations
- HTTP tests for Controllers
- Performance smoke tests
- Security penetration tests

## Risk Management

### High-Risk Areas
- Database schema migration (180+ tables)
- Session security implementation
- External API integrations
- Performance optimization

### Mitigation Approaches
- Incremental schema migration
- Comprehensive test coverage
- Circuit breaker patterns
- Performance budgets per route
- Security review at each phase

## Success Metrics

### Quality Metrics
- Zero critical security issues
- 95%+ PSR-12 compliance
- 70%+ test coverage
- <2s average response time

### Business Metrics
- Feature parity with existing system
- Zero data loss during migration
- 99.5% uptime target
- User satisfaction >90%

## Next Steps (Immediate)
1. Complete Router middleware pipeline (1.2)
2. Implement structured logging (1.3)
3. Create migration framework (1.4)
4. Build admin interface (1.5)
5. Execute Phase 1 acceptance testing
