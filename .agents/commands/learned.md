---
description: Capture lessons from debugging sessions into project documentation. Invoke after resolving non-trivial bugs or discovering non-obvious behavior.
---

Review this conversation to identify what was just debugged, fixed, or discovered. Extract the non-obvious lesson — the thing that would have saved time if it had been documented before.

Then do the following:

1. **Identify the lesson.** What went wrong, why it was non-obvious, and what the fix or insight was. Be specific — "classmap replacement is two-pass" is useful, "replacement is complex" is not.

2. **Find the right place for it.** Read the existing docs in `docs/` and `AGENTS.md` to determine where this lesson belongs. It should go in the file that someone would be reading when they encounter this situation. If it fits naturally into an existing section, add it there. Don't create a new file unless nothing existing fits.

3. **Draft a targeted update.** A few lines in the right place. Match the tone and format of the surrounding documentation. Don't rewrite sections — add to them.

4. **If a regression test was added**, update the regression test table in `docs/testing.md` with the issue number, test location, and what it prevents.

5. **If this reveals a key gotcha** (something that would cause CI failures, subtle bugs, or wasted debugging time), consider adding a one-liner to the "Key things to know" section in `AGENTS.md`.

Present the proposed changes for review before applying them.
