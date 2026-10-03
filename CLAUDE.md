# CLAUDE.md

@AGENTS.md

## Agent notes

- Skills in `.claude/skills/`: the architecture skills (`application-layer`, `dependency-injection`, `error-handling`, `validation`, `events`, `testing-architecture`, `domain-layer-cqrs`, `package-boundaries`; see *Architecture* in AGENTS.md), `aegis-security` (read it for any change to what a request, a setting or the console can do), `package-upgrades` (anything that reaches an installed application), `package-testing`, `nova-development`, `testing-best-practices`, `laravel-best-practices`.
- A changed PHP file is formatted by the `PostToolUse` hook in `.claude/settings.json`; still run `make ready` before you say a change is done, and report its result.
- The core lives in the sibling repository `../nova-aegis`. Use only its public contract (see AGENTS.md); never change the core from here.
- `laravel/nova` is the test double in `stubs/nova`, a copy of the core's: a Nova API the module starts using is added in the core's `stubs/nova` first and copied here (see `package-testing`).
- Never push to `main`: work on a branch and open a pull request (see *Git workflow* in AGENTS.md). Write everything in English.
