# CLAUDE.md

@AGENTS.md

## Agent notes

- Skills in `.claude/skills/`: `aegis-security` (read it for any change to what a request, a setting or the console can do), `package-upgrades` (anything that reaches an installed application), `package-testing`, `nova-development`, `testing-best-practices`, `laravel-best-practices`.
- A changed PHP file is formatted by the `PostToolUse` hook in `.claude/settings.json`; still run `make ready` before you say a change is done, and report its result.
- The core lives in the sibling repository `../nova-aegis`. Use only its public contract (see AGENTS.md); never change the core from here.
- Never push to `main`: work on a branch and open a pull request (see *Git workflow* in AGENTS.md). Write everything in English.
