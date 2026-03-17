# Contributing

Contributions are welcome via pull requests. Please read through this guide before submitting one.

## Fork and Pull Request Workflow

1. Fork the repository on GitHub
2. Clone your fork locally:
   ```bash
   git clone https://github.com/your-username/housekeeping.git
   cd housekeeping
   ```
3. Add the upstream remote:
   ```bash
   git remote add upstream https://github.com/gordon-fcl/housekeeping.git
   ```
4. Create a branch from `staging`:
   ```bash
   git checkout staging
   git pull upstream staging
   git checkout -b your-branch-name
   ```
5. Make your changes, then run the checks:
   ```bash
   composer test
   ```
6. Commit with a brief, imperative message:
   ```bash
   git commit -m "Fix label filtering when tag is empty"
   ```
7. Push to your fork:
   ```bash
   git push origin your-branch-name
   ```
8. Open a pull request against `staging` on the upstream repository

## Guidelines

- Keep commits atomic -- one logical change per commit
- Run `composer lint` before pushing to ensure code style compliance
- Run `composer test:types` to check for type errors
- Run `composer test:unit` to run the test suite
- Use British English in code, comments and documentation
- No emojis in commits, code or documentation
- Follow [SemVer](http://semver.org/) for any version-related changes
- You may need to [rebase](https://git-scm.com/book/en/v2/Git-Branching-Rebasing) to avoid merge conflicts

## Setup

Install dependencies:

```bash
composer install
```

Run all checks at once:

```bash
composer test
```
