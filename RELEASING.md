# Releasing

Releasing is automated; you never create tags or Releases by hand. Packagist reads the git tags, so the tag is the release.

## Steps

1. Merge your PRs to `main`. Main squash-merges, so each PR title must be a valid Conventional Commit.
2. Open a `chore: release X.Y.Z` PR that changes `Nomba::VERSION` in `src/Nomba.php` to `X.Y.Z` and turns `## [Unreleased]` in `CHANGELOG.md` into a `## [X.Y.Z] - YYYY-MM-DD` section (Breaking, Security, Added, Fixed, Changed, in plain language). `composer.json` has no `version` field; don't add one.
3. Merge it. `release.yml` validates `composer.json` and runs the style check, PHPStan and the tests, then waits. Open the run in **Actions** and approve the **release** deployment. Only then does it create the `vX.Y.Z` tag and GitHub Release at the merged commit. Packagist picks the tag up through its GitHub hook within a minute or two.

## Choosing the version

| Change since the last release | Bump |
|-------------------------------|------|
| `feat!:` or a `BREAKING CHANGE:` footer (a method, option or exception renamed or removed, a type narrowed) | major |
| `feat:` | minor |
| `fix:` / `perf:` | patch |
| only `chore:` `docs:` `refactor:` `test:` `ci:` | no release |

Before 1.0.0, breaking changes bump the minor version.

## After releasing

Check Packagist directly:

```bash
curl -s https://repo.packagist.org/p2/nombaoss/nomba-php.json | php -r '$d=json_decode(stream_get_contents(STDIN),true);echo $d["packages"]["nombaoss/nomba-php"][0]["version"],PHP_EOL;'
```

Then confirm the tag and GitHub Release exist, and in an empty directory run `composer require nombaoss/nomba-php` (expect `nombaoss/nomba-php` plus Guzzle and its PSR packages only).

## One-time setup (repo owner)

- GitHub: create an environment named `release` with yourself as required reviewer, limited to protected branches, so a stolen login can't publish on its own.
- Packagist: sign in to packagist.org with the NombaOSS GitHub account, submit `https://github.com/NombaOSS/nomba-php`, and add NombaOSS maintainers to the package.
- GitHub hook: in the Packagist package settings, enable the GitHub integration (or add the webhook `https://packagist.org/api/github?username=<packagist user>` with the Packagist API token as secret, content type `application/json`, push events only) so new tags appear without a manual update.
