---
contract_version: 2
kind: tool
service: terminus-mass-update   # a Terminus plugin that applies upstream updates to many sites at once (`site:mass-update:apply`). Runs on users' machines and in CI; nothing runs on Pantheon infrastructure

trigger: tag                        # a maintainer pushes a version tag; Packagist lists it. Merging to 1.x releases nothing
merged_implies_deployed: false      # but see the first other_paths_to_prod entry
other_paths_to_prod:
  - "anyone who installs from the git URL (`terminus self:plugin:install git@github.com:pantheon-systems/terminus-mass-update.git`) or copies the repo into ~/.terminus/plugins gets the HEAD of 1.x: Terminus runs `git clone --depth 1` with no ref (src/Commands/Self/Plugin/InstallCommand.php on Terminus 4.x). For them a merge IS the release"
  - "pushing any tag, on any commit or branch, publishes that version on Packagist"
  - "deleting or force-moving a tag changes what Packagist and Composer caches resolve, with no workflow run"
  # GitHub releases exist for v1.0.0 (tagged `1.0.0`) and v1.1.1; v1.1.0 is a tag only. Nothing reads releases: Packagist follows tags

pipeline:
  system: github-actions
  workflow: null                    # no release workflow. `.github/workflows/ci.yml` (PHP Compatibility, and a deprecations check on PHP 8.4) run checks only
  jobs_in_order: []
  typical_minutes: null             # n/a: the release is a tag push (seconds) plus Packagist's update
  minutes_by_change: {}
  watch: "Packagist: `curl -s https://repo.packagist.org/p2/pantheon-systems/terminus-mass-update.json` until the new version appears"
  concurrency_group: null
  cancel_in_progress: null
  blocked_by_waiting_run: null
  approval_gate: null               # no GitHub Environment

release:
  mechanism: "a maintainer pushes a version tag on the merged commit of 1.x (`git tag <version> && git push origin <version>`), and (inconsistently) creates a GitHub release for it by hand. Packagist picks the tag up on its own; no workflow step does it"   # inferred: Packagist webhook vs crawl; no release has been observed for this draft
  tag_scheme: "semver, as already used: latest tag v1.1.1"
  never: "move or delete a published tag (Packagist and Composer caches key on it); publish a fixed patch instead"
  procedure: "none written down. TODO(owner): who releases this plugin, and is there a checklist?"

consumers:                          # `gh search code --owner pantheon-systems`, then each hit read; observed 2026-11-10 (search capped at 30 hits per query)
  - {repo: docker-build-tools-ci, pin: "none (Packagist latest at image build)", file: Dockerfile, picked_up_by: "`RUN terminus self:plugin:add terminus-mass-update` at image build; a release reaches the image the next time it is rebuilt"}
  - {repo: documentation, pin: "none", picked_up_by: "linked from src/source/content/core-updates.md, terminus/03-examples.md and the plugin directory (08-directory.md), which show `site:mass-update:apply` as the way to apply upstream updates in bulk"}
  - {repo: "Terminus users", pin: "composer constraint chosen at install", picked_up_by: "`terminus self:plugin:install terminus-mass-update`, then `self:plugin:update`"}

repo_properties:
  observed_at: 2026-11-10
  trust_level: TL5
  ccb_status: in-ccb
  owner: developer-experience
  repo_type: plugin

gating_checks:                      # `gh api repos/pantheon-systems/terminus-mass-update/rules/branches/1.x`
  required: ["Wiz IaC Scanner", "Wiz Vulnerability Scanner", "Wiz Data Scanner"]   # org ruleset 'TL5'
  approvals: {required: 1, code_owner: true, last_push_approval: false, strict_up_to_date: false, merge_queue: false}   # TL5 - Non-Prod (org) and 'Branch protection: 1.x' (repo; adds 1 code-owner approval and dismisses stale reviews on push)
  run_but_not_required: ["PHP Compatibility", "Check for deprecations", "CodeQL"]
  conditional: []
  release_gate: "none. A tag can be pushed on any commit, including one that never went through review; CCB is the process gate"

environments:
  - {name: packagist, target: "https://packagist.org/packages/pantheon-systems/terminus-mass-update", gates_prod: true}
  - {name: github-release, target: "github.com/pantheon-systems/terminus-mass-update/releases/<tag>", gates_prod: false}   # by hand, informational

