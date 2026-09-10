# Laravel vs Spring Boot — Technical Comparison

Two implementations of the same kost-search API were built for the Mamikos backend technical test:

| | Laravel | Spring Boot |
|---|---|---|
| Repository | https://github.com/notsuperganang/mamikos-kost-api-laravel | https://github.com/notsuperganang/mamikos-kost-api-spring |
| Framework | Laravel 13.31 on PHP 8.5 | Spring Boot 4.1.1 on Java 21 (Spring Framework 7, Spring Security 7, Hibernate 7, Jackson 3) |
| Database | PostgreSQL 18 | PostgreSQL 18 |
| Build / run | Composer, `php artisan serve` | Maven wrapper, `./mvnw spring-boot:run` or an executable jar |
| Auth | Sanctum personal access tokens (opaque, hashed in DB, revocable) | Self-issued HS256 JWT validated by Spring's OAuth2 resource-server support (stateless) |
| Schema | Laravel migrations | Flyway SQL migrations, Hibernate in `validate` mode |
| Tests | Pest 5, 36 tests (≈200 assertions) against a real PostgreSQL database, run in < 1 s | JUnit 5 + Mockito + `MockMvcTester`, 43 tests, integration tests on Testcontainers PostgreSQL |
| Style / CI | Pint, GitHub Actions with a PostgreSQL service | Spotless (google-java-format), GitHub Actions with Docker for Testcontainers |
| Hand-written code (excl. tests) | ~1,650 lines PHP (incl. migrations, seeders, routes) | ~1,690 lines Java + 60 lines SQL |
| Test code | ~440 lines | ~940 lines |

Both expose the identical contract (`/api/v1`, `snake_case` JSON, `{data, meta}` pagination, RFC 9457 problem responses, same status codes), so a client cannot tell them apart. The rest of this document compares how each framework got there and what that implies for a team choosing between them.

## 1. Project structure

**Laravel** dictates the skeleton: `app/Http/Controllers`, `app/Http/Requests`, `app/Models`, `routes/api.php`, `database/migrations`. The implementation follows those conventions and adds only what has real logic: `app/Services` (credits, inquiries, token issuing), `app/Enums`, `app/Data` (two small value objects), `app/Policies` and one middleware. Routes are declared centrally, which makes the whole API surface readable in a single file.

**Spring Boot** has no prescribed layout, so the project is organised **package-by-feature** (`auth`, `user`, `kost`, `inquiry`, `credit`, `common`, `config`). Each package owns its entity, repository, service, controller and DTO records. Routes are declared on the controllers. This scales well as features grow, but a reader has to open several classes to see the full API surface; springdoc/OpenAPI would normally fill that gap.

Verdict: Laravel is faster to navigate for a small API; the Spring layout pays off when features and teams multiply.

## 2. Domain model and persistence

Both projects model the three account types as an **enum on the user row** (`owner`, `regular`, `premium`) with the monthly allowance and the "can inquire" rule attached to the enum, plus an append-only `credit_transactions` ledger and a `room_availability_inquiries` table. A `roles`/`permissions` schema would be over-engineering for three fixed types.

| | Laravel / Eloquent | Spring Data JPA / Hibernate |
|---|---|---|
| Mapping | Convention-based (`kosts` table ↔ `Kost`), attributes for fillable/hidden, `casts()` for enums | Explicit annotations on every field; Lombok reduces boilerplate on entities, records are used for DTOs |
| Search | Query scope with `when()` chains, `ILIKE` native to PostgreSQL | `JpaSpecificationExecutor` with composable `Specification`s; `Specification.unrestricted()` for absent criteria (Spring Data 4 no longer accepts `null` parts) |
| Atomic credit deduction | `User::whereKey($id)->where('credit', '>=', $n)->decrement('credit', $n)` | `@Modifying @Query("update User u set u.credit = u.credit - :n where … and u.credit >= :n")` |
| Monthly recharge | Raw SQL (`DB::affectingStatement`) with a data-modifying CTE | Same SQL through `JdbcClient` |
| Schema source of truth | Migrations, written in PHP; raw statements for CHECK constraints and functional indexes | Flyway SQL; Hibernate validates the mapping at startup |

