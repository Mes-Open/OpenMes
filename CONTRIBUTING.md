# Contributing to OpenMES

First off, thank you for considering contributing to OpenMES! 🎉

It's people like you that make OpenMES such a great tool for small manufacturers around the world.

## 📋 Table of Contents

- [Code of Conduct](#code-of-conduct)
- [How Can I Contribute?](#how-can-i-contribute)
- [Development Setup](#development-setup)
- [Pull Request Process](#pull-request-process)
- [Coding Standards](#coding-standards)
- [Testing Guidelines](#testing-guidelines)
- [Commit Message Guidelines](#commit-message-guidelines)

---

## Code of Conduct

This project and everyone participating in it is governed by our commitment to providing a welcoming and inspiring community for all. Please be respectful and constructive in your interactions.

**Our Standards:**
- ✅ Be respectful and inclusive
- ✅ Accept constructive criticism gracefully
- ✅ Focus on what's best for the community
- ✅ Show empathy towards other community members
- ❌ No trolling, insulting, or harassing behavior
- ❌ No personal or political attacks
- ❌ No publishing others' private information

---

## How Can I Contribute?

### 🐛 Reporting Bugs

Before creating bug reports, please check existing issues to avoid duplicates.

**When filing a bug report, include:**
- Clear, descriptive title
- Exact steps to reproduce
- Expected vs. actual behavior
- Screenshots (if applicable)
- Environment details:
  - OS (Linux, Windows, macOS)
  - Docker version
  - Browser (for frontend issues)
  - PHP version (for backend issues)

**Template:**
```markdown
**Description:**
A clear description of the bug.

**Steps to Reproduce:**
1. Go to '...'
2. Click on '...'
3. Scroll down to '...'
4. See error

**Expected Behavior:**
What you expected to happen.

**Actual Behavior:**
What actually happened.

**Environment:**
- OS: Ubuntu 22.04
- Docker: 24.0.5
- Browser: Chrome 120
```

### 💡 Suggesting Enhancements

We love feature suggestions! Before creating enhancement suggestions:
- Check if it's already been suggested
- Consider if it fits OpenMES's scope (MES for small manufacturers)
- Provide a clear use case

**Template:**
```markdown
**Problem Statement:**
Describe the problem this feature solves.

**Proposed Solution:**
Your suggested implementation.

**Alternatives Considered:**
Other approaches you've thought about.

**Additional Context:**
Any mockups, examples, or references.
```

### 📝 Improving Documentation

Documentation improvements are always welcome:
- Fix typos or clarify confusing sections
- Add examples or screenshots
- Translate documentation
- Write guides or tutorials

### 🔧 Contributing Code

1. **Pick an Issue**: Look for issues labeled `good first issue` or `help wanted`
2. **Comment**: Let us know you're working on it
3. **Fork & Branch**: Create a feature branch
4. **Code**: Implement your changes
5. **Test**: Write tests and ensure all tests pass
6. **Submit**: Open a pull request

---

## Development Setup

### Prerequisites

- **Docker** & Docker Compose
- **Git**
- **Node.js** 18+ (for local frontend development)
- **PHP** 8.2+ and Composer (for local backend development)

### Initial Setup

```bash
# 1. Fork the repository on GitHub

# 2. Clone your fork
git clone https://github.com/YOUR-USERNAME/OpenMes.git
cd OpenMes

# 3. Add upstream remote
git remote add upstream https://github.com/Mes-Open/OpenMes.git

# 4. Create environment file
cp .env.example .env

# 5. Update credentials in .env
nano .env  # Set DB_PASSWORD and DEFAULT_ADMIN_PASSWORD

# 6. Start services
docker-compose up -d

# 7. Run migrations
docker-compose exec backend php artisan migrate:fresh --seed

# 8. Access the application
# Frontend: http://localhost
# API: http://localhost:8000
```

### Local Development (Without Docker)

**Backend:**
```bash
cd backend

# Install dependencies
composer install

# Configure .env
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate:fresh --seed

# Start dev server
php artisan serve
```

**Frontend:**
```bash
cd frontend

# Install dependencies
npm install

# Start dev server
npm run dev
```

### Keeping Your Fork Updated

```bash
# Fetch upstream changes
git fetch upstream

# Merge upstream main into your local main
git checkout main
git merge upstream/main

# Push updates to your fork
git push origin main
```

---

## Pull Request Process

### Before Submitting

1. **Sync with upstream**: Ensure your branch is up-to-date with `main`
2. **Run tests**: All tests must pass
   ```bash
   # Backend tests
   docker-compose exec backend php artisan test

   # Frontend tests
   cd frontend && npm test
   ```
3. **Run linters**:
   ```bash
   # Backend (Laravel Pint)
   docker-compose exec backend ./vendor/bin/pint

   # Frontend (ESLint)
   cd frontend && npm run lint
   ```
4. **Check for security issues**:
   ```bash
   composer audit
   npm audit
   ```

### Pull Request Guidelines

1. **Create a feature branch**:
   ```bash
   git checkout -b feature/amazing-feature
   ```

2. **Write clear, atomic commits** (see [Commit Guidelines](#commit-message-guidelines))

3. **Update documentation** if you're changing functionality

4. **Add tests** for new features or bug fixes

5. **Fill out the PR template**:
   ```markdown
   **Description:**
   Brief description of changes.

   **Related Issue:**
   Closes #123

   **Type of Change:**
   - [ ] Bug fix
   - [ ] New feature
   - [ ] Breaking change
   - [ ] Documentation update

   **Testing:**
   - [ ] All tests pass
   - [ ] Added new tests
   - [ ] Manual testing completed

   **Checklist:**
   - [ ] Code follows project style guidelines
   - [ ] Self-review completed
   - [ ] Comments added for complex code
   - [ ] Documentation updated
   - [ ] No new warnings generated
   ```

6. **Be responsive** to review feedback

7. **Squash commits** if requested before merge

### PR Review Process

- Maintainers will review your PR within 7 days
- Address any requested changes
- Once approved, a maintainer will merge your PR
- Your contribution will be credited in release notes! 🎉

---

## Coding Standards

### Backend (Laravel)

**Follow Laravel conventions:**
- Use PSR-12 coding standard
- Run Laravel Pint before committing:
  ```bash
  ./vendor/bin/pint
  ```
- Use Eloquent ORM (no raw SQL with user input)
- Use Form Requests for validation
- Follow service layer pattern for business logic
- Use Gates/Policies for authorization

**Example:**
```php
// Good - Service layer with validation
class WorkOrderService
{
    public function createWorkOrder(array $data): WorkOrder
    {
        return DB::transaction(function () use ($data) {
            // Business logic here
        });
    }
}

// Bad - Logic in controller
class WorkOrderController
{
    public function store(Request $request)
    {
        WorkOrder::create($request->all()); // No validation!
    }
}
```

### Frontend (React)

**Follow React best practices:**
- Use TypeScript where possible
- Use functional components with hooks
- Run ESLint before committing:
  ```bash
  npm run lint
  ```
- Use Mantine UI components
- Use TanStack Query for server state
- Keep components focused and small

**Example:**
```tsx
// Good - Typed, focused component
interface StepButtonProps {
  step: BatchStep;
  onStart: (stepId: number) => void;
}

const StepButton: React.FC<StepButtonProps> = ({ step, onStart }) => {
  return (
    <Button onClick={() => onStart(step.id)}>
      Start {step.name}
    </Button>
  );
};

// Bad - Untyped, doing too much
const StepButton = (props) => {
  // 200 lines of mixed logic...
};
```

### Database

- **Use migrations** for all schema changes
- **Never edit migrations** that have been pushed to main
- **Add indexes** for foreign keys and frequently queried columns
- **Use transactions** for multi-step operations
- **Document complex queries** with comments

### Security

**Critical security rules:**
- ❌ Never commit `.env` files
- ❌ Never use raw SQL with user input
- ❌ Never expose stack traces in production
- ❌ Never store passwords in plaintext
- ✅ Always validate user input server-side
- ✅ Always use parameterized queries
- ✅ Always implement authorization checks
- ✅ Always sanitize output

---

## Testing Guidelines

### Backend Tests

**Test structure:**
```
tests/
├── Feature/           # API endpoint tests
│   └── Api/
└── Unit/              # Business logic tests
    └── Services/
```

**Writing tests:**
```php
<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WorkOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_create_work_order(): void
    {
        $user = User::factory()->admin()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/work-orders', [
                'order_no' => 'WO-001',
                'line_id' => Line::factory()->create()->id,
                'product_type_id' => ProductType::factory()->create()->id,
                'planned_qty' => 100,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('work_orders', ['order_no' => 'WO-001']);
    }
}
```

**Coverage requirements:**
- All new features must include tests
- Bug fixes must include regression tests
- Aim for >80% code coverage
- Critical business logic requires 100% coverage

**Run tests:**
```bash
# All tests
php artisan test

# With coverage
php artisan test --coverage

# Specific file
php artisan test tests/Feature/Api/WorkOrderTest.php
```

### Frontend Tests

**Run tests:**
```bash
# Unit tests
npm test

# With coverage
npm run test:coverage

# E2E tests (when implemented)
npm run test:e2e
```

---

## Commit Message Guidelines

### Format

```
<type>(<scope>): <subject>

<body>

<footer>
```

### Type

- **feat**: New feature
- **fix**: Bug fix
- **docs**: Documentation changes
- **style**: Code style changes (formatting, no logic change)
- **refactor**: Code refactoring
- **test**: Adding or updating tests
- **chore**: Maintenance tasks (dependencies, build config)
- **perf**: Performance improvements
- **ci**: CI/CD changes

### Scope

- `backend` - Backend changes
- `frontend` - Frontend changes
- `db` - Database changes
- `docker` - Docker/deployment changes
- `api` - API changes
- `ui` - UI/UX changes

### Examples

```bash
# Good commit messages
feat(backend): add CSV import with column mapping
fix(frontend): resolve step button disabled state issue
docs: update installation guide with troubleshooting
test(backend): add tests for batch step execution
refactor(api): extract work order service logic

# Bad commit messages
update stuff
fix bug
WIP
asdfasdf
```

### Additional Guidelines

- Use imperative mood ("add" not "added")
- Don't end subject line with period
- Limit subject line to 72 characters
- Reference issues: `Closes #123` or `Fixes #456`
- Explain **why** not **what** in the body

---

## Getting Help

### Resources

- 📖 **Documentation**: [docs/](docs/)
- 💬 **Discussions**: [GitHub Discussions](https://github.com/Mes-Open/OpenMes/discussions)
- 🐛 **Issues**: [GitHub Issues](https://github.com/Mes-Open/OpenMes/issues)
- 📧 **Email**: openmmes@example.com

### Questions?

Don't hesitate to ask questions in:
- GitHub Discussions (preferred for general questions)
- Issue comments (for specific issues)
- Pull request comments (for PR-specific questions)

---

## Recognition

All contributors will be:
- Listed in [CONTRIBUTORS.md](CONTRIBUTORS.md)
- Mentioned in release notes
- Credited in the project README

---

## License

By contributing to OpenMES, you agree that your contributions will be licensed under the MIT License.

---

**Thank you for contributing to OpenMES!** 🙌

Every contribution, no matter how small, makes a difference. We're excited to have you as part of our community.

**Happy coding!** 🚀
