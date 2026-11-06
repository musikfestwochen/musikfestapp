# Security audit skill

Use `.agents/skills/security-audit/SKILL.md` for security guidance and audits. Skills CLI owns this skill; do not install or update it through Laravel Boost, and do not edit its upstream files.

Before starting an audit, run:

```sh
npx --yes skills@1.7.6 check security-audit --project
```

Confirm output explicitly says the project skill is up to date. Missing skill/lock entries, network errors, or other check failures mean freshness is unknown; report the blocker and pause the audit. If an update exists, pause and refresh:

```sh
npx --yes skills@1.7.6 update security-audit --project --yes
node --test .agents/skills/security-audit/validate-findings.test.cjs .agents/skills/security-audit/validate-coverage-ledger.test.cjs
```

Review changed skill files and `skills-lock.json`, then reload the skill and its companion instructions before starting the audit. Keep audit reports outside this repository unless explicitly instructed otherwise. Follow the skill's sandbox requirements before executing target code.

The `Security audit skill` workflow checks updates weekly or on manual dispatch and opens a draft update PR. Upstream attribution is in `.github/security-audit-LICENSE`.