The recharge job is the most interesting piece: one CTE per role locks eligible rows, inserts ledger entries and resets balances, skipping users who already have a `monthly_recharge` row for the current month (month start computed in `Asia/Jakarta`). The same statement is used in both projects, which shows that once logic becomes set-based SQL, the ORM matters little. Eloquent made the row-level operations shorter; JPA forced more explicit code but caught schema drift at boot.

## 3. Authentication and authorization

**Laravel** uses Sanctum: `php artisan install:api`, `HasApiTokens` on the model, `auth:sanctum` on routes. Tokens are hashed in `personal_access_tokens`, expire after 7 days (configurable) and can be revoked instantly. Role checks are a four-line middleware (`role:owner`), ownership is a `KostPolicy` invoked with `Gate::authorize`. Login/registration are rate-limited with the built-in `throttle` middleware. Total auth-related code is under 100 lines.

**Spring Boot** issues HS256 JWTs with `NimbusJwtEncoder` and validates them with the OAuth2 resource-server filter, the approach recommended by the Spring Security team over hand-written JWT filters. Roles come from a `role` claim mapped to `ROLE_*` authorities and enforced with `@PreAuthorize`; ownership is checked in `KostService`. Custom entry points return problem-detail JSON for 401/403. Spring Security's flexibility costs more configuration (~200 lines including the filter chain and JWT beans) and more concepts (filter chain, converters, method security).

Trade-off: Sanctum tokens hit the database on every request but are revocable; JWTs are stateless but cannot be revoked before expiry without a denylist. For a single first-party API both are acceptable; the JWT approach is the natural fit if other services must verify tokens without a shared database.

## 4. Validation and error handling

| | Laravel | Spring Boot |
|---|---|---|
| Input validation | FormRequest classes with the rules DSL (`Rule::unique`, `Rule::enum`, `Password::min`) | Bean Validation annotations on records, `@Validated` for query parameters, uniqueness checked in the service |
| Error format | Central renderer registered in `bootstrap/app.php` mapping framework exceptions to problem documents | `@RestControllerAdvice` extending `ResponseEntityExceptionHandler`, plus security entry points |
| Field names | Already `snake_case` | Converted from `camelCase` in the handler to match |

Laravel's validation DSL is more expressive and the messages are friendlier out of the box. Spring's is type-safe and lives next to the DTO, but producing the exact same JSON required deliberate work (naming strategy, `ProblemDetail` properties, security handlers).

## 5. Scheduled credit recharge

| | Laravel | Spring Boot |
|---|---|---|
| Definition | `Schedule::command('credits:recharge')->monthlyOn(1, '00:00')->timezone(...)->onOneServer()->withoutOverlapping()` | `@Scheduled(cron = "0 0 0 1 * *", zone = "Asia/Jakarta")` on a bean |
| Runtime | One system cron entry runs `schedule:run` every minute; the scheduler decides what is due | Runs inside the application JVM; a CLI trigger (`--app.jobs.recharge-credits=true`) is provided for cron-style deployments |
| Multi-instance safety | `onOneServer()` uses the shared cache as a lock | Not built in; would need ShedLock. The job is idempotent within a month, which already prevents double-application |
| Manual run / preview | `php artisan credits:recharge [--dry-run]` | `java -jar … --spring.main.web-application-type=none --app.jobs.recharge-credits=true` |
| Tests | Run the command through `artisan()` and assert on the database; `schedule:list` asserts the cron | Service tested against Testcontainers; `CronExpression` unit test asserts next runs |

Laravel's scheduler is the more complete out-of-the-box answer (locking, overlap prevention, `schedule:list`). Spring gives a solid primitive and leaves operational concerns to the team.

## 6. Testing

