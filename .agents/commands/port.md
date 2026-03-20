---
description: Port a fix from one branch to others via cherry-pick. Supports forward porting to master and backporting to release branches.
---

# /port Command

Port commits from a source branch/PR to target branches using cherry-pick.

## Usage

```
/port [PR number or branch name]
```

If no argument provided, will prompt for source.

## Workflow

### Step 1: Identify Source
Ask: "Which PR or branch should be ported? (or 'current' for current branch)"

If 'current', use current branch name.
If PR number provided, fetch PR details via GitHub CLI.

### Step 2: Discover Commits
Show list of commits to be ported:
```
Found N commits:
  - [hash] [message]
  - [hash] [message]
  ...
```

### Step 3: Forward Port to Master
Ask: "Forward port to master? (Y/n)"

If yes:
1. Create branch: `forward/[source-branch-name]`
2. Cherry-pick all commits from source
3. If conflicts:
   - Try auto-resolve with `--strategy-option=theirs`
   - If still conflicts: STOP and ask user to resolve manually
4. Push to GitHub
5. Create PR to master with:
   - Title: "Forward: [original PR title]"
   - Body: "Forward port of #[original PR number]"

### Step 4: Backport to Release Branches
Ask: "Backport to which release branches? (comma-separated, or 'none')"
Examples: "release-1.1" or "release-1.1, release-1.0"

For each target branch:
1. Create branch: `backport/[source-branch-name]`
2. Cherry-pick all commits from source
3. If conflicts:
   - Try auto-resolve with `--strategy-option=theirs`
   - If still conflicts: STOP and ask user to resolve manually
4. Push to GitHub
5. Create PR to target branch with:
   - Title: "Backport: [original PR title]"
   - Body: "Backport of #[original PR number]"

## Conflict Resolution

When conflicts are detected that cannot be auto-resolved:

```
⚠ Conflicts detected in:
  - src/File.php
  - src/Another.php

Options:
1. Resolve manually (will wait for you)
2. Skip this branch
3. Abort entire operation
```

If option 1:
- Pause and wait for user
- User resolves conflicts manually
- User runs: `git cherry-pick --continue`
- Verify resolution and continue

## Example Session

```
> /port
Which PR or branch? (current: fix/337-config-validation-warning) 
→ [Enter] (accepts current)

Found 3 commits:
  - 3075fdd Add validation warnings...
  - 29a143c Display warnings...
  - c55b209 Add unit tests...

Forward port to master? (Y/n)
→ Y

✓ Created forward/337-config-validation-warning
✓ Cherry-picked 3 commits cleanly
✓ Pushed to origin
✓ Created PR #376

Backport to which branches? (none, or list: release-1.1, release-1.0)
→ release-1.1

✓ Created backport/337-config-validation-warning
⚠ Conflict in src/Console/Commands/Config.php
  1. Resolve manually and continue
  2. Skip this branch
→ 1

[Waiting for manual resolution...]
[User resolves conflicts and runs git cherry-pick --continue]

✓ Cherry-pick completed
✓ Pushed to origin
✓ Created PR #377

Porting complete!
```

## Implementation Notes

- Always fetch PR details via `gh pr view [number]` to get title and commits
- Use `git cherry-pick [commit-hash]` for each commit in order
- Branch naming: `forward/[source-name]` or `backport/[source-name]`
- PR creation via `gh pr create --title "..." --body "..." --base [target]`
- Run linting/tests before pushing (as per lint-before-commit rule)
- If any step fails, provide clear error message and options to retry/skip/abort