artifact:
  image: null
  file: "the tagged source tree, resolved by Composer from Packagist"
  tag_scheme: semver
  tag_mutable: false                # by convention; nothing enforces it
  retention: "tags kept indefinitely"
  version_signal: "`composer show pantheon-systems/terminus-mass-update` in the plugin directory, or the version in `terminus self:plugin:list`"   # inferred
  tag_to_sha: "git rev-list -n1 <tag>"

runtime: []                         # runs on users' machines and in CI

deployed_when:                      # ALL must hold
  - "`git ls-remote --tags origin <version>` returns the merged commit on 1.x"
  - "Packagist lists <version>: `curl -s https://repo.packagist.org/p2/pantheon-systems/terminus-mass-update.json` (the first entry of `versions`)"

health:
  soak_minutes: 0                   # no fleet to watch; health comes from smoke checks now and user reports afterwards
  baseline: "Packagist lists v1.1.1, listed 2025-05-02; the list matches the tags v1.0.0 to v1.1.1"
  checks:
    - name: packagist has the tag
      kind: command
      run: "curl -s https://repo.packagist.org/p2/pantheon-systems/terminus-mass-update.json | python3 -c \"import json,sys;print(json.load(sys.stdin)['packages']['pantheon-systems/terminus-mass-update'][0]['version'])\""
      healthy: "== <version>"       # run 2026-11-10: returns the latest tag listed above
    - name: plugin installs and loads
      kind: command
      run: "export TERMINUS_PLUGINS_DIR=$(mktemp -d) && terminus self:plugin:install terminus-mass-update && terminus list | head -40"
      healthy: "'Installed pantheon-systems/terminus-mass-update' (or the plugin's commands) appear in `terminus list`, exit 0"   # TODO(verify-on-deploy): not run for this draft
    - name: consumers and users
      kind: delegate
      run: "the next rebuild of docker-build-tools-ci, and user reports on the repo's issues"
      healthy: "no new regression reports in the first days"
  known_baseline_errors: []
  dashboards: []
  alerts: []

rollback:
  options:
    - when: "a release is broken and a fix will take more than a few hours"
      kind: retract-and-repin
      steps: |
        1. Revert the bad change on 1.x through a PR.
        2. Tag the next patch release from the revert (never reuse or move the bad tag). Packagist lists it; `terminus self:plugin:update` then picks it up.
        3. Users who can't wait reinstall the previous version by pinning a Composer constraint (`composer require pantheon-systems/terminus-mass-update:<previous>` in the plugin's directory under ~/.terminus/plugins).   # inferred: not exercised
        4. Optional, if the bad version is harmful: a Packagist maintainer can delete that version in the Packagist UI. Composer caches and lock files keep it.   # inferred
      time_to_rollback_minutes: 30  # inferred: PR review plus the tag
    - when: otherwise
      kind: roll-forward
      steps: "fix on 1.x and tag the next patch"
      time_to_rollback_minutes: 60  # inferred
  not_restored:
    - "plugins already installed on users' machines and in CI images keep the bad version until updated"
    - "Composer lock files that resolved the bad version"
  one_way_changes: []               # checked: a release changes no server-side state
  currently_blocked: null
  last_exercised: null
  capture_before_merge: "the latest tag: `git ls-remote --tags origin | sort -V | tail -3`, and Packagist's latest: the check under health"
  who_can: ["@pantheon-systems/developer-experience: push tags and merge on 1.x. TODO(owner): who are the Packagist maintainers of pantheon-systems/terminus-mass-update (needed to delete a version)"]
  verify: "Packagist's first listed version is the new patch; `terminus self:plugin:update` on an affected install reports the new version"

escalation:
  slack: "#ask-hosting"
  owner_team: developer-experience
  paged: false                      # inferred: no alerts exist
  issues: "https://github.com/pantheon-systems/terminus-mass-update/issues"

preflight:
  - "gh auth status (write on pantheon-systems/terminus-mass-update to push tags)"
  - "terminus and composer on PATH (for the install smoke check)"

