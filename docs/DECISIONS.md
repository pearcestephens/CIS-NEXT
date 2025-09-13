# CIS V2 Architectural Decision Records

## ADR-001: Quality-First Development Approach
**Date**: 2025-09-09  
**Status**: ACCEPTED  
**Deciders**: System Architect Bot, CIS Development Team

### Context
CIS V2 requires enterprise-grade reliability and maintainability. Previous system had technical debt and inconsistent code quality.

### Decision
Implement comprehensive quality gates system with automated PSR-12 linting, security scanning, and test coverage requirements before any code is promoted.

### Consequences
- **Positive**: Higher code quality, reduced bugs, easier maintenance
- **Negative**: Slightly slower initial development velocity
- **Neutral**: Requires discipline and tooling investment

### Implementation
- Created `tools/quality_gates.php` with automated checking
- PSR-12 compliance target: 95%
- Test coverage requirement: 70%
- Security scan must pass with 0 critical issues

---

## ADR-002: JSONL Long-Term Memory System
**Date**: 2025-09-09  
**Status**: ACCEPTED  
**Deciders**: System Architect Bot

### Context
Need persistent memory system for autonomous development that can track decisions, schema changes, and system evolution over time.

### Decision
Use JSONL (JSON Lines) format for append-only memory system in `docs/knowledge/brain.jsonl`.

### Consequences
- **Positive**: Simple, parseable, version-controllable, append-only safety
- **Negative**: Requires custom tooling for analysis
- **Neutral**: File size grows over time but remains manageable

### Implementation
Each phase writes snapshot: `{phase, decisions, schema_deltas, interfaces, TODO_risks, metrics}`

---

## ADR-003: Strict MVC with DDD Principles  
**Date**: 2025-09-09  
**Status**: ACCEPTED  
**Deciders**: System Architect Bot, Business Requirements

### Context
Complex ERP system with 180+ database tables requires clear separation of concerns and maintainable architecture.

### Decision
Implement strict MVC pattern with Domain-Driven Design principles:
- Thin controllers (orchestration only)
- Rich domain services (business logic)
- Models for data access only
- Clear layer boundaries

### Consequences
- **Positive**: Maintainable, testable, scalable architecture
- **Negative**: More initial complexity than simple CRUD
- **Neutral**: Requires developer training on patterns

### Implementation
```
app/Http/Controllers/     - Request orchestration
app/Domain/Services/      - Business logic
app/Models/              - Data persistence
app/Infra/Persistence/   - Database layer
```

---

## ADR-004: PSR-12 and Strict Types Mandate
**Date**: 2025-09-09  
**Status**: ACCEPTED  
**Deciders**: System Architect Bot, Quality Requirements

### Context
Need consistent code style and type safety for enterprise-grade PHP application.

### Decision
Mandate PSR-12 compliance and `declare(strict_types=1)` on all PHP files.

### Consequences
- **Positive**: Consistent code style, type safety, reduced bugs
- **Negative**: Requires reformatting existing code
- **Neutral**: Tooling can automate compliance checking

### Implementation
- All files must start with `<?php declare(strict_types=1);`
- PSR-12 compliance verified by `tools/lint.php`
- Quality gates enforce 95% compliance rate

---

## ADR-005: QueryBuilder for All Database Operations
**Date**: 2025-09-09  
**Status**: ACCEPTED  
**Deciders**: System Architect Bot, Security Requirements

### Context
Need to prevent SQL injection and provide consistent database interface across 180+ table schema.

### Decision
All database operations must use QueryBuilder class with prepared statements. No raw SQL in controllers or models.

### Consequences
- **Positive**: SQL injection prevention, consistent interface, query logging
- **Negative**: Learning curve for developers used to raw SQL
- **Neutral**: Slight performance overhead vs optimized raw queries

### Implementation
- `app/Infra/Persistence/MariaDB/QueryBuilder.php` provides fluent interface
- BaseModel integrates QueryBuilder for common operations
- Quality gates check for raw SQL usage

---

## ADR-006: JSON Structured Logging
**Date**: 2025-09-09  
**Status**: ACCEPTED  
**Deciders**: System Architect Bot, Observability Requirements

### Context
ERP system needs comprehensive logging for debugging, auditing, and performance monitoring.

### Decision
Implement structured JSON logging with correlation IDs and performance metrics.

### Consequences
- **Positive**: Parseable logs, correlation tracking, performance insights
- **Negative**: Slightly larger log files than plain text
- **Neutral**: Requires log aggregation tooling for analysis

### Implementation
```json
{
  "ts": "2025-09-09T12:00:00Z",
  "level": "INFO",
  "event": "user.login",
  "request_id": "uuid",
  "user_id": 123,
  "msg": "User login successful"
}
```

---

## ADR-007: Redis for Session and Caching Backend
**Date**: 2025-09-09  
**Status**: PROPOSED  
**Deciders**: TBD

### Context
File-based sessions don't scale across multiple servers and lack performance optimization.

### Decision
TBD - Planning to use Redis for session storage and application caching.

### Consequences
- **Positive**: Better performance, scalability, session sharing
- **Negative**: Additional infrastructure dependency
- **Neutral**: Requires Redis configuration and monitoring

### Implementation
- Session handler in `app/Shared/Session/RedisSessionHandler.php`
- Permission caching for RBAC performance
- Queue backend for background jobs

---

## ADR-008: Middleware Pipeline Order
**Date**: 2025-09-09  
**Status**: PROPOSED  
**Deciders**: TBD

### Context
Need standardized middleware execution order for security, logging, and performance.

### Decision
TBD - Proposed order:
1. SecurityHeaders
2. RequestId  
3. RateLimiter
4. SessionStart
5. CSRF
6. Auth
7. RBAC
8. Profiler

### Consequences
TBD

---

## Decision Tracking

### Accepted Decisions: 6
### Proposed Decisions: 2  
### Rejected Decisions: 0
### Superseded Decisions: 0

### Impact Areas
- **Architecture**: 3 decisions
- **Quality**: 2 decisions  
- **Security**: 2 decisions
- **Performance**: 1 decision
- **Observability**: 1 decision

---

**Next Review**: Phase 1 completion  
**Last Updated**: 2025-09-09 12:00:00 UTC