Both suites exercise the same scenarios over HTTP: credit per role, duplicate email, login failures, the 401/403 matrix, owner isolation, search filters/sort/pagination, credit deduction, insufficient credit, 404 without charge, and recharge idempotency.

- **Laravel**: Pest's expressive syntax, `RefreshDatabase`, factories with states (`->owner()`, `->withCredit(4)`), `Sanctum::actingAs`. Tests are short (≈440 lines for 36 tests). Running against PostgreSQL instead of SQLite keeps `ILIKE` and CHECK constraints honest; the whole suite runs in under a second.
- **Spring**: `@SpringBootTest` + `@AutoConfigureMockMvc` + Testcontainers `@ServiceConnection` boots the full context against a throwaway PostgreSQL container; `MockMvcTester` with AssertJ JSON-path assertions; Mockito unit tests for services. More verbose (≈940 lines for 43 tests) and slower to start (container + context ≈ 30 s), but every layer, including security and Flyway, is tested exactly as in production.

## 7. Developer experience

| | Laravel | Spring Boot |
|---|---|---|
| Time to first working endpoint | Minutes: scaffold, `install:api`, a FormRequest and a controller | Longer: security filter chain and JWT beans must exist before any protected endpoint works |
| Feedback loop | No compile step, `php artisan serve` reloads | Compile + restart (~5 s); DevTools can hot-restart |
| Framework upgrades | Laravel 13 had no meaningful breaking changes | Boot 4 renamed starters (`-web` → `-webmvc`), moved to Jackson 3 (`tools.jackson`), replaced `@MockBean`; upgrading existing projects needs care |
| Tooling | Pint, Pest, Artisan generators, Sail | Maven, Spotless, Initializr, excellent IDE support and static typing |
| Type safety | PHP 8 types + attributes; Larastan optional | Compile-time, records, JSpecify null-safety |

## 8. Runtime footprint (measured locally, single instance, no load)

| | Laravel (`php artisan serve`) | Spring Boot (`spring-boot:run`) |
|---|---|---|
| Startup | < 1 s (per-request bootstrap ≈ 20–40 ms) | ≈ 3–4 s JVM + context |
| Resident memory | ≈ 30 MB per PHP worker | ≈ 250–300 MB JVM heap + metaspace |
| Throughput model | Process-per-request (PHP-FPM / Octane for persistent workers) | Long-lived JVM with virtual threads enabled; JIT warms up over time |

Neither number matters at this scale; they matter when choosing hosting: Laravel runs happily on the cheapest PHP host, Spring wants a JVM-sized container.

## 9. Security defaults

Both: bcrypt password hashing, tokens never logged, secrets via environment, validation on every input, `CHECK` constraints and unique indexes at the database, no stack traces in API responses.

- Laravel adds rate limiting on auth routes with one middleware and hashes tokens at rest.
- Spring adds a hard JWT secret length check at startup (fails fast if `JWT_SECRET` is too short) and a stateless session policy. Rate limiting was left to the gateway layer.

## 10. What was deliberately not built

Hexagonal layers, CQRS, repository-over-Eloquent, Redis caching, Elasticsearch, OAuth2 authorization server, refresh tokens, permission tables, Kubernetes manifests, message queues. Each would add code without changing the observable behaviour of a ten-endpoint API. The first upgrades if the product grows would be: ShedLock (Spring) for multi-instance scheduling, a JWT denylist or short-lived tokens with refresh (Spring), soft deletes for kosts, `pg_trgm` indexes for search, and OpenAPI documentation.

## 11. Recommendation

- Choose **Laravel** when the team is PHP-native, the product is a first-party web/mobile API, and speed of iteration matters most. It delivered the same feature set with less ceremony, a nicer scheduler, and a shorter test suite.
- Choose **Spring Boot** when the API will sit among other JVM services, when compile-time guarantees and long-lived process performance matter, or when the organisation already runs on the JVM. It costs more upfront configuration but the resulting code is explicit, strongly typed and tested exactly as deployed.

For Mamikos' scale, both are production-viable; the deciding factor is the team and the surrounding platform rather than the framework.
