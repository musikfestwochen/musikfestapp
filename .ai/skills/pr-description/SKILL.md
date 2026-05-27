---
name: pr-description
description: "Use this skill when creating or editing pull request descriptions. Activate when the user asks to create a PR, write a PR description, or open a pull request. Covers: analyzing branch diffs, writing concise summaries, detecting multi-purpose PRs, linking issues, and structuring descriptions for reviewers. Do not use for commit messages, changelogs, or release notes."
---

# Pull Request Descriptions

## When to Use This Skill

Activate when the user asks to create a pull request, write a PR description, or review/improve an existing PR description.

## Gathering Context

Before writing the description, collect information by running these commands:

```bash
# Understand what the branch changed vs the base branch
git log --oneline main..HEAD
git diff --stat main...HEAD
git diff main...HEAD

# Build GitHub file links for Details entries
git rev-parse --abbrev-ref HEAD
gh repo view --json nameWithOwner -q .nameWithOwner
```

Use `main` or the appropriate base branch. Adjust if the user specifies a different target.

## Core Principles

1. **Short over long.** A description that gets fully read beats a thorough one that gets skimmed. Aim for a few focused sentences, not paragraphs.
2. **No code in the description.** Reviewers use the diff viewer for code. The description answers _why_ and _what_, not _how_.
3. **Lead with impact.** Start with what improved for users or the system (e.g. safer, faster, less manual work, better UX, fewer failure modes), then add context.
4. **Assume reviewers run locally.** If setup steps are needed after pulling the branch, call them out explicitly.
5. **Ask when unclear.** If the commits are ambiguous or the purpose is unclear, ask the user before guessing.

## Structure

Use this template as a starting point, but **drop any section that has nothing meaningful to say**. An empty section is worse than no section.

````markdown
<1-3 sentences: what improved and why this matters>

## Details

- [ImportantFile.php](https://github.com/<owner>/<repo>/blob/<branch>/path/to/ImportantFile.php) - <only if this file needs special reviewer attention>

## Notes

<only if relevant: deployment steps, breaking changes, migration instructions, new env vars, risk callout, testing notes, or screenshot placeholder -- whatever genuinely helps the reviewer>

## Reviewer Setup

In this PR vs `origin/main`, changes were made in <area(s)>. You likely need to run:

```sh
composer update
npm run build
```

Fixes #<number>

---
**«<inspiring quote here>»**
_<author>_
````

### Section Guidelines

- **Opening paragraph (no heading): Always present.** Do not start with `## Summary`. In 1-3 sentences, state outcomes and impact, not implementation trivia. Prefer phrasing like:
    - Removed manual review work from critical flow.
    - Made validation always enforce server-side instead of relying on user behavior.
    - Added a clean user exit path to prevent orphaned test data.
      Avoid vague or circular wording that just repeats filenames/action names.
- **Reviewer Setup**: Include only the commands that apply based on the diff:
    - Put this section at the very end of the PR body (after `Details` / `Notes`, before issue-closing lines if present).
    - Use a short sentence explaining what changed and why commands are needed, then a fenced `sh` block with commands.
    - If `composer.json` or `composer.lock` changed, include `composer update`.
    - If migrations changed, include `php artisan migrate`.
    - If frontend sources/build inputs changed (Blade/JS/CSS/Vite assets), include `npm run build`.
    - Include other setup commands when relevant (not limited to the three above).
      Skip this section entirely if no reviewer setup commands are needed.
- **Details**: Optional. Replace `Key Files` with `Details` when useful. Include only genuinely important callouts. If listing files:
    - Use filename-only link labels (e.g. `[InspectWeblingInvoicePdfAction.php](...)`), not full-path labels.
    - Link each file to its GitHub blob URL on the current PR branch, e.g. `https://github.com/<owner>/<repo>/blob/<branch>/<path>`.
    - Include the list only when it helps reviewer focus; omit if obvious or redundant.
- **Notes**: Optional catch-all. Only include when there is something a reviewer or deployer _needs to know_. Examples:
    - Breaking changes that affect other teams or downstream consumers.
    - Migrations, new env vars, or config changes that require deployment action.
    - A brief testing note if coverage is unusual (e.g. "no tests -- pure CSS change" or "added integration tests for the new flow").
    - A risk callout if the change touches auth, payments, or data integrity.
    - A screenshot placeholder (`> TODO: attach before/after screenshots`) for visual changes.
    - Do **not** include these subsections by default. Only when they are genuinely important.
- **Fixes #N**: Only include when the PR actually fixes/closes a GitHub issue. Use `Fixes #<number>` syntax (one per line if multiple). Do not fabricate issue numbers -- ask the user if unsure.
- **Ending line + quote (required):** End every PR description with horizontal rule, then one inspiring quote line. Format:

  ```markdown
  ---
  **«<inspiring quote here>»**
  _<author>_
  ```

  Use `php artisan inspire --no-interaction` to source quote.

## Multi-Purpose PR Detection

Analyze the commits on the branch. If they contain clearly unrelated changes (e.g. a bug fix plus an unrelated feature), **warn the user** before writing the description:

> It looks like this branch contains unrelated changes:
>
> - Commits A, B: fix payment rounding bug
> - Commits C, D: add user avatar upload
>
> Would you like to split this into separate PRs, or should I write a combined description?

If the user wants a combined description, organize the summary to clearly separate the concerns.

## Creating the PR

Always create PRs as **drafts** unless the user explicitly asks for a ready PR. Use the `--draft` flag:

```bash
gh pr create --draft --title "Short, descriptive title" --body "$(cat <<'EOF'
<1-3 sentences: what improved and why this matters>

## Details

- [ImportantFile.php](https://github.com/<owner>/<repo>/blob/<branch>/path/to/ImportantFile.php) - why reviewers should look here

## Reviewer Setup

In this PR vs `origin/main`, changes were made in dependencies and frontend assets. You likely need to run:

~~~sh
composer update
npm run build
~~~

Fixes #123
EOF
)"

```

## Common Mistakes to Avoid

- Writing a commit-by-commit changelog instead of a summary.
- Opening with a `## Summary` heading.
- Including code snippets or diffs in the description.
- Adding boilerplate sections that say nothing ("No breaking changes", "N/A").
- Listing files that only restate the obvious behavior from their names.
- Listing files without links to the branch blob URL.
- Formatting setup commands as plain text bullets instead of a fenced `sh` block.
- Guessing issue numbers instead of asking.
- Writing a long description for a small, obvious change.