automation_blockers:
  - blocker: "a release is a hand-pushed tag with no written procedure and no check that the tag is on the reviewed head of 1.x"
    clears_when: "a release workflow (or a documented checklist) tags only a merged commit on 1.x, and creates the GitHub release"
  - blocker: "GitHub releases and tags have drifted apart: GitHub releases exist for v1.0.0 (tagged `1.0.0`) and v1.1.1; v1.1.0 is a tag only."
    clears_when: "the release process creates both together"

verified: null                      # TODO(verify-on-deploy): pass 2 fills it from a real release
---

# Deployment

`terminus-mass-update` is a Terminus plugin that applies upstream updates to many sites at once (`site:mass-update:apply`). "Deploying" it means publishing a version tag, which Packagist picks up. Users then get it through `terminus self:plugin:install`, `terminus self:plugin:update` or Composer. Nothing runs on Pantheon infrastructure.

Merging to `1.x` does not release anything for Packagist users. Since v1.1.1 the default branch has only SHA pinning and github-terraform metadata commits (6 commits).

## How a change reaches production

1. **PR to `1.x`.** Org rules for TL5 require the three Wiz scanners; one code-owner approval is required (TL5 - Non-Prod (org) and 'Branch protection: 1.x' (repo; adds 1 code-owner approval and dismisses stale reviews on push)). `.github/workflows/ci.yml` (PHP Compatibility, and a deprecations check on PHP 8.4) run on pushes and PRs but don't block a merge, so check they're green.
2. **Merge.** Nothing is published.
3. **Release.** A maintainer tags the merged commit (`git tag <version> && git push origin <version>`) and sometimes creates a GitHub release by hand. There is no release workflow and no written procedure (TODO(owner)). Packagist lists the new version on its own.
4. **Users** pick it up with `terminus self:plugin:update`, or on a fresh install. The latest version listed on Packagist today is v1.1.1, listed 2025-05-02; the list matches the tags v1.0.0 to v1.1.1.

Other paths to production: anyone installing from the git URL gets the head of `1.x` (Terminus clones it with `--depth 1`), so for them a merge is a release. Pushing any tag publishes it.

docker-build-tools-ci installs the latest release into its CI image, and the Pantheon docs send users to it for bulk upstream updates (`site:mass-update:apply` runs against customer sites). GitHub releases exist for v1.0.0 (tagged `1.0.0`) and v1.1.1; v1.1.0 is a tag only.

## How to confirm it deployed

```bash
# tag points at the merged commit
git ls-remote --tags https://github.com/pantheon-systems/terminus-mass-update.git <version>

# Packagist lists it
curl -s https://repo.packagist.org/p2/pantheon-systems/terminus-mass-update.json \
  | python3 -c "import json,sys;print(json.load(sys.stdin)['packages']['pantheon-systems/terminus-mass-update'][0]['version'])"
```

## How to confirm it's healthy

There is no telemetry, dashboard or alert. Health is the install smoke test (TODO(verify-on-deploy): not run for this draft), the next rebuild of docker-build-tools-ci, and user reports on the repo's issues.

## Rollback

Releases are normally fixed forward. Never delete or move a published tag.

1. **Before tagging**, record the latest tag (`git ls-remote --tags origin | sort -V | tail -3`).
2. Revert the bad change on `1.x` through a PR, and tag the next patch release from it.
3. Users who can't wait pin the previous version with Composer inside their plugin directory. [inferred]
4. If the version is actively harmful, a Packagist maintainer can delete it in the Packagist UI; Composer caches and lock files keep it. [inferred]

**Verify:** Packagist lists the new patch first, and `terminus self:plugin:update` on an affected install reports it.

**What this doesn't restore:** plugins already installed (they keep the bad version until updated), CI images built in the meantime, and Composer lock files that resolved it.

Rollback has never been exercised.

## Escalation

- Owner: `developer-experience` (code owners `@pantheon-systems/developer-experience`).
- Nobody is paged and there are no alerts.
- Issues: https://github.com/pantheon-systems/terminus-mass-update/issues
- Slack: `#ask-hosting`.

## Open owner questions

- TODO(owner): who releases this plugin, and is there a checklist?
- TODO(owner): who are the Packagist maintainers of `pantheon-systems/terminus-mass-update`?

## Suggested pipeline changes

- A release workflow that tags a reviewed commit on `1.x` and creates the GitHub release together with the tag.
